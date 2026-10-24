<?php

namespace App\Services\ChannelAdapters;

use InvalidArgumentException;

class JsonAdapter implements ChannelAdapterInterface
{
    /**
     * Build the outbound JSON payload for an inventory update.
     *
     * @param  array<int, array{room_type_id: int, date: string, available: int, rate: float|string}>  $rows
     * @return string
     */
    public function buildInventoryPayload(array $rows): string
    {
        return (string) json_encode(['inventory' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Parse an inbound booking webhook JSON body.
     *
     * @param  string  $raw
     * @return array{external_ref: string, room_type_id: int, guest_name: string, check_in: string, check_out: string, rooms: int}
     */
    public function parseBookingPayload(string $raw): array
    {
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            throw new InvalidArgumentException('Invalid JSON payload');
        }

        return [
            'external_ref' => (string) ($data['external_ref'] ?? ''),
            'room_type_id' => (int) ($data['room_type_id'] ?? 0),
            'guest_name'   => (string) ($data['guest_name'] ?? ''),
            'check_in'     => (string) ($data['check_in'] ?? ''),
            'check_out'    => (string) ($data['check_out'] ?? ''),
            'rooms'        => (int) ($data['rooms'] ?? 1),
        ];
    }

    /**
     * The Content-Type header value for outbound requests.
     */
    public function contentType(): string
    {
        return 'application/json';
    }
}
