@extends('layouts.app')

@section('title', 'About · MeetSpace')
@section('page', 'about')

@section('content')
<div class="wrap narrow">
    <header class="page-head">
        <a class="back" href="/">← Back to bookings</a>
        <h1>About</h1>
    </header>

    <section class="card about">
        <img src="/icons/icon-192.png" alt="" width="72" height="72" class="about-logo">
        <h2>MeetSpace</h2>
        <p class="note">Book meeting rooms in a few taps. See who has booked what, and manage your own bookings from anywhere.</p>
        <ul class="about-list">
            <li><span>Version</span><b>1.0</b></li>
            <li><span>Developed by</span><b>Ether Technologies</b></li>
            <li><span>Sister concern of</span><b>Webable Digital</b></li>
        </ul>
        <p class="note about-foot">&copy; {{ date('Y') }} Ether Technologies, a sister concern of Webable Digital. All rights reserved.</p>
    </section>
</div>
@endsection
