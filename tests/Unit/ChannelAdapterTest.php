<?php

namespace Tests\Unit;

use App\Models\Channel;
use App\Services\ChannelAdapters\ChannelAdapterFactory;
use App\Services\ChannelAdapters\JsonAdapter;
use App\Services\ChannelAdapters\XmlAdapter;
use PHPUnit\Framework\TestCase;

class ChannelAdapterTest extends TestCase
{
    public function test_json_adapter_builds_and_parses(): void
    {
        $adapter = new JsonAdapter();

        $rows = [
            ['room_type_id' => 1, 'date' => '2025-06-01', 'available' => 5, 'rate' => '150.00'],
            ['room_type_id' => 2, 'date' => '2025-06-01', 'available' => 3, 'rate' => '275.00'],
        ];

        $payload = $adapter->buildInventoryPayload($rows);
        $this->assertEquals('application/json', $adapter->contentType());

        $decoded = json_decode($payload, true);
        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded['inventory']);
        $this->assertEquals(5, $decoded['inventory'][0]['available']);

        $bookingJson = json_encode([
            'external_ref' => 'BOOK-1234',
            'room_type_id' => 1,
            'guest_name' => 'Gustave H.',
            'check_in' => '2025-06-01',
            'check_out' => '2025-06-03',
            'rooms' => 1,
        ]);

        $parsed = $adapter->parseBookingPayload($bookingJson);
        $this->assertEquals('BOOK-1234', $parsed['external_ref']);
        $this->assertEquals(1, $parsed['room_type_id']);
        $this->assertEquals('Gustave H.', $parsed['guest_name']);
        $this->assertEquals('2025-06-01', $parsed['check_in']);
        $this->assertEquals('2025-06-03', $parsed['check_out']);
        $this->assertEquals(1, $parsed['rooms']);
    }

    public function test_xml_adapter_builds_and_parses(): void
    {
        $adapter = new XmlAdapter();

        $rows = [
            ['room_type_id' => 1, 'date' => '2025-06-01', 'available' => 5, 'rate' => '150.00'],
        ];

        $payload = $adapter->buildInventoryPayload($rows);
        $this->assertEquals('application/xml', $adapter->contentType());

        $xml = simplexml_load_string($payload);
        $this->assertNotFalse($xml);
        $this->assertEquals('1', (string) $xml->item[0]->room_type_id);
        $this->assertEquals('5', (string) $xml->item[0]->available);

        $bookingXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<booking>
    <external_ref>BOOK-XML-99</external_ref>
    <room_type_id>2</room_type_id>
    <guest_name>Zero Moustafa</guest_name>
    <check_in>2025-07-01</check_in>
    <check_out>2025-07-04</check_out>
    <rooms>2</rooms>
</booking>
XML;

        $parsed = $adapter->parseBookingPayload($bookingXml);
        $this->assertEquals('BOOK-XML-99', $parsed['external_ref']);
        $this->assertEquals(2, $parsed['room_type_id']);
        $this->assertEquals('Zero Moustafa', $parsed['guest_name']);
        $this->assertEquals('2025-07-01', $parsed['check_in']);
        $this->assertEquals('2025-07-04', $parsed['check_out']);
        $this->assertEquals(2, $parsed['rooms']);
    }

    public function test_factory_creates_correct_adapter(): void
    {
        $jsonChannel = new Channel(['format' => 'json']);
        $xmlChannel = new Channel(['format' => 'xml']);

        $this->assertInstanceOf(JsonAdapter::class, ChannelAdapterFactory::make($jsonChannel));
        $this->assertInstanceOf(XmlAdapter::class, ChannelAdapterFactory::make($xmlChannel));
    }
}
