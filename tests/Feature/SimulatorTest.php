<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SimulatorTest extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;
    protected RoomType $roomType;
    protected Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'The Grand Budapest']);

        $this->roomType = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe Suite',
            'total_rooms' => 3,
            'base_rate' => 275.00,
        ]);

        $this->channel = Channel::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Alpha OTA',
            'format' => 'json',
            'endpoint_url' => 'http://localhost/mock/1/inventory',
            'api_key' => 'alpha-key-001',
            'active' => true,
        ]);
    }

    public function test_simulator_page_renders_successfully(): void
    {
        $response = $this->get('/simulator');

        $response->assertStatus(200);
        $response->assertSee('OTA Booking Simulator', false);
        $response->assertSee('Alpha OTA');
        $response->assertSee('Deluxe Suite');
        $response->assertSee('Race Condition Lab');
    }
}
