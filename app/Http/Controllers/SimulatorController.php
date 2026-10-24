<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class SimulatorController extends Controller
{
    /**
     * Render the OTA Booking Simulator page.
     */
    public function index(): View
    {
        $channels = Channel::where('active', true)->get();
        $roomTypes = RoomType::all();
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();
        $dayAfter = Carbon::today()->addDays(2)->toDateString();

        return view('simulator', [
            'channels'  => $channels,
            'roomTypes' => $roomTypes,
            'today'     => $today,
            'tomorrow'  => $tomorrow,
            'dayAfter'  => $dayAfter,
        ]);
    }
}
