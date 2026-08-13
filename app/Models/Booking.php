<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasUuids;

    protected $fillable = [
        'room_id',
        'title',
        'organizer',
        'date',
        'start_time',
        'end_time',
        'attendees',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'attendees' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
