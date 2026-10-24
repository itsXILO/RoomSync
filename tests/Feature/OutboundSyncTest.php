<?php

namespace Tests\Feature;

use App\Jobs\PushInventoryToChannel;
use App\Models\Channel;
use App\Models\Hotel;
use App\Models\Inventory;
use App\Models\RoomType;
use App\Models\SyncLog;
use App\Services\InventorySyncService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OutboundSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;
    protected RoomType $roomType;
    protected Channel $jsonChannel;
    protected Channel $xmlChannel;

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

        $this->jsonChannel = Channel::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Alpha OTA',
            'format' => 'json',
            'endpoint_url' => 'http://localhost/mock/1/inventory',
            'api_key' => 'alpha-key-001',
            'active' => true,
        ]);

        $this->xmlChannel = Channel::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Beta Travel',
            'format' => 'xml',
            'endpoint_url' => 'http://localhost/mock/2/inventory',
            'api_key' => 'beta-key-002',
            'active' => true,
        ]);

        Inventory::create([
            'room_type_id' => $this->roomType->id,
            'date' => '2025-06-01',
            'available' => 4,
            'rate' => 150.00,
        ]);

        Inventory::create([
            'room_type_id' => $this->roomType->id,
            'date' => '2025-06-02',
            'available' => 3,
            'rate' => 160.00,
        ]);
    }

    public function test_job_pushes_json_payload_and_logs_success(): void
    {
        Http::fake([
            'http://localhost/mock/1/inventory' => Http::response(['status' => 'ok'], 200),
        ]);

        $job = new PushInventoryToChannel(
            $this->jsonChannel,
            $this->roomType->id,
            '2025-06-01',
            '2025-06-02'
        );

        $job->handle();

        Http::assertSent(function (Request $request) {
            $this->assertEquals('alpha-key-001', $request->header('X-API-Key')[0]);
            $this->assertEquals('application/json', $request->header('Content-Type')[0]);

            $data = json_decode($request->body(), true);
            $this->assertIsArray($data['inventory']);
            $this->assertCount(2, $data['inventory']);
            $this->assertEquals(4, $data['inventory'][0]['available']);
            $this->assertEquals('2025-06-01', $data['inventory'][0]['date']);

            return true;
        });

        $this->assertDatabaseHas('sync_logs', [
            'channel_id' => $this->jsonChannel->id,
            'direction' => 'out',
            'event' => 'inventory_push',
            'response_code' => 200,
            'status' => 'success',
        ]);
    }

    public function test_job_pushes_xml_payload_and_logs_success(): void
    {
        Http::fake([
            'http://localhost/mock/2/inventory' => Http::response('<response><status>ok</status></response>', 200),
        ]);

        $job = new PushInventoryToChannel(
            $this->xmlChannel,
            $this->roomType->id,
            '2025-06-01',
            '2025-06-01'
        );

        $job->handle();

        Http::assertSent(function (Request $request) {
            $this->assertEquals('beta-key-002', $request->header('X-API-Key')[0]);
            $this->assertEquals('application/xml', $request->header('Content-Type')[0]);

            $xml = simplexml_load_string($request->body());
            $this->assertNotFalse($xml);
            $this->assertEquals('4', (string) $xml->item[0]->available);

            return true;
        });

        $this->assertDatabaseHas('sync_logs', [
            'channel_id' => $this->xmlChannel->id,
            'direction' => 'out',
            'event' => 'inventory_push',
            'response_code' => 200,
            'status' => 'success',
        ]);
    }

    public function test_job_fails_and_logs_when_ota_returns_500(): void
    {
        Http::fake([
            'http://localhost/mock/1/inventory' => Http::response('Server Error', 500),
        ]);

        $job = new PushInventoryToChannel(
            $this->jsonChannel,
            $this->roomType->id,
            '2025-06-01',
            '2025-06-02'
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Channel Alpha OTA returned HTTP 500');

        try {
            $job->handle();
        } finally {
            $this->assertDatabaseHas('sync_logs', [
                'channel_id' => $this->jsonChannel->id,
                'direction' => 'out',
                'event' => 'inventory_push',
                'response_code' => 500,
                'status' => 'failed',
            ]);
        }
    }

    public function test_inventory_sync_service_dispatches_to_other_active_channels(): void
    {
        Queue::fake();

        // Create an inactive channel
        Channel::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Inactive OTA',
            'format' => 'json',
            'endpoint_url' => 'http://localhost/mock/99/inventory',
            'api_key' => 'inactive-key',
            'active' => false,
        ]);

        InventorySyncService::pushToChannels(
            $this->roomType->id,
            '2025-06-01',
            '2025-06-05',
            $this->jsonChannel->id
        );

        // Dispatched to xmlChannel (active)
        Queue::assertPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->xmlChannel->id;
        });

        // NOT dispatched to excluded jsonChannel
        Queue::assertNotPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->jsonChannel->id;
        });

        // NOT dispatched to inactive channel
        Queue::assertNotPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->name === 'Inactive OTA';
        });
    }
}
