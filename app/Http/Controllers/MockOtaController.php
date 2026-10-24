<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\MockOtaInventory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MockOtaController extends Controller
{
    /**
     * Handle mock OTA inventory push update.
     */
    public function updateInventory(Request $request, Channel $channel): JsonResponse
    {
        // 1. Failure simulation (from query param ?fail=0.3 or config)
        $failRate = $request->has('fail')
            ? (float) $request->query('fail')
            : (float) config('roomsync.mock_ota_fail_rate', 0);

        if ($failRate > 0) {
            $random = mt_rand(1, 1000) / 1000;
            if ($random <= $failRate) {
                return response()->json([
                    'error' => "Simulated OTA outage for channel: {$channel->name}",
                ], 500);
            }
        }

        // 2. Parse inbound payload depending on format / Content-Type
        $contentType = $request->header('Content-Type', '');
        $rawContent = $request->getContent();
        $items = [];

        try {
            if (str_contains($contentType, 'xml') || $channel->format === 'xml') {
                $xml = simplexml_load_string($rawContent);
                if ($xml && isset($xml->item)) {
                    foreach ($xml->item as $item) {
                        $items[] = [
                            'room_type_id' => (int) $item->room_type_id,
                            'date'         => (string) $item->date,
                            'available'    => (int) $item->available,
                            'rate'         => (float) $item->rate,
                        ];
                    }
                }
            } else {
                $data = json_decode($rawContent, true);
                $items = $data['inventory'] ?? [];
            }
        } catch (Throwable $e) {
            return response()->json(['error' => 'Malformed payload: ' . $e->getMessage()], 400);
        }

        // 3. Upsert into mock_ota_inventory
        foreach ($items as $item) {
            if (empty($item['room_type_id']) || empty($item['date'])) {
                continue;
            }

            MockOtaInventory::updateOrCreate(
                [
                    'channel_id'   => $channel->id,
                    'room_type_id' => (int) $item['room_type_id'],
                    'date'         => Carbon::parse($item['date'])->format('Y-m-d'),
                ],
                [
                    'available'    => (int) ($item['available'] ?? 0),
                    'rate'         => (float) ($item['rate'] ?? 0),
                ]
            );
        }

        return response()->json([
            'status'  => 'success',
            'channel' => $channel->name,
            'updated' => count($items),
        ], 200);
    }

    /**
     * Retrieve mock OTA's current inventory state.
     */
    public function getState(Channel $channel): JsonResponse
    {
        $inventory = MockOtaInventory::where('channel_id', $channel->id)
            ->with('roomType')
            ->orderBy('date')
            ->get();

        return response()->json([
            'channel'   => $channel->name,
            'format'    => $channel->format,
            'inventory' => $inventory,
        ]);
    }
}
