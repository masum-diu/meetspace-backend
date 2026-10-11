<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditBookingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: array<string, string>} */
    private function signedIn(string $email): array
    {
        $user = User::create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => Str::random(20)]);
        $plain = Str::random(64);
        ApiToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $plain)]);

        return [$user, ['Authorization' => "Bearer $plain"]];
    }

    private function book(Room $room, ?User $user, array $extra = []): Booking
    {
        return Booking::create(array_merge([
            'room_id' => $room->id, 'user_id' => $user?->id, 'organizer' => $user?->name ?? 'Guest', 'title' => 'Sync',
            'date' => '2030-01-01', 'start_time' => '10:00', 'end_time' => '11:00', 'attendees' => 3,
        ], $extra));
    }

    private function room(string $name = 'Room 1'): Room
    {
        return Room::create(['name' => $name, 'capacity' => 8, 'floor' => 1, 'color' => '#4f46e5']);
    }

    private function change(Booking $booking, array $extra = []): array
    {
        return array_merge([
            'room_id' => $booking->room_id, 'title' => 'Renamed', 'date' => '2030-01-01',
            'start_time' => '10:00', 'end_time' => '11:00', 'attendees' => 3,
        ], $extra);
    }

    public function test_owner_can_edit_a_booking(): void
    {
        [$ada, $auth] = $this->signedIn('ada@example.com');
        $other = $this->room('Room 2');
        $booking = $this->book($this->room(), $ada);

        $this->putJson("/api/me/bookings/{$booking->id}", $this->change($booking, [
            'room_id' => $other->id, 'title' => 'Moved', 'start_time' => '14:00', 'end_time' => '15:30', 'attendees' => 6,
        ]), $auth)->assertOk()->assertJsonPath('title', 'Moved')->assertJsonPath('room.name', 'Room 2');

        $saved = $booking->fresh();
        $this->assertSame($other->id, $saved->room_id);
        $this->assertSame('14:00', substr($saved->start_time, 0, 5)); // SQLite and MySQL differ on seconds
        $this->assertSame(6, $saved->attendees);
    }

    public function test_a_booking_does_not_clash_with_itself(): void
    {
        [$ada, $auth] = $this->signedIn('ada@example.com');
        $booking = $this->book($this->room(), $ada);

        // shrink the same slot, it overlaps its own old time
        $this->putJson("/api/me/bookings/{$booking->id}", $this->change($booking, ['end_time' => '10:30']), $auth)->assertOk();
    }

    public function test_editing_into_someone_elses_slot_is_refused(): void
    {
        [$ada, $auth] = $this->signedIn('ada@example.com');
        [$bob] = $this->signedIn('bob@example.com');
        $room = $this->room();
        $mine = $this->book($room, $ada);
        $this->book($room, $bob, ['start_time' => '12:00', 'end_time' => '13:00']);

        $this->putJson("/api/me/bookings/{$mine->id}", $this->change($mine, ['start_time' => '12:30', 'end_time' => '13:30']), $auth)
            ->assertStatus(409);
        $this->assertSame('10:00', substr($mine->fresh()->start_time, 0, 5));
    }

    public function test_cannot_edit_someone_elses_booking(): void
    {
        [, $adaAuth] = $this->signedIn('ada@example.com');
        [$bob] = $this->signedIn('bob@example.com');
        $theirs = $this->book($this->room(), $bob);

        $this->putJson("/api/me/bookings/{$theirs->id}", $this->change($theirs), $adaAuth)->assertForbidden();
        $this->assertDatabaseHas('bookings', ['id' => $theirs->id, 'title' => 'Sync']);
    }

    public function test_edit_requires_sign_in_and_valid_times(): void
    {
        [$ada, $auth] = $this->signedIn('ada@example.com');
        $booking = $this->book($this->room(), $ada);

        $this->putJson("/api/me/bookings/{$booking->id}", $this->change($booking))->assertStatus(401);
        $this->putJson("/api/me/bookings/{$booking->id}", $this->change($booking, ['end_time' => '09:00']), $auth)->assertStatus(422);
    }

    public function test_bookings_can_be_listed_from_a_day_onward_in_order(): void
    {
        $room = $this->room();
        $this->book($room, null, ['date' => '2030-01-03', 'title' => 'C']);
        $this->book($room, null, ['date' => '2030-01-01', 'start_time' => '15:00', 'end_time' => '16:00', 'title' => 'B']);
        $this->book($room, null, ['date' => '2030-01-01', 'title' => 'A']);
        $this->book($room, null, ['date' => '2029-12-31', 'title' => 'old']);

        $this->getJson('/api/bookings?from=2030-01-01')->assertOk()
            ->assertJsonCount(3)->assertJsonPath('0.title', 'A')->assertJsonPath('1.title', 'B')->assertJsonPath('2.title', 'C');
        $this->getJson('/api/bookings?from=2030-01-01&to=2030-01-02')->assertOk()->assertJsonCount(2);
    }
}
