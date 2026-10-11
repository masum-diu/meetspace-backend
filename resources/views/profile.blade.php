@extends('layouts.app')

@section('title', 'My bookings · MeetSpace')
@section('page', 'profile')

@section('content')
<div class="wrap">
    {{-- shown when signed out --}}
    <section id="gate" class="card gate" hidden>
        <h2>Sign in to see your bookings</h2>
        <p class="note">Sign in to view your profile and the status of every room you booked.</p>
        <div class="gbtn-slot" data-signin-slot></div>
    </section>

    <div id="profileWrap" hidden>
        <section class="card profile">
            <img id="pAvatar" class="avatar big" alt="" referrerpolicy="no-referrer" hidden>
            <div class="pinfo">
                <h1 id="pName"></h1>
                <p id="pEmail" class="sub"></p>
            </div>
            <div class="pactions">
                <a class="btn primary" href="/book">+ New booking</a>
                <button id="manageAcct" class="btn ghost">Manage account</button>
                <button id="signOut" class="btn ghost">Sign out</button>
            </div>
        </section>

        <section class="stats">
            <div class="stat"><span id="stUpcoming">–</span><small>Upcoming</small></div>
            <div class="stat"><span id="stDone">–</span><small>Completed</small></div>
            <div class="stat"><span id="stTotal">–</span><small>Total bookings</small></div>
        </section>

        <h2 class="sec">My bookings</h2>
        <div class="viewbar">
            <div class="tabs big" role="tablist">
                <button role="tab" data-tab="upcoming" class="on">Upcoming <span class="count" id="tcUp"></span></button>
                <button role="tab" data-tab="done">Completed <span class="count" id="tcDone"></span></button>
                <button role="tab" data-tab="all">All <span class="count" id="tcAll"></span></button>
            </div>
        </div>
        <ol id="myList" class="blist" aria-live="polite"></ol>
    </div>
</div>
@endsection

@section('scripts')
<script src="/profile.js"></script>
@endsection
