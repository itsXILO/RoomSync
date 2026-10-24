<?php

namespace Tests\Feature;

use App\Jobs\PushInventoryToChannel;
use App\Models\Channel;
use App\Models\Hotel;
use App\Models\Inventory;
use App\Models\MockOtaInventory;
use App\Models\RoomType;
use App\Models\SyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncLogAndChannelTest extends TestCase
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
    }

    public function test_logs_page_renders_and_filters(): void
    {
        SyncLog::create([
            'channel_id' => $this->channel->id,
            'direction' => 'out',
            'event' => 'inventory_push',
            'payload' => json_encode(['inventory' => []]),
            'response_code' => 200,
            'status' => 'success',
            'attempts' => 1,
            'created_at' => now(),
        ]);

        $response = $this->get('/logs?direction=out&status=success');

        $response->assertStatus(200);
        $response->assertSee('Sync Logs & Audit Trail', false);
        $response->assertSee('inventory_push');
        $response->assertSee('Alpha OTA');
    }

    public function test_log_retry_redispatches_outbound_job(): void
    {
        Queue::fake();

        $log = SyncLog::create([
            'channel_id' => $this->channel->id,
            'direction' => 'out',
            'event' => 'inventory_push',
            'payload' => json_encode([
                'inventory' => [
                    ['room_type_id' => $this->roomType->id, 'date' => '2025-06-01', 'available' => 5, 'rate' => 150.00]
                ]
            ]),
            'response_code' => 500,
            'status' => 'failed',
            'attempts' => 1,
            'created_at' => now(),
        ]);

        $response = $this->post("/logs/{$log->id}/retry");

        $response->assertStatus(302);
        Queue::assertPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->channel->id;
        });
    }

    public function test_channels_page_renders_with_mismatch_status(): void
    {
        Inventory::create([
            'room_type_id' => $this->roomType->id,
            'date' => now()->toDateString(),
            'available' => 5,
            'rate' => 150.00,
        ]);

        // Mock OTA has 2 rooms instead of 5 -> mismatch!
        MockOtaInventory::create([
            'channel_id' => $this->channel->id,
            'room_type_id' => $this->roomType->id,
            'date' => now()->toDateString(),
            'available' => 2,
            'rate' => 150.00,
        ]);

        $response = $this->get('/channels');

        $response->assertStatus(200);
        $response->assertSee('Channel Monitor & OTA Comparison', false);
        $response->assertSee('Alpha OTA');
        $response->assertSee('MISMATCH');
    }

    public function test_channel_full_sync_dispatches_jobs_for_all_room_types(): void
    {
        Queue::fake();

        $response = $this->post("/channels/{$this->channel->id}/sync");

        $response->assertStatus(302);
        Queue::assertPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->channel->id
                && $job->roomTypeId === $this->roomType->id;
        });
    }
}
