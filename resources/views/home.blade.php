@extends('layouts.app')

@section('title', 'MeetSpace')
@section('page', 'home')

@section('actions')
    <button id="adminBtn" class="btn ghost">Admin</button>
    <a id="newBtn" class="btn primary" href="/book">+ New booking</a>
@endsection

@section('content')
<div class="wrap narrow">
    <header class="page-head">
        <h1>Bookings</h1>
        <p id="dayDate" class="sub"></p>
    </header>

    <div class="viewbar">
        <div class="tabs big" id="viewTabs" role="tablist">
            <button role="tab" data-view="today" class="on">Today <span class="count" id="cToday"></span></button>
            <button role="tab" data-view="upcoming">Upcoming <span class="count" id="cUp"></span></button>
        </div>
    </div>

    <ol id="blist" class="blist" aria-live="polite"></ol>

    {{-- admin only: add, edit and delete rooms --}}
    <section id="roomsAdmin" class="card rooms-admin" hidden>
        <div class="sec-row">
            <h2 class="sec">Manage rooms</h2>
            <button id="addRoom" class="btn ghost small">+ Add room</button>
        </div>
        <ul id="roomList" class="roomlist"></ul>
    </section>
</div>

<a id="fab" class="fab" href="/book" aria-label="New booking">+</a>
@endsection

@push('modals')
<div id="scrim" class="scrim" hidden></div>

<form id="loginForm" class="sheet" hidden>
    <div class="grab"></div>
    <h2>Admin login</h2>
    <p class="note">Enter the admin password to add, edit or delete rooms and cancel bookings.</p>
    <label>Password
        <span class="pw">
            <input name="password" type="password" autocomplete="current-password" required>
            <button type="button" id="pwToggle" aria-label="Show password" aria-pressed="false">Show</button>
        </span>
    </label>
    <p id="loginMsg" role="status"></p>
    <div class="row">
        <button type="button" class="btn ghost close">Cancel</button>
        <button type="submit" class="btn primary">Log in</button>
    </div>
</form>

<form id="roomForm" class="sheet" hidden>
    <div class="grab"></div>
    <h2 id="roomTitle">Add room</h2>
    <label>Room name <input name="name" placeholder="e.g. Board Room" required maxlength="255"></label>
    <div class="row">
        <label>Capacity <input name="capacity" type="number" min="1" value="8" required></label>
        <label>Floor <input name="floor" type="number" value="1" required></label>
    </div>
    <label>Colour <input name="color" type="color" value="#4f46e5" class="colour"></label>
    <label>Amenities (comma separated) <input name="amenities" placeholder="TV, Whiteboard, Projector"></label>
    <p id="roomMsg" role="status"></p>
    <div class="row">
        <button type="button" class="btn ghost close">Cancel</button>
        <button type="submit" class="btn primary">Save room</button>
    </div>
</form>
@endpush

@section('scripts')
<script src="/app.js"></script>
@endsection
