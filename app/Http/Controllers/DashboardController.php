<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Inventory;
use App\Models\RoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $hotel = Hotel::with('channels')->first();

        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(29);
        $period = CarbonPeriod::create($startDate, $endDate);

        $dates = collect($period)->map(fn (Carbon $date) => [
            'dateString' => $date->toDateString(),
            'dayName'    => $date->format('D'),
            'dayNum'     => $date->format('j'),
            'month'      => $date->format('M'),
            'isWeekend'  => $date->isWeekend(),
        ]);

        $roomTypes = RoomType::when($hotel, fn ($q) => $q->where('hotel_id', $hotel->id))->get();

        // Fetch all inventory for the next 30 days
        $inventoryRows = Inventory::whereIn('room_type_id', $roomTypes->pluck('id'))
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        // Organize inventory by room_type_id and dateString
        $grid = [];
        foreach ($roomTypes as $roomType) {
            $grid[$roomType->id] = [];
            foreach ($dates as $d) {
                $dateStr = $d['dateString'];
                $row = $inventoryRows->first(function (Inventory $item) use ($roomType, $dateStr) {
                    return $item->room_type_id === $roomType->id && Carbon::parse($item->date)->toDateString() === $dateStr;
                });

                $grid[$roomType->id][$dateStr] = $row;
            }
        }

        return view('dashboard', [
            'hotel'     => $hotel,
            'roomTypes' => $roomTypes,
            'dates'     => $dates,
            'grid'      => $grid,
            'startDate' => $startDate->toDateString(),
            'endDate'   => $endDate->toDateString(),
        ]);
    }
}
