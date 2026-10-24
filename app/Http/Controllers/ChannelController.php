<?php

namespace App\Http\Controllers;

use App\Jobs\PushInventoryToChannel;
use App\Models\Channel;
use App\Models\Inventory;
use App\Models\MockOtaInventory;
use App\Models\RoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    /**
     * Display connected channels and side-by-side inventory comparison.
     */
    public function index(Request $request): View
    {
        $channels = Channel::orderBy('id')->get();
        $selectedChannelId = $request->query('channel_id', $channels->first()?->id);
        $selectedChannel = $channels->firstWhere('id', $selectedChannelId) ?? $channels->first();

        $roomTypes = RoomType::all();
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(29);
        $period = CarbonPeriod::create($startDate, $endDate);

        $dates = collect($period)->map(fn (Carbon $d) => $d->toDateString())->values();

        $comparison = [];
        $mismatchCount = 0;

        if ($selectedChannel) {
            $rsInventory = Inventory::whereIn('room_type_id', $roomTypes->pluck('id'))
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            $otaInventory = MockOtaInventory::where('channel_id', $selectedChannel->id)
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get();

            foreach ($roomTypes as $rt) {
                foreach ($dates as $dateStr) {
                    $rsRow = $rsInventory->first(function (Inventory $item) use ($rt, $dateStr) {
                        return $item->room_type_id === $rt->id && Carbon::parse($item->date)->toDateString() === $dateStr;
                    });

                    $otaRow = $otaInventory->first(function (MockOtaInventory $item) use ($rt, $dateStr) {
                        return $item->room_type_id === $rt->id && Carbon::parse($item->date)->toDateString() === $dateStr;
                    });

                    $rsAvailable = $rsRow ? $rsRow->available : 0;
                    $rsRate = $rsRow ? (float) $rsRow->rate : (float) $rt->base_rate;

                    $otaAvailable = $otaRow ? $otaRow->available : null;
                    $otaRate = $otaRow ? (float) $otaRow->rate : null;

                    $isMismatch = ($otaAvailable === null)
                        || ($rsAvailable !== $otaAvailable)
                        || (abs($rsRate - ($otaRate ?? 0)) > 0.01);

                    if ($isMismatch) {
                        $mismatchCount++;
                    }

                    $comparison[] = [
                        'room_type'     => $rt->name,
                        'date'          => $dateStr,
                        'rs_available'  => $rsAvailable,
                        'rs_rate'       => $rsRate,
                        'ota_available' => $otaAvailable,
                        'ota_rate'      => $otaRate,
                        'is_mismatch'   => $isMismatch,
                    ];
                }
            }
        }

        return view('channels', [
            'channels'        => $channels,
            'selectedChannel' => $selectedChannel,
            'comparison'      => $comparison,
            'mismatchCount'   => $mismatchCount,
            'totalDays'       => count($dates),
        ]);
    }

    /**
     * Dispatch a full 60-day synchronization push to the specified channel.
     */
    public function fullSync(Channel $channel): RedirectResponse
    {
        $roomTypes = RoomType::all();
        $startDate = Carbon::today()->toDateString();
        $endDate = Carbon::today()->addDays(59)->toDateString();

        foreach ($roomTypes as $rt) {
            PushInventoryToChannel::dispatch(
                $channel,
                $rt->id,
                $startDate,
                $endDate
            );
        }

        return back()->with('success', "Full 60-day inventory sync dispatched to {$channel->name}!");
    }
}
