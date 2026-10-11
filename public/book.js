/* /book page: sign-in gate + 3-step wizard (room -> time -> details). ?edit=<id> reuses it to change a booking. */
if (adminPw) location.replace('/'); // the admin manages rooms from the home page, not bookings of their own

const OPEN = 7 * 60, CLOSE = 22 * 60, STEP = 30;          // bookable day (minutes), slot size
const form = $('#bookForm');
const f = form.elements;                                    // named fields: f.title, f.attendees
const params = new URLSearchParams(location.search);
const editId = params.get('edit');

let rooms = [], dayBookings = [], ready = false, editing = null, step = 1, daySeq = 0, dayLoading = false;
// picking = the start slot is chosen and the end slot is still to be tapped
const state = { room: null, date: iso(new Date()), start: null, dur: 60, picking: false };
let lo = OPEN, hi = CLOSE;                                  // visible part of the day

const pad = (n) => String(n).padStart(2, '0');
const fmtTime = (m) => `${pad(Math.floor(m / 60) % 24)}:${pad(m % 60)}`;
const roomOf = (id) => rooms.find((r) => r.id === id);
const durText = (m) => (m < 60 ? `${m} min` : m % 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m / 60} h`);
const editDate = () => (editing ? String(editing.date).slice(0, 10) : null);

/* ---------- availability ---------- */
// free / taken / past for the 30 minutes starting at minute m
function slotState(m) {
    const b = dayBookings.find((x) => mins(x.start_time) < m + STEP && mins(x.end_time) > m);
    if (b) return { st: 'taken', b };
    // the part of the booking being edited that is already running stays selectable
    const inOriginal = editing && state.date === editDate() && m >= mins(editing.start_time) && m < mins(editing.end_time);
    if (!inOriginal && inPast(state.date, m)) return { st: 'past' };
    return { st: 'free' };
}
function rangeFree(start, dur) {
    if (start + dur > hi) return false;
    for (let m = start; m < start + dur; m += STEP) if (slotState(m).st !== 'free') return false;
    return true;
}
// a start chosen outside this page (timeline link): default to 1 hour if it is free, else 30 minutes
function fitDuration() { state.dur = rangeFree(state.start, 60) ? 60 : STEP; state.picking = false; }
const hasTime = () => state.start != null && !state.picking;
const reach = () => (state.room ? (hasTime() ? 3 : 2) : 1);              // furthest step the user may open

/* ---------- rendering ---------- */
function renderSteps() {
    document.querySelectorAll('#steps li').forEach((li) => {
        const k = +li.dataset.step;
        li.classList.toggle('on', k === step);
        li.classList.toggle('done', k < step);
        li.classList.toggle('locked', k > reach());
    });
    document.querySelectorAll('[data-panel]').forEach((p) => { p.hidden = +p.dataset.panel !== step; });
    $('#backBtn').hidden = step === 1;
    $('#deleteBtn').hidden = !(editing && step === 3);
}

function renderRooms() {
    $('#roomPick').innerHTML = rooms.map((r) => {
        const tags = (r.amenities || []).slice(0, 4).map((a) => `<span class="tag">${esc(a)}</span>`).join('');
        return `<button type="button" class="rp ${state.room === r.id ? 'on' : ''}" data-room="${esc(r.id)}" style="--c:${esc(r.color)}" aria-pressed="${state.room === r.id}">
            <span class="rp-top"><span class="dot"></span><b>${esc(r.name)}</b><i class="rp-tick">✓</i></span>
            <small>Floor ${esc(r.floor)} · fits ${esc(r.capacity)} people</small>${tags ? `<span class="tags">${tags}</span>` : ''}</button>`;
    }).join('') || '<p class="note">No rooms yet.</p>';
}

function renderDays() {
    const today = new Date(); today.setHours(12, 0, 0, 0);
    const first = state.date < iso(today) ? new Date(state.date + 'T12:00:00') : today;
    const days = Array.from({ length: 14 }, (_, i) => { const d = new Date(first); d.setDate(first.getDate() + i); return d; });
    if (!days.some((d) => iso(d) === state.date)) days[0] = new Date(state.date + 'T12:00:00');
    $('#dayPick').innerHTML = days.map((d) => `<button type="button" class="dp ${iso(d) === state.date ? 'on' : ''}" data-d="${iso(d)}">
        <small>${d.toLocaleDateString('en', { weekday: 'short' })}</small><b>${d.getDate()}</b><i>${d.toLocaleDateString('en', { month: 'short' })}</i></button>`).join('');
    $('#dayPick .on')?.scrollIntoView({ inline: 'center', block: 'nearest' });
}

function renderSlots() {
    const grid = $('#slotGrid');
    if (!state.room) { grid.innerHTML = ''; return; }
    if (dayLoading) { grid.innerHTML = Array.from({ length: 12 }, () => '<div class="skel skel-slot"></div>').join(''); return; }
    let html = '';
    for (let m = lo; m < hi; m += STEP) {
        const { st, b } = slotState(m);
        const sel = state.start != null && m >= state.start && m < state.start + state.dur;
        const edge = sel ? `${m === state.start ? ' first' : ''}${m + STEP >= state.start + state.dur ? ' last' : ''}` : '';
        // while the end is being picked, show where each reachable slot would end the booking
        const reachable = state.picking && m > state.start && rangeFree(state.start, m + STEP - state.start);
        const small = st === 'taken' ? `by ${esc(b.organizer)}` : reachable ? `ends ${t12(fmtTime(m + STEP))}` : state.picking && m === state.start ? 'start' : '';
        const tip = st === 'taken' ? `Booked: ${b.title} · by ${b.organizer}` : st === 'past' ? 'Already passed'
            : reachable ? `End the booking at ${t12(fmtTime(m + STEP))}` : 'Free';
        html += `<button type="button" class="slot ${st}${sel ? ' sel' : ''}${edge}${reachable ? ' reach' : ''}" data-m="${m}" ${st === 'past' ? 'disabled' : ''} title="${esc(tip)}">
            ${t12(fmtTime(m))}${small ? `<small>${small}</small>` : ''}</button>`;
    }
    grid.innerHTML = html;
    const room = roomOf(state.room);
    $('#slotHint').textContent = state.start == null
        ? `Step 1: tap the START time in ${room.name}.`
        : state.picking
            ? `Starts at ${t12(fmtTime(state.start))}. Step 2: tap the last slot you need (it ends at the time shown), or tap the start again for 30 minutes.`
            : `Booked from ${t12(fmtTime(state.start))} to ${t12(fmtTime(state.start + state.dur))} (${durText(state.dur)}). Tap any slot to choose a different time.`;
}

function renderSummary() {
    const room = roomOf(state.room);
    $('#sumMain').textContent = room ? room.name : 'Choose a room';
    if (!room) { $('#sumSub').textContent = `Step ${step} of 3`; return; }
    const day = new Date(state.date + 'T12:00:00').toLocaleDateString('en', { weekday: 'short', day: 'numeric', month: 'short' });
    $('#sumSub').textContent = state.start == null
        ? `${day} · pick a start time`
        : state.picking
            ? `${day} · from ${t12(fmtTime(state.start))} · now pick the end`
            : `${day} · ${t12(fmtTime(state.start))} – ${t12(fmtTime(state.start + state.dur))} (${durText(state.dur)})`;
}

function canNext() { return step === 1 ? !!state.room : step === 2 ? hasTime() && rangeFree(state.start, state.dur) : false; }
function canSubmit() { return !!state.room && hasTime() && f.title.value.trim() !== '' && Number(f.attendees.value) >= 1; }

function renderCheck() {
    const c = $('#check'), room = roomOf(state.room), guests = Number(f.attendees.value);
    if (!f.title.value.trim()) { c.className = 'check'; c.textContent = 'Add a meeting title to continue.'; }
    else if (!(guests >= 1)) { c.className = 'check err'; c.textContent = 'There must be at least 1 guest.'; }
    else if (room && guests > room.capacity) { c.className = 'check warn'; c.textContent = `Heads up: ${room.name} fits ${room.capacity} people.`; }
    else { c.className = 'check ok'; c.textContent = 'All set. Check the summary below and confirm.'; }
}

function renderNav() {
    const btn = $('#nextBtn');
    if (btn.dataset.label !== undefined) return; // busy: setBusy() owns the button
    const last = step === 3;
    btn.textContent = last ? (editing ? 'Save changes' : 'Confirm booking') : 'Next';
    btn.disabled = last ? !canSubmit() : !canNext();
}

function renderAll() {
    renderSteps(); renderRooms(); renderDays(); renderSlots(); renderSummary(); renderCheck(); renderNav();
}
function go(n) { step = Math.max(1, Math.min(n, reach())); renderAll(); window.scrollTo({ top: 0, behavior: 'smooth' }); }

/* ---------- data ---------- */
async function loadDay() {
    if (!state.room) return;
    const seq = ++daySeq;
    dayLoading = true; renderSlots(); renderNav();
    let list = [];
    try { list = await api(`/bookings?room_id=${encodeURIComponent(state.room)}&date=${state.date}`); }
    catch (e) { toast('Could not check availability. Check your connection.'); }
    if (seq !== daySeq) return; // room or day changed again while loading
    dayBookings = list.filter((b) => b.id !== editing?.id).sort((a, b) => mins(a.start_time) - mins(b.start_time));
    dayLoading = false;
    if (state.start != null && !rangeFree(state.start, state.dur)) { state.start = null; state.picking = false; } // someone took it
    renderAll();
}

function showState() {
    const signedIn = !!Auth.user;
    $('#gate').hidden = signedIn;
    $('#bookWrap').hidden = !signedIn;
    if (!signedIn) { mountGates(); return; }
    $('#who').innerHTML = `${avatarHtml(Auth.user)}<div>Booking as <b>${esc(Auth.user.name)}</b><small>${esc(Auth.user.email)}</small></div>`;
    if (!ready) init();
}

// leave the page when the booking to edit can't be changed
function leave(message) { toast(message); setTimeout(() => { location.href = '/profile'; }, 1200); }

// the whole page is dimmed (and not clickable) while rooms and the booking load
async function init() {
    $('#bookWrap').classList.add('is-loading');
    $('#bookWrap').setAttribute('aria-busy', 'true');
    try { await loadForm(); } finally { $('#bookWrap').classList.remove('is-loading'); $('#bookWrap').removeAttribute('aria-busy'); }
}

async function loadForm() {
    ready = true;
    try { rooms = await api('/rooms'); } catch (e) { toast('Could not load rooms. Check your connection.'); ready = false; return; }
    const today = iso(new Date());
    let wantedStart = null;

    if (editId) {
        try { editing = (await api('/me/bookings')).find((b) => b.id === editId) || null; }
        catch (e) { return leave('Could not load your booking.'); }
        if (!editing) return leave('That booking was not found, or it is not yours.');
        if (bookingDate(editing, editing.end_time) <= new Date()) return leave('That booking is over, so it can no longer be edited.');

        state.room = editing.room_id;
        state.date = editDate();
        state.start = mins(editing.start_time);
        state.dur = mins(editing.end_time) - mins(editing.start_time);
        lo = Math.min(OPEN, Math.floor(state.start / 60) * 60);
        hi = Math.max(CLOSE, Math.ceil((state.start + state.dur) / 60) * 60);
        f.title.value = editing.title;
        f.attendees.value = editing.attendees;
        step = 3;
        $('#pageTitle').textContent = 'Edit booking';
        $('#pageSub').textContent = 'Change anything you need, or delete the booking.';
        document.title = 'Edit booking · MeetSpace';
    } else {
        if (params.get('room') && roomOf(params.get('room'))) state.room = params.get('room');
        const wantedDate = params.get('date');
        if (wantedDate && wantedDate >= today) state.date = wantedDate;
        if (/^\d{2}:\d{2}$/.test(params.get('start') || '')) { wantedStart = mins(params.get('start')); state.start = wantedStart; }
        step = state.room ? (state.start != null ? 3 : 2) : 1; // coming from the timeline: room and start are already chosen
    }

    renderAll();
    await loadDay();
    if (wantedStart != null && state.start == null) { toast('That time is no longer free. Pick another.'); step = state.room ? 2 : 1; renderAll(); }
    else if (state.start != null) { fitDuration(); renderAll(); }
}

/* ---------- interaction ---------- */
$('#roomPick').onclick = (e) => {
    const b = e.target.closest('.rp'); if (!b) return;
    state.room = b.dataset.room;
    if (state.picking) { state.start = null; state.picking = false; }
    renderAll(); loadDay();
};
$('#dayPick').onclick = (e) => {
    const b = e.target.closest('.dp'); if (!b) return;
    state.date = b.dataset.d;
    if (state.picking) { state.start = null; state.picking = false; }
    renderAll(); loadDay();
};
// start -> end: the first tap sets the start, the second tap on a later slot sets the end (that slot's end time)
$('#slotGrid').onclick = (e) => {
    const b = e.target.closest('.slot'); if (!b) return;
    const m = +b.dataset.m, { st, b: taken } = slotState(m);
    if (st === 'taken') {
        if (state.picking && m > state.start) return toast(`"${taken.title}" (${t12(taken.start_time)} – ${t12(taken.end_time)}) is in the way. Pick an end before it.`);
        return toast(`"${taken.title}" is booked by ${taken.organizer} (${t12(taken.start_time)} – ${t12(taken.end_time)})`);
    }
    if (st !== 'free') return;

    if (state.picking && m > state.start) {
        const dur = m + STEP - state.start;
        if (!rangeFree(state.start, dur)) return toast('A booking or a closed time is in between. Pick an earlier end.');
        state.dur = dur; state.picking = false;
    } else if (state.picking && m === state.start) {
        state.dur = STEP; state.picking = false;            // tapping the start again = just that 30 minutes
    } else {
        state.start = m; state.dur = STEP; state.picking = true; // new start (also when tapping before the old start)
    }
    renderAll();
};
$('#steps').onclick = (e) => { const li = e.target.closest('li'); if (li && +li.dataset.step <= reach()) go(+li.dataset.step); };
$('#backBtn').onclick = () => go(step - 1);

$('#gMinus').onclick = () => { f.attendees.value = Math.max(1, Number(f.attendees.value || 1) - 1); renderCheck(); renderNav(); };
$('#gPlus').onclick = () => { f.attendees.value = Math.min(500, Number(f.attendees.value || 0) + 1); renderCheck(); renderNav(); };
form.addEventListener('input', () => { renderCheck(); renderNav(); });

form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    if (step < 3) { if (canNext()) go(step + 1); return; }
    if (!canSubmit()) return;

    const btn = $('#nextBtn'), check = $('#check');
    const data = {
        room_id: state.room, title: f.title.value.trim(), date: state.date,
        start_time: fmtTime(state.start), end_time: fmtTime(state.start + state.dur), attendees: Number(f.attendees.value),
    };
    setBusy(btn, true, editing ? 'Saving…' : 'Booking…');
    try {
        if (editing) await api('/me/bookings/' + editing.id, { method: 'PUT', body: JSON.stringify(data) });
        else await api('/bookings', { method: 'POST', body: JSON.stringify(data) });
        location.href = editing ? '/profile?updated=1' : '/profile?booked=1';
    } catch (err) {
        setBusy(btn, false);
        if (err.status === 409) { toast('Someone just booked that time. Pick another.'); state.start = null; step = 2; await loadDay(); go(2); }
        else { check.className = 'check err'; check.textContent = err.message; renderNav(); }
    }
});

$('#deleteBtn').onclick = async () => {
    if (!editing || !confirm('Delete this booking? The room becomes free for others.')) return;
    const btn = $('#deleteBtn');
    setBusy(btn, true, 'Deleting…');
    try { await api('/me/bookings/' + editing.id, { method: 'DELETE' }); location.href = '/profile?deleted=1'; }
    catch (err) { setBusy(btn, false); toast(err.message); }
};

addEventListener('authchange', showState);
showState();
