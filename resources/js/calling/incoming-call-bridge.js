// WaDesk WABA call bridge for /team-inbox.
//
// Two responsibilities:
//
//   1. Poll /wa-calling/pending every 4 seconds while the tab is
//      visible. When a ringing call appears, render the incoming-call
//      toast with caller info and the operator's accept/decline buttons.
//
//   2. Drive the WaCallPeer (browser WebRTC) when the operator
//      accepts. Manage the slim call panel + timer during the call.
//      Hang up tears the peer down and POSTs /terminate.
//
// All HTTPS endpoints — no Reverb, no queue. The poll cadence is
// intentionally slow (4s); Meta gives us ~30–60s before auto-
// terminating a connect, and a fresh inbound call doesn't expect
// sub-second pickup.

import { WaCallPeer } from './webrtc-peer.js';

const POLL_MS = 4000;
const $ = (sel) => document.querySelector(sel);

let peer = null;
let activeCallId = null;
let activeStartedAt = null;
let timerHandle = null;
let muted = false;

function csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; }

async function api(path, opts = {}) {
    const res = await fetch(path, {
        ...opts,
        credentials: 'same-origin',
        headers: {
            ...(opts.headers || {}),
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf(),
        },
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data?.error || data?.message || `HTTP ${res.status}`);
    return data;
}

function showToast(call) {
    const t = $('#wa-incoming-toast');
    if (!t) return;
    $('#wa-incoming-name').textContent  = call.contact_name || ('Caller +' + call.from_phone);
    $('#wa-incoming-phone').textContent = '+' + call.from_phone;
    t.dataset.callId   = call.id;
    t.dataset.sdpOffer = call.sdp_offer || '';
    t.classList.remove('hidden');
}

function hideToast() {
    const t = $('#wa-incoming-toast');
    if (t) {
        t.classList.add('hidden');
        delete t.dataset.callId;
        delete t.dataset.sdpOffer;
    }
}

function showPanel(call) {
    const p = $('#wa-call-panel');
    if (!p) return;
    $('#wa-call-name').textContent  = call.contact_name || ('+' + call.from_phone);
    const statusEl = $('#wa-call-status');
    if (statusEl) statusEl.textContent = call.ringing ? 'ringing…' : 'connected';
    p.classList.remove('hidden');

    if (call.ringing) {
        // Outbound, customer's phone still ringing. Show the panel (so the
        // operator can cancel) but DON'T start the duration timer yet — it
        // starts only when the answer lands and we go connected.
        const el = $('#wa-call-timer');
        if (el) el.textContent = '';
        if (timerHandle) { clearInterval(timerHandle); timerHandle = null; }
        activeStartedAt = null;
        return;
    }

    // Connected — start (or restart) the duration timer from now.
    activeStartedAt = Date.now();
    if (timerHandle) clearInterval(timerHandle);
    timerHandle = setInterval(updateTimer, 1000);
    updateTimer();
    resetMute();   // each fresh call starts unmuted with a clean control
}

function hidePanel() {
    const p = $('#wa-call-panel');
    if (p) p.classList.add('hidden');
    if (timerHandle) { clearInterval(timerHandle); timerHandle = null; }
    activeStartedAt = null;
}

function updateTimer() {
    if (!activeStartedAt) return;
    const secs = Math.floor((Date.now() - activeStartedAt) / 1000);
    const m = Math.floor(secs / 60);
    const s = (secs % 60).toString().padStart(2, '0');
    const el = $('#wa-call-timer');
    if (el) el.textContent = `${m}:${s}`;
}

async function accept() {
    const t = $('#wa-incoming-toast');
    const callId = parseInt(t?.dataset.callId || '0', 10);
    const sdp    = t?.dataset.sdpOffer || '';
    if (!callId) return;
    // Empty SDP means Meta's connect webhook landed without the session
    // body (malformed payload, transient issue, or already-AI-handled).
    // We MUST surface this to the operator instead of letting
    // setRemoteDescription throw an opaque DOMException downstream.
    if (!sdp || sdp.length < 50) {
        window.WaToaster?.error?.('Could not accept call: invalid call payload from WhatsApp. Try again.');
        hideToast();
        await safeReject(callId, 'INVALID_SDP');
        return;
    }
    hideToast();
    try {
        peer = new WaCallPeer({
            onState: (s) => {
                if (s === 'ended') { hidePanel(); peer = null; activeCallId = null; }
            },
            onError: (e) => console.error('[wa-call]', e),
        });
        activeCallId = callId;
        await peer.answer({ callId, sdpOffer: sdp });
        // Look up the call info once more to populate the panel name.
        showPanel({ contact_name: $('#wa-incoming-name')?.textContent || '—', from_phone: ($('#wa-incoming-phone')?.textContent || '').replace(/^\+/, '') });
    } catch (e) {
        // 409 = another operator already accepted; don't reject Meta —
        // the other operator's session is live.
        if (/HTTP 409|already accepted/i.test(e.message)) {
            window.WaToaster?.info?.('Call already accepted by another teammate.');
            peer?._teardown?.();
            peer = null;
            activeCallId = null;
            return;
        }
        window.WaToaster?.error?.('Could not accept call: ' + e.message);
        await safeReject(callId, 'OPERATOR_ERROR');
        peer = null;
        activeCallId = null;
    }
}

async function decline() {
    const t = $('#wa-incoming-toast');
    const callId = parseInt(t?.dataset.callId || '0', 10);
    hideToast();
    if (!callId) return;
    await safeReject(callId, 'REJECTED');
}

async function safeReject(callId, reason) {
    try {
        await api(`/wa-calling/calls/${callId}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason }),
        });
    } catch (e) { /* swallow — Meta will retry, webhook reconciles */ }
}

async function hangup() {
    if (peer) {
        try { await peer.hangup(); } catch (_) {}
    }
    hidePanel();
    peer = null;
    activeCallId = null;
}

function toggleMute() {
    if (!peer?.localStream) return;
    muted = !muted;
    peer.localStream.getAudioTracks().forEach((t) => (t.enabled = !muted));
    applyMuteUi();
}

function applyMuteUi() {
    const btn = $('#wa-call-mute');
    const label = $('#wa-call-mute-label');
    if (btn) {
        btn.classList.toggle('bg-accent-coral', muted);
        btn.classList.toggle('text-paper-0', muted);
        btn.classList.toggle('border-accent-coral', muted);
        btn.classList.toggle('bg-paper-100', !muted);
        btn.classList.toggle('text-ink-700', !muted);
    }
    if (label) label.textContent = muted
        ? (window.t ? window.t('Muted') : 'Muted')
        : (window.t ? window.t('Mute') : 'Mute');
}

function resetMute() {
    muted = false;
    if (peer?.localStream) peer.localStream.getAudioTracks().forEach((t) => (t.enabled = true));
    applyMuteUi();
}

/**
 * Outbound dial from the conversation-header call button. Owns the
 * SAME peer + panel as the inbound accept flow, so the hangup button
 * works for both, the timer renders once, and you can't accidentally
 * have two concurrent calls in one tab.
 */
export async function startOutboundCall({ configId, to, contactId, conversationId, displayName }) {
    if (peer || activeCallId) {
        throw new Error('Another call is already active in this tab.');
    }
    peer = new WaCallPeer({
        onState: (s) => {
            if (s === 'ringing') showPanel({ contact_name: displayName, from_phone: to, ringing: true });
            if (s === 'active')  showPanel({ contact_name: displayName, from_phone: to });
            if (s === 'ended') { hidePanel(); peer = null; activeCallId = null; }
        },
        onError: (e) => console.error('[wa-call]', e),
    });
    // Claim the busy slot immediately so the inbound-call poll doesn't pop
    // a toast over the top of our outbound dial while it's still ringing.
    activeCallId = -1;
    try {
        await peer.dial({ configId, to, contactId, conversationId });
        activeCallId = peer.callId;
    } catch (e) {
        peer = null;
        activeCallId = null;
        // No answer / declined / mic blocked — tell the operator instead of
        // failing silently. The panel (if it was showing) is already hidden
        // by the peer's 'ended' state.
        window.WaToaster?.error?.(e.message || 'Call could not be completed.');
        throw e;
    }
}

async function pollOnce() {
    if (document.hidden) return;
    if (activeCallId || peer) return;    // already busy (in/out call); skip the poll
    try {
        const r = await api('/wa-calling/pending', { method: 'GET' });
        const ringing = (r.calls || []).filter((c) => c.direction === 'USER_INITIATED');
        if (ringing.length === 0) {
            hideToast();
            return;
        }
        // Show the freshest one. If a previous toast was for a now-stale call, swap it.
        showToast(ringing[0]);
    } catch (e) {
        // Poll failures are not user-visible — next tick retries.
        console.warn('[wa-call] poll failed', e.message);
    }
}

let pollHandle = null;
function startPoll() {
    if (pollHandle) return;
    pollHandle = setInterval(pollOnce, POLL_MS);
}
function stopPoll() {
    if (!pollHandle) return;
    clearInterval(pollHandle);
    pollHandle = null;
}

export default function init() {
    $('#wa-incoming-accept')?.addEventListener('click', accept);
    $('#wa-incoming-decline')?.addEventListener('click', decline);
    $('#wa-call-hangup')?.addEventListener('click', hangup);
    $('#wa-call-mute')?.addEventListener('click', toggleMute);

    startPoll();
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) return;
        pollOnce();
    });
    window.addEventListener('pagehide', stopPoll);

    // Run one poll immediately so a tab opened while a call is
    // already ringing doesn't have to wait 4s for the toast.
    pollOnce();
}
