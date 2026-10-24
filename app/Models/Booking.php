<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'room_type_id', 'channel_id', 'external_ref', 'guest_name',
        'check_in', 'check_out', 'rooms', 'status', 'reason',
    ];

    protected $casts = [
        'check_in'  => 'date',
        'check_out' => 'date',
        'rooms'     => 'integer',
    ];

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
