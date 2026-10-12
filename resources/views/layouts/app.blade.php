<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', 'MeetSpace')</title>
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="MeetSpace">
    <link rel="stylesheet" href="/app.css">
    <script>window.MS = { clerk: @json(\App\Support\Clerk::forBrowser()) };</script>
</head>
<body data-page="@yield('page')">
<div id="offline" class="offline" hidden>You're offline · showing saved schedule</div>

<div class="appbar">
    <div class="bar-in">
        <a class="brand" href="/"><img src="/icons/icon-192.png" alt="" width="28" height="28"><span>MeetSpace</span></a>
        <nav class="mainnav" aria-label="Main">
            <a href="/profile" data-nav="profile" id="navMine" hidden>My bookings</a>
            <a href="/about" data-nav="about">About</a>
        </nav>
        <div class="actions">
            @yield('actions')
            <button id="install" class="btn ghost" hidden>Install app</button>
            <div id="authArea"></div>
        </div>
    </div>
</div>

@yield('content')

<nav class="bottomnav" aria-label="Primary">
    <a href="/" data-nav="home"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg><span>Home</span></a>
    <a href="/book" data-nav="book"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Book</span></a>
    <a href="/profile" data-nav="profile"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg><span>My bookings</span></a>
    <a href="/about" data-nav="about"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><span>About</span></a>
</nav>

@stack('modals')

<script src="/common.js"></script>
@yield('scripts')
</body>
</html>
