<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::first();

        RoomType::create([
            'hotel_id'    => $hotel->id,
            'name'        => 'Standard Room',
            'total_rooms' => 5,
            'base_rate'   => 150.00,
        ]);

        RoomType::create([
            'hotel_id'    => $hotel->id,
            'name'        => 'Deluxe Suite',
            'total_rooms' => 3,
            'base_rate'   => 275.00,
        ]);
    }
}
