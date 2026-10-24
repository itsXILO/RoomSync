<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MockOtaInventory extends Model
{
    protected $table = 'mock_ota_inventory';

    protected $fillable = [
        'channel_id', 'room_type_id', 'date', 'available', 'rate',
    ];

    protected $casts = [
        'date'      => 'date',
        'rate'      => 'decimal:2',
        'available' => 'integer',
    ];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = Carbon::parse($value)->format('Y-m-d');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
