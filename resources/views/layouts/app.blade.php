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
        </nav>
        <div class="actions">
            @yield('actions')
            <button id="install" class="btn ghost" hidden>Install app</button>
            <div id="authArea"></div>
        </div>
    </div>
</div>

@yield('content')

@stack('modals')

<script src="/common.js"></script>
@yield('scripts')
</body>
</html>
