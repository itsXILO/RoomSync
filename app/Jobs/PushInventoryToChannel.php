<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\Inventory;
use App\Models\SyncLog;
use App\Services\ChannelAdapters\ChannelAdapterFactory;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class PushInventoryToChannel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [5, 15, 30, 60];

    public function __construct(
        public Channel $channel,
        public int $roomTypeId,
        public string $dateFrom,
        public string $dateTo,
    ) {}

    public function handle(): void
    {
        $rows = Inventory::where('room_type_id', $this->roomTypeId)
            ->whereBetween('date', [$this->dateFrom, $this->dateTo])
            ->orderBy('date')
            ->get(['room_type_id', 'date', 'available', 'rate'])
            ->map(fn (Inventory $row) => [
                'room_type_id' => (int) $row->room_type_id,
                'date' => Carbon::parse($row->date)->toDateString(),
                'available' => (int) $row->available,
                'rate' => (string) $row->rate,
            ])
            ->toArray();

        $adapter = ChannelAdapterFactory::make($this->channel);
        $payload = $adapter->buildInventoryPayload($rows);
        $attempts = method_exists($this, 'attempts') ? $this->attempts() : 1;

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->channel->api_key,
                'Content-Type' => $adapter->contentType(),
            ])->timeout(10)->withBody($payload, $adapter->contentType())->post($this->channel->endpoint_url);

            $statusCode = $response->status();
            $isSuccess = $response->successful();

            SyncLog::create([
                'channel_id' => $this->channel->id,
                'direction' => 'out',
                'event' => 'inventory_push',
                'payload' => $payload,
                'response_code' => $statusCode,
                'status' => $isSuccess ? 'success' : 'failed',
                'attempts' => $attempts,
                'error' => $isSuccess ? null : "HTTP {$statusCode}: " . $response->body(),
                'created_at' => now(),
            ]);

            if (! $isSuccess) {
                throw new Exception("Channel {$this->channel->name} returned HTTP {$statusCode}");
            }
        } catch (Throwable $e) {
            if (! isset($response)) {
                SyncLog::create([
                    'channel_id' => $this->channel->id,
                    'direction' => 'out',
                    'event' => 'inventory_push',
                    'payload' => $payload,
                    'response_code' => 0,
                    'status' => 'failed',
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                    'created_at' => now(),
                ]);
            }

            throw $e;
        }
    }
}
