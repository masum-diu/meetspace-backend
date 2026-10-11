@extends('layouts.app')

@section('title', 'New booking · MeetSpace')
@section('page', 'book')

@section('content')
<div class="wrap narrow">
    <header class="page-head">
        <a class="back" href="/">← Back to rooms</a>
        <h1 id="pageTitle">New booking</h1>
        <p id="pageSub" class="sub">Three quick steps: room, time, details.</p>
    </header>

    {{-- shown when signed out --}}
    <section id="gate" class="card gate" hidden>
        <h2>Sign in to book a room</h2>
        <p class="note">Sign in (Google or email) so every booking is linked to you and you can track it in My bookings.</p>
        <div class="gbtn-slot" data-signin-slot></div>
    </section>

    <div id="bookWrap" hidden>
        <form id="bookForm" novalidate>
            <ol id="steps" class="steps" aria-label="Booking steps">
                <li data-step="1"><b>1</b><span>Room</span></li>
                <li data-step="2"><b>2</b><span>Time</span></li>
                <li data-step="3"><b>3</b><span>Details</span></li>
            </ol>

            {{-- step 1 --}}
            <section class="card step" data-panel="1">
                <h2>Choose a room</h2>
                <div id="roomPick" class="roompick"></div>
            </section>

            {{-- step 2 --}}
            <section class="card step" data-panel="2" hidden>
                <h2>Pick a day and time</h2>
                <div id="dayPick" class="daypick"></div>
                <p id="slotHint" class="note"></p>
                <div id="slotGrid" class="slotgrid"></div>
                <div class="legend2">
                    <span><i class="lg free"></i>Free</span>
                    <span><i class="lg taken"></i>Booked (tap to see who)</span>
                    <span><i class="lg sel"></i>Your time</span>
                </div>
            </section>

            {{-- step 3 --}}
            <section class="card step" data-panel="3" hidden>
                <h2>Meeting details</h2>
                <div id="who" class="who"></div>
                <label>Meeting title <input name="title" placeholder="e.g. Design sync" maxlength="255" autocomplete="off"></label>
                <label>Guests
                    <span class="stepper">
                        <button type="button" id="gMinus" aria-label="Fewer guests">−</button>
                        <input name="attendees" type="number" min="1" max="500" value="2" inputmode="numeric">
                        <button type="button" id="gPlus" aria-label="More guests">+</button>
                    </span>
                </label>
                <p id="check" class="check" role="status"></p>
                <button type="button" id="deleteBtn" class="btn danger wide" hidden>Delete this booking</button>
            </section>

            {{-- always visible: what you've picked so far + navigation --}}
            <div class="sumbar">
                <div class="sum"><b id="sumMain">Choose a room</b><small id="sumSub"></small></div>
                <div class="navbtns">
                    <button type="button" id="backBtn" class="btn ghost" hidden>Back</button>
                    <button type="submit" id="nextBtn" class="btn primary">Next</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="/book.js"></script>
@endsection
