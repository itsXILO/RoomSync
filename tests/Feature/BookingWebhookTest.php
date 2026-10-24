<?php

namespace Tests\Feature;

use App\Jobs\PushInventoryToChannel;
use App\Models\Booking;
use App\Models\Channel;
use App\Models\Hotel;
use App\Models\Inventory;
use App\Models\RoomType;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookingWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;
    protected RoomType $roomType;
    protected Channel $jsonChannel;
    protected Channel $xmlChannel;
    protected Channel $gammaChannel;

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

        $this->gammaChannel = Channel::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Gamma Stays',
            'format' => 'json',
            'endpoint_url' => 'http://localhost/mock/3/inventory',
            'api_key' => 'gamma-key-003',
            'active' => true,
        ]);

        // Seed 3 days of inventory: 2025-06-01, 2025-06-02, 2025-06-03
        foreach (['2025-06-01', '2025-06-02', '2025-06-03'] as $date) {
            Inventory::create([
                'room_type_id' => $this->roomType->id,
                'date' => $date,
                'available' => 5,
                'rate' => 150.00,
            ]);
        }

        Queue::fake();
    }

    public function test_booking_succeeds_and_decrements_all_nights(): void
    {
        Queue::fake();

        $payload = [
            'external_ref' => 'BOOK-101',
            'room_type_id' => $this->roomType->id,
            'guest_name' => 'M. Gustave',
            'check_in' => '2025-06-01',
            'check_out' => '2025-06-03', // 2 nights: June 1, June 2
            'rooms' => 2,
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/bookings", $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'external_ref' => 'BOOK-101',
            'status' => 'confirmed',
            'rooms' => 2,
        ]);

        // June 1 and June 2 decremented by 2 (5 -> 3), June 3 remains 5
        $this->assertEquals(3, Inventory::where('date', '2025-06-01')->value('available'));
        $this->assertEquals(3, Inventory::where('date', '2025-06-02')->value('available'));
        $this->assertEquals(5, Inventory::where('date', '2025-06-03')->value('available'));

        // Push jobs dispatched to other channels (Beta Travel) but not source (Alpha OTA)
        Queue::assertPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->xmlChannel->id;
        });

        Queue::assertNotPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->jsonChannel->id;
        });

        // Sync log recorded
        $this->assertDatabaseHas('sync_logs', [
            'channel_id' => $this->jsonChannel->id,
            'direction' => 'in',
            'event' => 'booking_received',
            'response_code' => 201,
            'status' => 'success',
        ]);
    }

    public function test_booking_over_capacity_is_rejected(): void
    {
        $payload = [
            'external_ref' => 'OVER-CAP-001',
            'room_type_id' => $this->roomType->id,
            'guest_name' => 'Madame D.',
            'check_in' => '2025-06-01',
            'check_out' => '2025-06-02',
            'rooms' => 10, // Only 5 available
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/bookings", $payload);

        $response->assertStatus(409);
        $this->assertDatabaseHas('bookings', [
            'external_ref' => 'OVER-CAP-001',
            'status' => 'rejected',
        ]);

        // Inventory untouched
        $this->assertEquals(5, Inventory::where('date', '2025-06-01')->value('available'));
    }

    public function test_multi_night_booking_where_one_night_full_is_rejected_entirely(): void
    {
        // Set June 2 available to 0
        Inventory::where('date', '2025-06-02')->update(['available' => 0]);

        $payload = [
            'external_ref' => 'PARTIAL-001',
            'room_type_id' => $this->roomType->id,
            'guest_name' => 'Zero',
            'check_in' => '2025-06-01',
            'check_out' => '2025-06-03',
            'rooms' => 1,
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/bookings", $payload);

        $response->assertStatus(409);

        // Neither night should be decremented
        $this->assertEquals(5, Inventory::where('date', '2025-06-01')->value('available'));
        $this->assertEquals(0, Inventory::where('date', '2025-06-02')->value('available'));
    }

    public function test_same_external_ref_twice_does_not_double_decrement(): void
    {
        $payload = [
            'external_ref' => 'IDEM-001',
            'room_type_id' => $this->roomType->id,
            'guest_name' => 'Agatha',
            'check_in' => '2025-06-01',
            'check_out' => '2025-06-02',
            'rooms' => 1,
        ];

        $first = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/bookings", $payload);

        $first->assertStatus(201);
        $this->assertEquals(4, Inventory::where('date', '2025-06-01')->value('available'));

        // Post again with same external_ref
        $second = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/bookings", $payload);

        $second->assertStatus(201);
        // Inventory still 4, not double-decremented
        $this->assertEquals(4, Inventory::where('date', '2025-06-01')->value('available'));
        $this->assertEquals(1, Booking::where('external_ref', 'IDEM-001')->count());
    }

    public function test_outbound_jobs_dispatched_to_other_channels(): void
    {
        Queue::fake();

        $payload = [
            'external_ref' => 'DISPATCH-TEST-01',
            'room_type_id' => $this->roomType->id,
            'guest_name' => 'Zero',
            'check_in' => '2025-06-01',
            'check_out' => '2025-06-02',
            'rooms' => 1,
        ];

        $response = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/bookings", $payload);

        $response->assertStatus(201);

        // Dispatched to Beta (xmlChannel) and Gamma (gammaChannel)
        Queue::assertPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->xmlChannel->id;
        });

        Queue::assertPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->gammaChannel->id;
        });

        // NOT dispatched to source channel Alpha (jsonChannel)
        Queue::assertNotPushed(PushInventoryToChannel::class, function ($job) {
            return $job->channel->id === $this->jsonChannel->id;
        });
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'wrong-key',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/bookings", []);

        $response->assertStatus(401);
    }

    public function test_xml_channel_booking(): void
    {
        Queue::fake();

        $xmlPayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<booking>
    <external_ref>XML-BOOK-500</external_ref>
    <room_type_id>{$this->roomType->id}</room_type_id>
    <guest_name>Ludwig</guest_name>
    <check_in>2025-06-01</check_in>
    <check_out>2025-06-02</check_out>
    <rooms>1</rooms>
</booking>
XML;

        $response = $this->call(
            'POST',
            "/api/webhooks/{$this->xmlChannel->id}/bookings",
            [],
            [],
            [],
            [
                'HTTP_X-API-Key' => 'beta-key-002',
                'CONTENT_TYPE' => 'application/xml',
            ],
            $xmlPayload
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'external_ref' => 'XML-BOOK-500',
            'status' => 'confirmed',
        ]);
        $this->assertEquals(4, Inventory::where('date', '2025-06-01')->value('available'));
    }

    public function test_cancellation_restores_inventory(): void
    {
        // 1. Create confirmed booking for 2 nights (June 1 and June 2) x 2 rooms
        $booking = Booking::create([
            'room_type_id' => $this->roomType->id,
            'channel_id'   => $this->jsonChannel->id,
            'external_ref' => 'CANCEL-ME-01',
            'guest_name'   => 'Dmitri',
            'check_in'     => '2025-06-01',
            'check_out'    => '2025-06-03',
            'rooms'        => 2,
            'status'       => 'confirmed',
        ]);

        // Decrement initial inventory (5 - 2 = 3)
        Inventory::whereIn('date', ['2025-06-01', '2025-06-02'])->decrement('available', 2);
        $this->assertEquals(3, Inventory::where('date', '2025-06-01')->value('available'));
        $this->assertEquals(3, Inventory::where('date', '2025-06-02')->value('available'));

        // 2. Send cancellation webhook
        $response = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/cancellations", [
            'external_ref' => 'CANCEL-ME-01',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);

        // Inventory restored to original 5
        $this->assertEquals(5, Inventory::where('date', '2025-06-01')->value('available'));
        $this->assertEquals(5, Inventory::where('date', '2025-06-02')->value('available'));

        // Log recorded
        $this->assertDatabaseHas('sync_logs', [
            'channel_id' => $this->jsonChannel->id,
            'direction' => 'in',
            'event' => 'booking_cancelled',
            'response_code' => 200,
            'status' => 'success',
        ]);
    }

    public function test_cancellation_of_nonexistent_booking_returns_404(): void
    {
        $response = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/cancellations", [
            'external_ref' => 'DOES-NOT-EXIST',
        ]);

        $response->assertStatus(404);
    }

    public function test_repeated_cancellation_is_idempotent(): void
    {
        $booking = Booking::create([
            'room_type_id' => $this->roomType->id,
            'channel_id'   => $this->jsonChannel->id,
            'external_ref' => 'ALREADY-CANCELLED',
            'guest_name'   => 'Serge X.',
            'check_in'     => '2025-06-01',
            'check_out'    => '2025-06-02',
            'rooms'        => 1,
            'status'       => 'cancelled',
        ]);

        $initialInventory = Inventory::where('date', '2025-06-01')->value('available');

        $response = $this->withHeaders([
            'X-API-Key' => 'alpha-key-001',
            'Content-Type' => 'application/json',
        ])->postJson("/api/webhooks/{$this->jsonChannel->id}/cancellations", [
            'external_ref' => 'ALREADY-CANCELLED',
        ]);

        $response->assertStatus(200);
        // Inventory not incremented again
        $this->assertEquals($initialInventory, Inventory::where('date', '2025-06-01')->value('available'));
    }
}
