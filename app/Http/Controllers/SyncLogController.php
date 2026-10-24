<?php

namespace App\Http\Controllers;

use App\Jobs\PushInventoryToChannel;
use App\Models\Channel;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SyncLogController extends Controller
{
    /**
     * Display a paginated list of sync logs with filtering.
     */
    public function index(Request $request): View
    {
        $channels = Channel::orderBy('name')->get();

        $query = SyncLog::with('channel')->latest('id');

        if ($request->filled('direction')) {
            $query->where('direction', $request->query('direction'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('channel_id')) {
            $query->where('channel_id', $request->query('channel_id'));
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('logs', [
            'logs'     => $logs,
            'channels' => $channels,
        ]);
    }

    /**
     * Retry a failed outbound sync log.
     */
    public function retry(SyncLog $syncLog): RedirectResponse
    {
        if ($syncLog->direction === 'out' && $syncLog->channel) {
            // Attempt to parse dates and room_type_id from payload
            $roomTypeId = 1;
            $startDate = Carbon::today()->toDateString();
            $endDate = Carbon::today()->addDays(29)->toDateString();

            try {
                if ($syncLog->channel->format === 'xml') {
                    $xml = simplexml_load_string($syncLog->payload);
                    if ($xml && isset($xml->item[0])) {
                        $roomTypeId = (int) $xml->item[0]->room_type_id;
                        $startDate = (string) $xml->item[0]->date;
                        $endDate = (string) $xml->item[count($xml->item) - 1]->date;
                    }
                } else {
                    $data = json_decode($syncLog->payload, true);
                    if (! empty($data['inventory'])) {
                        $roomTypeId = (int) ($data['inventory'][0]['room_type_id'] ?? 1);
                        $startDate = (string) ($data['inventory'][0]['date'] ?? $startDate);
                        $endDate = (string) ($data['inventory'][count($data['inventory']) - 1]['date'] ?? $endDate);
                    }
                }
            } catch (\Throwable $e) {
                // Fallback to default range
            }

            PushInventoryToChannel::dispatch(
                $syncLog->channel,
                $roomTypeId,
                $startDate,
                $endDate
            );

            return back()->with('success', "Outbound sync re-dispatched for {$syncLog->channel->name}.");
        }

        return back()->with('error', 'Cannot retry this log entry.');
    }
}
