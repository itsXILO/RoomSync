<?php

namespace App\Services;

use App\Jobs\PushInventoryToChannel;
use App\Models\Channel;

class InventorySyncService
{
    /**
     * Dispatch inventory push jobs to all active channels except the specified one.
     */
    public static function pushToChannels(int $roomTypeId, string $dateFrom, string $dateTo, ?int $excludeChannelId = null): void
    {
        $channels = Channel::where('active', true)
            ->when($excludeChannelId, fn ($q) => $q->where('id', '!=', $excludeChannelId))
            ->get();

        foreach ($channels as $channel) {
            PushInventoryToChannel::dispatch($channel, $roomTypeId, $dateFrom, $dateTo);
        }
    }
}
