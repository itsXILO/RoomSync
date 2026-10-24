<?php

namespace App\Services\ChannelAdapters;

use Exception;
use InvalidArgumentException;
use SimpleXMLElement;

class XmlAdapter implements ChannelAdapterInterface
{
    /**
     * Build the outbound XML payload for an inventory update.
     *
     * @param  array<int, array{room_type_id: int, date: string, available: int, rate: float|string}>  $rows
     * @return string
     */
    public function buildInventoryPayload(array $rows): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><inventory/>');

        foreach ($rows as $row) {
            $item = $xml->addChild('item');
            $item->addChild('room_type_id', (string) ($row['room_type_id'] ?? ''));
            $item->addChild('date', (string) ($row['date'] ?? ''));
            $item->addChild('available', (string) ($row['available'] ?? ''));
            $item->addChild('rate', (string) ($row['rate'] ?? ''));
        }

        return $xml->asXML() ?: '';
    }

    /**
     * Parse an inbound booking webhook XML body.
     *
     * @param  string  $raw
     * @return array{external_ref: string, room_type_id: int, guest_name: string, check_in: string, check_out: string, rooms: int}
     */
    public function parseBookingPayload(string $raw): array
    {
        libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string(trim($raw), 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $msg = ! empty($errors) ? $errors[0]->message : 'XML syntax error';
                throw new InvalidArgumentException('Invalid XML payload: ' . trim($msg));
            }
        } catch (Exception $e) {
            throw new InvalidArgumentException('Invalid XML payload: ' . $e->getMessage(), previous: $e);
        }

        return [
            'external_ref' => (string) ($xml->external_ref ?? ''),
            'room_type_id' => (int) ($xml->room_type_id ?? 0),
            'guest_name'   => (string) ($xml->guest_name ?? ''),
            'check_in'     => (string) ($xml->check_in ?? ''),
            'check_out'    => (string) ($xml->check_out ?? ''),
            'rooms'        => (int) ($xml->rooms ?? 1),
        ];
    }

    /**
     * The Content-Type header value for outbound requests.
     */
    public function contentType(): string
    {
        return 'application/xml';
    }
}
