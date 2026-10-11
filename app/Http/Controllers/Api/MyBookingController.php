<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyBookingController extends Controller
{
    /** Bookings of the signed-in user, newest first, with their room. */
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->with('room:id,name,color,floor')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->get();

        return response()->json($bookings);
    }

    /** Users may change only their own bookings (room, title, time, guests). */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403, 'You can only edit your own bookings.');

        $data = $request->validate([
            'room_id' => ['required', 'uuid', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'attendees' => ['required', 'integer', 'min:1'],
        ]);

        // the booking itself must not count as a clash with its new time
        if (Booking::conflictExists($data['room_id'], $data['date'], $data['start_time'], $data['end_time'], $booking->id)) {
            return response()->json(['message' => 'This room is already booked for the selected time.'], 409);
        }

        $booking->update($data);

        return response()->json($booking->load('room:id,name,color,floor'));
    }

    /** Users may delete only their own bookings. */
    public function destroy(Request $request, Booking $booking): JsonResponse
    {
        abort_unless($booking->user_id === $request->user()->id, 403, 'You can only cancel your own bookings.');

        $booking->delete();

        return response()->json(null, 204);
    }
}
