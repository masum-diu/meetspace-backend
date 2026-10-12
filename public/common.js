/* Shared by every page: helpers, API client, Clerk sign-in, PWA bits. */
const $ = (s, root = document) => root.querySelector(s);
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const mins = (t) => { const [h, m] = String(t).split(':'); return +h * 60 + +m; };
const hhmm = (t) => String(t).slice(0, 5);
const t12 = (t) => { const [h, m] = hhmm(t).split(':'); return `${+h % 12 || 12}:${m} ${+h < 12 ? 'AM' : 'PM'}`; };
const nowMins = () => { const n = new Date(); return n.getHours() * 60 + n.getMinutes(); };
// is the given day + minute-of-day already over?
const inPast = (dateStr, m) => dateStr < iso(new Date()) || (dateStr === iso(new Date()) && m <= nowMins());

// a booking's start/end as a Date (bookings come with date "YYYY-MM-DD" and times "HH:MM[:SS]")
const bookingDate = (b, time) => new Date(`${String(b.date).slice(0, 10)}T${hhmm(time)}:00`);
// "Today", "Tomorrow" or "Wed 14 Oct"
function dateLabel(dateStr) {
    const d = new Date(String(dateStr).slice(0, 10) + 'T12:00:00'), t = new Date();
    const tomorrow = new Date(); tomorrow.setDate(t.getDate() + 1);
    if (iso(d) === iso(t)) return 'Today';
    if (iso(d) === iso(tomorrow)) return 'Tomorrow';
    return d.toLocaleDateString('en', { weekday: 'short', day: 'numeric', month: 'short' });
}

let toastT;
function toast(text) {
    let t = $('#toast');
    if (!t) { t = document.createElement('div'); t.id = 'toast'; document.body.append(t); }
    t.textContent = text; t.classList.add('show');
    clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('show'), 2800);
}

// Busy state for a button: disables it, shows a spinner and a label; setBusy(btn, false) restores it.
function setBusy(btn, busy, label = '') {
    if (!btn) return;
    if (busy) {
        if (btn.dataset.label === undefined) btn.dataset.label = btn.innerHTML;
        btn.disabled = true;
        btn.classList.add('busy');
        btn.setAttribute('aria-busy', 'true');
        btn.innerHTML = `<span class="spin"></span>${esc(label)}`;
    } else {
        if (btn.dataset.label !== undefined) { btn.innerHTML = btn.dataset.label; delete btn.dataset.label; }
        btn.disabled = false;
        btn.classList.remove('busy');
        btn.removeAttribute('aria-busy');
    }
}
// placeholder lines shown while a list loads
const skelLines = (n = 2) => Array.from({ length: n }, (_, i) => `<div class="skel skel-line ${i % 2 ? 'w60' : ''}"></div>`).join('');

/* ---------- session (Clerk sign-in) ---------- */
const store = {
    get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* storage blocked */ } },
    del(k) { try { localStorage.removeItem(k); } catch (e) { /* storage blocked */ } },
};
const Auth = { token: store.get('ms_token'), user: null };
try { Auth.user = JSON.parse(store.get('ms_user') || 'null'); } catch (e) { /* ignore */ }
if (!Auth.token) Auth.user = null;

// admin password lives only for this browser tab
let adminPw = null;
try { adminPw = sessionStorage.getItem('adminPw'); } catch (e) { /* storage blocked */ }

async function api(path, opts = {}) {
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
    if (Auth.token) headers.Authorization = 'Bearer ' + Auth.token;
    if (adminPw) headers['X-Admin-Password'] = adminPw;
    const res = await fetch('/api' + path, { ...opts, headers });
    const body = res.status === 204 ? null : await res.json().catch(() => null);
    if (res.status === 401 && Auth.token && path !== '/auth/clerk') clearAuth(); // session expired
    if (!res.ok) {
        const err = new Error(body?.message || 'Request failed (' + res.status + ')');
        err.status = res.status;
        throw err;
    }
    return body;
}

function saveAuth(token, user) {
    Auth.token = token; Auth.user = user;
    store.set('ms_token', token); store.set('ms_user', JSON.stringify(user));
}
function clearAuth() {
    if (!Auth.token && !Auth.user) return;
    Auth.token = Auth.user = null;
    store.del('ms_token'); store.del('ms_user');
    renderAuth();
    dispatchEvent(new Event('authchange'));
}
const initial = (u) => (u?.name || u?.email || '?').trim().charAt(0).toUpperCase();
const avatarHtml = (u, cls = '') => u?.avatar
    ? `<img class="avatar ${cls}" src="${esc(u.avatar)}" alt="" referrerpolicy="no-referrer">`
    : `<span class="avatar init ${cls}">${esc(initial(u))}</span>`;

/* ---------- Clerk sign-in ----------
   Clerk handles the sign-in UI (Google, email, ...). Once signed in, the browser
   sends Clerk's session token to /api/auth/clerk, which verifies it and returns
   the app token used for bookings. */
const clerkCfg = window.MS?.clerk;
const clerkEnabled = !!(clerkCfg?.publishableKey && clerkCfg?.frontendApi);
let clerkReady = false, clerkFailed = false, exchanging = false;

function loadClerk() {
    if (!clerkEnabled) return;
    const s = document.createElement('script');
    s.async = true;
    s.crossOrigin = 'anonymous';
    s.setAttribute('data-clerk-publishable-key', clerkCfg.publishableKey);
    s.src = `https://${clerkCfg.frontendApi}/npm/@clerk/clerk-js@5/dist/clerk.browser.js`;
    s.onload = async () => {
        try {
            await window.Clerk.load();
            clerkReady = true;
            window.Clerk.addListener(onClerk);
            onClerk({ user: window.Clerk.user });
            renderAuth(); mountGates();
        } catch (e) { clerkFailed = true; renderAuth(); mountGates(); toast('The sign-in service failed to start. Please reload.'); }
    };
    s.onerror = () => { clerkFailed = true; renderAuth(); mountGates(); toast('Could not load the sign-in service. Check your connection.'); };
    document.head.append(s);
}

// keep the app session in step with Clerk's
async function onClerk({ user }) {
    if (user && !Auth.token && !exchanging) {
        exchanging = true;
        renderAuth(); mountGates();
        try {
            const token = await window.Clerk.session.getToken();
            const r = await api('/auth/clerk', { method: 'POST', body: JSON.stringify({ token }) });
            saveAuth(r.token, r.user);
            renderAuth();
            dispatchEvent(new Event('authchange'));
            toast('Signed in as ' + r.user.name);
        } catch (e) { toast(e.message); }
        exchanging = false;
        renderAuth(); mountGates();
    } else if (!user && Auth.token) {
        try { await api('/auth/logout', { method: 'POST' }); } catch (e) { /* already gone */ }
        clearAuth();
    }
}

function openSignIn() {
    if (!clerkEnabled) return toast('Sign-in is not set up yet. The site owner must add the Clerk keys.');
    if (!clerkReady) return toast('Sign-in is still loading. Try again in a moment.');
    window.Clerk.openSignIn({ fallbackRedirectUrl: location.href, signUpFallbackRedirectUrl: location.href });
}
function openAccount() {
    if (clerkReady) window.Clerk.openUserProfile();
    else toast('Account settings are still loading. Try again in a moment.');
}

async function signOut() {
    try { if (Auth.token) await api('/auth/logout', { method: 'POST' }); } catch (e) { /* already gone */ }
    clearAuth();
    try { await window.Clerk?.signOut(); } catch (e) { /* ignore */ }
    toast('Signed out');
}

// what the sign-in buttons say while something is still happening ('' = ready)
const signInBusy = () => (exchanging ? 'Signing in…' : clerkEnabled && !clerkReady && !clerkFailed ? 'Loading sign-in…' : '');

// sign-in prompt used inside the /book and /profile gates
function mountGates() {
    const busy = signInBusy();
    document.querySelectorAll('[data-signin-slot]').forEach((el) => {
        el.innerHTML = !clerkEnabled
            ? '<p class="note warn">Sign-in is not set up yet. The site owner must add the Clerk keys.</p>'
            : busy
                ? `<button type="button" class="btn primary wide signin-btn" disabled aria-busy="true"><span class="spin"></span>${busy}</button>`
                : '<button type="button" class="btn primary wide signin-btn">Sign in or create account</button>';
        el.querySelector('.signin-btn:not(:disabled)')?.addEventListener('click', openSignIn);
    });
}

// A visitor is either a signed-in user or the admin, never both: CSS hides the other role's buttons.
function syncRoles() {
    document.body.classList.toggle('is-user', !!Auth.user);
    document.body.classList.toggle('is-admin', !!adminPw);
}

function renderAuth() {
    syncRoles();
    const area = $('#authArea');
    $('#navMine').hidden = !Auth.user;
    if (Auth.user) {
        area.innerHTML = `<a class="me" href="/profile" title="${esc(Auth.user.email)}">${avatarHtml(Auth.user)}<span>${esc(Auth.user.name.split(' ')[0])}</span></a>`;
        return;
    }
    const busy = signInBusy();
    area.innerHTML = busy
        ? `<button class="btn primary" disabled aria-busy="true"><span class="spin"></span>${exchanging ? busy : 'Sign in'}</button>`
        : '<button class="btn primary" id="signInBtn">Sign in</button>';
    $('#signInBtn')?.addEventListener('click', openSignIn);
}

/* ---------- page chrome ---------- */
document.querySelectorAll('.mainnav a').forEach((a) => a.classList.toggle('on', a.dataset.nav === document.body.dataset.page));
renderAuth();
loadClerk();
if (Auth.token) api('/me').then((u) => { Auth.user = u; store.set('ms_user', JSON.stringify(u)); renderAuth(); }).catch(() => { /* 401 handled by api() */ });

function setOnline() { $('#offline').hidden = navigator.onLine; }
addEventListener('online', setOnline);
addEventListener('offline', setOnline);
setOnline();

// PWA install
let deferredInstall;
addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredInstall = e; $('#install').hidden = false; });
const standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
const iOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
// iOS Safari has no install prompt: show the button and explain Add to Home Screen
if (iOS && !standalone) $('#install').hidden = false;
$('#install').onclick = () => {
    if (!deferredInstall) { toast('Tap the Share button, then "Add to Home Screen"'); return; }
    $('#install').hidden = true; deferredInstall.prompt(); deferredInstall = null;
};
addEventListener('appinstalled', () => { $('#install').hidden = true; });
if ('serviceWorker' in navigator) addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
