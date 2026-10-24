<?php

namespace Database\Seeders;

use App\Models\Inventory;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $roomTypes = RoomType::all();
        $rows = [];

        foreach ($roomTypes as $roomType) {
            for ($i = 0; $i < 60; $i++) {
                $date = Carbon::today()->addDays($i)->toDateString();
                $rows[] = [
                    'room_type_id' => $roomType->id,
                    'date'         => $date,
                    'available'    => $roomType->total_rooms,
                    'rate'         => $roomType->base_rate,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }
        }

        // Insert all rows in one query — fast even for many rows
        Inventory::insert($rows);
    }
}
