<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    // Append-only: only created_at, no updated_at
    public $timestamps = false;

    protected $fillable = [
        'channel_id', 'direction', 'event', 'payload',
        'status', 'attempts', 'response_code', 'error', 'created_at',
    ];

    protected $casts = [
        'created_at'    => 'datetime',
        'attempts'      => 'integer',
        'response_code' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
