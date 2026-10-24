<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Channel;
use App\Models\Inventory;
use App\Models\SyncLog;
use App\Services\ChannelAdapters\ChannelAdapterFactory;
use App\Services\InventorySyncService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class BookingController extends Controller
{
    /**
     * Handle inbound booking webhook.
     */
    public function store(Request $request, Channel $channel): JsonResponse
    {
        // 1. Authenticate via X-API-Key
        $apiKey = $request->header('X-API-Key');
        if (! $apiKey || $apiKey !== $channel->api_key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 2. Parse payload using channel format adapter
        $rawContent = $request->getContent();
        try {
            $adapter = ChannelAdapterFactory::make($channel);
            $data = $adapter->parseBookingPayload($rawContent);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Malformed payload: ' . $e->getMessage()], 400);
        }

        // Validate basic fields
        if (empty($data['external_ref']) || empty($data['room_type_id']) || empty($data['check_in']) || empty($data['check_out'])) {
            return response()->json(['error' => 'Missing required booking fields'], 422);
        }

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);
        if ($checkOut->lte($checkIn)) {
            return response()->json(['error' => 'check_out must be after check_in'], 422);
        }

        // 3. Idempotency check: return existing booking if ref already processed for this channel
        $existing = Booking::where('channel_id', $channel->id)
            ->where('external_ref', $data['external_ref'])
            ->first();

        if ($existing) {
            $statusCode = ($existing->status === 'confirmed') ? 201 : 409;
            return response()->json($existing, $statusCode);
        }

        // Calculate all nights: [check_in, check_out - 1 day]
        $lastNight = $checkOut->copy()->subDay();
        $period = CarbonPeriod::create($checkIn, $lastNight);
        $datesNeeded = collect($period)->map(fn (Carbon $date) => $date->toDateString())->values();
        $roomsRequested = max(1, (int) ($data['rooms'] ?? 1));

        /** @var Booking|null $booking */
        $booking = null;

        // 4. Atomic transaction with row-level locks
        DB::transaction(function () use ($channel, $data, $datesNeeded, $roomsRequested, &$booking) {
            // Lock rows ordered by date to guarantee deadlock freedom
            $inventoryRows = Inventory::where('room_type_id', $data['room_type_id'])
                ->whereIn('date', $datesNeeded->all())
                ->orderBy('date', 'asc')
                ->lockForUpdate()
                ->get();

            $rowsByDate = $inventoryRows->keyBy(fn (Inventory $row) => Carbon::parse($row->date)->toDateString());

            // Check inventory exists for all nights
            foreach ($datesNeeded as $dateStr) {
                if (! $rowsByDate->has($dateStr)) {
                    $booking = Booking::create([
                        'room_type_id' => $data['room_type_id'],
                        'channel_id'   => $channel->id,
                        'external_ref' => $data['external_ref'],
                        'guest_name'   => $data['guest_name'] ?? 'Unknown Guest',
                        'check_in'     => $data['check_in'],
                        'check_out'    => $data['check_out'],
                        'rooms'        => $roomsRequested,
                        'status'       => 'rejected',
                        'reason'       => "No inventory record for date: {$dateStr}",
                    ]);
                    return;
                }
            }

            // Check capacity for all nights
            foreach ($datesNeeded as $dateStr) {
                /** @var Inventory $row */
                $row = $rowsByDate->get($dateStr);
                if ($row->available < $roomsRequested) {
                    $booking = Booking::create([
                        'room_type_id' => $data['room_type_id'],
                        'channel_id'   => $channel->id,
                        'external_ref' => $data['external_ref'],
                        'guest_name'   => $data['guest_name'] ?? 'Unknown Guest',
                        'check_in'     => $data['check_in'],
                        'check_out'    => $data['check_out'],
                        'rooms'        => $roomsRequested,
                        'status'       => 'rejected',
                        'reason'       => "Insufficient rooms on {$dateStr} (requested: {$roomsRequested}, available: {$row->available})",
                    ]);
                    return;
                }
            }

            // All nights have sufficient inventory -> decrement atomically
            foreach ($datesNeeded as $dateStr) {
                /** @var Inventory $row */
                $row = $rowsByDate->get($dateStr);
                $row->decrement('available', $roomsRequested);
            }

            $booking = Booking::create([
                'room_type_id' => $data['room_type_id'],
                'channel_id'   => $channel->id,
                'external_ref' => $data['external_ref'],
                'guest_name'   => $data['guest_name'] ?? 'Unknown Guest',
                'check_in'     => $data['check_in'],
                'check_out'    => $data['check_out'],
                'rooms'        => $roomsRequested,
                'status'       => 'confirmed',
                'reason'       => null,
            ]);
        });

        $isConfirmed = $booking->status === 'confirmed';
        $statusCode = $isConfirmed ? 201 : 409;

        // 5. Append-only sync log
        SyncLog::create([
            'channel_id'    => $channel->id,
            'direction'     => 'in',
            'event'         => 'booking_received',
            'payload'       => $rawContent,
            'response_code' => $statusCode,
            'status'        => $isConfirmed ? 'success' : 'failed',
            'attempts'      => 1,
            'error'         => $booking->reason,
            'created_at'    => now(),
        ]);

        // 6. After transaction commit: push new availability to all other active channels
        if ($isConfirmed) {
            InventorySyncService::pushToChannels(
                $booking->room_type_id,
                $data['check_in'],
                $datesNeeded->last(),
                $channel->id
            );
        }

        return response()->json($booking, $statusCode);
    }

    /**
     * Handle inbound cancellation webhook.
     */
    public function cancel(Request $request, Channel $channel): JsonResponse
    {
        // 1. Authenticate via X-API-Key
        $apiKey = $request->header('X-API-Key');
        if (! $apiKey || $apiKey !== $channel->api_key) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $rawContent = $request->getContent();
        try {
            $adapter = ChannelAdapterFactory::make($channel);
            $data = $adapter->parseBookingPayload($rawContent);
        } catch (Throwable $e) {
            return response()->json(['error' => 'Malformed payload: ' . $e->getMessage()], 400);
        }

        $booking = Booking::where('channel_id', $channel->id)
            ->where('external_ref', $data['external_ref'])
            ->first();

        if (! $booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        if ($booking->status === 'cancelled') {
            return response()->json($booking, 200);
        }

        if ($booking->status === 'rejected') {
            return response()->json(['error' => 'Cannot cancel a rejected booking'], 422);
        }

        $checkIn = Carbon::parse($booking->check_in);
        $lastNight = Carbon::parse($booking->check_out)->subDay();
        $period = CarbonPeriod::create($checkIn, $lastNight);
        $dates = collect($period)->map(fn (Carbon $date) => $date->toDateString())->values();

        DB::transaction(function () use ($booking, $dates) {
            $inventoryRows = Inventory::where('room_type_id', $booking->room_type_id)
                ->whereIn('date', $dates->all())
                ->orderBy('date', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($inventoryRows as $row) {
                $row->increment('available', $booking->rooms);
            }

            $booking->update(['status' => 'cancelled']);
        });

        SyncLog::create([
            'channel_id'    => $channel->id,
            'direction'     => 'in',
            'event'         => 'booking_cancelled',
            'payload'       => $rawContent,
            'response_code' => 200,
            'status'        => 'success',
            'attempts'      => 1,
            'error'         => null,
            'created_at'    => now(),
        ]);

        InventorySyncService::pushToChannels(
            $booking->room_type_id,
            $dates->first(),
            $dates->last(),
            $channel->id
        );

        return response()->json($booking->fresh(), 200);
    }
}
