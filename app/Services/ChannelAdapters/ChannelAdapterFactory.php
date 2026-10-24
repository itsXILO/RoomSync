<?php

namespace App\Services\ChannelAdapters;

use App\Models\Channel;
use InvalidArgumentException;

class ChannelAdapterFactory
{
    /**
     * Resolve the channel adapter for a given channel model.
     *
     * @param  Channel  $channel
     * @return ChannelAdapterInterface
     *
     * @throws InvalidArgumentException
     */
    public static function make(Channel $channel): ChannelAdapterInterface
    {
        return match ($channel->format) {
            'json' => new JsonAdapter(),
            'xml'  => new XmlAdapter(),
            default => throw new InvalidArgumentException("Unsupported channel format: {$channel->format}"),
        };
    }
}
