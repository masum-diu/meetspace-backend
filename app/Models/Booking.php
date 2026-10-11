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
        'user_id',
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

    /** Is the room already taken for an overlapping time? Pass the booking's own id when editing it. */
    public static function conflictExists(string $roomId, string $date, string $startTime, string $endTime, ?string $excludeId = null): bool
    {
        return static::query()
            ->where('room_id', $roomId)
            ->where('date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when($excludeId, fn ($query, $id) => $query->where('id', '!=', $id))
            ->exists();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
