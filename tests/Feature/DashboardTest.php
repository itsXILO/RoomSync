<?php

namespace Tests\Feature;

use App\Jobs\PushInventoryToChannel;
use App\Models\Channel;
use App\Models\Hotel;
use App\Models\Inventory;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;
    protected RoomType $roomType;
    protected Channel $channel;
    protected Inventory $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'The Grand Budapest']);

        $this->roomType = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Standard Room',
            'total_rooms' => 5,
            'base_rate' => 150.00,
        ]);

        $this->channel = Channel::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Alpha OTA',
            'format' => 'json',
            'endpoint_url' => 'http://localhost/mock/1/inventory',
            'api_key' => 'alpha-key-001',
            'active' => true,
        ]);

        $this->inventory = Inventory::create([
            'room_type_id' => $this->roomType->id,
            'date' => now()->toDateString(),
            'available' => 5,
            'rate' => 150.00,
        ]);
    }

    public function test_dashboard_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('The Grand Budapest');
        $response->assertSee('Standard Room');
        $response->assertSee('RoomSync');
    }

    public function test_inventory_update_api_updates_and_dispatches_sync(): void
    {
        Queue::fake();

        $response = $this->putJson("/api/inventory/{$this->inventory->id}", [
            'available' => 2,
            'rate' => 185.50,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $this->inventory->id,
            'available' => 2,
            'rate' => '185.50',
        ]);

        $this->assertDatabaseHas('inventory', [
            'id' => $this->inventory->id,
            'available' => 2,
        ]);

        Queue::assertPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->channel->id
                && $job->roomTypeId === $this->roomType->id;
        });
    }

    public function test_inventory_update_validation_fails_for_negative_values(): void
    {
        $response = $this->putJson("/api/inventory/{$this->inventory->id}", [
            'available' => -1,
            'rate' => -50,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['available', 'rate']);
    }
}
