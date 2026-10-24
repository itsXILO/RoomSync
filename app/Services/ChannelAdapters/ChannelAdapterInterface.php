<?php

namespace App\Services\ChannelAdapters;

interface ChannelAdapterInterface
{
    /**
     * Build the outbound payload for an inventory update.
     *
     * @param  array<int, array{room_type_id: int, date: string, available: int, rate: float|string}>  $rows
     * @return string The serialized payload (JSON string or XML string)
     */
    public function buildInventoryPayload(array $rows): string;

    /**
     * Parse an inbound booking webhook body.
     *
     * @param  string  $raw The raw request body
     * @return array{external_ref: string, room_type_id: int, guest_name: string, check_in: string, check_out: string, rooms: int}
     */
    public function parseBookingPayload(string $raw): array;

    /**
     * The Content-Type header value for outbound requests.
     */
    public function contentType(): string;
}
