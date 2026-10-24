<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Hotel;
use App\Models\MockOtaInventory;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockOtaTest extends TestCase
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
    }

    public function test_mock_ota_accepts_json_inventory_push(): void
    {
        $payload = [
            'inventory' => [
                [
                    'room_type_id' => $this->roomType->id,
                    'date' => '2025-06-01',
                    'available' => 4,
                    'rate' => '150.00',
                ],
                [
                    'room_type_id' => $this->roomType->id,
                    'date' => '2025-06-02',
                    'available' => 2,
                    'rate' => '160.00',
                ],
            ],
        ];

        $response = $this->postJson("/mock/{$this->jsonChannel->id}/inventory", $payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success', 'updated' => 2]);

        $this->assertDatabaseHas('mock_ota_inventory', [
            'channel_id' => $this->jsonChannel->id,
            'room_type_id' => $this->roomType->id,
            'date' => '2025-06-01',
            'available' => 4,
        ]);

        $this->assertDatabaseHas('mock_ota_inventory', [
            'channel_id' => $this->jsonChannel->id,
            'room_type_id' => $this->roomType->id,
            'date' => '2025-06-02',
            'available' => 2,
        ]);
    }

    public function test_mock_ota_accepts_xml_inventory_push(): void
    {
        $xmlPayload = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<inventory>
    <item>
        <room_type_id>{$this->roomType->id}</room_type_id>
        <date>2025-06-01</date>
        <available>3</available>
        <rate>175.00</rate>
    </item>
</inventory>
XML;

        $response = $this->call(
            'POST',
            "/mock/{$this->xmlChannel->id}/inventory",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/xml'],
            $xmlPayload
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('mock_ota_inventory', [
            'channel_id' => $this->xmlChannel->id,
            'room_type_id' => $this->roomType->id,
            'date' => '2025-06-01',
            'available' => 3,
        ]);
    }

    public function test_mock_ota_failure_injection_returns_500(): void
    {
        $response = $this->postJson("/mock/{$this->jsonChannel->id}/inventory?fail=1.0", [
            'inventory' => [],
        ]);

        $response->assertStatus(500);
        $response->assertJsonStructure(['error']);
    }

    public function test_mock_ota_get_state_returns_inventory(): void
    {
        MockOtaInventory::create([
            'channel_id' => $this->jsonChannel->id,
            'room_type_id' => $this->roomType->id,
            'date' => '2025-06-01',
            'available' => 3,
            'rate' => 150.00,
        ]);

        $response = $this->getJson("/mock/{$this->jsonChannel->id}/state");

        $response->assertStatus(200);
        $response->assertJson([
            'channel' => 'Alpha OTA',
            'format' => 'json',
        ]);
        $response->assertJsonCount(1, 'inventory');
    }
}
