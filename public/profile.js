/* /profile page: who I am + status of every booking I made (same list design as the home page). */
if (adminPw) location.replace('/'); // admin has no personal profile
let bookings = [], tab = 'upcoming', loaded = false;

const at = (b, time) => new Date(`${String(b.date).slice(0, 10)}T${hhmm(time)}:00`);
const LABEL = { soon: 'Upcoming', live: 'In progress', done: 'Completed' };
const STATS = ['#stUpcoming', '#stDone', '#stTotal'];

function statusOf(b, now = new Date()) {
    if (at(b, b.end_time) <= now) return 'done';
    if (at(b, b.start_time) <= now) return 'live';
    return 'soon';
}
function dur(ms) {
    const m = Math.max(1, Math.round(ms / 60000));
    if (m < 60) return `${m} min`;
    if (m < 60 * 24) return `${Math.floor(m / 60)} h${m % 60 ? ' ' + (m % 60) + ' min' : ''}`;
    const d = Math.floor(m / 1440);
    return `${d} day${d > 1 ? 's' : ''}`;
}
function whenText(b, st, now) {
    if (st === 'live') return `Ends in ${dur(at(b, b.end_time) - now)}`;
    if (st === 'soon') return `Starts in ${dur(at(b, b.start_time) - now)}`;
    return '';
}

function show() {
    const signedIn = !!Auth.user;
    $('#gate').hidden = signedIn;
    $('#profileWrap').hidden = !signedIn;
    if (!signedIn) { mountGates(); return; }

    const u = Auth.user;
    $('#pName').textContent = u.name;
    $('#pEmail').textContent = u.email;
    const img = $('#pAvatar');
    if (u.avatar) { img.src = u.avatar; img.hidden = false; } else img.hidden = true;
    if (!loaded) { renderLoading(); load(); } else render();
}

// grey placeholders while the bookings load
function renderLoading() {
    STATS.forEach((id) => { const el = $(id); el.textContent = '0000'; el.classList.add('skel'); });
    ['#tcUp', '#tcDone', '#tcAll'].forEach((id) => { $(id).textContent = ''; });
    $('#myList').innerHTML = Array.from({ length: 3 }, () => `<li class="bi skel-bi" aria-hidden="true">
        <div class="skel skel-line tall"></div><div>${skelLines(3)}</div><div></div></li>`).join('');
}

async function load() {
    try { bookings = await api('/me/bookings'); loaded = true; }
    catch (e) {
        if (e.status !== 401) {
            STATS.forEach((id) => { const el = $(id); el.classList.remove('skel'); el.textContent = '–'; });
            $('#myList').innerHTML = '<li class="card retry"><p>Could not load your bookings. Check your connection.</p><button class="btn primary retry-btn">Try again</button></li>';
        }
        return;
    }
    render();
}

// one booking, same markup as on the home page
function item({ b, st }, now) {
    const room = b.room || {}, d = at(b, b.start_time), rel = whenText(b, st, now);
    const acts = st !== 'done'
        ? `<div class="acts"><a class="x" href="/book?edit=${esc(b.id)}">Edit</a><button class="x mydel" data-id="${esc(b.id)}">Delete</button></div>`
        : '';
    return `<li class="bi ${st === 'done' ? 'done' : ''}" style="--c:${esc(room.color || '#999')}">
        <div class="bi-time"><b>${t12(b.start_time)}</b><small>${t12(b.end_time)}</small></div>
        <div class="bi-main">
            <h3>${esc(b.title)}</h3>
            <p><span class="dot"></span>${esc(room.name || 'Room')}${room.floor != null ? ' · Floor ' + esc(room.floor) : ''}</p>
            <p class="meta">${d.toLocaleDateString('en', { weekday: 'short', day: 'numeric', month: 'short' })} · ${esc(b.attendees)} guest${b.attendees > 1 ? 's' : ''}${rel ? ' · ' + rel : ''}</p>
            ${acts}
        </div>
        <div class="bi-side"><span class="badge ${st}">${LABEL[st]}</span></div>
    </li>`;
}

function render() {
    STATS.forEach((id) => $(id).classList.remove('skel'));
    const now = new Date();
    const rows = bookings.map((b) => ({ b, st: statusOf(b, now) }));
    const upcoming = rows.filter((r) => r.st !== 'done').sort((x, y) => at(x.b, x.b.start_time) - at(y.b, y.b.start_time));
    const done = rows.filter((r) => r.st === 'done').sort((x, y) => at(y.b, y.b.start_time) - at(x.b, x.b.start_time));

    $('#stUpcoming').textContent = upcoming.length;
    $('#stDone').textContent = done.length;
    $('#stTotal').textContent = rows.length;
    $('#tcUp').textContent = upcoming.length;
    $('#tcDone').textContent = done.length;
    $('#tcAll').textContent = rows.length;
    document.querySelectorAll('.tabs button').forEach((t) => t.classList.toggle('on', t.dataset.tab === tab));

    const list = tab === 'upcoming' ? upcoming : tab === 'done' ? done : [...upcoming, ...done];
    if (!list.length) {
        const msg = { upcoming: 'No upcoming bookings.', done: 'No completed bookings yet.', all: "You haven't booked anything yet." }[tab];
        $('#myList').innerHTML = `<li class="bi-empty"><p>${msg}</p><a class="btn primary" href="/book">Book a room</a></li>`;
        return;
    }
    let html = '', last = '';
    list.forEach((r) => {
        const d = String(r.b.date).slice(0, 10);
        if (d !== last) { html += `<li class="grp">${esc(dateLabel(d))}</li>`; last = d; }
        html += item(r, now);
    });
    $('#myList').innerHTML = html;
}

$('.tabs').onclick = (e) => { const t = e.target.closest('button'); if (t) { tab = t.dataset.tab; render(); } };
$('#myList').onclick = async (e) => {
    if (e.target.closest('.retry-btn')) { renderLoading(); return load(); }
    const btn = e.target.closest('button.mydel');
    if (!btn || !confirm('Delete this booking? The room becomes free for others.')) return;
    setBusy(btn, true, 'Deleting…');
    try { await api('/me/bookings/' + btn.dataset.id, { method: 'DELETE' }); toast('Booking deleted'); load(); }
    catch (err) { setBusy(btn, false); toast(err.message); }
};
$('#signOut').onclick = signOut;
$('#manageAcct').onclick = openAccount;

addEventListener('authchange', () => { loaded = false; bookings = []; show(); });
show();
setInterval(() => { if (loaded) render(); }, 60000);

// message after coming back from /book
const FLASH = { booked: 'Booking confirmed', updated: 'Booking updated', deleted: 'Booking deleted' };
const flash = Object.keys(FLASH).find((k) => new URLSearchParams(location.search).get(k) === '1');
if (flash) { toast(FLASH[flash]); history.replaceState(null, '', '/profile'); }
