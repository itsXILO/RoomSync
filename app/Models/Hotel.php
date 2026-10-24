<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    protected $fillable = ['name'];

    /** A hotel has many different room categories (Standard, Deluxe, etc.) */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    /** A hotel has many connected booking channels (OTAs) */
    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }
}
