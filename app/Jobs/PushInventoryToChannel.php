<?php

namespace App\Jobs;

use App\Models\Channel;
use App\Models\Inventory;
use App\Models\SyncLog;
use App\Services\ChannelAdapters\ChannelAdapterFactory;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

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
            ->toArray();

        $adapter = ChannelAdapterFactory::make($this->channel);
        $payload = $adapter->buildInventoryPayload($rows);

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->channel->api_key,
                'Content-Type' => $adapter->contentType(),
            ])->timeout(10)->withBody($payload, $adapter->contentType())->post($this->channel->endpoint_url);

            SyncLog::create([
                'channel_id' => $this->channel->id,
                'direction' => 'out',
                'event' => 'inventory_push',
                'payload' => $payload,
                'response_code' => $response->status(),
                'status' => $response->successful() ? 'success' : 'failed',
                'attempts' => $this->attempts(),
                'error' => $response->successful() ? null : $response->body(),
                'created_at' => now(),
            ]);

            if (! $response->successful()) {
                throw new Exception("Channel {$this->channel->name} returned HTTP {$response->status()}");
            }
        } catch (Exception $e) {
            SyncLog::create([
                'channel_id' => $this->channel->id,
                'direction' => 'out',
                'event' => 'inventory_push',
                'payload' => $payload,
                'response_code' => 500,
                'status' => 'failed',
                'attempts' => $this->attempts(),
                'error' => $e->getMessage(),
                'created_at' => now(),
            ]);

            throw $e;
        }
    }
}
