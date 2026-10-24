<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\Hotel;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::first();
        $baseUrl = rtrim(config('app.url'), '/');

        $channels = [
            [
                'hotel_id'     => $hotel->id,
                'name'         => 'Alpha OTA',
                'format'       => 'json',
                // endpoint_url points to the mock OTA inside the same app
                // Channel ID 1 will be assigned by the DB auto-increment
                'endpoint_url' => $baseUrl . '/mock/1/inventory',
                'api_key'      => 'alpha-key-001',
                'active'       => true,
            ],
            [
                'hotel_id'     => $hotel->id,
                'name'         => 'Beta Travel',
                'format'       => 'xml',
                'endpoint_url' => $baseUrl . '/mock/2/inventory',
                'api_key'      => 'beta-key-002',
                'active'       => true,
            ],
            [
                'hotel_id'     => $hotel->id,
                'name'         => 'Gamma Stays',
                'format'       => 'json',
                'endpoint_url' => $baseUrl . '/mock/3/inventory',
                'api_key'      => 'gamma-key-003',
                'active'       => true,
            ],
        ];

        foreach ($channels as $data) {
            Channel::create($data);
        }
    }
}
