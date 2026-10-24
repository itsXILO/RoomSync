<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Run order matters — each seeder depends on the one before it.
     * Hotels → RoomTypes → Inventory (needs room types) → Channels
     */
    public function run(): void
    {
        $this->call([
            HotelSeeder::class,
            RoomTypeSeeder::class,
            InventorySeeder::class,
            ChannelSeeder::class,
        ]);
    }
}
