/* Home page: only the booking list, Today and Upcoming. Booking itself lives on /book. */
const UPCOMING_DAYS = 30;

let rooms = [], todayList = [], upcomingList = [], view = 'today';
let loadedOnce = false, loadSeq = 0, loadedDay = iso(new Date());
const roomOf = (id) => rooms.find((r) => r.id === id);
const isMine = (b) => !!Auth.user && b.user_id === Auth.user.id;
const hasEnded = (b, now = new Date()) => bookingDate(b, b.end_time) <= now;
const youTag = '<span class="mine">You</span>';
const byTime = (a, b) => (String(a.date).slice(0, 10) + hhmm(a.start_time)).localeCompare(String(b.date).slice(0, 10) + hhmm(b.start_time));

// one booking in the list
function item(b, now) {
    const room = roomOf(b.room_id), mine = isMine(b), over = hasEnded(b, now);
    const live = !over && bookingDate(b, b.start_time) <= now;
    let acts = '';
    if (mine && !over) {
        acts = `<div class="acts"><a class="x" href="/book?edit=${esc(b.id)}">Edit</a><button class="x mydel" data-b="${esc(b.id)}">Delete</button></div>`;
    } else if (adminPw) {
        acts = `<div class="acts"><button class="x admdel" data-b="${esc(b.id)}">Cancel booking</button></div>`;
    }
    const badge = over ? '<span class="badge done">Done</span>' : live ? '<span class="badge live">In progress</span>' : '';
    return `<li class="bi ${over ? 'done' : ''}" style="--c:${esc(room?.color || '#999')}">
        <div class="bi-time"><b>${t12(b.start_time)}</b><small>${t12(b.end_time)}</small></div>
        <div class="bi-main">
            <h3>${esc(b.title)} ${mine ? youTag : ''}</h3>
            <p><span class="dot"></span>${esc(room?.name || 'Room')}${room ? ' · Floor ' + esc(room.floor) : ''}</p>
            <p class="meta">Booked by <b>${esc(b.organizer)}</b> · ${esc(b.attendees)} guest${b.attendees > 1 ? 's' : ''}</p>
            ${acts}
        </div>
        <div class="bi-side">${badge}</div>
    </li>`;
}

const emptyState = (text) => `<li class="bi-empty"><p>${text}</p><a class="btn primary" href="/book">Book a room</a></li>`;

function renderAll() {
    if (!loadedOnce) return; // still showing the loading skeleton
    const now = new Date();
    const today = [...todayList].sort(byTime);
    const upcoming = [...upcomingList].sort(byTime);

    $('#cToday').textContent = today.length;
    $('#cUp').textContent = upcoming.length;
    document.querySelectorAll('#viewTabs button').forEach((t) => t.classList.toggle('on', t.dataset.view === view));

    if (view === 'today') {
        $('#blist').innerHTML = today.length ? today.map((b) => item(b, now)).join('') : emptyState('No bookings today.');
    } else {
        let html = '', last = '';
        upcoming.forEach((b) => {
            const d = String(b.date).slice(0, 10);
            if (d !== last) { html += `<li class="grp">${esc(dateLabel(d))}</li>`; last = d; }
            html += item(b, now);
        });
        $('#blist').innerHTML = html || emptyState(`No upcoming bookings in the next ${UPCOMING_DAYS} days.`);
    }

    // admin: room list
    $('#roomList').innerHTML = rooms.map((r) => `<li style="--c:${esc(r.color)}">
        <span class="dot"></span>
        <div><b>${esc(r.name)}</b><small>Floor ${esc(r.floor)} · fits ${esc(r.capacity)}${(r.amenities || []).length ? ' · ' + esc(r.amenities.join(', ')) : ''}</small></div>
        <button class="edit" data-room="${esc(r.id)}">Edit</button><button class="del" data-room="${esc(r.id)}">Delete</button>
    </li>`).join('') || '<li class="none">No rooms yet. Add the first one.</li>';
}

// first load: grey placeholders instead of an empty page
function renderLoading() {
    $('#cToday').textContent = ''; $('#cUp').textContent = '';
    $('#blist').innerHTML = Array.from({ length: 3 }, () => `<li class="bi skel-bi" aria-hidden="true">
        <div class="skel skel-line tall"></div><div>${skelLines(3)}</div><div></div></li>`).join('');
}
function renderLoadError() {
    $('#blist').innerHTML = '<li class="card retry"><p>Could not load the bookings. Check your connection.</p><button class="btn primary retry-btn">Try again</button></li>';
}

async function load() {
    const list = $('#blist'), seq = ++loadSeq;
    $('#dayDate').textContent = new Date().toLocaleDateString('en', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    if (loadedOnce) list.classList.add('is-loading'); else renderLoading(); // later loads keep the old list, dimmed
    list.setAttribute('aria-busy', 'true');
    let failed = false;
    try {
        const now = new Date(), tomorrow = new Date(), end = new Date();
        tomorrow.setDate(now.getDate() + 1); end.setDate(now.getDate() + UPCOMING_DAYS);
        const [r, t, u] = await Promise.all([
            api('/rooms'),
            api('/bookings?date=' + iso(now)),
            api(`/bookings?from=${iso(tomorrow)}&to=${iso(end)}`),
        ]);
        if (seq !== loadSeq) return; // a newer load took over
        rooms = r; todayList = t; upcomingList = u; loadedOnce = true; loadedDay = iso(now);
    } catch (e) { failed = true; }
    if (seq !== loadSeq) return;
    list.classList.remove('is-loading');
    list.removeAttribute('aria-busy');
    if (failed && !loadedOnce) return renderLoadError();
    if (failed) toast('Could not refresh. Showing the last data.');
    renderAll();
}

$('#viewTabs').onclick = (e) => { const t = e.target.closest('button'); if (t) { view = t.dataset.view; renderAll(); } };

$('#blist').onclick = async (e) => {
    if (e.target.closest('.retry-btn')) return load();
    // delete from the list: own bookings (any signed-in user) or any booking (admin)
    const own = e.target.closest('.mydel'), adm = e.target.closest('.admdel');
    if (own) {
        if (!confirm('Delete your booking? The room becomes free for others.')) return;
        setBusy(own, true, 'Deleting…');
        try { await api('/me/bookings/' + own.dataset.b, { method: 'DELETE' }); toast('Booking deleted'); load(); }
        catch (err) { setBusy(own, false); toast(err.message); }
    } else if (adm) {
        if (!confirm('Cancel this booking?')) return;
        setBusy(adm, true, 'Cancelling…');
        try { await api('/bookings/' + adm.dataset.b, { method: 'DELETE' }); toast('Booking cancelled'); load(); }
        catch (err) { setBusy(adm, false); handleAdminError(err); }
    }
};

// ---------- admin ----------
const loginForm = $('#loginForm'), roomForm = $('#roomForm');
const closeSheet = () => { document.querySelectorAll('.sheet').forEach((f) => { f.hidden = true; }); $('#scrim').hidden = true; };
document.querySelectorAll('.close').forEach((b) => { b.onclick = closeSheet; });
$('#scrim').onclick = closeSheet;
addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSheet(); });
function showSheet(f) { closeSheet(); f.hidden = $('#scrim').hidden = false; }

function applyAdmin() {
    syncRoles();
    $('#adminBtn').textContent = adminPw ? 'Log out' : 'Admin';
    $('#roomsAdmin').hidden = !adminPw;
    renderAll();
}
function setAdmin(pw) {
    adminPw = pw;
    try { pw ? sessionStorage.setItem('adminPw', pw) : sessionStorage.removeItem('adminPw'); } catch (e) { /* ignore */ }
    applyAdmin();
}
// a 403 means the saved password is wrong or was changed
const handleAdminError = (err) => { if (/password/i.test(err.message)) { setAdmin(null); closeSheet(); } toast(err.message); };

// show / hide password
function setPwVisible(on) {
    loginForm.password.type = on ? 'text' : 'password';
    $('#pwToggle').textContent = on ? 'Hide' : 'Show';
    $('#pwToggle').setAttribute('aria-pressed', on);
    $('#pwToggle').setAttribute('aria-label', on ? 'Hide password' : 'Show password');
}
$('#pwToggle').onclick = () => setPwVisible(loginForm.password.type === 'password');

$('#adminBtn').onclick = () => {
    if (adminPw) { setAdmin(null); return toast('Logged out'); }
    loginForm.reset(); setPwVisible(false); $('#loginMsg').textContent = ''; showSheet(loginForm); loginForm.password.focus();
};
loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const pw = loginForm.password.value, msg = $('#loginMsg'), prev = adminPw, btn = loginForm.querySelector('[type=submit]');
    adminPw = pw;
    setBusy(btn, true, 'Checking…');
    try { await api('/admin/verify'); closeSheet(); setAdmin(pw); toast('Admin mode on'); }
    catch (err) { adminPw = prev; msg.className = 'err'; msg.textContent = err.message; }
    finally { setBusy(btn, false); }
});

function openRoomForm(room) {
    roomForm.reset();
    roomForm.dataset.id = room?.id || '';
    $('#roomTitle').textContent = room ? 'Edit room' : 'Add room';
    if (room) {
        roomForm.name.value = room.name; roomForm.capacity.value = room.capacity; roomForm.floor.value = room.floor;
        roomForm.color.value = /^#[0-9a-f]{6}$/i.test(room.color) ? room.color : '#4f46e5';
        roomForm.amenities.value = (room.amenities || []).join(', ');
    }
    $('#roomMsg').textContent = '';
    showSheet(roomForm);
}
$('#addRoom').onclick = () => openRoomForm();

roomForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = roomForm.elements, id = roomForm.dataset.id, msg = $('#roomMsg');
    const data = {
        name: f.name.value.trim(), capacity: Number(f.capacity.value), floor: Number(f.floor.value), color: f.color.value,
        amenities: f.amenities.value.split(',').map((a) => a.trim()).filter(Boolean),
    };
    const btn = roomForm.querySelector('[type=submit]');
    setBusy(btn, true, 'Saving…');
    try {
        await api(id ? '/rooms/' + id : '/rooms', { method: id ? 'PUT' : 'POST', body: JSON.stringify(data) });
        closeSheet(); toast(id ? 'Room updated' : 'Room added'); load();
    } catch (err) { msg.className = 'err'; msg.textContent = err.message; if (/password/i.test(err.message)) handleAdminError(err); }
    finally { setBusy(btn, false); }
});

async function deleteRoom(room) {
    if (!room || !confirm(`Delete "${room.name}"? Its bookings may be removed too.`)) return;
    try { await api('/rooms/' + room.id, { method: 'DELETE' }); toast('Room deleted'); load(); }
    catch (err) { handleAdminError(err); }
}
$('#roomList').onclick = (e) => {
    const edit = e.target.closest('.edit'), del = e.target.closest('.del');
    if (edit) openRoomForm(roomOf(edit.dataset.room));
    else if (del) deleteRoom(roomOf(del.dataset.room));
};

addEventListener('online', load);
// signing in as a user ends admin mode
addEventListener('authchange', () => { if (Auth.user && adminPw) setAdmin(null); else renderAll(); });
if (Auth.user && adminPw) setAdmin(null);

load().then(() => { if (adminPw) applyAdmin(); });
// keep "In progress" / "Done" current, and start over when the day changes
setInterval(() => { if (iso(new Date()) !== loadedDay) load(); else renderAll(); }, 60000);
