<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->when($request->query('room_id'), fn (Builder $query, $roomId) => $query->where('room_id', $roomId))
            ->when($request->query('date'), fn (Builder $query, $date) => $query->where('date', $date))
            // upcoming view: everything from a day onward (optionally up to another day)
            ->when($request->query('from'), fn (Builder $query, $from) => $query->where('date', '>=', $from))
            ->when($request->query('to'), fn (Builder $query, $to) => $query->where('date', '<=', $to))
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(500)
            ->get();

        return response()->json($bookings);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'attendees' => ['required', 'integer', 'min:1'],
        ]);

        // the booking belongs to whoever is signed in
        $user = $request->user();
        $data['user_id'] = $user->id;
        $data['organizer'] = $user->name;

        if ($this->hasConflict($data['room_id'], $data['date'], $data['start_time'], $data['end_time'])) {
            return response()->json(['message' => 'This room is already booked for the selected time.'], 409);
        }

        $booking = Booking::create($data);

        return response()->json($booking, 201);
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $booking->delete();

        return response()->json(null, 204);
    }

    public function checkConflict(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $conflict = $this->hasConflict($data['room_id'], $data['date'], $data['start_time'], $data['end_time']);

        return response()->json(['conflict' => $conflict]);
    }

    private function hasConflict(string $roomId, string $date, string $startTime, string $endTime): bool
    {
        return Booking::conflictExists($roomId, $date, $startTime, $endTime);
    }
}
