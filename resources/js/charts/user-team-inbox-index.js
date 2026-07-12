/*
 * /team-inbox — workspace SPA wired to /team-inbox/api/*.
 *
 * Lives on top of the static blade shell in resources/views/user/team-inbox/index.blade.php.
 * The blade gives us the DOM skeleton (panels, buttons, lists); this file
 * fills it in with real workspace data and routes user actions to the API.
 *
 * Pattern: queue + active conversation are polled every 10s / 3s. Every
 * mutation does an optimistic local update first, then POSTs; on 4xx/5xx
 * we toast the error and reload the queue from server-truth so the UI
 * snaps back to a consistent state.
 */
import initWaCallBridge from '../calling/incoming-call-bridge.js';

export default function init() {
    // WABA incoming-call bridge — polls /wa-calling/pending and drives
    // the WebRTC peer when the operator answers. Self-contained, won't
    // throw on Baileys-only workspaces (the poll just returns empty).
    try { initWaCallBridge(); } catch (e) { console.warn('[team-inbox] WA-call bridge init failed', e); }

    const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

    // ---- WhatsApp Flow FORM: submission card → detail slide-over -----------
    const FORM_VIEW_CACHE = {};
    function fvEsc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]; }); }
    function openFormPanel(form) {
        if (!form || typeof form !== 'object') return;
        var old = document.getElementById('form-view-panel'); if (old) old.remove();
        var fields = Array.isArray(form.fields) ? form.fields : [];
        var rows = fields.length
            ? fields.map(function (f) {
                return '<div class="border-b border-paper-200 py-3">'
                    + '<div class="text-[11px] uppercase tracking-[0.1em] text-ink-500 font-mono mb-0.5">' + fvEsc(f && f.label ? f.label : 'Field') + '</div>'
                    + '<div class="text-[14px] text-ink-900 break-words whitespace-pre-wrap">' + (fvEsc(f && f.value ? f.value : '') || '&mdash;') + '</div></div>';
            }).join('')
            : '<div class="text-[13px] text-ink-500 py-6 text-center">No fields.</div>';
        var wrap = document.createElement('div');
        wrap.id = 'form-view-panel';
        wrap.className = 'fixed inset-0 z-[60] flex justify-end';
        wrap.innerHTML = '<div class="absolute inset-0 bg-black/30" data-fv-close></div>'
            + '<div class="relative w-[380px] max-w-[90vw] h-full bg-paper-0 shadow-2xl flex flex-col">'
            + '<div class="flex items-center justify-between px-4 h-14 border-b border-paper-200 shrink-0">'
            + '<div class="font-serif text-[17px]">Form response</div>'
            + '<button type="button" data-fv-close class="w-8 h-8 rounded-full hover:bg-paper-100 grid place-items-center" aria-label="Close"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>'
            + '</div>'
            + '<div class="px-4 py-2 border-b border-paper-200 shrink-0"><div class="text-[11px] uppercase tracking-[0.1em] text-ink-500 font-mono">Form</div><div class="text-[15px] font-semibold">' + fvEsc(form.title || 'Form') + '</div></div>'
            + '<div class="flex-1 overflow-y-auto px-4 pb-6">' + rows + '</div>'
            + (form.submission_id ? '<div class="px-4 py-3 border-t border-paper-200 shrink-0"><a href="/wa-forms" class="text-[12px] text-wa-deep hover:underline font-medium">View all submissions &rarr;</a></div>' : '')
            + '</div>';
        document.body.appendChild(wrap);
        wrap.querySelectorAll('[data-fv-close]').forEach(function (el) { el.addEventListener('click', function () { wrap.remove(); }); });
        var onEsc = function (ev) { if (ev.key === 'Escape') { wrap.remove(); document.removeEventListener('keydown', onEsc); } };
        document.addEventListener('keydown', onEsc);
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('[data-form-view]') : null;
        if (!b) return;
        e.preventDefault();
        openFormPanel(FORM_VIEW_CACHE[b.getAttribute('data-form-view')]);
    });

    // WABA "Retry download" — re-fetch a media (e.g. a voice note) that failed
    // at receive-time, using the stored media_id (Meta keeps media ~30 days), so
    // the customer doesn't have to resend. Delegated so it works for any bubble.
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-retry-media]');
        if (!btn) return;
        e.preventDefault();
        const mid = btn.getAttribute('data-retry-media');
        if (!mid || btn.disabled) return;
        const orig = btn.textContent;
        btn.textContent = '…'; btn.disabled = true;
        try {
            const r = await fetch(`/team-inbox/api/messages/${mid}/retry-media`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const d = await r.json().catch(() => ({}));
            if (r.ok && d.ok) {
                if (typeof openConversation === 'function' && typeof state !== 'undefined' && state && state.activeId) {
                    openConversation(state.activeId);
                }
                return;
            }
            btn.textContent = 'Failed';
        } catch (_) {
            btn.textContent = 'Failed';
        }
        setTimeout(() => { btn.textContent = orig; btn.disabled = false; }, 1800);
    });

    // #42 — Concurrent GET-dedupe. When the user clicks a queue row while
    // the 3-second active poll is mid-flight, both requests fired the
    // same `/conversations/{id}` endpoint. We now share the in-flight
    // Promise between callers so only one network roundtrip happens.
    // POSTs / PATCHes / DELETEs are NEVER deduped — those have side
    // effects so two callers must round-trip both times.
    const inflightGets = new Map();
    const api  = (path, opts = {}) => {
        const isGet = !opts.method || opts.method.toUpperCase() === 'GET';
        if (isGet && inflightGets.has(path)) return inflightGets.get(path);

        const p = fetch(path, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
                ...(opts.body ? { 'Content-Type': 'application/json' } : {}),
                ...(opts.headers || {}),
            },
            ...opts,
            body: opts.body && typeof opts.body !== 'string' ? JSON.stringify(opts.body) : opts.body,
        }).then(async r => {
            const data = await r.json().catch(() => ({}));
            if (!r.ok) {
                const msg = data?.message || data?.errors?.[Object.keys(data?.errors || {})[0]]?.[0] || `HTTP ${r.status}`;
                throw new Error(msg);
            }
            return data;
        }).finally(() => {
            if (isGet) inflightGets.delete(path);
        });

        if (isGet) inflightGets.set(path, p);
        return p;
    };

    const $  = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    const toast = (msg, kind = 'info') => {
        const el = $('#toast');
        if (!el) { console.log(`[${kind}]`, msg); return; }
        el.textContent = msg;
        el.className = `toast toast-${kind}`;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.style.display = 'none'; }, 3500);
    };

    // Themed replacement for window.prompt() — same return shape
    // (string on submit, null on cancel) so existing call sites work
    // unchanged. Single text input, autofocus, Enter submits, Esc cancels.
    function themedPrompt({ title, placeholder = '', defaultValue = '', multiline = false, confirmLabel = 'Save' } = {}) {
        return new Promise((resolve) => {
            const wrap = document.createElement('div');
            wrap.className = 'fixed inset-0 z-[60] flex items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]';
            const inputHtml = multiline
                ? `<textarea autofocus rows="4" class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10 resize-y" placeholder="${placeholder.replace(/"/g,'&quot;')}">${(defaultValue||'').replace(/[<>]/g,'')}</textarea>`
                : `<input autofocus type="text" class="w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10" placeholder="${placeholder.replace(/"/g,'&quot;')}" value="${(defaultValue||'').replace(/"/g,'&quot;')}">`;
            wrap.innerHTML = `
                <div class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <h3 class="font-serif text-[18px] leading-tight text-ink-900">${(title||'').replace(/[<>]/g,'')}</h3>
                    </div>
                    <div class="p-5">${inputHtml}</div>
                    <div class="px-5 pb-5 flex items-center justify-end gap-2">
                        <button type="button" data-tp-cancel class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">Cancel</button>
                        <button type="button" data-tp-ok class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">${confirmLabel}</button>
                    </div>
                </div>`;
            document.body.appendChild(wrap);
            const input = wrap.querySelector('input, textarea');
            const close = (val) => { wrap.remove(); resolve(val); };
            wrap.querySelector('[data-tp-cancel]').addEventListener('click', () => close(null));
            wrap.addEventListener('click', (e) => { if (e.target === wrap) close(null); });
            wrap.querySelector('[data-tp-ok]').addEventListener('click', () => close(input.value));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && (!multiline || e.ctrlKey || e.metaKey)) { e.preventDefault(); close(input.value); }
                if (e.key === 'Escape') { e.preventDefault(); close(null); }
            });
            setTimeout(() => { input.focus(); input.select?.(); }, 30);
        });
    }

    /**
     * Themed multi-choice dialog. Returns the option `value` chosen,
     * or null on cancel/backdrop/escape. Used for the per-message
     * Delete dialog which needs to offer "Delete for everyone" vs
     * "Delete for me only" instead of a single yes/no.
     */
    function themedChoice({ title, body = '', options = [], cancelLabel = 'Cancel' } = {}) {
        return new Promise((resolve) => {
            const wrap = document.createElement('div');
            wrap.className = 'fixed inset-0 z-[60] flex items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]';
            const opts = options.map((o) => {
                const variant = o.variant === 'danger'
                    ? 'bg-accent-coral text-paper-0 hover:opacity-90'
                    : o.variant === 'primary'
                        ? 'bg-wa-deep text-paper-0 hover:bg-wa-teal'
                        : 'bg-white border border-paper-200 text-ink-900 hover:border-wa-deep';
                return `<button type="button" data-choice="${(o.value || '').replace(/"/g,'&quot;')}" class="w-full text-left px-4 py-2.5 rounded-xl font-semibold text-[13px] ${variant}">
                    <div class="flex items-center justify-between gap-2">
                        <span>${(o.label || '').replace(/[<>]/g,'')}</span>
                        ${o.hint ? `<span class="text-[11px] font-normal opacity-80">${(o.hint || '').replace(/[<>]/g,'')}</span>` : ''}
                    </div>
                    ${o.sub ? `<div class="text-[11px] font-normal opacity-80 mt-0.5">${(o.sub || '').replace(/[<>]/g,'')}</div>` : ''}
                </button>`;
            }).join('');
            wrap.innerHTML = `
                <div class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <h3 class="font-serif text-[18px] leading-tight text-ink-900">${(title||'').replace(/[<>]/g,'')}</h3>
                        ${body ? `<p class="text-[12.5px] text-ink-500 mt-1">${(body||'').replace(/[<>]/g,'')}</p>` : ''}
                    </div>
                    <div class="p-5 space-y-2">${opts}</div>
                    <div class="px-5 pb-5 flex items-center justify-end">
                        <button type="button" data-tc-cancel class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">${cancelLabel}</button>
                    </div>
                </div>`;
            document.body.appendChild(wrap);
            const close = (v) => { wrap.remove(); resolve(v); };
            wrap.querySelector('[data-tc-cancel]').addEventListener('click', () => close(null));
            wrap.addEventListener('click', (e) => { if (e.target === wrap) close(null); });
            wrap.querySelectorAll('[data-choice]').forEach(b => b.addEventListener('click', () => close(b.dataset.choice)));
            document.addEventListener('keydown', function escClose(e) {
                if (e.key === 'Escape') { document.removeEventListener('keydown', escClose); close(null); }
            });
        });
    }

    /**
     * WhatsApp-style edit modal: shows the original bubble at top
     * (so the operator sees the exact thing they're replacing) and a
     * text area at bottom with a Save button. Returns the new body
     * string on confirm, null on cancel/backdrop/escape.
     *
     * Visual reference: WhatsApp Web's Edit modal — green bubble preview
     * up top + outlined input with emoji + circular green checkmark.
     */
    function openEditModal(msg) {
        const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[c]);
        const original = msg.body || '';
        return new Promise((resolve) => {
            const wrap = document.createElement('div');
            wrap.className = 'fixed inset-0 z-[70] flex items-center justify-center p-5 bg-[rgba(11,31,28,0.55)]';
            wrap.innerHTML = `
              <div class="w-full max-w-xl bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.6)] overflow-hidden">
                <div class="px-5 py-3.5 border-b border-paper-200 flex items-center gap-3">
                    <button type="button" data-ed-cancel class="w-8 h-8 rounded-full hover:bg-paper-100 grid place-items-center" title="Cancel (Esc)">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                    </button>
                    <h3 class="font-serif text-[18px] leading-tight text-ink-900">Edit message</h3>
                </div>
                <details class="border-b border-paper-200 bg-paper-50">
                    <summary class="px-5 py-2 text-[11.5px] font-mono uppercase tracking-[0.14em] text-ink-700 cursor-pointer hover:bg-paper-100 select-none flex items-center gap-2">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 4l4 4-4 4"/></svg>
                        How editing works
                    </summary>
                    <pre class="px-5 py-3 text-[11.5px] leading-relaxed text-ink-700 font-mono whitespace-pre overflow-x-auto bg-paper-0 border-t border-paper-200">You change the text below
   │
   ▼
${(window.WADESK_BRAND && window.WADESK_BRAND.appName) || 'WaDesk'} sends the new text to WhatsApp
   │
   ├── Customer's chat updates with the new text
   ├── A small "Edited" tag appears under both bubbles
   └── The original wording is gone — only the new one stays

Limits:
  • Only your own outbound messages
  • Only within 15 minutes of sending (WhatsApp's rule)
  • Edit option disappears after the window expires</pre>
                </details>
                <div class="bg-paper-50 px-6 py-8 flex items-center justify-center min-h-[180px]">
                    <div class="max-w-[80%] bg-wa-mint border border-wa-green/30 rounded-2xl rounded-br-md px-3.5 py-2 text-[13px] leading-snug text-ink-900 shadow-sm whitespace-pre-wrap break-words">${escapeHtml(original)}</div>
                </div>
                <div class="px-5 py-4 border-t border-paper-200 flex items-start gap-2.5">
                    <span class="w-9 h-9 rounded-full bg-paper-100 grid place-items-center shrink-0 text-ink-500 text-[16px]">😊</span>
                    <textarea
                        data-ed-input rows="3"
                        class="flex-1 px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-green focus:ring-4 focus:ring-wa-green/15 resize-y leading-snug"
                        placeholder="Edit your message…">${escapeHtml(original)}</textarea>
                    <button type="button" data-ed-save class="w-10 h-10 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 grid place-items-center shrink-0" title="Save edit (Ctrl+Enter)">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.5 8.5l3 3 6-7"/></svg>
                    </button>
                </div>
                <div class="px-5 pb-4 text-[11px] text-ink-500 font-mono">
                    WhatsApp gives a 15-minute edit window. Ctrl/⌘+Enter to save · Esc to cancel.
                </div>
              </div>`;
            document.body.appendChild(wrap);
            const input = wrap.querySelector('[data-ed-input]');
            const close = (val) => { wrap.remove(); document.removeEventListener('keydown', onKey); resolve(val); };
            const onKey = (e) => {
                if (e.key === 'Escape') { e.preventDefault(); close(null); }
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); close(input.value); }
            };
            document.addEventListener('keydown', onKey);
            wrap.addEventListener('click', (e) => { if (e.target === wrap) close(null); });
            wrap.querySelector('[data-ed-cancel]').addEventListener('click', () => close(null));
            wrap.querySelector('[data-ed-save]').addEventListener('click', () => close(input.value));
            setTimeout(() => { input.focus(); input.setSelectionRange(input.value.length, input.value.length); }, 30);
        });
    }

    // ── State ────────────────────────────────────────────────────────────
    const state = {
        me: null,
        permissions: {},
        teams: [],
        members: [],
        tags: [],
        savedReplies: [],
        slaPolicies: [],

        queue: [],
        counts: { mine: 0, unassigned: 0, all: 0, mentions: 0, sla_breach: 0 },
        activeId: null,
        active: null,
        thread: [],
        notes: [],
        events: [],

        tab: 'all',
        teamFilter: null,
        // Multi-device filter — null = show all paired devices.
        // Hydrated from the URL's ?device_id= on load and written
        // back on every change so the filter survives reloads.
        deviceFilter: null,
        statusFilter: 'open',
        search: '',
        bulkMode: false,
        selected: new Set(),
        // WhatsApp-style list controls.
        archivedView: false,   // showing the "Archived" section
        labelFilter: null,     // tag_id the list is filtered to (null = all)
        archivedCount: 0,
        _labelSig: null,       // last-rendered tag list signature (dropdown sync)
        composerMode: 'reply',
        selectedTemplate: null,
        aiAgents: [],
        flows: [],
        templates: [],
        businessHours: null,
        aiKeys: [],
        routingRules: [],

        polling: { queue: null, active: null, gc: null },

        // Cache fingerprint of last queue payload so we can skip
        // re-rendering when polling returns identical items.
        _lastQueueSig: null,

        // #40 — Per-conversation thread LRU. Map<convId, { thread, notes,
        // events, history, ts }>. When the operator re-opens a recently
        // viewed convo we paint from this cache immediately so the
        // thread doesn't flash empty during the loadActive() refetch.
        // Capped at 20 entries via _trimThreadCache(); resolved convos
        // older than 5min are dropped on GC tick.
        _threadCache: new Map(),
        _threadCacheCap: 20,

        // #41 — Track in-flight rAF for renderActive coalescing.
        _renderActiveScheduled: false,

        // Pagination state for older-message lazy load.
        threadHasMore: false,
        threadLoadingOlder: false,
    };

    // ── Bootstrap ────────────────────────────────────────────────────────
    async function bootstrap() {
        try {
            const data = await api('/team-inbox/api/bootstrap');
            Object.assign(state, {
                me: data.me,
                permissions: data.permissions,
                teams: data.teams || [],
                members: data.members || [],
                tags: data.tags || [],
                savedReplies: data.saved_replies || [],
                slaPolicies: data.sla_policies || [],
                aiAgents: data.ai_agents || [],
                aiKeys: data.ai_keys || [],
                routingRules: data.routing_rules || [],
                flows: data.flows || [],
                templates: data.templates || [],
                businessHours: data.business_hours || null,
                // Active paired devices (multi-device feature). Used by
                // the routing-rule builder + AI agent editor. Empty
                // array on single-device workspaces, so all device-aware
                // UI elements render-guard with `state.devices.length > 1`.
                devices: data.devices || [],
            });
            renderAiAgentNav();
            renderTeamNav();
            renderMyProfile();
            updateNavRoutingCount();
            updateNavQuickRepliesCount();
            await loadQueue();
            // Deep-link: the "new message" notification (and any link) opens us
            // with #c=<id> — jump straight to THAT conversation instead of just
            // showing the whole queue.
            try {
                const m = (window.location.hash || '').match(/[#&]c=(\d+)/);
                if (m) openConversation(parseInt(m[1], 10));
            } catch (e) { /* ignore bad hash */ }
        } catch (e) {
            toast(t('Failed to load inbox') + ': ' + e.message, 'error');
        } finally {
            // Start polling no matter what — even if bootstrap or the
            // first loadQueue failed, the recurring poll is the way the
            // inbox auto-discovers new conversations. Without it the
            // operator has to hard-reload the page to see new inbounds.
            startPolling();
        }
    }

    function startPolling() {
        clearInterval(state.polling.queue);
        clearInterval(state.polling.active);
        clearInterval(state.polling.gc);
        // Queue every 5s — short enough that a new inbound shows in the
        // list within seconds, long enough to avoid hammering the API.
        state.polling.queue  = setInterval(() => loadQueue(true), 5000);
        state.polling.active = setInterval(() => state.activeId && loadActive(state.activeId, true), 3000);
        // Memory GC every 60s — drop resolved conversations from the
        // queue cache that haven't moved in > 1h. Keeps long-running
        // operator sessions from accumulating heap as conversations
        // resolve throughout the day.
        state.polling.gc = setInterval(gcResolvedQueue, 60000);
    }

    function gcResolvedQueue() {
        if (Array.isArray(state.queue) && state.queue.length > 0) {
            const cutoff = Date.now() - 3600_000; // 1 hour
            const before = state.queue.length;
            state.queue = state.queue.filter(c => {
                if (c.inbox_status !== 'resolved') return true;
                if (!c.last_message_at) return true;
                return new Date(c.last_message_at).getTime() >= cutoff;
            });
            if (state.queue.length !== before) {
                // Re-render so the dropped rows leave the DOM. The next
                // poll will resync from server-truth.
                state._lastQueueSig = null;
                renderQueue();
            }
        }

        // #40 — Drop cached threads for conversations that the operator
        // hasn't visited in 5+ minutes AND that are resolved/closed.
        // Keeps active workflows in cache (instant re-open) while
        // freeing memory for the dozens of resolved tickets an operator
        // touches in a shift. The active conversation is NEVER evicted.
        if (state._threadCache.size > 0) {
            const idleCutoff = Date.now() - 300_000; // 5 minutes
            const queueIdx = Object.fromEntries((state.queue || []).map(c => [c.id, c.inbox_status]));
            for (const [id, entry] of state._threadCache) {
                if (id === state.activeId) continue;
                const status = queueIdx[id] || entry.active?.inbox_status;
                const isResolved = status === 'resolved' || status === 'closed';
                if (isResolved && entry.ts < idleCutoff) {
                    state._threadCache.delete(id);
                }
            }
        }
    }

    // Refresh immediately when the tab regains focus — setInterval is
    // throttled by browsers when the tab is hidden, so a user returning
    // from another tab would otherwise wait up to the next poll tick to
    // see fresh data.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadQueue(true);
            if (state.activeId) loadActive(state.activeId, true);
        }
    });

    // Also refresh on window focus — catches the case where the page is
    // visible but a different browser window had focus.
    window.addEventListener('focus', () => {
        loadQueue(true);
        if (state.activeId) loadActive(state.activeId, true);
    });

    // Manual refresh button in the queue header — spins the icon while
    // the request is in flight so the operator gets visible feedback.
    $('#queue-refresh-btn')?.addEventListener('click', async () => {
        const btn = $('#queue-refresh-btn');
        const svg = btn?.querySelector('svg');
        svg?.classList.add('animate-spin');
        try {
            await loadQueue();
            if (state.activeId) await loadActive(state.activeId);
        } finally {
            setTimeout(() => svg?.classList.remove('animate-spin'), 300);
        }
    });

    // ── Queue ────────────────────────────────────────────────────────────
    // Cheap signature so we can skip rerender when polling returns the
    // same items in the same order. Only items.id + last_message_at +
    // inbox_status need to match — those are the bits the queue row
    // renderer cares about. Counts are compared separately.
    function queueSignature(items) {
        return (items || []).map(c => `${c.id}:${c.last_message_at}:${c.inbox_status}:${c.unread_count}`).join('|');
    }

    async function loadQueue(silent = false) {
        try {
            const params = new URLSearchParams({
                tab:    state.tab,
                status: state.statusFilter,
            });
            if (state.teamFilter)   params.set('team_id',   state.teamFilter);
            if (state.deviceFilter) params.set('device_id', state.deviceFilter);
            if (state.search)       params.set('q',         state.search);
            if (state.archivedView) params.set('archived',  '1');
            if (state.labelFilter)  params.set('tag_id',    state.labelFilter);
            const data = await api(`/team-inbox/api/queue?${params}`);
            const newItems  = data.items  || [];
            const newCounts = data.counts || state.counts;
            const newSig    = queueSignature(newItems);
            const sameItems = newSig === state._lastQueueSig;
            const sameCounts = JSON.stringify(newCounts) === JSON.stringify(state.counts);
            state.queue        = newItems;
            state.counts       = newCounts;
            state._lastQueueSig = newSig;

            // WhatsApp-style list chrome — Archived row + label dropdown.
            state.archivedCount = Number(data.archived_count ?? state.archivedCount) || 0;
            renderArchivedRow();
            syncLabelFilter();

            // Alert on any conversation that gained unread since the last poll
            // (covers chats that AREN'T currently open). One ping per poll cycle;
            // seeded silently on first load so existing unreads don't fire a burst.
            try {
                const prev = state.__prevUnread;
                const now = {};
                let fired = false;
                for (const c of newItems) {
                    now[c.id] = Number(c.unread_count || 0);
                    // Muted conversations never ping / pop a notification.
                    if (!c.muted && prev && !fired && c.id !== state.activeId && now[c.id] > (prev[c.id] || 0)) {
                        alertNewMessage(c.title || 'New message', c.preview || '');
                        fired = true;
                    }
                }
                state.__prevUnread = now;
            } catch (e) { /* best-effort */ }

            if (!sameCounts) renderCounts();
            if (!sameItems)  renderQueue();
            renderEmptyState();
        } catch (e) {
            if (!silent) toast('Queue: ' + e.message, 'error');
        }
    }

    /**
     * Pick which middle-pane empty state to show.
     *  - getting-started: workspace has 0 conversations AND user can manage
     *    teams (so they're an owner/admin landing here for the first time)
     *  - generic empty: there are conversations but this view is empty
     *  - hidden: a conversation is open
     */
    function renderEmptyState() {
        // First call after bootstrap completes — drop the shimmer so we
        // can show the real empty state. Keeps the page from flashing the
        // getting-started panel before /queue has confirmed the count.
        $('#thread-skeleton')?.classList.add('hidden');
        if (state.activeId) return; // a conversation is open — neither empty state shows
        const totalInWorkspace = state.counts.all || 0;
        const isFresh = totalInWorkspace === 0;
        const canManage = !!state.permissions?.['team.manage'];
        const showGettingStarted = isFresh && canManage;

        $('#thread-getting-started')?.classList.toggle('hidden', !showGettingStarted);
        $('#thread-empty')?.classList.toggle('hidden', showGettingStarted);
    }

    // -----------------------------------------------------------------
    // New-message alerts — desktop notification + a short "ping" sound.
    // The client reported inbound messages arriving silently; this adds
    // both. Audio uses WebAudio (no asset to ship) and needs a user
    // gesture to unlock, wired once on first interaction below.
    // -----------------------------------------------------------------
    let __audioCtx = null;
    function unlockAlerts() {
        try {
            if (!__audioCtx) __audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (__audioCtx.state === 'suspended') __audioCtx.resume();
        } catch (e) { /* WebAudio unavailable */ }
        try {
            if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
        } catch (e) { /* Notifications unavailable */ }
    }
    ['click', 'keydown'].forEach(ev => window.addEventListener(ev, unlockAlerts, { once: true, passive: true }));

    function playPing() {
        try {
            if (!__audioCtx) __audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (__audioCtx.state === 'suspended') __audioCtx.resume();
            const t = __audioCtx.currentTime;
            const o = __audioCtx.createOscillator();
            const g = __audioCtx.createGain();
            o.type = 'sine';
            o.frequency.setValueAtTime(880, t);
            o.frequency.setValueAtTime(1175, t + 0.09);
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(0.18, t + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, t + 0.32);
            o.connect(g); g.connect(__audioCtx.destination);
            o.start(t); o.stop(t + 0.34);
        } catch (e) { /* best-effort */ }
    }

    function desktopNotify(title, body) {
        try {
            if ('Notification' in window && Notification.permission === 'granted') {
                const n = new Notification(String(title || 'New message'), {
                    body: String(body || '').slice(0, 140),
                    tag: 'wa-inbox', renotify: true, icon: '/favicon.ico',
                });
                n.onclick = () => { try { window.focus(); } catch (e) {} n.close(); };
            }
        } catch (e) { /* best-effort */ }
    }

    // Sound always; pop a system notification when the tab is backgrounded,
    // otherwise a lightweight in-app toast so a working operator still sees it.
    function alertNewMessage(title, body) {
        playPing();
        if (document.hidden) desktopNotify(title, body);
        else { try { toast('New message · ' + String(title || ''), 'info'); } catch (e) {} }
    }

    // De-dupe a thread by real id (falls back to wa_message_id); always keeps
    // optimistic/temp rows (no real id). Safety net so a duplicate row never
    // renders twice even if one slips past the server-side idempotency guard.
    function dedupeThread(list) {
        const seen = new Set();
        const out = [];
        for (const m of (list || [])) {
            let key = null;
            if (typeof m.id === 'number') key = 'id:' + m.id;
            else if (m && m.meta && m.meta.wa_message_id) key = 'wa:' + m.meta.wa_message_id;
            if (key) { if (seen.has(key)) continue; seen.add(key); }
            out.push(m);
        }
        return out;
    }

    async function loadActive(id, silent = false) {
        try {
            const data = await api(`/team-inbox/api/conversations/${id}`);
            // Preserve any locally-failed optimistic messages so they don't
            // disappear on the next poll before the operator can retry.
            // Only preserve when we're still on the same conversation
            // (switching wipes the local cache as expected).
            const keepFailed = (state.activeId === id)
                ? (state.thread || []).filter(m => m.__tempId && m.status === 'failed')
                : [];
            const keepFailedNotes = (state.activeId === id)
                ? (state.notes || []).filter(n => n.__tempId && n.status === 'failed')
                : [];

            state.active  = data.conversation;
            state.thread  = dedupeThread([...(data.messages || []), ...keepFailed]);

            // Ping when a NEW inbound lands in the OPEN chat (silent poll only —
            // not when the operator first opens it). Sound only; they're looking.
            try {
                const inIds = (data.messages || [])
                    .filter(m => m.direction === 'in' && typeof m.id === 'number')
                    .map(m => m.id);
                const maxIn = inIds.length ? Math.max(...inIds) : 0;
                if (silent && state.__activeMaxInId && maxIn > state.__activeMaxInId) playPing();
                state.__activeMaxInId = maxIn;
            } catch (e) { /* best-effort */ }

            state.notes   = [...(data.notes    || []), ...keepFailedNotes];
            state.events  = data.events  || [];
            state.history = data.history || [];
            state.threadHasMore = !!data.has_more;
            state.threadLoadingOlder = false;
            cacheActiveThread(id);
            scheduleRenderActive();
        } catch (e) {
            if (!silent) toast('Conversation: ' + e.message, 'error');
        }
    }

    // Pagination: fetch the next batch of 80 OLDER messages (with id <
    // the oldest currently in state.thread). Prepended without scroll-jump.
    async function loadOlderMessages() {
        if (!state.activeId || !state.threadHasMore || state.threadLoadingOlder) return;
        const oldestId = (state.thread || [])
            .filter(m => typeof m.id === 'number')
            .map(m => m.id)
            .reduce((a, b) => a < b ? a : b, Number.MAX_SAFE_INTEGER);
        if (oldestId === Number.MAX_SAFE_INTEGER) return;

        state.threadLoadingOlder = true;
        const threadEl = $('#thread');
        const prevH = threadEl?.scrollHeight || 0;
        const prevS = threadEl?.scrollTop || 0;
        try {
            const data = await api(`/team-inbox/api/conversations/${state.activeId}?before=${oldestId}`);
            const older = data.messages || [];
            if (older.length === 0) {
                state.threadHasMore = false;
            } else {
                state.thread = [...older, ...state.thread];
                state.threadHasMore = !!data.has_more;
                renderActive();
                // Preserve scroll position: bottom of viewport stays where
                // it was so the user keeps reading without losing context.
                if (threadEl) {
                    const newH = threadEl.scrollHeight;
                    threadEl.scrollTop = prevS + (newH - prevH);
                }
            }
        } catch (e) {
            toast('Couldn\'t load older messages: ' + e.message, 'error');
        } finally {
            state.threadLoadingOlder = false;
        }
    }

    // ── Render — Team navigation ─────────────────────────────────────────
    function renderTeamNav() {
        const list = $('#team-nav-list');
        if (!list) return;
        const teams = state.teams || [];
        $('#team-nav-empty')?.classList.toggle('hidden', teams.length > 0);
        list.innerHTML = teams.map(t => `
            <div class="flex items-center group">
              <button data-team-nav="${t.id}" class="team-nav-btn flex-1" style="--team-color:${safeColor(t.color, '#075E54')}">
                <span class="w-2 h-2 rounded-full shrink-0" style="background:${safeColor(t.color, '#075E54')}"></span>
                <span class="truncate">${escape(t.name)}</span>
                <span class="ct ml-auto"></span>
              </button>
              <button data-edit-team="${t.id}" title="Edit team"
                class="w-6 h-6 rounded hover:bg-paper-200 grid place-items-center text-ink-400 hover:text-ink-700 opacity-0 group-hover:opacity-100 transition-opacity shrink-0 mr-1">
                <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 2l3 3-8 8H3v-3l8-8z"/></svg>
              </button>
            </div>
        `).join('');
        list.querySelectorAll('[data-team-nav]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.teamFilter = parseInt(btn.dataset.teamNav, 10);
                $$('#team-nav-list [data-team-nav]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                $('[data-team-nav="all"]')?.classList.remove('active');
                const label = btn.querySelector('span.truncate')?.textContent?.trim() || btn.textContent.trim();
                $('#active-team-label') && ($('#active-team-label').textContent = label);
                loadQueue();
            });
        });
        list.querySelectorAll('[data-edit-team]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const team = (state.teams || []).find(t => t.id === parseInt(btn.dataset.editTeam, 10));
                if (team) openEditTeamModal(team);
            });
        });
        $('[data-team-nav="all"]')?.addEventListener('click', () => {
            state.teamFilter = null;
            $$('.team-nav-btn').forEach(b => b.classList.remove('active'));
            $('[data-team-nav="all"]')?.classList.add('active');
            $('#active-team-label') && ($('#active-team-label').textContent = 'All teams');
            loadQueue();
        });
    }

    function renderCounts() {
        // hide the badge entirely when the count is 0 — leaving an empty
        // pill behind looks like a broken UI element rather than "zero"
        for (const [k, v] of Object.entries(state.counts)) {
            const el = $(`[data-count="${k}"]`);
            if (!el) continue;
            if (v > 0) {
                el.textContent = v;
                el.style.display = '';
            } else {
                el.textContent = '';
                el.style.display = 'none';
            }
        }
        const allEl = $('[data-nav-count="all"]');
        if (allEl) {
            const n = state.counts.all || 0;
            allEl.textContent = n > 0 ? n : '';
            allEl.style.display = n > 0 ? '' : 'none';
        }
    }

    function renderMyProfile() {
        if (!state.me) return;

        // Render up-to-4 member avatars + a "+N" overflow chip in the bottom
        // of the queue column. The whole strip is wrapped in a <a> that points
        // to /team-inbox/members so clicking jumps to full member management.
        const avatars = $('#presence-avatars');
        const summary = $('#presence-summary');
        const members = state.members || [];
        if (avatars) {
            const shown = members.slice(0, 4);
            const overflow = Math.max(0, members.length - 4);
            const palette = ['av-1','av-2','av-3','av-4','av-5','av-6'];
            avatars.innerHTML = shown.map((m, i) => {
                const initials = (m.name || '?').split(/\s+/).map(s => s[0] || '').slice(0,2).join('').toUpperCase();
                return `<span class="w-5 h-5 rounded-full ${palette[i % palette.length]} text-paper-0 text-[8px] font-semibold grid place-items-center ring-2 ring-paper-0" title="${escape(m.name || '')}">${escape(initials)}</span>`;
            }).join('') + (overflow > 0
                ? `<span class="w-5 h-5 rounded-full bg-ink-700 text-paper-0 text-[8px] font-semibold grid place-items-center ring-2 ring-paper-0" title="View all ${members.length} members">+${overflow}</span>`
                : '');
        }
        if (summary) {
            if (members.length === 0) {
                summary.textContent = 'No teammates yet — invite';
            } else if (members.length > 4) {
                summary.textContent = `+${members.length - 4} more — view all`;
            } else {
                const online = members.filter(m => m.status === 'online').length;
                summary.textContent = `${members.length} member${members.length === 1 ? '' : 's'} · ${online} online`;
            }
        }
    }

    // ── Render — Queue list ──────────────────────────────────────────────
    function renderQueue() {
        const list = $('#conv-list');
        if (!list) return;
        closeConvMenu();   // drop any open per-row menu before repaint
        if (state.queue.length === 0) {
            list.innerHTML = `<div class="px-4 py-12 text-center text-[12px] text-ink-500">
                <div class="font-serif text-[16px] text-ink-700 mb-1">No conversations</div>
                <div>Your queue is clear in this view.</div>
            </div>`;
            return;
        }
        list.innerHTML = state.queue.map(c => convRow(c)).join('');
        list.querySelectorAll('[data-conv-id]').forEach(row => {
            row.addEventListener('click', e => {
                if (e.target.closest('input[type=checkbox]')) return;
                const id = parseInt(row.dataset.convId, 10);
                openConversation(id);
            });
        });
        list.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.addEventListener('change', () => {
                const id = parseInt(cb.dataset.id, 10);
                if (cb.checked) state.selected.add(id); else state.selected.delete(id);
                updateBulkBar();
            });
        });
        // Right-click (desktop) / long-press (touch) → per-conversation menu.
        list.querySelectorAll('[data-conv-id]').forEach(row => {
            const id = parseInt(row.dataset.convId, 10);
            row.addEventListener('contextmenu', e => { e.preventDefault(); openConvMenu(id, e.clientX, e.clientY); });
            let lpTimer = null;
            row.addEventListener('touchstart', e => {
                const t = e.touches && e.touches[0];
                const x = t ? t.clientX : 0, y = t ? t.clientY : 0;
                lpTimer = setTimeout(() => openConvMenu(id, x, y), 480);
            }, { passive: true });
            const cancelLp = () => { if (lpTimer) { clearTimeout(lpTimer); lpTimer = null; } };
            row.addEventListener('touchend', cancelLp);
            row.addEventListener('touchmove', cancelLp);
        });
    }

    function convRow(c) {
        const initials = (c.title || '?').split(/\s+/).map(s => s[0] || '').slice(0, 2).join('').toUpperCase() || '?';
        const tag = (n, c) => `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono bg-paper-100 text-ink-700">${escape(n)}</span>`;
        const sla = c.sla_breached
            ? `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono bg-accent-coral/20 text-accent-coral">SLA</span>`
            : '';
        const priorityPill = c.priority && c.priority !== 'normal'
            ? `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono bg-paper-100 text-ink-700 uppercase">${escape(c.priority)}</span>`
            : '';
        const checkbox = state.bulkMode
            ? `<input type="checkbox" data-id="${c.id}" class="mr-2" ${state.selected.has(c.id) ? 'checked' : ''} />` : '';
        return `
        <button data-conv-id="${c.id}" class="conv-row w-full text-left px-3 py-2.5 border-b border-paper-200 hover:bg-paper-50 ${state.activeId === c.id ? 'bg-paper-100' : ''}">
          <div class="flex items-start gap-2.5">
            ${checkbox}
            <span class="w-9 h-9 rounded-full text-paper-0 font-semibold grid place-items-center bg-wa-deep text-[11.5px] shrink-0">${escape(initials)}</span>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span class="font-medium text-[13px] truncate">${escape(c.title || 'Unnamed')}</span>
                <span class="ml-auto flex items-center gap-1 shrink-0">
                  ${c.pinned ? `<svg viewBox="0 0 16 16" class="w-3 h-3 text-wa-deep" fill="currentColor"><path d="M9.5 2l4.5 4.5-2 .5-2.5 2.5-.5 3-1.5-1.5L3 14l3.5-4-1.5-1.5 3-.5L10.5 5 9.5 2z"/></svg>` : ''}
                  ${c.muted ? `<svg viewBox="0 0 16 16" class="w-3 h-3 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 6v4h2l3 3V3L6 6H4zM11 6l3 4M14 6l-3 4"/></svg>` : ''}
                  ${Number(c.unread_count) > 0 ? `<span class="min-w-[18px] h-[18px] px-1 rounded-full bg-wa-teal text-paper-0 text-[9.5px] font-mono grid place-items-center">${Number(c.unread_count) > 99 ? '99+' : c.unread_count}</span>` : ''}
                  <span class="text-[10px] font-mono text-ink-500">${formatTime(c.last_message_at)}</span>
                </span>
              </div>
              <div class="text-[11.5px] text-ink-500 truncate">${escape(c.preview || '')}</div>
              <div class="flex items-center gap-1 mt-1 flex-wrap">
                ${(c.tags || []).map(t => `<span class="px-1.5 py-0.5 rounded-full text-[9.5px] font-mono" style="background:${safeColor(t.color, '#075E54')}22;color:${safeColor(t.color, '#075E54')}">${escape(t.name)}</span>`).join('')}
                ${c.wa_username ? `<span class="px-1.5 py-0.5 rounded-full text-[9.5px] font-mono bg-wa-mint/30 text-wa-deep">@${escape(c.wa_username)}</span>` : ''}
                ${c.assignee_name ? `<span class="px-1.5 py-0.5 rounded-full text-[9.5px] font-mono bg-wa-mint/40 text-wa-deep">${escape(c.assignee_name)}</span>` : `<span class="px-1.5 py-0.5 rounded-full text-[9.5px] font-mono bg-paper-100 text-ink-500">unassigned</span>`}
                ${c.team_name ? `<span class="px-1.5 py-0.5 rounded-full text-[9.5px] font-mono" style="background:${safeColor(c.team_color, '#075E54')}20;color:${safeColor(c.team_color, '#075E54')}">${escape(c.team_name)}</span>` : ''}
                ${c.agent_name ? `<span class="px-1.5 py-0.5 rounded-full text-[9.5px] font-mono" style="background:${safeColor(c.agent_color)}22;color:${safeColor(c.agent_color)}">🤖 ${escape(c.agent_name)}</span>` : ''}
                ${c.device_label ? (() => {
                    const ds = c.device_status || 'unknown';
                    const dotColor = ds === 'connected' || ds === 'cloud' ? 'bg-emerald-500'
                                   : ds === 'disconnected' ? 'bg-red-500'
                                   : 'bg-amber-500';
                    const tipParts = [c.device_phone || ''];
                    if (ds && ds !== 'cloud') tipParts.push(`Status: ${ds}`);
                    return `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9.5px] font-mono bg-wa-bubble/40 text-wa-deep" title="${escape(tipParts.filter(Boolean).join(' · '))}"><span class="w-1.5 h-1.5 rounded-full ${dotColor}"></span><svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="4.5" y="2" width="7" height="12" rx="1.5"/><path d="M7 12.5h2"/></svg>${escape(c.device_label)}</span>`;
                })() : ''}
                ${priorityPill}
                ${sla}
              </div>
            </div>
          </div>
        </button>`;
    }

    // ── Render — Active conversation ─────────────────────────────────────
    async function openConversation(id) {
        state.activeId = id;
        // On mobile (<768px) the queue and thread are stacked, only one is
        // visible at a time. Toggle a class so the thread takes the screen.
        $('#ti-frame')?.classList.add('mobile-thread-open');

        // #40 — Paint immediately from the thread cache if we have it
        // so the operator sees the previous content instantly. The
        // refetch below replaces it with server-truth in ~300ms.
        const cached = state._threadCache.get(id);
        if (cached) {
            state.active  = cached.active  || state.active;
            state.thread  = cached.thread  || [];
            state.notes   = cached.notes   || [];
            state.events  = cached.events  || [];
            state.history = cached.history || [];
            scheduleRenderActive();
            // LRU bump: re-insert at the end of the Map so this entry
            // is the most-recently used.
            state._threadCache.delete(id);
            state._threadCache.set(id, { ...cached, ts: Date.now() });
        }

        await loadActive(id);
    }

    /**
     * #41 — Coalesce multiple renderActive() calls inside a single
     * frame (a poll-tick + a tag-change + a saved-reply update can
     * easily trigger 3 in 5ms). Wrap each call in rAF so only one
     * paint actually runs.
     */
    function scheduleRenderActive() {
        if (state._renderActiveScheduled) return;
        state._renderActiveScheduled = true;
        requestAnimationFrame(() => {
            state._renderActiveScheduled = false;
            renderActive();
        });
    }

    function cacheActiveThread(id) {
        if (!id) return;
        // LRU eviction — drop the OLDEST entry once the cap is hit.
        // Map iteration order matches insertion order so the first
        // key is the oldest.
        if (state._threadCache.size >= state._threadCacheCap) {
            const oldest = state._threadCache.keys().next().value;
            if (oldest !== undefined) state._threadCache.delete(oldest);
        }
        state._threadCache.set(id, {
            active:  state.active,
            thread:  state.thread || [],
            notes:   state.notes  || [],
            events:  state.events || [],
            history: state.history || [],
            ts:      Date.now(),
        });
    }

    function renderActive() {
        const c = state.active;
        if (!c) return;

        // reveal the header / composer / contact panel that the empty-state
        // page hides by default. The right panel uses the existing
        // `.no-contact` class on #ti-frame to collapse the grid column,
        // not a `hidden` on the aside (the css grid keeps the column
        // sized to 320px regardless of display:none on its child).
        $('#th-header')?.classList.remove('hidden');
        $('#ct-panel')?.classList.remove('hidden');
        $('#th-shortcuts')?.classList.remove('hidden');
        $('#thread-empty')?.classList.add('hidden');
        $('#thread-getting-started')?.classList.add('hidden');
        $('#thread-skeleton')?.classList.add('hidden');

        // Default: contact panel CLOSED. The arrow/open button in the
        // thread header is the gateway to expand it again. Respect a
        // saved preference if the user explicitly opened it before.
        //
        // MOBILE (<768px): the queue / thread / detail are ONE-at-a-time
        // views and the detail panel is a full-screen overlay. So it must
        // NEVER auto-open when you tap a conversation — that slammed the
        // detail overlay on top of the chat every time ("click a chat →
        // options screen pops up"). Force it CLOSED on phones; the header
        // details button opens it as an overlay and its × returns to the
        // thread. Desktop/tablet still respect the saved preference (the
        // panel sits alongside the thread there).
        const isMobileTi = window.matchMedia('(max-width: 767.98px)').matches;
        let savedOpen = null;
        try { savedOpen = localStorage.getItem('ti.contactOpen'); } catch (_) {}
        setContactPanel(!isMobileTi && savedOpen === '1');

        // Composer state — respects the user's last close/open choice.
        // Without this, the unconditional show on every loadActive (and
        // every polling refresh) would re-open the composer the moment
        // the operator collapsed it.
        let savedComposer = null;
        try { savedComposer = localStorage.getItem('ti.composerOpen'); } catch (_) {}
        setComposer(savedComposer !== '0');

        // header
        const initials = (c.title || '?').split(/\s+/).map(s => s[0] || '').slice(0, 2).join('').toUpperCase() || '?';
        $('#th-avatar') && ($('#th-avatar').textContent = initials);
        $('#th-name')   && ($('#th-name').textContent   = c.title || '—');
        $('#th-status') && ($('#th-status').textContent = (c.inbox_status || 'open').toUpperCase());
        $('#th-sla')    && ($('#th-sla').textContent    = c.sla_breached ? 'breached' : (c.sla_first_due ? humanDuration(c.sla_first_due) : '—'));
        $('#th-phone')  && ($('#th-phone').textContent  = '');
        $('#th-presence') && ($('#th-presence').innerHTML = '');

        // Device connection state pill — shows green/red dot + label
        // next to the contact phone, so the operator can tell at a
        // glance whether the paired phone is online before typing a
        // reply that won't actually send.
        const dsEl = $('#th-device-state');
        if (dsEl) {
            const ds = c.device_status || 'unknown';
            if (ds === 'cloud') {
                dsEl.classList.add('hidden');
            } else {
                const cls = ds === 'connected' ? 'bg-emerald-100 text-emerald-700'
                          : ds === 'disconnected' ? 'bg-red-100 text-red-700'
                          : 'bg-amber-100 text-amber-700';
                const dot = ds === 'connected' ? 'bg-emerald-500'
                          : ds === 'disconnected' ? 'bg-red-500'
                          : 'bg-amber-500';
                const label = ds === 'connected' ? 'Device online'
                            : ds === 'disconnected' ? 'Device offline'
                            : 'Connecting…';
                const seen = c.device_last_seen
                    ? ` · seen ${formatTime(c.device_last_seen)}`
                    : '';
                dsEl.className = `inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-mono ${cls}`;
                dsEl.innerHTML = `<span class="w-1.5 h-1.5 rounded-full ${dot}"></span>${label}${ds !== 'connected' ? escape(seen) : ''}`;
            }
        }

        // WABA call button — the server gate (wa_calling_enabled) already encodes
        // "workspace calling on + provider is WABA + real phone JID", so we only
        // add the contact_phone guard (the dialer needs digits to call). NOTE: we
        // used to also require device_id===null, but since the device-unification
        // fix a WABA thread carries the wa_provider_configs id in device_id (not
        // null) — that guard hid the button on EVERY WABA chat. Removed.
        // display:none inline as well so stale CSS can't leave it visible.
        const callBtn = $('#wa-call-btn');
        if (callBtn) {
            const callOk = !!c.wa_calling_enabled && !!c.contact_phone;
            callBtn.classList.toggle('hidden', !callOk);
            callBtn.style.display = callOk ? '' : 'none';
        }

        // thread — only auto-scroll to the bottom when the operator
        // is already there (within ~80px of the floor). Otherwise the
        // polling refresh would yank them away from older messages
        // they were trying to read. Switching to a different thread
        // (activeId changed) still scrolls to bottom — that's a
        // fresh-thread open, not a poll.
        const thread = $('#thread');
        if (thread) {
            const wasNearBottom = (thread.scrollHeight - thread.scrollTop - thread.clientHeight) < 80;
            const isNewThread   = thread.dataset.activeId !== String(state.activeId);

            const items = [...state.thread.map(m => ({ kind: m.direction === 'out' ? 'out' : 'in', ...m })),
                           ...state.notes.map(n => ({ kind: 'note', ...n }))]
                .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            // Centered "Today / Yesterday / D Mon YYYY" date chip before the
            // first message of each day, like WhatsApp — so operators can see
            // which day a run of messages belongs to.
            let prevDay = null;
            thread.innerHTML = items.map(m => {
                const k = m.created_at ? dayKey(m.created_at) : '';
                let sep = '';
                if (k && k !== prevDay) { sep = dateChipHtml(dayLabel(m.created_at)); prevDay = k; }
                return sep + renderThreadItem(m);
            }).join('');
            wireAudioPlayers(thread);

            // Wire retry on failed optimistic messages.
            thread.querySelectorAll('[data-retry-msg]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const tempId = btn.dataset.retryMsg;
                    const failed = (state.thread || []).find(m => m.__tempId === tempId);
                    if (!failed) return;
                    // Drop the failed bubble + reseed the composer so the
                    // user can edit + resend.
                    state.thread = state.thread.filter(m => m.__tempId !== tempId);
                    $('#composer') && ($('#composer').value = failed.body || '');
                    renderActive();
                    $('#composer-rich')?.focus?.();
                });
            });

            // Wire the per-message hover-menu (Info / Pin / Star / React /
            // Forward / Delete) — opens the floating menu next to the
            // bubble's chevron button.
            thread.querySelectorAll('[data-msg-menu]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openMessageMenu(parseInt(btn.dataset.msgMenu, 10), btn);
                });
            });

            if (isNewThread || wasNearBottom) {
                thread.scrollTop = thread.scrollHeight;
            }
            thread.dataset.activeId = String(state.activeId);
        }

        // contact panel
        $('#ct-avatar') && ($('#ct-avatar').textContent = initials);
        $('#ct-name')   && ($('#ct-name').textContent   = c.title || '—');
        $('#ct-phone')  && ($('#ct-phone').textContent  = '');

        // assignee chip — when an assignee id exists, try to resolve the
        // display name from the loaded members list (the API may not
        // include it directly). Fall back to "Assigned" rather than "?"
        // so the chip never reads as broken.
        const ass = $('#ct-assignee');
        if (ass) {
            if (c.assignee_user_id) {
                const member = (state.members || []).find(m => m.id === c.assignee_user_id);
                const name = c.assignee_name || member?.name || member?.email || '';
                const initials = (name || 'U').trim().split(/\s+/).map(s => s[0]).join('').slice(0, 2).toUpperCase();
                const displayName = name || 'Assigned';
                ass.innerHTML = `<span class="w-7 h-7 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px] font-semibold">${escape(initials)}</span>
                                 <div class="ml-2"><div class="text-[12px] font-medium">${escape(displayName)}</div><div class="text-[10px] text-ink-500">${escape(c.team_name || '')}</div></div>`;
            } else {
                ass.innerHTML = `<span class="text-[12px] text-ink-500">Unassigned</span>`;
            }
        }

        // Resolution banner — shown below the thread when conversation is resolved
        const resolveBanner = $('#resolve-banner');
        if (resolveBanner) {
            if (c.inbox_status === 'resolved' && c.resolved_at) {
                let who = c.resolved_by_agent_name
                    ? `AI agent <strong>${escape(c.resolved_by_agent_name)}</strong>`
                    : (c.resolved_by_name ? `<strong>${escape(c.resolved_by_name)}</strong>` : 'a team member');
                resolveBanner.innerHTML = `
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-accent-mint shrink-0" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>
                    Resolved by ${who} · <span class="font-mono">${formatTime(c.resolved_at)}</span>`;
                resolveBanner.classList.remove('hidden');
            } else {
                resolveBanner.classList.add('hidden');
            }
        }

        // AI agent chip in contact panel
        const agentEl = $('#ct-agent');
        if (agentEl) {
            if (c.assignee_agent_id && c.agent_name) {
                const color = safeColor(c.agent_color);
                agentEl.innerHTML = `<span class="inline-flex items-center gap-1.5">
                    <span class="w-6 h-6 rounded-full text-paper-0 text-[9px] font-semibold grid place-items-center" style="background:${color}">${escape((c.agent_name||'AI').slice(0,2).toUpperCase())}</span>
                    <span class="text-[12px] font-medium">${escape(c.agent_name)}</span>
                    <span class="font-mono text-[9.5px] text-ink-500">AI agent</span>
                </span>`;
            } else {
                agentEl.innerHTML = `<span class="text-[12px] text-ink-500">None assigned</span>`;
            }
        }

        // AI agent button label in thread header
        const aiLabel = $('#ai-agent-label');
        if (aiLabel) {
            aiLabel.textContent = c.agent_name ? escape(c.agent_name) : 'AI Agent';
        }

        // tags
        const tagsEl = $('#ct-tags');
        if (tagsEl) {
            tagsEl.innerHTML = (c.tags || []).map(t => `
                <span class="px-2 py-0.5 rounded-full text-[10px] font-mono inline-flex items-center gap-1" style="background:${safeColor(t.color, '#075E54')}20;color:${safeColor(t.color, '#075E54')}">
                    ${escape(t.name)}
                    <button data-untag="${t.id}" class="hover:opacity-60">×</button>
                </span>
            `).join('');
            tagsEl.querySelectorAll('[data-untag]').forEach(b => {
                b.addEventListener('click', () => untag(c.id, parseInt(b.dataset.untag, 10)));
            });
        }

        // Sales Pipeline deals for this contact — async, plan-gated. Keeps
        // the deal next to the chat so an agent sees what's in play.
        renderConversationDeals(c.id);

        // past conversations — prior threads with the same contact,
        // pulled from /api/conversations/{id} as `history`. Empty state
        // hides the section header so the panel stays tidy.
        const historyEl = $('#ct-history');
        if (historyEl) {
            const items = state.history || [];
            if (items.length === 0) {
                historyEl.innerHTML = `<li class="text-[11px] text-ink-500 ml-1">No past conversations.</li>`;
            } else {
                historyEl.innerHTML = items.map(h => {
                    const dot = h.resolved_at
                        ? 'bg-accent-mint'
                        : (h.inbox_status === 'snoozed' ? 'bg-accent-amber' : 'bg-wa-deep');
                    return `
                        <li class="relative">
                          <span class="absolute -left-[15px] top-1 w-2 h-2 rounded-full ${dot} ring-2 ring-paper-0"></span>
                          <button data-history-id="${h.id}" class="text-left w-full hover:bg-paper-50 rounded px-1 py-0.5">
                            <div class="text-[11.5px] text-ink-700 truncate">${escape(h.preview || '(no message)')}</div>
                            <div class="font-mono text-[9.5px] text-ink-500">${formatTime(h.last_message_at)} · ${h.resolved_at ? 'resolved' : (h.inbox_status || 'open')}</div>
                          </button>
                        </li>`;
                }).join('');
                historyEl.querySelectorAll('[data-history-id]').forEach(b => {
                    b.addEventListener('click', () => loadActive(parseInt(b.dataset.historyId, 10)).then(() => { state.activeId = parseInt(b.dataset.historyId, 10); }));
                });
            }
        }

        // events (resolution history + activity log)
        renderEvents();

        // notes
        const notesEl = $('#ct-notes');
        if (notesEl) {
            $('#notes-count') && ($('#notes-count').textContent = state.notes.length);
            notesEl.innerHTML = state.notes.map(n => `
                <div class="text-[11.5px] bg-accent-amber/10 border border-accent-amber/30 rounded-md px-2.5 py-2">
                  <div class="flex items-center justify-between mb-1">
                    <span class="font-mono text-[10px] text-ink-700 font-semibold">${escape(n.author_name || 'agent')}</span>
                    <span class="text-[9.5px] font-mono text-ink-500">${formatTime(n.created_at)}</span>
                  </div>
                  <div class="text-ink-700 whitespace-pre-wrap">${escape(n.body)}</div>
                </div>`).join('');
        }

        renderQueue(); // refresh active row highlight
    }

    function renderEvents() {
        const el = $('#ct-events');
        if (!el) return;
        const events = (state.events || []).slice().reverse(); // newest first
        if (events.length === 0) {
            el.innerHTML = `<li class="text-[11px] text-ink-500 ml-1">No events yet.</li>`;
            return;
        }
        const eventLabel = {
            resolved:        'Resolved',
            reopened:        'Reopened',
            assigned:        'Assigned',
            unassigned:      'Unassigned',
            agent_assigned:  'AI agent assigned',
            agent_unassigned:'AI agent removed',
            snoozed:         'Snoozed',
            priority_changed:'Priority changed',
            tagged:          'Tagged',
            note_added:      'Note added',
        };
        const dotColor = {
            resolved:        'bg-accent-mint',
            reopened:        'bg-wa-deep',
            assigned:        'bg-[#0ea5e9]',
            agent_assigned:  'bg-[#6366f1]',
            agent_unassigned:'bg-paper-300',
            snoozed:         'bg-accent-amber',
            priority_changed:'bg-paper-300',
        };
        el.innerHTML = events.map(ev => {
            const label = eventLabel[ev.type] || ev.type.replace(/_/g, ' ');
            const dot = dotColor[ev.type] || 'bg-paper-300';
            // Build actor line: show "by Name" + optional "· AI: AgentName"
            let actor = ev.actor_name ? escape(ev.actor_name) : 'System';
            if (ev.agent_name) {
                actor += ` · <span class="text-[#6366f1] font-semibold">${escape(ev.agent_name)} (AI)</span>`;
            }
            return `
            <li class="relative text-[11.5px]">
              <span class="absolute -left-[15px] top-1 w-2.5 h-2.5 rounded-full ${dot} ring-2 ring-paper-0"></span>
              <div class="font-semibold text-ink-800">${escape(label)}</div>
              <div class="text-ink-500 mt-0.5">by ${actor}</div>
              <div class="font-mono text-[9.5px] text-ink-400 mt-0.5">${formatTime(ev.created_at)}</div>
            </li>`;
        }).join('');
    }

    function renderMediaBlock(m) {
        // Contact-card sharing — renders a vCard-style card with name,
        // phone, and action buttons. No file URL needed since the
        // payload lives on Message::meta.contact. #21 — adds an inline
        // "Add to contacts" button that POSTs to extract-contact and
        // promotes the captured vCard into a real Contact row.
        if (m.media_type === 'contact' && m.contact) {
            const c = m.contact;
            const initials = (c.name || '?').split(/\s+/).map(s => s[0] || '').slice(0, 2).join('').toUpperCase();
            const phone = (c.phone || '').replace(/\D+/g, '');
            const cap = m.body ? `<div class="text-[12.5px] whitespace-pre-wrap break-words mt-1.5">${escape(m.body)}</div>` : '';
            return `<div class="bg-paper-0 border border-paper-200 rounded-md p-2.5 flex items-center gap-2.5 max-w-[300px]">
                <span class="w-10 h-10 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold grid place-items-center shrink-0">${escape(initials)}</span>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-semibold text-ink-900 truncate">${escape(c.name || 'Contact')}</div>
                    <div class="text-[11.5px] font-mono text-wa-deep truncate">${escape(c.phone || '—')}</div>
                </div>
                <div class="flex flex-col gap-1 shrink-0">
                  ${phone ? `<a href="https://wa.me/${phone}" target="_blank" rel="noopener" class="px-2 py-1 rounded-full bg-wa-mint text-wa-deep text-[10px] font-semibold hover:bg-wa-bubble whitespace-nowrap text-center">Open</a>` : ''}
                  ${phone ? `<button type="button" data-add-contact="${m.id}" class="px-2 py-1 rounded-full bg-paper-0 border border-paper-200 hover:bg-paper-50 text-[10px] font-semibold whitespace-nowrap text-ink-700">+ Save</button>` : ''}
                </div>
            </div>${cap}`;
        }
        // Location share — map-pin tile with name/address + "Open in
        // Google Maps" deep link. Top-level lat/lng come from the
        // inbox_messages columns; serializer ships them under .location.
        if (m.media_type === 'location' && m.location) {
            const loc = m.location;
            const lat = Number(loc.lat ?? 0);
            const lng = Number(loc.lng ?? 0);
            const label = escape(loc.name || 'Shared location');
            const addr  = loc.address ? `<div class="text-[11px] text-ink-500 truncate">${escape(loc.address)}</div>` : '';
            const cap2  = m.body && m.body !== loc.name && m.body !== loc.address
                ? `<div class="text-[12.5px] whitespace-pre-wrap break-words mt-1.5">${escape(m.body)}</div>` : '';
            return `<a href="https://www.google.com/maps?q=${lat},${lng}" target="_blank" rel="noopener" class="block bg-paper-50 border border-paper-200 rounded-md p-2.5 hover:bg-paper-100 max-w-[300px]">
                <div class="flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-5 h-5 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5a4.5 4.5 0 0 0-4.5 4.5c0 3.5 4.5 8.5 4.5 8.5s4.5-5 4.5-8.5A4.5 4.5 0 0 0 8 1.5Z"/><circle cx="8" cy="6" r="1.5"/></svg>
                    <div class="min-w-0 flex-1">
                        <div class="text-[12.5px] font-semibold text-ink-800 truncate">${label}</div>
                        ${addr}
                        <div class="font-mono text-[9.5px] text-ink-400 mt-0.5">${lat.toFixed(5)}, ${lng.toFixed(5)}</div>
                    </div>
                </div>
            </a>${cap2}`;
        }
        const cap = m.body ? `<div class="text-[12.5px] whitespace-pre-wrap break-words mt-1.5">${escape(m.body)}</div>` : '';

        // BUGFIX: images/videos that arrive as a "document" attachment (e.g. a
        // forwarded photo "sent as document") must preview inline, not show a
        // bare filename. The mime isn't serialised, so detect from the file
        // extension on the stored URL / original filename.
        const extMatch = String(m.media_url || m.media_name || '').toLowerCase().match(/\.([a-z0-9]+)(?:\?|#|$)/);
        const ext = extMatch ? extMatch[1] : '';
        const isImageExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic'].includes(ext);
        const isVideoExt = ['mp4', '3gp', 'mov', 'mkv', 'webm', 'm4v'].includes(ext);
        let kind = m.media_type;
        if ((kind === 'document' || !kind) && isImageExt) kind = 'image';
        else if ((kind === 'document' || !kind) && isVideoExt) kind = 'video';

        // BUGFIX: media that should have a file but didn't download (forwarded /
        // old media evicted from WhatsApp's CDN) used to render an EMPTY bubble.
        // Show a clear "unavailable" placeholder instead.
        if (!m.media_url) {
            if (['image', 'video', 'audio', 'document', 'sticker'].includes(m.media_type)) {
                const label = m.media_type === 'image' ? 'Photo'
                    : m.media_type === 'video' ? 'Video'
                    : m.media_type === 'audio' ? 'Voice message' : 'File';
                return `<div class="flex items-center gap-2 bg-paper-50 border border-dashed border-paper-200 rounded-md px-2.5 py-2 text-ink-500 max-w-[320px]">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6.5"/><path d="M8 5v4M8 11h.01"/></svg>
                    <div class="min-w-0 flex-1"><div class="text-[12px] font-medium truncate">${label} unavailable</div><div class="text-[10px] text-ink-400">Media could not be downloaded</div></div>
                    ${m.id ? `<button type="button" data-retry-media="${m.id}" class="shrink-0 text-[11px] font-semibold text-wa-deep hover:underline">Retry</button>` : ''}
                </div>${cap}`;
            }
            return '';
        }

        const url = m.media_url;
        const name = escape(m.media_name || 'file');
        switch (kind) {
            case 'image':
                return `<button type="button" data-lightbox="${url}" data-lightbox-name="${name}" class="block w-full text-left">
                    <img src="${url}" alt="${name}" class="max-w-full max-h-[280px] rounded-md object-cover cursor-zoom-in hover:opacity-95" loading="lazy">
                </button>${cap}`;
            case 'sticker':
                return `<img src="${url}" alt="sticker" class="max-w-[160px] max-h-[160px] object-contain" loading="lazy">${cap}`;
            case 'video':
                return `<video src="${url}" controls class="max-w-full max-h-[280px] rounded-md"></video>${cap}`;
            case 'audio':
                // Custom voice-note player — the bare <audio controls> rendered
                // as a broken dash on the bubble (esp. dark theme). This shows a
                // round play/pause button + seek bar + duration, consistent in
                // both themes and on both bubble sides. Wired by wireAudioPlayers().
                return `<div class="wa-audio flex items-center gap-2.5 rounded-full bg-paper-0/70 border border-paper-200 px-2 py-1.5 max-w-[260px] min-w-[210px]">
                    <button type="button" class="wa-audio-play w-9 h-9 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 grid place-items-center shrink-0" aria-label="Play voice message">
                        <svg class="wa-audio-play-ico w-3.5 h-3.5" viewBox="0 0 16 16" fill="currentColor"><path d="M4 3.2v9.6c0 .5.6.8 1 .5l7-4.8c.4-.3.4-.9 0-1.1l-7-4.8c-.4-.3-1 0-1 .5z"/></svg>
                        <svg class="wa-audio-pause-ico w-3.5 h-3.5 hidden" viewBox="0 0 16 16" fill="currentColor"><rect x="4" y="3" width="3" height="10" rx="1"/><rect x="9" y="3" width="3" height="10" rx="1"/></svg>
                    </button>
                    <div class="flex-1 min-w-0">
                        <input type="range" class="wa-audio-seek w-full accent-wa-deep cursor-pointer" min="0" max="1000" value="0" step="1" aria-label="Seek">
                        <div class="flex items-center justify-between text-[10px] font-mono text-ink-500 mt-0.5">
                            <span class="wa-audio-cur">0:00</span><span class="wa-audio-dur">0:00</span>
                        </div>
                    </div>
                    <audio class="wa-audio-el hidden" src="${url}" preload="metadata"></audio>
                </div>${cap}`;
            case 'document':
            default:
                return `<a href="${url}" target="_blank" rel="noopener" class="flex items-center gap-2 bg-paper-50 border border-paper-200 rounded-md px-2.5 py-2 hover:bg-paper-100">
                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-wa-deep shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 1H3v14h10V5z M9 1v4h4"/></svg>
                    <div class="min-w-0">
                        <div class="text-[12px] font-semibold text-ink-700 truncate">${name}</div>
                        <div class="font-mono text-[9.5px] uppercase tracking-wider text-ink-500">Download</div>
                    </div>
                </a>${cap}`;
        }
    }

    // Wire the custom voice-note players rendered by the 'audio' case above —
    // round play/pause button, seek bar, live duration. Idempotent per element.
    function wireAudioPlayers(root) {
        (root || document).querySelectorAll('.wa-audio').forEach((box) => {
            if (box.dataset.audioWired === '1') return;
            box.dataset.audioWired = '1';
            const audio  = box.querySelector('.wa-audio-el');
            const btn    = box.querySelector('.wa-audio-play');
            const seek   = box.querySelector('.wa-audio-seek');
            const curEl  = box.querySelector('.wa-audio-cur');
            const durEl  = box.querySelector('.wa-audio-dur');
            const playI  = box.querySelector('.wa-audio-play-ico');
            const pauseI = box.querySelector('.wa-audio-pause-ico');
            if (!audio || !btn) return;

            const fmt = (s) => {
                s = Math.max(0, Math.floor(Number(s) || 0));
                return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
            };
            const showPlay  = () => { playI?.classList.remove('hidden'); pauseI?.classList.add('hidden'); };
            const showPause = () => { playI?.classList.add('hidden');    pauseI?.classList.remove('hidden'); };

            audio.addEventListener('loadedmetadata', () => {
                if (isFinite(audio.duration)) durEl.textContent = fmt(audio.duration);
            });
            audio.addEventListener('timeupdate', () => {
                if (audio.duration && isFinite(audio.duration)) {
                    seek.value = String(Math.round((audio.currentTime / audio.duration) * 1000));
                }
                curEl.textContent = fmt(audio.currentTime);
            });
            audio.addEventListener('ended', () => { showPlay(); seek.value = '0'; curEl.textContent = '0:00'; });
            audio.addEventListener('pause', showPlay);
            audio.addEventListener('play', showPause);

            btn.addEventListener('click', () => {
                // Only one voice note plays at a time.
                document.querySelectorAll('.wa-audio-el').forEach((a) => { if (a !== audio) a.pause(); });
                if (audio.paused) audio.play().catch(() => {}); else audio.pause();
            });
            seek.addEventListener('input', () => {
                if (audio.duration && isFinite(audio.duration)) {
                    audio.currentTime = (Number(seek.value) / 1000) * audio.duration;
                }
            });
        });
    }

    // ── Date separators (WhatsApp-style "Today / Yesterday / D Mon YYYY") ──
    /** Local Y-M-D key so messages are grouped by calendar day, not UTC. */
    function dayKey(ts) { const d = new Date(ts); return `${d.getFullYear()}-${d.getMonth()}-${d.getDate()}`; }
    /** Human day label relative to now: Today / Yesterday / "5 Jul 2026". */
    function dayLabel(ts) {
        const d = new Date(ts), now = new Date();
        const k = dayKey(ts);
        if (k === dayKey(now)) return 'Today';
        if (k === dayKey(new Date(now.getTime() - 86400000))) return 'Yesterday';
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
    }
    /** Centered pill shown before the first message of each day. */
    function dateChipHtml(label) {
        return `<div class="flex justify-center my-2"><span class="px-2.5 py-1 rounded-md bg-paper-100/90 text-ink-500 text-[10.5px] font-mono uppercase tracking-wide shadow-sm">${escape(label)}</span></div>`;
    }

    // Crash-proof wrapper: a single malformed message must NEVER throw and
    // blank the entire thread. Any render error falls back to a plain text
    // bubble and logs the cause, so the inbox stays alive no matter what.
    function renderThreadItem(m) {
        try {
            return renderThreadItemImpl(m);
        } catch (err) {
            console.warn('[team-inbox] message render failed — text fallback', m && m.id, err);
            const o   = !!(m && m.kind === 'out');
            const esc = (s) => String(s ?? '').replace(/[&<>]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;' }[c]));
            return '<div class="' + (o ? 'ml-auto' : '') + ' w-fit max-w-[70%] bg-paper-0 border border-paper-200 rounded-lg px-3 py-2 text-[13px] text-ink-700 whitespace-pre-wrap break-words">'
                + esc(m && m.body ? m.body : '(message)') + '</div>';
        }
    }
    function renderThreadItemImpl(m) {
        const t = formatTime(m.created_at);
        if (m.kind === 'note') {
            return `<div class="bg-accent-amber/10 border border-accent-amber/30 rounded-md px-3 py-2 text-[12px] text-[#7B5A14]">
                <div class="font-mono text-[9.5px] uppercase tracking-wider mb-1">Internal note · ${escape(m.author_name || '')} · ${t}</div>
                <div class="whitespace-pre-wrap">${escape(m.body || '')}</div>
            </div>`;
        }
        const out = m.kind === 'out';
        const align = out ? 'ml-auto' : '';

        // Voice call entry — WhatsApp-style call bubble (icon + "Voice call ·
        // 4 min" / "Missed voice call") instead of a blank "Message unavailable".
        // Fed by the calls-webhook mirror (media_type='call', meta.event='call').
        if (m.media_type === 'call' || m.call) {
            const st     = (m.call && m.call.status) || '';
            const missed = ['missed', 'no_answer', 'declined'].includes(st);
            const label  = m.body || (missed ? 'Missed voice call' : 'Voice call');
            const bg     = out ? 'bg-wa-bubble' : 'bg-paper-0';
            const ring   = missed ? 'bg-accent-coral/10 text-accent-coral' : 'bg-wa-mint/30 text-wa-deep';
            const icon   = `<svg viewBox="0 0 20 20" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 4.5c0 6 5.5 11.5 11.5 11.5a1.5 1.5 0 0 0 1.5-1.5v-1.8a1 1 0 0 0-.8-1l-2.4-.5a1 1 0 0 0-1 .4l-.6.8a9 9 0 0 1-4-4l.8-.6a1 1 0 0 0 .4-1l-.5-2.4a1 1 0 0 0-1-.8H5.5A1.5 1.5 0 0 0 4 4.5Z"/></svg>`;
            return `<div class="${align} w-fit max-w-[70%] min-w-[120px] group">
                <div class="${bg} border border-paper-200 rounded-lg px-3 py-2 flex items-center gap-2.5">
                    <span class="grid place-items-center w-8 h-8 rounded-full ${ring}">${icon}</span>
                    <span class="text-[13px] font-medium leading-tight">${escape(label)}</span>
                </div>
                <div class="text-[9.5px] font-mono text-ink-500 mt-0.5 ${out ? 'text-right' : ''}">${t}</div>
            </div>`;
        }

        // Catalog send — render a proper product-card tile instead of
        // the plain "[Catalog · ...]" body string. Matches the visual
        // the buyer sees in WhatsApp.
        if (m.catalog) {
            const cat = m.catalog;
            const modeLabel = cat.mode === 'spm' ? 'Single product'
                            : cat.mode === 'mpm' ? 'Product carousel · ' + (cat.products || []).length + ' items'
                            : 'Catalog link';
            const cards = (cat.products || []).slice(0, 6).map(p => `
                <div class="bg-white rounded-md border border-paper-200 overflow-hidden flex flex-col">
                    <div class="h-20 bg-paper-50 grid place-items-center overflow-hidden">
                        ${p.image_url
                            ? `<img src="${escape(p.image_url)}" class="w-full h-full object-cover" onerror="this.outerHTML='<span class=&quot;text-[20px]&quot;>📦</span>'">`
                            : `<span class="text-[20px]">📦</span>`}
                    </div>
                    <div class="px-1.5 py-1">
                        <div class="text-[11px] font-semibold leading-tight truncate">${escape(p.name || '')}</div>
                        <div class="text-[10px] font-mono text-wa-deep">${escape(p.price_display || '')}</div>
                    </div>
                </div>
            `).join('');

            const moreLabel = (cat.products || []).length > 6
                ? `<div class="text-[10.5px] font-mono text-white/80 mt-1">+ ${cat.products.length - 6} more</div>`
                : '';

            return `<div class="${align} w-fit max-w-[80%]">
                <div class="bg-[#005c4b] text-white rounded-lg p-2.5 shadow-sm">
                    <div class="flex items-center gap-1.5 text-[10.5px] font-mono uppercase tracking-[0.12em] text-white/70 mb-1.5">
                        <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="3" width="12" height="10" rx="1.5"/><path d="M2 6h12M5 3v3"/></svg>
                        ${escape(modeLabel)}
                    </div>
                    ${m.body ? `<div class="text-[13px] whitespace-pre-wrap break-words leading-snug mb-1.5">${escape(m.body)}</div>` : ''}
                    ${cards
                        ? `<div class="grid grid-cols-2 sm:grid-cols-3 gap-1.5">${cards}</div>${moreLabel}`
                        : ''}
                </div>
                <div class="text-[9.5px] font-mono text-ink-500 mt-0.5 text-right">${t}</div>
            </div>`;
        }

        // WhatsApp Flow FORM submission — clickable "Form response" card that
        // opens the detail slide-over. Defensive (string concat, no template
        // interpolation of functions) and shielded by the try/catch wrapper.
        if (m.form && m.form.kind === 'submission') {
            FORM_VIEW_CACHE[m.id] = m.form;
            const fc   = Array.isArray(m.form.fields) ? m.form.fields.length : 0;
            const skin = out ? 'bg-[#005c4b] text-white' : 'bg-paper-0 border border-paper-200 text-ink-900';
            const sub  = out ? 'text-white/70' : 'text-ink-500';
            const divc = out ? 'border-white/20' : 'border-paper-200';
            const ctac = out ? 'text-white/90' : 'text-wa-deep';
            const iconBg = out ? 'bg-white/15' : 'bg-wa-deep/10 text-wa-deep';
            return '<div class="' + align + ' w-fit max-w-[70%] min-w-[190px]">'
                + '<button type="button" data-form-view="' + m.id + '" class="w-full text-left ' + skin + ' rounded-lg px-3 py-2.5 shadow-sm hover:opacity-95 transition">'
                + '<div class="flex items-center gap-2.5">'
                + '<span class="w-8 h-8 rounded-full ' + iconBg + ' grid place-items-center shrink-0"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="3" y="1.5" width="10" height="13" rx="1.5"/><path d="M5.5 5h5M5.5 8h5M5.5 11h3"/></svg></span>'
                + '<div class="min-w-0"><div class="text-[13px] font-semibold leading-tight truncate">Form response</div>'
                + '<div class="text-[11px] ' + sub + ' truncate">' + escape(m.form.title || 'Form') + ' &middot; ' + fc + ' field' + (fc === 1 ? '' : 's') + '</div></div>'
                + '</div>'
                + '<div class="text-[11px] ' + ctac + ' font-medium mt-1.5 text-center border-t ' + divc + ' pt-1.5">Tap to view details</div>'
                + '</button>'
                + '<div class="text-[9.5px] font-mono text-ink-500 mt-0.5 ' + (out ? 'text-right' : '') + '">' + t + '</div>'
                + '</div>';
        }

        // Template-rendered outbound — header, body, footer, buttons
        // all came from WaTemplate.meta. Show the same WhatsApp-style
        // green bubble the composer preview shows so the operator sees
        // exactly what the recipient saw.
        const hasTemplate = m.header || m.footer || (Array.isArray(m.buttons) && m.buttons.length > 0);
        if (hasTemplate && out) {
            const buttonsHtml = (m.buttons || []).map(b => `
                <div class="bg-white/10 border-t border-white/20 px-2 py-1.5 rounded text-center text-[12px] font-medium">
                    ${escape(b.text || b.label || 'Button')}
                </div>`).join('');
            return `<div class="${align} w-fit max-w-[70%] min-w-[80px]">
                <div class="bg-[#005c4b] text-white rounded-lg px-3.5 py-2.5 text-[13px] shadow-sm">
                    ${m.header ? `<div class="font-semibold text-[14px] leading-tight mb-1">${escape(m.header)}</div>` : ''}
                    <div class="whitespace-pre-wrap break-words leading-snug">${escape(m.body || '')}</div>
                    ${m.footer ? `<div class="text-[11.5px] text-white/70 mt-1.5">${escape(m.footer)}</div>` : ''}
                    ${buttonsHtml ? `<div class="mt-2 space-y-1">${buttonsHtml}</div>` : ''}
                </div>
                <div class="text-[9.5px] font-mono text-ink-500 mt-0.5 text-right">${t}</div>
            </div>`;
        }

        const bg = out ? 'bg-wa-bubble' : 'bg-paper-0';
        const mediaHtml = renderMediaBlock(m);
        // Real-time translation — `translated_body` is always the agent-language
        // view (inbound: translated FROM the customer; outbound: what the agent
        // typed before it was sent in the customer's language). Show it as the
        // primary text with a no-JS <details> toggle back to the original.
        const xlated = !!(m.is_translated && m.translated_body);
        const primaryText = xlated ? m.translated_body : (m.body || '');
        const xlateLang = String(m.detected_language || '').toUpperCase();
        const xlateToggle = xlated
            ? `<details class="mt-1 text-[10px] text-ink-500 leading-snug">
                <summary class="cursor-pointer select-none inline-flex items-center gap-1 hover:text-ink-700">
                    <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6.5"/><path d="M1.5 8h13M8 1.5c2 2 2 11 0 13M8 1.5c-2 2-2 11 0 13"/></svg>
                    ${out ? `sent in ${escape(xlateLang)} · show sent text` : `translated from ${escape(xlateLang)} · show original`}
                </summary>
                <div class="mt-0.5 whitespace-pre-wrap break-words italic opacity-80">${escape(m.body || '')}</div>
            </details>`
            : '';
        // Never render a fully-blank bubble. When there is no media block and no
        // text (an empty AI reply, a WABA type we don't extract a body for, or a
        // failed decrypt), show a muted label instead of an empty box so the
        // message is still visible in the thread.
        const emptyText = !mediaHtml && String(primaryText).trim() === '';
        // NOTE: `t` is the time string in this function (const t = formatTime),
        // so the i18n `t('…')` helper is SHADOWED here — calling it throws
        // "t is not a function" and blanks the message. Use plain labels.
        const emptyLabel = m.media_type
            ? ({ image: 'Photo', video: 'Video', audio: 'Voice message', document: 'File', sticker: 'Sticker' }[m.media_type] || 'Attachment')
            : 'Message unavailable';
        const bodyHtml = mediaHtml
            ? mediaHtml
            : (emptyText
                ? `<div class="italic text-ink-400 text-[12px]">${escape(emptyLabel)}</div>`
                : `<div class="whitespace-pre-wrap break-words">${escape(primaryText)}</div>${xlateToggle}`);
        // Forwarded indicator — matches WhatsApp's UI: single curved arrow
        // for "Forwarded", double arrow + "Frequently forwarded" label for
        // anything with forwardingScore >= 5. Renders above the bubble.
        const forwardLabel = m.frequently_forwarded
            ? `<div class="text-[10.5px] italic text-ink-500 mb-0.5 flex items-center gap-1">
                <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 4l3 3-3 3M2 7h10M5 7l3 3-3 3M2 10h6"/></svg>
                Frequently forwarded
              </div>`
            : (m.forwarded
                ? `<div class="text-[10.5px] italic text-ink-500 mb-0.5 flex items-center gap-1">
                    <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4l3 3-3 3M2 7h6"/></svg>
                    Forwarded
                  </div>`
                : '');
        // Agent badge — shown on outbound messages generated by an AI agent
        const agentBadge = (out && m.agent_id && m.agent_name) ? `
            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9.5px] font-mono"
                  style="background:${safeColor(m.agent_color)}22;color:${safeColor(m.agent_color)}">
                <svg viewBox="0 0 10 10" class="w-2.5 h-2.5" fill="currentColor"><rect x="2" y="3" width="6" height="5" rx="1.5"/><path d="M4 3V2a1 1 0 012 0v1"/><circle cx="3.5" cy="5.5" r=".75"/><circle cx="6.5" cy="5.5" r=".75"/></svg>
                ${escape(m.agent_name)}
            </span>` : '';
        // AI self-rating badge — internal-only quality score. Shown next
        // to the agent badge so operators can tell at a glance whether
        // the AI thinks it answered well. Never sent to the customer.
        const qualityBadge = (out && m.agent_id && m.quality_score)
            ? `<span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9.5px] font-mono ${m.quality_score >= 8 ? 'bg-wa-mint/40 text-wa-deep' : m.quality_score >= 5 ? 'bg-accent-amber/20 text-[#7B5A14]' : 'bg-accent-coral/15 text-accent-coral'}" title="${escape(m.quality_note || 'AI self-rating')}">★ ${m.quality_score}/10</span>`
            : '';
        // Optimistic UI indicators — "sending…" (clock) while in-flight,
        // or "Failed · Retry" pill when the dispatcher errored. Only on
        // outbound bubbles with a __tempId (server messages get a real id
        // and don't pass through these branches once loadActive replaces
        // them).
        let statusPill = '';
        if (out && m.status === 'sending') {
            statusPill = `<span class="inline-flex items-center gap-1 text-[9.5px] font-mono text-ink-500" title="Sending…">
                <svg viewBox="0 0 16 16" class="w-2.5 h-2.5 animate-spin" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6" stroke-opacity="0.25"/><path d="M14 8a6 6 0 0 1-6 6" stroke-linecap="round"/></svg>
                sending
            </span>`;
        } else if (out && m.status === 'failed') {
            const reason = (m.failure_reason || '').trim();
            statusPill = `<span class="inline-flex flex-col items-end gap-0.5">
                <button type="button" data-retry-msg="${escape(m.__tempId || m.id)}" class="inline-flex items-center gap-1 text-[9.5px] font-mono text-accent-coral hover:underline" title="${escape(reason || 'Send failed')}">
                    <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M8 5v3M8 11h.01"/></svg>
                    failed · retry
                </button>
                ${reason ? `<span class="text-[9px] text-accent-coral/80 max-w-[230px] text-right leading-tight">${escape(reason)}</span>` : ''}
            </span>`;
        }
        // "Edited HH:MM" tag — WhatsApp shows this inline with the
        // timestamp on any message that's been edited. We mirror that.
        const editedTag = m.edited_at
            ? `<span class="text-[9.5px] font-mono text-ink-500 italic" title="Edited ${escape(formatTime(m.edited_at))}">Edited</span>`
            : '';
        const metaLine = out
            ? `<div class="flex items-center justify-end gap-1.5 mt-0.5">${agentBadge}${qualityBadge}${statusPill}${editedTag}<span class="text-[9.5px] font-mono text-ink-500">${t}</span></div>`
            : `<div class="text-[9.5px] font-mono text-ink-500 mt-0.5">${editedTag ? editedTag + ' · ' : ''}${t}</div>`;
        const opacityClass = (out && m.status === 'sending') ? 'opacity-75' : '';
        // Reaction pip — WhatsApp shows the emoji a contact reacted with
        // on the bottom corner of the bubble. Single emoji, no count.
        const reactionPip = m.reaction
            ? `<span class="absolute -bottom-2 ${out ? 'left-2' : 'right-2'} bg-paper-0 border border-paper-200 rounded-full px-1.5 py-0.5 text-[13px] shadow-sm leading-none" title="Reaction">${escape(m.reaction)}</span>`
            : '';
        // Star + pin badges (small icons on top of the bubble) mirror /chat.
        const pinBadge   = m.pinned  ? `<span class="text-[10px] text-wa-deep" title="Pinned">📌</span>` : '';
        const starBadge  = m.starred ? `<span class="text-[10px] text-accent-amber" title="Starred">⭐</span>` : '';
        // Hover menu trigger — shown on every real (non-optimistic) message.
        // The dropdown lives outside the bubble so its overflow doesn't
        // clip on the rounded edges.
        const isRealMsg = typeof m.id === 'number' && !m.__tempId;
        const menuBtn = isRealMsg ? `<button type="button" data-msg-menu="${m.id}" class="absolute -top-2 ${out ? 'left-1' : 'right-1'} w-5 h-5 rounded-full bg-paper-0 border border-paper-200 hover:bg-paper-50 grid place-items-center text-ink-500 opacity-0 group-hover:opacity-100 transition" title="Actions">
            <svg viewBox="0 0 16 16" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6l4 4 4-4"/></svg>
        </button>` : '';
        // WhatsApp-style bubble sizing: width fits the content, capped
        // at 70% of the thread. Min-width keeps super-short messages
        // ("Hi", "ok") from collapsing to a weird narrow pill. The
        // metaLine sits under the bubble at full wrapper width.
        return `<div class="${align} w-fit max-w-[70%] min-w-[80px] ${opacityClass} group">
            ${forwardLabel}
            <div class="relative ${bg} border border-paper-200 rounded-lg px-3 py-2 text-[13px]">
                ${bodyHtml}
                ${reactionPip}
                ${menuBtn}
                ${(pinBadge || starBadge) ? `<span class="absolute -top-1.5 ${out ? 'right-1' : 'left-1'} flex items-center gap-0.5">${pinBadge}${starBadge}</span>` : ''}
            </div>
            ${metaLine}
        </div>`;
    }

    // ── Actions ──────────────────────────────────────────────────────────
    // In-flight guard. We toggle a class on the Send button + flip its
    // label to "Sending…" with a spinner while a request is open, and
    // bail early on any subsequent click. Without this, rapid clicks
    // (or hitting Cmd+Enter twice while the server is still working)
    // fire duplicate sends — the customer ends up with four identical
    // messages.
    let sendInFlight = false;
    function lockSendButton() {
        sendInFlight = true;
        const btn = $('#send-btn');
        if (!btn) return;
        btn.disabled = true;
        btn.dataset.originalHtml = btn.dataset.originalHtml || btn.innerHTML;
        btn.innerHTML = `
            <svg viewBox="0 0 24 24" class="w-3 h-3 animate-spin" fill="none" stroke="currentColor" stroke-width="3">
                <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                <path d="M22 12a10 10 0 0 1-10 10" stroke-linecap="round"/>
            </svg>
            Sending…`;
        btn.classList.add('opacity-75', 'cursor-not-allowed');
    }
    function unlockSendButton() {
        sendInFlight = false;
        const btn = $('#send-btn');
        if (!btn) return;
        btn.disabled = false;
        if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
        btn.classList.remove('opacity-75', 'cursor-not-allowed');
    }

    async function reply() {
        if (sendInFlight) return;
        if (!state.activeId) return;
        // Template mode — send template_id; server rebuilds the message
        // with header/footer/buttons. Body is ignored server-side.
        if (state.composerMode === 'template' && state.selectedTemplate?.id) {
            lockSendButton();
            try {
                await api(`/team-inbox/api/conversations/${state.activeId}/reply`, {
                    method: 'POST',
                    body:   { template_id: state.selectedTemplate.id },
                });
                clearTemplateSelection();
                // Flip back to Reply so the operator's default state is
                // typing a freeform message.
                document.querySelector('#composer-tabs [data-mode="reply"]')?.click();
                await loadActive(state.activeId);
                await loadQueue(true);
            } catch (e) {
                toast(t('Send failed') + ': ' + e.message, 'error');
            } finally {
                unlockSendButton();
            }
            return;
        }
        const body = $('#composer')?.value?.trim();
        if (!body) return;

        // Capture composer mode + active id at start so they don't shift
        // mid-flight if the operator clicks a different tab or opens a
        // different conversation while the request is still going.
        const mode = state.composerMode;
        const convId = state.activeId;

        // Optimistic UI — push a fake bubble into the thread BEFORE the
        // network call so the operator sees their message instantly.
        const tempId = 'tmp-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
        const optimisticMsg = {
            id: tempId,
            __tempId: tempId,
            direction: 'out',
            body,
            media_url: null, media_path: null, media_type: null,
            status: 'sending',
            user_id: state.me?.id || null,
            agent_id: null, agent_name: null, agent_color: null,
            created_at: new Date().toISOString(),
            sent_at: null, delivered_at: null, read_at: null,
            time_label: 'now',
            kind: mode === 'note' ? 'note' : 'out',
        };
        if (mode === 'note') {
            optimisticMsg.author_name = state.me?.name || 'You';
            state.notes = [...(state.notes || []), {
                id: tempId, __tempId: tempId,
                body, mentions: [], is_pinned: false,
                author_id: state.me?.id, author_name: state.me?.name || 'You',
                created_at: optimisticMsg.created_at, edited_at: null,
            }];
        } else {
            state.thread = [...(state.thread || []), optimisticMsg];
        }
        renderActive();
        $('#composer').value = '';
        $('#char-count') && ($('#char-count').textContent = '0 chars');

        // Read the positional {{N}} → attribute_key map the attribute
        // picker recorded into the hidden field next to the composer.
        // Server-side resolver substitutes the values before dispatch.
        let variableMap = {};
        try {
            const mapEl = document.querySelector('#composer-textarea-wrap [data-attr-map]')
                       || document.querySelector('[data-attr-map]');
            if (mapEl?.value) variableMap = JSON.parse(mapEl.value);
        } catch (_) { variableMap = {}; }

        lockSendButton();
        try {
            const endpoint = mode === 'note'
                ? `/team-inbox/api/conversations/${convId}/notes`
                : `/team-inbox/api/conversations/${convId}/reply`;
            const payload  = mode === 'note'
                ? { body, mentions: [] }
                : { body, variable_map: variableMap };
            const res = await api(endpoint, { method: 'POST', body: payload });
            // Reset the map after a successful send so the next message
            // starts fresh.
            const mapEl2 = document.querySelector('#composer-textarea-wrap [data-attr-map]')
                        || document.querySelector('[data-attr-map]');
            if (mapEl2) mapEl2.value = '{}';

            // #39 — In-place reconcile. Server returns the canonical
            // serialized message; we swap the optimistic temp bubble for
            // it instead of refetching the whole conversation. That
            // shaves a roundtrip (~300ms) off every send and keeps the
            // operator's scroll position. Falls back to loadActive() if
            // the response shape is unexpected so we never lose state.
            const realMsg = res?.message || res?.note;
            if (state.activeId === convId && realMsg) {
                const arr = mode === 'note' ? state.notes : state.thread;
                const idx = arr.findIndex(m => m.__tempId === tempId);
                if (idx >= 0) {
                    arr[idx] = { ...realMsg, __tempId: undefined };
                    scheduleRenderActive();
                }
            } else if (state.activeId === convId) {
                // Defensive fallback — server didn't return the message
                // (older API revision, error in serialization). Pull a
                // fresh copy so we don't leave a 'sending' bubble.
                await loadActive(state.activeId);
            }
            await loadQueue(true);
        } catch (e) {
            // Mark the optimistic bubble failed using the captured mode.
            const arr = mode === 'note' ? state.notes : state.thread;
            const idx = arr.findIndex(m => m.__tempId === tempId);
            if (idx >= 0) {
                arr[idx] = { ...arr[idx], status: 'failed', failure_reason: e.message };
                renderActive();
            }
            toast('Send failed: ' + e.message, 'error');
        } finally {
            unlockSendButton();
        }
    }

    async function resolve() {
        if (!state.activeId) return;
        try {
            await api(`/team-inbox/api/conversations/${state.activeId}/resolve`, { method: 'POST' });
            toast(t('Resolved.'), 'success');
            await loadActive(state.activeId);
            await loadQueue(true);
        } catch (e) { toast(t('Resolve failed') + ': ' + e.message, 'error'); }
    }

    async function snooze() {
        if (!state.activeId) return;
        const until = await themedPrompt({
            title: t('Snooze conversation'),
            placeholder: t('ISO date, or "1h", "30m", "2d"'),
            defaultValue: '1h',
            confirmLabel: t('Snooze'),
        });
        if (!until) return;
        const target = parseSnoozeTarget(until);
        if (!target) return toast(t('Invalid snooze duration.'), 'error');
        try {
            await api(`/team-inbox/api/conversations/${state.activeId}/snooze`, {
                method: 'POST', body: { until: target },
            });
            toast(t('Snoozed.'), 'success');
            await loadActive(state.activeId);
            await loadQueue(true);
        } catch (e) { toast(t('Snooze failed') + ': ' + e.message, 'error'); }
    }

    async function assign(userId, teamId, strategy = 'manual') {
        if (!state.activeId) return;
        try {
            await api(`/team-inbox/api/conversations/${state.activeId}/assign`, {
                method: 'POST',
                body: { user_id: userId, team_id: teamId, strategy },
            });
            toast(t('Assigned.'), 'success');
            await loadActive(state.activeId);
            await loadQueue(true);
        } catch (e) { toast(t('Assign failed') + ': ' + e.message, 'error'); }
    }

    async function untag(convId, tagId) {
        try {
            await api(`/team-inbox/api/conversations/${convId}/tag/${tagId}`, { method: 'DELETE' });
            await loadActive(convId);
        } catch (e) { toast('Untag failed: ' + e.message, 'error'); }
    }

    async function addTag(name) {
        if (!state.activeId || !name) return;
        try {
            await api(`/team-inbox/api/conversations/${state.activeId}/tag`, {
                method: 'POST', body: { name },
            });
            await loadActive(state.activeId);
        } catch (e) { toast('Tag failed: ' + e.message, 'error'); }
    }

    async function addNote(body) {
        if (!state.activeId || !body) return;
        try {
            // Parse @mentions from the note body. Backend
            // (TeamInboxController::addNote) accepts a mentions array
            // shaped as [{user_id, name}, …] and fires a notifyMention()
            // per entry — recipient sees an in-app notification + the
            // conversation lights up in the `@me` queue tab.
            const mentions = parseMentions(body);
            await api(`/team-inbox/api/conversations/${state.activeId}/notes`, {
                method: 'POST', body: { body, mentions },
            });
            await loadActive(state.activeId);
            if (mentions.length) {
                const names = mentions.map(m => m.name).join(', ');
                toast(`Note saved — ${names} notified.`, 'success');
            }
        } catch (e) { toast('Note failed: ' + e.message, 'error'); }
    }

    /**
     * Extract @mentions from a note body and resolve them to workspace
     * members. Strategy:
     *
     *   1. Scan for `@<token>` where token is a sequence of word chars
     *      (letters, digits, dot, dash, underscore).
     *   2. Compare each token (lower-case) against every member's name
     *      and email. A member matches if their name starts with the
     *      token OR contains it as a word. Email match is exact local
     *      part (before the @).
     *   3. Dedupe + skip self-mentions (backend also blocks those).
     *
     * Returns an array of { user_id, name } objects ready for POST.
     */
    function parseMentions(body) {
        const members = state.members || [];
        const me = state.me?.id || 0;
        const seen = new Set();
        const out = [];
        const re = /@([A-Za-z0-9._-]{2,})/g;
        let match;
        while ((match = re.exec(body)) !== null) {
            const token = match[1].toLowerCase();
            // Find a member whose name or email-local-part matches the token.
            const hit = members.find(m => {
                if (!m || !m.id || m.id === me) return false;
                const name = String(m.name || '').toLowerCase();
                const email = String(m.email || '').toLowerCase();
                const emailLocal = email.split('@')[0];
                // Exact match on name (single-word) or first word
                if (name === token) return true;
                if (name.split(/\s+/)[0] === token) return true;
                // Email-local match
                if (emailLocal && emailLocal === token) return true;
                // Loose contains (substring) — last resort for partial names
                if (name.length > 0 && name.includes(token)) return true;
                return false;
            });
            if (hit && !seen.has(hit.id)) {
                seen.add(hit.id);
                out.push({ user_id: hit.id, name: hit.name });
            }
        }
        return out;
    }

    async function bulkAction(action, extra = {}) {
        if (state.selected.size === 0) return;
        try {
            await api('/team-inbox/api/bulk', {
                method: 'POST',
                body: { ids: [...state.selected], action, ...extra },
            });
            toast(`Bulk ${action} on ${state.selected.size} conversations.`, 'success');
            state.selected.clear();
            updateBulkBar();
            await loadQueue();
        } catch (e) { toast('Bulk failed: ' + e.message, 'error'); }
    }

    // ── WhatsApp-style list controls (pin / mute / archive / delete / label) ──
    // Single-conversation actions reuse the /bulk endpoint with a one-id array,
    // so there's exactly one authorized, audited code path for both.
    async function convAction(id, action, extra = {}) {
        try {
            await api('/team-inbox/api/bulk', { method: 'POST', body: { ids: [id], action, ...extra } });
            if (action === 'delete' && state.activeId === id) {
                state.activeId = null; state.active = null;
                $('#ti-frame')?.classList.remove('mobile-thread-open');
                renderEmptyState();
            }
            await loadQueue();
        } catch (e) { toast('Action failed: ' + e.message, 'error'); }
    }

    let __convMenuEl = null;
    function closeConvMenu() { if (__convMenuEl) { __convMenuEl.remove(); __convMenuEl = null; } }
    function openConvMenu(id, x, y) {
        closeConvMenu();
        const c = (state.queue || []).find(k => k.id === id);
        if (!c) return;
        const ic = (p) => `<svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.6">${p}</svg>`;
        const item = (action, label, icon, danger) =>
            `<button type="button" data-a="${action}" class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-[12.5px] ${danger ? 'text-accent-coral hover:bg-accent-coral/10' : 'text-ink-800 hover:bg-paper-50'}">${icon}<span>${label}</span></button>`;
        const pinIcon = '<path d="M9.5 2l4.5 4.5-2 .5-2.5 2.5-.5 3-1.5-1.5L3 14l3.5-4-1.5-1.5 3-.5L10.5 5 9.5 2z"/>';
        const rows = [
            c.pinned  ? item('unpin',  window.t ? window.t('Unpin') : 'Unpin', ic(pinIcon))
                      : item('pin',    window.t ? window.t('Pin') : 'Pin',   ic(pinIcon)),
            c.muted   ? item('unmute', window.t ? window.t('Unmute') : 'Unmute', ic('<path d="M4 6v4h2l3 3V3L6 6H4z"/>'))
                      : item('mute',   window.t ? window.t('Mute') : 'Mute',   ic('<path d="M4 6v4h2l3 3V3L6 6H4zM11 6l3 4M14 6l-3 4"/>')),
            c.archived ? item('unarchive', window.t ? window.t('Unarchive') : 'Unarchive', ic('<rect x="2" y="3" width="12" height="3" rx="1"/><path d="M3 6v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6"/>'))
                       : item('archive',   window.t ? window.t('Archive') : 'Archive',   ic('<rect x="2" y="3" width="12" height="3" rx="1"/><path d="M3 6v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6M6.5 9h3"/>')),
            item('label', window.t ? window.t('Label…') : 'Label…', ic('<path d="M3 3h6l5 5-6 6-5-5z"/><circle cx="6" cy="6" r="0.8"/>')),
            `<div class="h-px bg-paper-200 my-1"></div>`,
            item('delete', window.t ? window.t('Delete') : 'Delete', ic('<path d="M3 4h10M6 4V3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v1M5 4v9a1 1 0 0 0 1 1h4a1 1 0 0 0 1-1V4"/>'), true),
        ].join('');
        const menu = document.createElement('div');
        menu.className = 'fixed z-[70] w-44 bg-paper-0 border border-paper-200 rounded-xl shadow-[0_20px_60px_-25px_rgba(11,31,28,0.55)] py-1 overflow-hidden';
        menu.innerHTML = rows;
        document.body.appendChild(menu);
        const mw = 176, mh = menu.offsetHeight || 240;
        menu.style.left = Math.max(8, Math.min(x, window.innerWidth  - mw - 8)) + 'px';
        menu.style.top  = Math.max(8, Math.min(y, window.innerHeight - mh - 8)) + 'px';
        __convMenuEl = menu;
        menu.querySelectorAll('[data-a]').forEach(b => b.addEventListener('click', async () => {
            const a = b.dataset.a;
            closeConvMenu();
            if (a === 'delete') {
                const ok = await themedChoice({
                    title: 'Delete conversation?',
                    body: 'This permanently deletes the chat and its messages. It cannot be undone.',
                    options: [{ value: 'yes', label: 'Delete permanently', variant: 'danger' }],
                });
                if (ok === 'yes') convAction(id, 'delete');
                return;
            }
            if (a === 'label') {
                const name = await themedPrompt({ title: 'Add label', placeholder: 'e.g. priority · sales · billing', confirmLabel: 'Add label' });
                if (!name || !name.trim()) return;
                try {
                    const r = await api('/team-inbox/api/tags', { method: 'POST', body: { name: name.trim() } });
                    convAction(id, 'tag', { tag_id: r.tag.id });
                } catch (e) { toast('Label failed: ' + e.message, 'error'); }
                return;
            }
            convAction(id, a);
        }));
    }
    // Dismiss the menu on any outside interaction.
    ['click', 'scroll', 'resize'].forEach(ev => window.addEventListener(ev, closeConvMenu, true));
    window.addEventListener('keydown', e => { if (e.key === 'Escape') closeConvMenu(); });

    // Rebuild the label-filter dropdown from workspace tags (preserve selection).
    function syncLabelFilter() {
        const sel = $('#inbox-label-filter');
        const wrap = $('#inbox-label-filter-wrap');
        if (!sel || !wrap) return;
        const tags = state.tags || [];
        wrap.classList.toggle('hidden', tags.length === 0);
        wrap.classList.toggle('flex', tags.length > 0);
        const sig = tags.map(t => `${t.id}:${t.name}`).join('|');
        if (sig === state._labelSig) return;   // unchanged — keep DOM + selection
        state._labelSig = sig;
        const cur = state.labelFilter || '';
        sel.innerHTML = `<option value="">${window.t ? window.t('All labels') : 'All labels'}</option>` +
            tags.map(t => `<option value="${t.id}">${escape(t.name)}</option>`).join('');
        sel.value = cur;
    }

    // Show / flip the "Archived" row (hidden when there are none).
    function renderArchivedRow() {
        const row = $('#archived-row');
        if (!row) return;
        const active = state.archivedView;
        row.classList.toggle('hidden', !active && state.archivedCount === 0);
        row.classList.toggle('flex', active || state.archivedCount > 0);
        row.dataset.active = active ? '1' : '0';
        const label = $('#archived-row-label');
        const count = $('#archived-row-count');
        if (label) label.textContent = active ? (window.t ? window.t('Back to chats') : 'Back to chats') : (window.t ? window.t('Archived') : 'Archived');
        if (count) count.textContent = active ? '' : String(state.archivedCount);
        const icon = $('#archived-row-icon');
        if (icon) icon.innerHTML = active
            ? '<path d="M10 3L5 8l5 5"/>'
            : '<rect x="2" y="3" width="12" height="3" rx="1"/><path d="M3 6v6a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6M6.5 9h3"/>';
    }

    // ── Wiring (DOM events) ──────────────────────────────────────────────
    $('#search')?.addEventListener('input', debounce(e => {
        state.search = e.target.value;
        loadQueue();
    }, 250));

    $('#queue-tabs')?.querySelectorAll('[data-queue]').forEach(btn => {
        btn.addEventListener('click', () => {
            $$('#queue-tabs .ti-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            state.tab = btn.dataset.queue;
            loadQueue();
        });
    });

    // Device filter — only present in the DOM when the workspace has
    // more than one paired device. Persists the selection in the URL
    // so the operator can deep-link or refresh without losing context.
    const deviceFilterEl = $('#inbox-device-filter');
    if (deviceFilterEl) {
        // Hydrate from URL on load (e.g. operator pastes a link).
        try {
            const initial = new URLSearchParams(window.location.search).get('device_id');
            if (initial) {
                state.deviceFilter = initial;
                deviceFilterEl.value = initial;
            }
        } catch (_) { /* malformed URL — ignore */ }

        deviceFilterEl.addEventListener('change', (e) => {
            const v = e.target.value;
            state.deviceFilter = v || null;
            // Mirror the filter into the URL so back/forward + refresh
            // both keep the operator on the same device's queue.
            const url = new URL(window.location);
            if (state.deviceFilter) url.searchParams.set('device_id', state.deviceFilter);
            else                    url.searchParams.delete('device_id');
            window.history.replaceState({}, '', url);
            loadQueue();
        });
    }

    // ── Compose: new outbound message to many recipients at once ─────────
    // The "+" in the queue header opens a panel over the thread area. Pick
    // a channel to send FROM, then any mix of saved contacts, contact
    // groups, and manually-typed numbers, and one message goes to all.
    (() => {
        const panel = $('#compose-panel');
        if (!panel) return;
        const S = { loaded: false, contacts: [], groups: [], selC: new Set(), selG: new Set() };
        const chanSel  = $('#compose-channel');
        const listEl   = $('#compose-contacts');
        const groupsWrap = $('#compose-groups-wrap');
        const groupsEl = $('#compose-groups');
        const searchEl = $('#compose-contact-search');
        const numsEl   = $('#compose-numbers');
        const bodyEl   = $('#compose-body');
        const sendBtn  = $('#compose-send');
        const countEl  = $('#compose-reccount');
        const statusEl = $('#compose-status');

        const esc = (s) => (s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
        const digits = (s) => (s || '').replace(/\D+/g, '');

        function recipientPhones() {
            const set = new Set();
            S.contacts.forEach(c => {
                if (S.selC.has(c.id)) set.add(c.phone);
                else if (Array.isArray(c.groups) && c.groups.some(g => S.selG.has(g))) set.add(c.phone);
            });
            (numsEl.value || '').split(/[,;\r\n]+/).forEach(n => { const d = digits(n); if (d.length >= 8) set.add(d); });
            return set;
        }
        function updateCount() { countEl.textContent = recipientPhones().size + ' selected'; }

        function renderContacts() {
            const q = (searchEl.value || '').toLowerCase().trim();
            const rows = S.contacts
                .filter(c => !q || c.name.toLowerCase().includes(q) || c.phone.includes(q))
                .slice(0, 500);
            listEl.innerHTML = rows.length ? rows.map(c => `
                <label class="flex items-center gap-2 px-3 py-2 hover:bg-paper-50 cursor-pointer">
                    <input type="checkbox" data-cid="${c.id}" ${S.selC.has(c.id) ? 'checked' : ''} class="rounded border-paper-200 text-wa-deep focus:ring-wa-deep">
                    <span class="min-w-0 flex-1 truncate">${esc(c.name)}</span>
                    <span class="font-mono text-[10.5px] text-ink-500">${esc(c.phone)}</span>
                </label>`).join('') : `<div class="px-3 py-4 text-center text-ink-500 text-[12px]">No contacts</div>`;
        }
        function renderGroups() {
            if (!S.groups.length) { groupsWrap.classList.add('hidden'); return; }
            groupsWrap.classList.remove('hidden');
            groupsEl.innerHTML = S.groups.map(g => `
                <button type="button" data-gid="${g.id}"
                    class="px-2.5 py-1 rounded-full text-[11.5px] border ${S.selG.has(g.id) ? 'bg-wa-deep text-paper-0 border-wa-deep' : 'bg-paper-50 text-ink-700 border-paper-200 hover:bg-paper-100'}">${esc(g.name)}</button>`).join('');
        }

        async function load() {
            if (S.loaded) return;
            try {
                const d = await api('/team-inbox/api/compose/options');
                chanSel.innerHTML = '';
                (d.channels || []).forEach(c => {
                    const o = document.createElement('option');
                    o.value = c.value; o.textContent = `${c.engine} · ${c.label}`;
                    chanSel.appendChild(o);
                });
                if (!chanSel.options.length) {
                    const o = document.createElement('option'); o.value = ''; o.textContent = 'No connected channel'; chanSel.appendChild(o);
                }
                S.contacts = d.contacts || [];
                S.groups   = d.groups || [];
                S.loaded = true;
                renderContacts(); renderGroups(); updateCount();
            } catch (e) {
                statusEl.textContent = 'Could not load channels / contacts.';
            }
        }

        $('#compose-new-btn')?.addEventListener('click', () => { panel.classList.remove('hidden'); load(); });
        $('#compose-close')?.addEventListener('click', () => panel.classList.add('hidden'));
        searchEl?.addEventListener('input', debounce(renderContacts, 150));
        numsEl?.addEventListener('input', debounce(updateCount, 200));
        listEl?.addEventListener('change', (e) => {
            const cb = e.target.closest('input[data-cid]'); if (!cb) return;
            const id = parseInt(cb.dataset.cid, 10);
            cb.checked ? S.selC.add(id) : S.selC.delete(id);
            updateCount();
        });
        groupsEl?.addEventListener('click', (e) => {
            const b = e.target.closest('button[data-gid]'); if (!b) return;
            const id = parseInt(b.dataset.gid, 10);
            S.selG.has(id) ? S.selG.delete(id) : S.selG.add(id);
            renderGroups(); updateCount();
        });

        sendBtn?.addEventListener('click', async () => {
            const channel = chanSel.value;
            const body = (bodyEl.value || '').trim();
            const phones = recipientPhones();
            if (!channel) { window.toast?.('Pick a channel to send from', 'error'); return; }
            if (!phones.size) { window.toast?.('Add at least one recipient', 'error'); return; }
            if (!body) { window.toast?.('Type a message', 'error'); return; }
            sendBtn.disabled = true; statusEl.textContent = 'Sending…';
            try {
                const res = await api('/team-inbox/api/compose', {
                    method: 'POST',
                    body: {
                        channel,
                        body,
                        contact_ids: [...S.selC],
                        group_ids: [...S.selG],
                        numbers: numsEl.value || '',
                    },
                });
                if (res.ok) {
                    window.toast?.(`Sent to ${res.sent} recipient${res.sent === 1 ? '' : 's'}` + (res.failed ? `, ${res.failed} failed` : ''), 'success');
                    bodyEl.value = ''; numsEl.value = ''; S.selC.clear(); S.selG.clear();
                    renderContacts(); renderGroups(); updateCount();
                    panel.classList.add('hidden');
                    loadQueue();
                } else {
                    window.toast?.(res.error || `Failed (${res.failed || 0} failed)`, 'error');
                }
            } catch (e) {
                window.toast?.(e?.message || 'Send failed', 'error');
            } finally {
                sendBtn.disabled = false; statusEl.textContent = '';
            }
        });
    })();

    // #43 — When the operator focuses the composer (typically by tapping
    // it on mobile, which raises the keyboard), scroll the thread to its
    // latest message. Without this the keyboard hides whatever's at the
    // bottom and the operator has to manually scroll.
    $('#composer')?.addEventListener('focus', () => {
        const t = $('#thread');
        if (!t) return;
        // Use rAF so we run AFTER the browser has reflowed for the
        // virtual keyboard rising. setTimeout 0 would be unreliable on iOS.
        requestAnimationFrame(() => {
            t.scrollTop = t.scrollHeight;
        });
    });

    $('#composer-tabs')?.querySelectorAll('[data-mode]').forEach(btn => {
        btn.addEventListener('click', () => {
            $$('#composer-tabs .ti-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            state.composerMode = btn.dataset.mode;
            const hint = $('#composer-hint');
            if (hint) hint.classList.toggle('hidden', state.composerMode !== 'note');
            const ph = state.composerMode === 'note' ? 'Add an internal note (only your team can see this)…'
                     : state.composerMode === 'template' ? 'Pick a template above or type a reply…'
                     : 'Type a reply…';
            $('#composer') && ($('#composer').placeholder = ph);
            // Show/hide the template grid panel above the textarea.
            // Hidden for reply + note, visible for template mode so the
            // operator can pick from approved templates.
            $('#template-panel')?.classList.toggle('hidden', state.composerMode !== 'template');
            // Leaving Template mode discards any selected-template
            // preview so the freeform textarea is visible again.
            if (state.composerMode !== 'template' && state.selectedTemplate) {
                clearTemplateSelection();
            }
        });
    });

    // Template card → render the full template preview (header / body
    // / footer / buttons) above the composer area and hide the freeform
    // textarea. The Send button then posts `template_id` so the server
    // rebuilds the WhatsApp interactive message from WaTemplate.
    function selectTemplate(card) {
        let buttons = [];
        try { buttons = JSON.parse(card.dataset.templateButtons || '[]'); } catch (_) {}
        state.selectedTemplate = {
            id:     parseInt(card.dataset.templateId, 10),
            title:  card.dataset.templateTitle || '',
            header: card.dataset.templateHeader || '',
            body:   card.dataset.templateBody || '',
            footer: card.dataset.templateFooter || '',
            buttons,
        };

        // Populate the preview bubble.
        const tp = state.selectedTemplate;
        $('#tp-name') && ($('#tp-name').textContent = tp.title || 'Untitled');
        const headerEl = $('#tp-header');
        if (headerEl) {
            headerEl.textContent = tp.header;
            headerEl.classList.toggle('hidden', !tp.header);
        }
        const bodyEl = $('#tp-body');
        if (bodyEl) bodyEl.textContent = tp.body || '';
        const footerEl = $('#tp-footer');
        if (footerEl) {
            footerEl.textContent = tp.footer;
            footerEl.classList.toggle('hidden', !tp.footer);
        }
        const btnsEl = $('#tp-buttons');
        if (btnsEl) {
            if (tp.buttons.length === 0) {
                btnsEl.classList.add('hidden');
                btnsEl.innerHTML = '';
            } else {
                btnsEl.classList.remove('hidden');
                btnsEl.innerHTML = tp.buttons.map(b => `
                    <div class="bg-white/10 border-t border-white/20 px-2 py-1.5 rounded text-center text-[12px] font-medium">
                        ${escape(b.text || b.label || 'Button')}
                    </div>`).join('');
            }
        }

        // Swap the UI: hide picker grid + textarea, show preview.
        $('#template-cards')?.parentElement?.classList.add('hidden');
        $('#composer-textarea-wrap')?.classList.add('hidden');
        $('#template-preview')?.classList.remove('hidden');
        toast(`Template "${tp.title}" selected`, 'success');
    }

    $$('.template-card').forEach(card => {
        card.addEventListener('click', () => selectTemplate(card));
    });

    // Clear selection — flip back to the picker grid so the operator
    // can pick a different template (or switch to Reply / Note).
    function clearTemplateSelection() {
        state.selectedTemplate = null;
        $('#template-preview')?.classList.add('hidden');
        $('#composer-textarea-wrap')?.classList.remove('hidden');
        $('#template-cards')?.parentElement?.classList.remove('hidden');
    }
    $('#tp-clear')?.addEventListener('click', clearTemplateSelection);

    // Template search — narrow cards by title or body.
    $('#template-search')?.addEventListener('input', (e) => {
        const q = (e.target.value || '').toLowerCase().trim();
        $$('.template-card').forEach(card => {
            const t = (card.dataset.templateTitle || '').toLowerCase();
            const b = (card.dataset.templateBody || '').toLowerCase();
            card.classList.toggle('hidden', q !== '' && !t.includes(q) && !b.includes(q));
        });
    });

    // Mirror the rich compose-textarea component into the hidden
    // #composer textarea that the send code reads. Done both ways so
    // we can still set #composer.value = '' after send and the rich
    // box clears too.
    const richInput = document.querySelector('#composer-rich');
    const hiddenInput = document.querySelector('#composer');
    if (richInput && hiddenInput) {
        const syncRichToHidden = () => { hiddenInput.value = richInput.value; hiddenInput.dispatchEvent(new Event('input', { bubbles: true })); };
        richInput.addEventListener('input', syncRichToHidden);
        // After send code does hiddenInput.value = '', mirror that back.
        const obs = new MutationObserver(() => { if (hiddenInput.value !== richInput.value) richInput.value = hiddenInput.value; });
        // Watch programmatic value changes via assignment by overriding
        // the property descriptor on this instance.
        let _val = hiddenInput.value;
        Object.defineProperty(hiddenInput, 'value', {
            get() { return _val; },
            set(v) { _val = v; if (richInput.value !== v) richInput.value = v; hiddenInput.dispatchEvent(new Event('input', { bubbles: true })); },
            configurable: true,
        });
        richInput.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                e.preventDefault();
                $('#send-btn')?.click();
            }
        });
    }

    $('#composer')?.addEventListener('input', e => {
        const n = e.target.value.length;
        $('#char-count') && ($('#char-count').textContent = `${n} chars`);
    });

    $('#composer')?.addEventListener('keydown', e => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') { e.preventDefault(); reply(); }
    });

    $('#send-btn')?.addEventListener('click', () => reply());
    $('#resolve-btn')?.addEventListener('click', () => resolve());
    $('#snooze-btn')?.addEventListener('click', () => snooze());

    // "Create deal" — opens a proper mini-form (name + value + stage) so the
    // deal is actionable the moment it's created, prefilled with the contact
    // and linked to this conversation. Stages load once from /deals/stages.
    // Render the contact's Sales Pipeline deals into the contact panel. The
    // section stays hidden unless the plan has the feature (server says so via
    // `enabled`), so non-CRM workspaces never see an empty box.
    async function renderConversationDeals(convId) {
        const section = $('#ct-deals-section');
        const list = $('#ct-deals');
        const countEl = $('#ct-deals-count');
        if (!section || !list) return;
        section.classList.add('hidden');
        list.innerHTML = '';
        if (!convId) return;
        let res;
        try { res = await api(`/team-inbox/api/conversations/${convId}/deals`); }
        catch (_) { return; }
        if (!res || !res.enabled) return; // plan lacks the feature
        section.classList.remove('hidden');
        const deals = Array.isArray(res.deals) ? res.deals : [];
        if (countEl) countEl.textContent = String(deals.length);
        if (deals.length === 0) {
            list.innerHTML = `<div class="text-[11px] text-ink-500">No deals yet. <button id="ct-deals-empty-new" class="text-wa-deep font-semibold hover:underline">Create one</button></div>`;
            list.querySelector('#ct-deals-empty-new')?.addEventListener('click', () => $('#create-deal-btn')?.click());
            return;
        }
        const pill = (s) => s === 'won'
            ? '<span class="px-1.5 py-0.5 rounded-full text-[9px] font-mono bg-accent-mint/20 text-accent-mint">WON</span>'
            : s === 'lost'
                ? '<span class="px-1.5 py-0.5 rounded-full text-[9px] font-mono bg-accent-coral/20 text-accent-coral">LOST</span>'
                : '<span class="px-1.5 py-0.5 rounded-full text-[9px] font-mono bg-wa-deep/10 text-wa-deep">OPEN</span>';
        list.innerHTML = deals.map(d => `
            <a href="${escape(d.url)}" class="block px-2.5 py-2 rounded-xl border border-paper-200 bg-paper-0 hover:border-wa-deep transition">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[12px] font-medium text-ink-900 truncate">${escape(d.title)}</span>
                    ${pill(d.status)}
                </div>
                <div class="flex items-center justify-between mt-0.5">
                    <span class="text-[11px] text-ink-500">${escape(d.stage_name || '')}</span>
                    <span class="font-mono text-[11px] text-ink-700">${escape(d.value_display || '')}</span>
                </div>
            </a>`).join('');
    }
    $('#ct-deal-new')?.addEventListener('click', () => $('#create-deal-btn')?.click());

    let DEAL_STAGES = null;
    async function loadDealStages() {
        if (DEAL_STAGES) return DEAL_STAGES;
        try {
            const r = await api('/deals/stages');
            DEAL_STAGES = Array.isArray(r.data) ? r.data : [];
        } catch (_) { DEAL_STAGES = []; }
        return DEAL_STAGES;
    }

    function createDealModal({ defaultTitle, stages }) {
        return new Promise((resolve) => {
            const wrap = document.createElement('div');
            wrap.className = 'fixed inset-0 z-[60] flex items-center justify-center p-5 bg-[rgba(11,31,28,0.46)]';
            const stageOpts = (stages || []).map(s => `<option value="${s.id}">${escape(s.name)}</option>`).join('');
            wrap.innerHTML = `
                <div class="w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
                    <div class="px-5 py-4 border-b border-paper-200">
                        <h3 class="font-serif text-[18px] leading-tight text-ink-900">Create deal</h3>
                        <p class="text-[12px] text-ink-500 mt-0.5">A sales opportunity from this chat — linked to the contact and conversation.</p>
                    </div>
                    <div class="p-5 space-y-3">
                        <label class="block">
                            <span class="text-[11px] font-semibold text-ink-500">Deal name</span>
                            <input data-d-title type="text" value="${escape(defaultTitle)}" class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="text-[11px] font-semibold text-ink-500">Value</span>
                                <input data-d-value type="number" min="0" step="0.01" placeholder="0" class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10">
                            </label>
                            <label class="block">
                                <span class="text-[11px] font-semibold text-ink-500">Stage</span>
                                <select data-d-stage class="mt-1 w-full px-3 py-2 rounded-xl border border-paper-200 bg-white text-[13px] focus:outline-none focus:border-wa-deep">${stageOpts}</select>
                            </label>
                        </div>
                    </div>
                    <div class="px-5 pb-5 flex items-center justify-end gap-2">
                        <button type="button" data-d-cancel class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep">Cancel</button>
                        <button type="button" data-d-ok class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal">Create deal</button>
                    </div>
                </div>`;
            document.body.appendChild(wrap);
            const close = (val) => { wrap.remove(); resolve(val); };
            const titleEl = wrap.querySelector('[data-d-title]');
            wrap.querySelector('[data-d-cancel]').addEventListener('click', () => close(null));
            wrap.addEventListener('click', (e) => { if (e.target === wrap) close(null); });
            wrap.querySelector('[data-d-ok]').addEventListener('click', () => close({
                title: wrap.querySelector('[data-d-title]').value.trim(),
                value: wrap.querySelector('[data-d-value]').value,
                stage_id: wrap.querySelector('[data-d-stage]').value || null,
            }));
            titleEl.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(null); });
            setTimeout(() => { titleEl.focus(); titleEl.select?.(); }, 30);
        });
    }

    $('#create-deal-btn')?.addEventListener('click', async () => {
        if (!state.activeId) return;
        const btn = $('#create-deal-btn');
        const defaultTitle = state.active?.contact_profile?.name || state.active?.name || 'Deal';
        const stages = await loadDealStages();
        const form = await createDealModal({ defaultTitle, stages });
        if (!form) return; // cancelled
        btn.disabled = true;
        try {
            const res = await api(`/team-inbox/api/conversations/${state.activeId}/create-deal`, {
                method: 'POST',
                body: {
                    title: form.title || defaultTitle,
                    value: form.value || null,
                    stage_id: form.stage_id,
                },
            });
            toast(res.message || 'Deal created.', 'info');
            renderConversationDeals(state.activeId); // reflect the new deal in the panel
        } catch (e) {
            toast('Could not create deal: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    // WABA call button — opens the dial flow.
    // Visibility is set in openConversation() based on c.wa_calling_enabled.
    //
    // Two paths the click handler picks between based on the
    // conversation's wa_calling_permission flag (from serializeFull):
    //   - 'granted' → dial immediately via WaCallPeer
    //   - anything else (null / expired / declined) → ask the operator
    //     if they want to send a permission_request first; on confirm,
    //     POST /permission-request and surface a friendly message
    $('#wa-call-btn')?.addEventListener('click', async () => {
        const c = state.active;
        if (!c) return;
        // Server-side gate (c.wa_calling_enabled) is authoritative — it already
        // encodes "this is a WABA chat + calling is enabled". We do NOT re-check
        // c.device_id here: since the device-unification fix a WABA thread carries
        // the wa_provider_configs id in device_id (not null), so that guard wrongly
        // rejected EVERY WABA chat as "Baileys".
        if (!c.wa_calling_enabled) {
            window.toast?.('Calling is not enabled on this conversation.', 'error');
            return;
        }
        const to = (c.contact_phone || '').replace(/\D+/g, '');
        if (!to) {
            window.toast?.('No phone number on this contact.', 'error');
            return;
        }
        try {
            // Pick the workspace's first calling-enabled WABA config.
            // Most workspaces have one — when multi-WABA lands later,
            // we'll prompt the operator to choose.
            const r = await fetch('/wa-calling/status', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then(x => x.json());
            const cfg = (r.rows || []).find((row) => row.calling_enabled);
            if (!cfg) {
                window.toast?.('Calling is not enabled on any WABA number.', 'error');
                return;
            }

            // Permission gate. Server enforces this too, but a clear
            // UX here saves a wasted Meta round-trip and an operator
            // staring at a generic error.
            // Our local permission cache can be stale (a permission_update webhook
            // may have been missed), so DON'T hard-block. Let the operator choose:
            //   OK     → call now anyway (Meta connects if the customer already
            //            tapped "Allow"; if not, it declines with a clear reason)
            //   Cancel → send a fresh permission request instead
            if (c.wa_calling_permission !== 'granted') {
                const callNow = window.confirm(
                    'We don\'t have a confirmed calling permission on file for this contact.\n\n' +
                    'OK      = Call now anyway (works if they already tapped "Allow" in WhatsApp).\n' +
                    'Cancel  = Send them a permission request instead.'
                );
                if (!callNow) {
                    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                    const res = await fetch('/wa-calling/permission-request', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ config_id: cfg.id, to }),
                    }).then(x => x.json());
                    if (res.ok) {
                        window.toast?.('Permission request sent. They will see Accept/Decline in WhatsApp — try Call again after they respond.', 'success');
                    } else {
                        window.toast?.('Could not send permission request: ' + (res.error || 'unknown'), 'error');
                    }
                    return;
                }
                // OK → fall through and place the call (server + Meta enforce permission).
            }

            // Permission good → place the call via the bridge so the
            // peer + panel + hangup button are all the SAME instance the
            // inbound-accept flow uses. Prevents a stranded peer when
            // the operator hangs up an outbound call.
            const { startOutboundCall } = await import('../calling/incoming-call-bridge.js');
            await startOutboundCall({
                configId:       cfg.id,
                to,
                contactId:      c.contact_id,
                conversationId: c.id,
                displayName:    c.title || ('+' + to),
            });
        } catch (e) {
            const msg = (e?.message || '').toLowerCase().includes('permission')
                ? 'No active calling permission. Click Call again to send a permission request.'
                : ('Could not start call: ' + (e?.message || 'unknown'));
            window.toast?.(msg, 'error');
        }
    });

    // ── Voice notes ─────────────────────────────────────────────────────
    // MediaRecorder pipeline: tap 🎤 → request mic → record opus/webm →
    // tap stop → preview → send. Uploads as multipart FormData to the
    // voice endpoint, which dispatches via WhatsAppDispatcher with the
    // ptt=true flag so Baileys / WABA render the round play-button bubble.
    const voiceState = {
        recorder: null,
        chunks:   [],
        stream:   null,
        startedAt: 0,
        timerId:  null,
        blob:     null,
        mime:     '',
    };

    function pickMimeType() {
        // Preference order — opus is preferred by WhatsApp, webm container
        // is widely supported on Chromium-based browsers, fall back to mp4
        // for Safari, then default.
        const candidates = [
            'audio/webm;codecs=opus',
            'audio/ogg;codecs=opus',
            'audio/webm',
            'audio/mp4',
        ];
        for (const t of candidates) {
            if (window.MediaRecorder?.isTypeSupported?.(t)) return t;
        }
        return '';
    }

    function fmtMs(ms) {
        const total = Math.max(0, Math.floor(ms / 1000));
        const m = Math.floor(total / 60);
        const s = String(total % 60).padStart(2, '0');
        return `${m}:${s}`;
    }

    function showRecorderBar() {
        $('#composer-actions')?.classList.add('hidden');
        $('#voice-preview-bar')?.classList.add('hidden');
        $('#voice-recorder-bar')?.classList.remove('hidden');
    }
    function showPreviewBar() {
        $('#voice-recorder-bar')?.classList.add('hidden');
        $('#composer-actions')?.classList.add('hidden');
        $('#voice-preview-bar')?.classList.remove('hidden');
    }
    function resetVoiceBars() {
        $('#voice-recorder-bar')?.classList.add('hidden');
        $('#voice-preview-bar')?.classList.add('hidden');
        $('#composer-actions')?.classList.remove('hidden');
    }

    async function startRecording() {
        if (!state.activeId) { toast(t('Open a conversation first.'), 'error'); return; }
        if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) {
            toast(t('Your browser does not support voice recording.'), 'error');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const mime = pickMimeType();
            const rec  = new MediaRecorder(stream, mime ? { mimeType: mime } : undefined);
            voiceState.stream = stream;
            voiceState.recorder = rec;
            voiceState.chunks = [];
            voiceState.mime = mime || rec.mimeType || 'audio/webm';
            voiceState.startedAt = Date.now();
            voiceState.blob = null;
            rec.ondataavailable = (e) => { if (e.data && e.data.size > 0) voiceState.chunks.push(e.data); };
            rec.onstop = () => {
                voiceState.blob = new Blob(voiceState.chunks, { type: voiceState.mime });
                stopMicTracks();
                renderPreview();
            };
            rec.start();
            showRecorderBar();
            $('#voice-timer') && ($('#voice-timer').textContent = '0:00');
            voiceState.timerId = setInterval(() => {
                $('#voice-timer') && ($('#voice-timer').textContent = fmtMs(Date.now() - voiceState.startedAt));
            }, 250);
        } catch (e) {
            toast('Microphone access denied: ' + (e?.message || 'unknown'), 'error');
        }
    }

    function stopRecording(send) {
        if (voiceState.timerId) { clearInterval(voiceState.timerId); voiceState.timerId = null; }
        const rec = voiceState.recorder;
        if (rec && rec.state !== 'inactive') {
            try { rec.stop(); } catch (_) {}
        }
        if (!send) {
            // cancel: drop everything, no preview
            voiceState.chunks = [];
            voiceState.blob   = null;
            stopMicTracks();
            resetVoiceBars();
        }
    }

    function stopMicTracks() {
        const s = voiceState.stream;
        if (s) { s.getTracks().forEach(t => t.stop()); }
        voiceState.stream = null;
    }

    function renderPreview() {
        const audio = $('#voice-preview-audio');
        if (audio && voiceState.blob) {
            audio.src = URL.createObjectURL(voiceState.blob);
        }
        $('#voice-preview-duration') && ($('#voice-preview-duration').textContent = fmtMs(Date.now() - voiceState.startedAt));
        showPreviewBar();
    }

    async function sendVoice() {
        if (!state.activeId || !voiceState.blob) return;
        const sendBtn = $('#voice-send-btn');
        if (sendBtn) { sendBtn.disabled = true; sendBtn.textContent = 'Sending…'; }
        const ext = voiceState.mime.includes('ogg') ? 'ogg'
                  : voiceState.mime.includes('mp4') ? 'm4a'
                  : 'webm';
        const fd = new FormData();
        fd.append('audio', voiceState.blob, `voice.${ext}`);
        fd.append('duration', Math.floor((Date.now() - voiceState.startedAt) / 1000));
        try {
            const res = await fetch(`/team-inbox/api/conversations/${state.activeId}/voice`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: fd,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data?.message || data?.error || `HTTP ${res.status}`);
            toast('Voice note sent.', 'success');
            await loadActive(state.activeId);
            await loadQueue(true);
        } catch (e) {
            toast('Voice send failed: ' + e.message, 'error');
        } finally {
            voiceState.blob = null;
            voiceState.chunks = [];
            resetVoiceBars();
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="currentColor"><path d="M2 14l13-6L2 2v5l9 1-9 1z"/></svg> Send voice';
            }
        }
    }

    $('#voice-record-btn')?.addEventListener('click', startRecording);
    $('#voice-stop-btn')?.addEventListener('click', () => stopRecording(true));
    $('#voice-cancel-btn')?.addEventListener('click', () => stopRecording(false));
    $('#voice-discard-btn')?.addEventListener('click', () => {
        voiceState.blob = null;
        voiceState.chunks = [];
        resetVoiceBars();
    });
    $('#voice-send-btn')?.addEventListener('click', sendVoice);

    // ── Media attach (multi-image / multi-file) ─────────────────────────
    // Operator clicks 📎 → picks files (multi) → previews appear → Send
    // All fires one POST per file so each lands as its own WhatsApp
    // message. We dispatch sequentially (not in parallel) to keep the
    // sent order deterministic from the recipient's perspective.
    const mediaQueue = []; // [{file, url}]

    function renderMediaPreviews() {
        const grid = $('#media-preview-grid');
        const cnt  = $('#media-preview-count');
        const bar  = $('#media-preview-bar');
        if (!grid || !bar) return;
        if (mediaQueue.length === 0) {
            bar.classList.add('hidden');
            grid.innerHTML = '';
            return;
        }
        bar.classList.remove('hidden');
        if (cnt) cnt.textContent = mediaQueue.length;
        grid.innerHTML = mediaQueue.map((m, i) => {
            const isImage = m.file.type.startsWith('image/');
            const thumb = isImage
                ? `<img src="${m.url}" alt="${escape(m.file.name)}" class="w-full h-full object-cover">`
                : `<div class="w-full h-full grid place-items-center bg-paper-100 text-ink-500 text-[9.5px] font-mono">${escape(m.file.name.split('.').pop().toUpperCase())}</div>`;
            return `<div class="relative w-16 h-16 rounded-md border border-paper-200 overflow-hidden">
                ${thumb}
                <button type="button" data-media-rm="${i}" class="absolute top-0 right-0 w-5 h-5 rounded-bl-md bg-ink-900/70 hover:bg-accent-coral text-paper-0 text-[10px] grid place-items-center" title="Remove">×</button>
            </div>`;
        }).join('');
        grid.querySelectorAll('[data-media-rm]').forEach(b => {
            b.addEventListener('click', () => {
                const idx = parseInt(b.dataset.mediaRm, 10);
                if (mediaQueue[idx]) URL.revokeObjectURL(mediaQueue[idx].url);
                mediaQueue.splice(idx, 1);
                renderMediaPreviews();
            });
        });
    }

    $('#media-attach-btn')?.addEventListener('click', () => {
        if (!state.activeId) { toast('Open a conversation first.', 'error'); return; }
        $('#media-file-input')?.click();
    });

    $('#media-file-input')?.addEventListener('change', (e) => {
        const files = Array.from(e.target.files || []);
        files.forEach(f => mediaQueue.push({ file: f, url: URL.createObjectURL(f) }));
        e.target.value = ''; // allow re-pick of same files
        renderMediaPreviews();
    });

    $('#media-clear-btn')?.addEventListener('click', () => {
        mediaQueue.forEach(m => URL.revokeObjectURL(m.url));
        mediaQueue.length = 0;
        renderMediaPreviews();
    });

    $('#media-send-btn')?.addEventListener('click', async () => {
        if (!state.activeId || mediaQueue.length === 0) return;
        const caption = $('#composer')?.value?.trim() || '';
        const sendBtn = $('#media-send-btn');
        if (sendBtn) { sendBtn.disabled = true; sendBtn.textContent = 'Sending…'; }

        // Dispatch one-by-one so the recipient sees them in order.
        let ok = 0, fail = 0;
        for (let i = 0; i < mediaQueue.length; i++) {
            const m = mediaQueue[i];
            const fd = new FormData();
            fd.append('file', m.file, m.file.name);
            // Caption attaches to the FIRST file only so the operator
            // doesn't end up with the same caption repeated on every image.
            if (i === 0 && caption) fd.append('caption', caption);
            try {
                const res = await fetch(`/team-inbox/api/conversations/${state.activeId}/media`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data?.message || data?.error || `HTTP ${res.status}`);
                ok++;
            } catch (e) {
                fail++;
                console.warn('media send failed', e);
            }
        }
        // Cleanup
        mediaQueue.forEach(m => URL.revokeObjectURL(m.url));
        mediaQueue.length = 0;
        renderMediaPreviews();
        if (caption) { $('#composer') && ($('#composer').value = ''); }
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send all';
        }
        toast(fail === 0 ? `Sent ${ok} attachment${ok === 1 ? '' : 's'}.` : `Sent ${ok}, failed ${fail}.`, fail === 0 ? 'success' : 'error');
        await loadActive(state.activeId);
        await loadQueue(true);
    });

    // ── Lazy-load older messages on scroll-to-top ───────────────────────
    // When the operator scrolls to the top of the thread (within 60px),
    // fetch the next 80 older messages. Throttled by the in-flight flag
    // inside loadOlderMessages() so rapid scrolling doesn't spam the API.
    $('#thread')?.addEventListener('scroll', (e) => {
        const el = e.currentTarget;
        if (!el) return;
        if (el.scrollTop <= 60 && state.threadHasMore && !state.threadLoadingOlder) {
            loadOlderMessages();
        }
    });

    // ── Image lightbox ──────────────────────────────────────────────────
    // Click any thread image to open it full-screen with prev/next
    // navigation across all images in the active thread. Esc closes.
    const lightbox = {
        urls: [],
        idx: 0,
        el: null,

        open(url) {
            // Collect all image URLs in the current thread so the
            // prev/next arrows navigate through the conversation gallery
            // in order.
            this.urls = (state.thread || [])
                .filter(m => {
                    if (!m.media_url) return false;
                    if (m.media_type === 'image') return true;
                    // Also include images that arrived as document attachments.
                    if (m.media_type === 'document' || !m.media_type) {
                        const ex = String(m.media_url || m.media_name || '').toLowerCase().match(/\.([a-z0-9]+)(?:\?|#|$)/);
                        return ex && ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'heic'].includes(ex[1]);
                    }
                    return false;
                })
                .map(m => ({ url: m.media_url, name: m.media_name || 'image' }));
            this.idx = this.urls.findIndex(u => u.url === url);
            if (this.idx < 0) {
                this.urls = [{ url, name: 'image' }];
                this.idx = 0;
            }
            this.render();
            document.addEventListener('keydown', this.onKey);
        },

        close() {
            this.el?.remove();
            this.el = null;
            document.removeEventListener('keydown', this.onKey);
        },

        prev() { if (this.urls.length > 1) { this.idx = (this.idx - 1 + this.urls.length) % this.urls.length; this.render(); } },
        next() { if (this.urls.length > 1) { this.idx = (this.idx + 1) % this.urls.length; this.render(); } },

        onKey: (e) => {
            if (e.key === 'Escape') lightbox.close();
            else if (e.key === 'ArrowLeft') lightbox.prev();
            else if (e.key === 'ArrowRight') lightbox.next();
        },

        render() {
            if (!this.el) {
                this.el = document.createElement('div');
                this.el.className = 'fixed inset-0 z-[80] bg-black/90 flex items-center justify-center';
                document.body.appendChild(this.el);
                this.el.addEventListener('click', (e) => {
                    if (e.target === this.el) this.close();
                });
            }
            const cur = this.urls[this.idx];
            if (!cur) return this.close();
            const hasMulti = this.urls.length > 1;
            this.el.innerHTML = `
                <button type="button" data-lb-close class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center" title="Close (Esc)">
                    <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                </button>
                <a href="${cur.url}" download="${escape(cur.name)}" class="absolute top-4 right-16 px-3 py-2 rounded-full bg-white/10 hover:bg-white/20 text-white text-[12px] font-mono inline-flex items-center gap-1.5">
                    <svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v9M4 7l4 4 4-4M3 14h10"/></svg>
                    Download
                </a>
                ${hasMulti ? `<button type="button" data-lb-prev class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center" title="Previous (←)">
                    <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 3l-5 5 5 5"/></svg>
                </button>` : ''}
                ${hasMulti ? `<button type="button" data-lb-next class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white grid place-items-center" title="Next (→)">
                    <svg viewBox="0 0 16 16" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 3l5 5-5 5"/></svg>
                </button>` : ''}
                <img src="${cur.url}" alt="${escape(cur.name)}" class="max-w-[92vw] max-h-[88vh] object-contain rounded-md shadow-2xl">
                ${hasMulti ? `<div class="absolute bottom-4 left-1/2 -translate-x-1/2 px-3 py-1 rounded-full bg-white/10 text-white text-[11px] font-mono">${this.idx + 1} / ${this.urls.length}</div>` : ''}
            `;
            this.el.querySelector('[data-lb-close]')?.addEventListener('click', () => this.close());
            this.el.querySelector('[data-lb-prev]')?.addEventListener('click', (e) => { e.stopPropagation(); this.prev(); });
            this.el.querySelector('[data-lb-next]')?.addEventListener('click', (e) => { e.stopPropagation(); this.next(); });
        },
    };

    // Event delegation — works for newly-rendered thread items too.
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-lightbox]');
        if (!btn) return;
        e.preventDefault();
        lightbox.open(btn.dataset.lightbox);
    });

    // #21 — "+ Save" on an inbound contact-card bubble. Calls the
    // extract-contact endpoint and shows a toast with the result.
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-add-contact]');
        if (!btn) return;
        e.preventDefault();
        const msgId = parseInt(btn.dataset.addContact, 10);
        if (!msgId) return;
        btn.disabled = true; const orig = btn.textContent; btn.textContent = 'Saving…';
        try {
            const res = await api(`/team-inbox/api/messages/${msgId}/extract-contact`, { method: 'POST' });
            if (res?.ok) {
                btn.textContent = res.created ? '✓ Added' : '✓ Updated';
                toast(res.created ? 'Contact added.' : 'Contact already existed — name refreshed.', 'ok');
            } else {
                btn.textContent = orig; btn.disabled = false;
                toast(res?.message || 'Failed to add contact.', 'error');
            }
        } catch (err) {
            btn.textContent = orig; btn.disabled = false;
            toast('Failed: ' + err.message, 'error');
        }
    });

    // ── Per-message hover-menu (Info / Pin / Star / React / Forward / Delete) ──
    const REACT_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
    let msgMenuEl = null;

    function closeMsgMenu() {
        msgMenuEl?.remove();
        msgMenuEl = null;
    }

    function openMessageMenu(msgId, anchorBtn) {
        closeMsgMenu();
        const msg = (state.thread || []).find(m => m.id === msgId);
        if (!msg) return;
        const reactRow = REACT_EMOJIS.map(em =>
            `<button type="button" data-act-react="${em}" class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-[18px] leading-none" title="React ${em}">${em}</button>`
        ).join('');
        // "+" opens a full emoji picker so the operator isn't limited
        // to the six preset reactions. Lazy-loads emoji-picker-element
        // the same way wa-editor.js does — bundle never lands unless
        // the operator actually wants a non-preset emoji.
        const morePicker = `<button type="button" data-act-react-more class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-700 text-[15px] font-semibold" title="More emojis…">+</button>`;
        const clearReact = msg.reaction
            ? `<button type="button" data-act-react="" class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-500" title="Clear reaction">✕</button>`
            : '';

        msgMenuEl = document.createElement('div');
        msgMenuEl.className = 'fixed z-[60] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1.5 w-[230px]';
        msgMenuEl.innerHTML = `
            <div class="flex items-center gap-0.5 px-2 pb-1 border-b border-paper-200 relative">${reactRow}${morePicker}${clearReact}
                <div data-extra-picker class="hidden absolute right-2 top-full mt-1 z-[70] rounded-2xl border border-paper-200 bg-paper-0 shadow-soft overflow-hidden w-[340px]"></div>
            </div>
            <button type="button" data-act="info"    class="w-full text-left px-3 py-1.5 hover:bg-paper-50 text-[12.5px] flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="8" cy="8" r="6"/><path d="M8 7v4M8 5h.01"/></svg>
                Message info
            </button>
            <button type="button" data-act="pin"     class="w-full text-left px-3 py-1.5 hover:bg-paper-50 text-[12.5px] flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10 2v5l3 3H3l3-3V2"/><path d="M8 10v4"/></svg>
                ${msg.pinned ? 'Unpin' : 'Pin'}
            </button>
            <button type="button" data-act="star"    class="w-full text-left px-3 py-1.5 hover:bg-paper-50 text-[12.5px] flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2l1.8 4.2L14 7l-3.2 3 .8 4.5L8 12.3 4.4 14.5l.8-4.5L2 7l4.2-.8z"/></svg>
                ${msg.starred ? 'Unstar' : 'Star'}
            </button>
            <button type="button" data-act="forward" class="w-full text-left px-3 py-1.5 hover:bg-paper-50 text-[12.5px] flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 4l4 4-4 4M3 8h10"/></svg>
                Forward
            </button>
            ${msg.editable ? `
            <button type="button" data-act="edit" class="w-full text-left px-3 py-1.5 hover:bg-paper-50 text-[12.5px] flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M11.5 1.5l3 3L5 14H2v-3zM10 3l3 3"/></svg>
                Edit
            </button>` : ''}
            <button type="button" data-act="delete"  class="w-full text-left px-3 py-1.5 hover:bg-accent-coral/10 text-accent-coral text-[12.5px] flex items-center gap-2">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2h4v2M5 4l.5 9h5l.5-9"/></svg>
                Delete
            </button>
        `;
        document.body.appendChild(msgMenuEl);
        // Position next to the anchor — clamp inside viewport.
        const r = anchorBtn.getBoundingClientRect();
        const w = msgMenuEl.offsetWidth, h = msgMenuEl.offsetHeight;
        let left = r.right + 4;
        if (left + w > window.innerWidth - 8) left = r.left - w - 4;
        if (left < 8) left = 8;
        let top  = r.bottom + 4;
        if (top + h > window.innerHeight - 8) top = r.top - h - 4;
        if (top < 8) top = 8;
        msgMenuEl.style.left = left + 'px';
        msgMenuEl.style.top  = top  + 'px';

        // Helper — fires the actual reaction request for any emoji.
        async function sendReact(emoji) {
            closeMsgMenu();
            try {
                const r = await api(`/team-inbox/api/conversations/${state.activeId}/messages/${msgId}/react`, {
                    method: 'POST', body: { emoji },
                });
                if (r?.data && r.data.wa_ok === false) {
                    toast('Reaction saved locally but WhatsApp says: ' + (r.data.wa_error || 'unknown'), 'error');
                } else {
                    toast(emoji ? `Reacted ${emoji}` : 'Reaction cleared', 'success');
                }
                await loadActive(state.activeId);
            } catch (e) { toast('React failed: ' + e.message, 'error'); }
        }

        msgMenuEl.querySelectorAll('[data-act-react]').forEach(b => {
            b.addEventListener('click', () => sendReact(b.dataset.actReact));
        });

        // "+" → lazy-load emoji-picker-element + mount it next to the
        // reaction row. Picking an emoji fires sendReact.
        const moreBtn  = msgMenuEl.querySelector('[data-act-react-more]');
        const extraPnl = msgMenuEl.querySelector('[data-extra-picker]');
        let pickerMounted = false;
        moreBtn?.addEventListener('click', async (e) => {
            e.stopPropagation();
            if (extraPnl.classList.contains('hidden')) {
                if (!pickerMounted) {
                    pickerMounted = true;
                    await import('emoji-picker-element');
                    const picker = document.createElement('emoji-picker');
                    picker.classList.add('chat-emoji-picker', 'light');
                    picker.addEventListener('emoji-click', (ev) => {
                        const native = ev.detail?.unicode || ev.detail?.emoji?.unicode;
                        if (native) sendReact(native);
                    });
                    extraPnl.appendChild(picker);
                }
                extraPnl.classList.remove('hidden');
            } else {
                extraPnl.classList.add('hidden');
            }
        });

        msgMenuEl.querySelectorAll('[data-act]').forEach(b => {
            b.addEventListener('click', () => handleMsgAction(msgId, b.dataset.act));
        });
    }

    async function handleMsgAction(msgId, action) {
        closeMsgMenu();
        if (!state.activeId) return;
        const url = `/team-inbox/api/conversations/${state.activeId}/messages/${msgId}`;
        try {
            if (action === 'info') {
                const data = await api(url + '/info');
                const d = data.data || {};
                const lines = [
                    `Direction: ${d.direction || '—'}`,
                    `Status:    ${d.status || '—'}`,
                    `Sent:      ${d.sent_at      ? formatTime(d.sent_at)      : '—'}`,
                    `Delivered: ${d.delivered_at ? formatTime(d.delivered_at) : '—'}`,
                    `Read:      ${d.read_at      ? formatTime(d.read_at)      : '—'}`,
                    d.failure ? `Failure: ${d.failure}` : '',
                ].filter(Boolean).join('\n');
                await themedPrompt({ title: 'Message info', defaultValue: lines, multiline: true, confirmLabel: 'Close' });
            } else if (action === 'pin') {
                const r = await api(url + '/pin',  { method: 'PATCH' });
                if (r?.data?.wa_ok === false) {
                    toast('Pinned locally but WhatsApp says: ' + (r.data.wa_error || 'unknown'), 'error');
                } else {
                    toast(r?.data?.pinned ? 'Pinned on WhatsApp.' : 'Unpinned.', 'success');
                }
                await loadActive(state.activeId);
            } else if (action === 'star') {
                const r = await api(url + '/star', { method: 'PATCH' });
                if (r?.data?.wa_ok === false) {
                    toast('Starred locally but WhatsApp says: ' + (r.data.wa_error || 'unknown'), 'error');
                } else {
                    toast(r?.data?.starred ? 'Starred.' : 'Unstarred.', 'success');
                }
                await loadActive(state.activeId);
            } else if (action === 'forward') {
                // Forward → pick a target conversation by id. Simple
                // numeric prompt for v1; a real picker is a nicer future
                // polish but not blocking.
                const raw = await themedPrompt({
                    title: 'Forward to conversation',
                    placeholder: 'Conversation id from the queue (column 2)',
                    confirmLabel: 'Forward',
                });
                const targetId = parseInt(raw, 10);
                if (!targetId) return;
                await api(url + '/forward', { method: 'POST', body: { target_conversation_id: targetId } });
                toast('Forwarded.', 'success');
            } else if (action === 'edit') {
                const msg = (state.thread || []).find(x => x.id === msgId);
                if (!msg) return;
                if (!msg.editable) {
                    toast('This message is past WhatsApp\'s 15-minute edit window.', 'error');
                    return;
                }
                const newBody = await openEditModal(msg);
                if (newBody === null || newBody === undefined) return;
                if (newBody.trim() === '' || newBody === (msg.body || '')) return;
                try {
                    const r = await api(url + '/edit', { method: 'PATCH', body: { body: newBody } });
                    if (r?.data?.wa_ok === false) {
                        toast('Edit not sent to WhatsApp: ' + (r.data.wa_error || 'unknown'), 'error');
                    } else {
                        toast('Message edited.', 'success');
                    }
                    await loadActive(state.activeId);
                } catch (e) {
                    // The server returns 422 with { error, message } for window/no-id failures.
                    const msgText = e?.body?.message || e?.message || 'Edit failed.';
                    toast(msgText, 'error');
                }
            } else if (action === 'delete') {
                // Two delete modes:
                //   • everyone — also revokes on WhatsApp via Baileys
                //                (sock.sendMessage(jid, { delete: key }))
                //     → recipient sees "This message was deleted"
                //   • local    — just soft-deletes our row, recipient
                //                still sees the original on their device
                // Only outbound messages can be revoked; the menu hides
                // "Delete for everyone" for inbound bubbles.
                const msg = (state.thread || []).find(m => m.id === msgId);
                const isOutbound = msg?.kind === 'out';
                const options = [];
                if (isOutbound) {
                    options.push({
                        value:   'everyone',
                        variant: 'danger',
                        label:   'Delete for everyone',
                        sub:     'Recipient sees "This message was deleted" on WhatsApp.',
                    });
                }
                options.push({
                    value:   'local',
                    variant: isOutbound ? 'default' : 'danger',
                    label:   'Delete for me only',
                    sub:     'Hides the message from this inbox. The recipient still sees the original.',
                });

                const choice = await themedChoice({
                    title: 'Delete message?',
                    body:  isOutbound
                        ? 'Pick how you want to delete this message.'
                        : 'Inbound messages can only be hidden locally — only your own messages can be revoked on WhatsApp.',
                    options,
                });
                if (!choice) return;

                const r = await api(url + '?mode=' + encodeURIComponent(choice), { method: 'DELETE' });
                if (r?.data?.wa_ok === false && choice === 'everyone') {
                    toast('Deleted locally but WhatsApp says: ' + (r.data.wa_error || 'unknown'), 'error');
                } else {
                    toast(choice === 'everyone' ? 'Deleted on WhatsApp.' : 'Hidden from this inbox.', 'success');
                }
                await loadActive(state.activeId);
            }
        } catch (e) {
            toast('Action failed: ' + e.message, 'error');
        }
    }

    // Close the message menu on any outside click. The trigger handler
    // calls e.stopPropagation() so opening a menu on a different bubble
    // doesn't immediately self-close here.
    document.addEventListener('click', (e) => {
        if (msgMenuEl && !msgMenuEl.contains(e.target)) closeMsgMenu();
    });
    // Also close on Escape for keyboard parity with the lightbox.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && msgMenuEl) closeMsgMenu();
    });

    $('#assign-btn')?.addEventListener('click', () => {
        const menu = $('#assign-menu');
        if (!menu) return;
        menu.classList.toggle('hidden');
        if (menu.classList.contains('hidden')) return;
        menu.innerHTML = `
          <div class="menu-section">
            <div class="font-mono text-[9.5px] uppercase tracking-wider text-ink-500 px-3 pt-2 pb-1">Teams</div>
            ${state.teams.map(t => `<button data-assign-team="${t.id}" class="menu-item">${escape(t.name)} <span class="ml-auto text-[10px] text-ink-500">auto-assign</span></button>`).join('')}
            <div class="font-mono text-[9.5px] uppercase tracking-wider text-ink-500 px-3 pt-2 pb-1 border-t border-paper-200">Members</div>
            ${state.members.map(m => `<button data-assign-user="${m.id}" class="menu-item">${escape(m.name)} <span class="ml-auto text-[10px] text-ink-500">${escape(m.status || '')}</span></button>`).join('')}
            <div class="border-t border-paper-200"></div>
            <button data-unassign class="menu-item text-accent-coral">Unassign</button>
          </div>`;
        menu.querySelectorAll('[data-assign-team]').forEach(b => b.addEventListener('click', () => {
            const tid = parseInt(b.dataset.assignTeam, 10);
            assign(null, tid, 'least_loaded');
            menu.classList.add('hidden');
        }));
        menu.querySelectorAll('[data-assign-user]').forEach(b => b.addEventListener('click', () => {
            const uid = parseInt(b.dataset.assignUser, 10);
            assign(uid, state.active?.assignee_team_id, 'manual');
            menu.classList.add('hidden');
        }));
        menu.querySelector('[data-unassign]')?.addEventListener('click', async () => {
            try {
                await api(`/team-inbox/api/conversations/${state.activeId}/unassign`, { method: 'POST' });
                await loadActive(state.activeId);
                await loadQueue(true);
            } catch (e) { toast('Unassign failed: ' + e.message, 'error'); }
            menu.classList.add('hidden');
        });
    });

    // AI Agent assignment dropdown
    $('#ai-agent-btn')?.addEventListener('click', () => {
        const menu = $('#ai-agent-menu');
        if (!menu) return;
        menu.classList.toggle('hidden');
        if (menu.classList.contains('hidden')) return;
        const agents = state.aiAgents || [];
        const activeAgentId = state.active?.assignee_agent_id;
        menu.innerHTML = `
          <div class="menu-section">
            <div class="font-mono text-[9.5px] uppercase tracking-wider text-ink-500 px-3 pt-2 pb-1">AI Agents</div>
            ${agents.length === 0
                ? `<div class="px-3 py-2 text-[11.5px] text-ink-500">No agents yet — <button class="text-wa-deep font-semibold" data-open-ai-agents>create one</button></div>`
                : agents.map(a => `
                    <button data-assign-agent="${a.id}" class="menu-item ${activeAgentId === a.id ? 'text-wa-deep font-semibold' : ''}">
                        <span class="w-5 h-5 rounded-full text-paper-0 text-[8px] font-semibold grid place-items-center shrink-0" style="background:${safeColor(a.avatar_color)}">${escape((a.name||'AI').slice(0,2).toUpperCase())}</span>
                        ${escape(a.name)}
                        ${activeAgentId === a.id ? '<span class="ml-auto text-[9.5px]">✓</span>' : ''}
                    </button>`).join('')}
            <div class="border-t border-paper-200 mt-1"></div>
            ${activeAgentId ? `<button data-unassign-agent class="menu-item text-accent-coral">Remove AI agent</button>` : ''}
            <button data-open-ai-agents class="menu-item text-ink-500 text-[11.5px]">Manage agents…</button>
          </div>`;
        menu.querySelectorAll('[data-assign-agent]').forEach(b => {
            b.addEventListener('click', async () => {
                await assignAgent(parseInt(b.dataset.assignAgent, 10));
                menu.classList.add('hidden');
            });
        });
        menu.querySelectorAll('[data-unassign-agent]').forEach(b => {
            b.addEventListener('click', async () => {
                await assignAgent(null);
                menu.classList.add('hidden');
            });
        });
        menu.querySelectorAll('[data-open-ai-agents]').forEach(b => {
            b.addEventListener('click', () => { menu.classList.add('hidden'); openAiAgentsModal(); });
        });
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#ai-agent-btn') && !e.target.closest('#ai-agent-menu')) {
            $('#ai-agent-menu')?.classList.add('hidden');
        }
    });

    // CT panel agent change
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#ct-agent-change')) return;
        $('#ai-agent-btn')?.click();
    });

    async function assignAgent(agentId) {
        if (!state.activeId) return;
        try {
            const data = await api(`/team-inbox/api/conversations/${state.activeId}/assign-agent`, {
                method: 'POST', body: { agent_id: agentId },
            });
            toast(agentId ? `AI agent assigned: ${data.agent_name}` : 'AI agent removed.', 'success');
            await loadActive(state.activeId);
            await loadQueue(true);
        } catch (e) { toast('Agent assign failed: ' + e.message, 'error'); }
    }

    // ── AI Agents Modal ──────────────────────────────────────────────────
    // Model lists kept in sync with AdminAiKeyController.php (canonical
    // allow-list). gpt-3.5-turbo + gemini-1.5-* are EOL — replaced with
    // the current 4.x / 2.5+ families. Per-provider order: fastest →
    // smartest so the modal's first option is the cost-friendly default.
    const MODEL_BY_PROVIDER = {
        openai:    [
            { v: 'gpt-4o-mini',                l: 'GPT-4o mini (fast)' },
            { v: 'gpt-4o',                     l: 'GPT-4o (smart)' },
            { v: 'gpt-4.1',                    l: 'GPT-4.1 (smartest)' },
        ],
        anthropic: [
            { v: 'claude-haiku-4-5-20251001',  l: 'Claude Haiku 4.5 (fast)' },
            { v: 'claude-sonnet-4-6',          l: 'Claude Sonnet 4.6 (smart)' },
            { v: 'claude-opus-4-7',            l: 'Claude Opus 4.7 (smartest)' },
        ],
        gemini:    [
            { v: 'gemini-2.5-flash-lite',      l: 'Gemini 2.5 Flash-Lite (fast)' },
            { v: 'gemini-2.5-flash',           l: 'Gemini 2.5 Flash (balanced)' },
            { v: 'gemini-2.5-pro',             l: 'Gemini 2.5 Pro (smart)' },
        ],
    };

    function renderAiAgentNav() {
        const el = $('#nav-ai-count');
        const active = (state.aiAgents || []).filter(a => a.is_active).length;
        if (el) {
            el.textContent = active;
            el.style.display = active > 0 ? '' : 'none';
        }
    }

    function openAiAgentsModal() {
        const m = $('#ai-agents-modal');
        if (!m) return;
        m.classList.remove('hidden');
        renderAiAgentsList();
    }
    function closeAiAgentsModal() { $('#ai-agents-modal')?.classList.add('hidden'); }

    async function refreshAgents() {
        const data = await api('/team-inbox/api/ai-agents');
        state.aiAgents = Array.isArray(data) ? data : [];
        renderAiAgentNav();
    }

    function renderAiAgentsList() {
        const list = $('#ai-agents-list');
        if (!list) return;
        const agents = state.aiAgents || [];
        if (agents.length === 0) {
            list.innerHTML = `<div class="text-[12px] text-ink-500 text-center py-8">No AI agents yet. Click "Add agent" to create your first one.</div>`;
            return;
        }
        list.innerHTML = agents.map(a => `
            <div class="bg-paper-0 border border-paper-200 rounded-xl p-3 flex items-start gap-3">
                <span class="w-9 h-9 rounded-full text-paper-0 text-[11px] font-semibold grid place-items-center shrink-0" style="background:${safeColor(a.avatar_color)}">${escape((a.name||'AI').slice(0,2).toUpperCase())}</span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-[13px] truncate">${escape(a.name)}</span>
                        <span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono ${a.is_active ? 'bg-wa-mint/40 text-wa-deep' : 'bg-paper-100 text-ink-500'}">${a.is_active ? 'active' : 'disabled'}</span>
                        <span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono bg-paper-100 text-ink-700">${escape(a.model)}</span>
                    </div>
                    <div class="text-[11.5px] text-ink-500 mt-0.5 truncate">${escape(a.system_prompt || 'Generic assistant')}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="font-mono text-[9.5px] text-ink-500">${a.messages_sent} msgs sent</span>
                        <span class="font-mono text-[9.5px] text-ink-500">·</span>
                        <span class="font-mono text-[9.5px] text-ink-500">${a.tone}</span>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button data-edit-agent="${a.id}" class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-600" title="Edit">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 2l3 3-8 8H3v-3l8-8z"/></svg>
                    </button>
                    <button data-toggle-agent="${a.id}" class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-600" title="${a.is_active ? 'Disable' : 'Enable'}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5">${a.is_active ? '<path d="M4 4l8 8M12 4l-8 8"/>' : '<path d="M3 8l3 3 7-7"/>'}</svg>
                    </button>
                    <button data-delete-agent="${a.id}" class="w-7 h-7 rounded-full hover:bg-accent-coral/10 grid place-items-center text-accent-coral" title="Delete">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10M6 4V2h4v2M5 4l.5 9h5l.5-9"/></svg>
                    </button>
                </div>
            </div>`).join('');

        list.querySelectorAll('[data-edit-agent]').forEach(b => {
            b.addEventListener('click', () => {
                const agent = (state.aiAgents || []).find(a => a.id === parseInt(b.dataset.editAgent, 10));
                if (agent) openAgentForm(agent);
            });
        });
        list.querySelectorAll('[data-toggle-agent]').forEach(b => {
            b.addEventListener('click', async () => {
                const id = parseInt(b.dataset.toggleAgent, 10);
                const agent = (state.aiAgents || []).find(a => a.id === id);
                try {
                    await api(`/team-inbox/api/ai-agents/${id}`, {
                        method: 'PATCH', body: { is_active: !agent?.is_active },
                    });
                    await refreshAgents();
                    renderAiAgentsList();
                    toast(agent?.is_active ? 'Agent disabled.' : 'Agent enabled.', 'success');
                } catch (e) { toast('Failed: ' + e.message, 'error'); }
            });
        });
        list.querySelectorAll('[data-delete-agent]').forEach(b => {
            b.addEventListener('click', async () => {
                const id = parseInt(b.dataset.deleteAgent, 10);
                const agent = (state.aiAgents || []).find(a => a.id === id);
                if (!confirm(`Delete "${agent?.name}"? This will also remove it from any assigned conversations.`)) return;
                try {
                    await api(`/team-inbox/api/ai-agents/${id}`, { method: 'DELETE' });
                    await refreshAgents();
                    renderAiAgentsList();
                    toast('Agent deleted.', 'success');
                } catch (e) { toast('Delete failed: ' + e.message, 'error'); }
            });
        });
    }

    $$('[data-close-agents]').forEach(b => b.addEventListener('click', closeAiAgentsModal));
    $('#nav-ai-agents')?.addEventListener('click', openAiAgentsModal);
    $('#ai-agent-add-btn')?.addEventListener('click', () => openAgentForm(null));

    // ── AI Agent Form Modal ──────────────────────────────────────────────
    function openAgentForm(agent = null) {
        const m = $('#ai-agent-form-modal');
        if (!m) return;
        m.classList.remove('hidden');
        $('#agent-form-title') && ($('#agent-form-title').textContent = agent ? 'Edit agent' : 'Create agent');
        $('#agent-form-id') && ($('#agent-form-id').value = agent?.id || '');
        $('#agent-form-error')?.classList.add('hidden');
        $('#agent-test-result')?.classList.add('hidden');

        const form = $('#ai-agent-form');
        if (!form) return;
        form.reset();

        // Render the device-scope checkbox list even for the "create"
        // path, so a new agent can be scoped on first save. The helper
        // hides the wrap entirely when the workspace has 0–1 devices.
        renderAgentDeviceScope([]);

        // Fill form fields from existing agent
        if (agent) {
            form.querySelector('[name="name"]').value = agent.name || '';
            const p = agent.provider || 'openai';
            form.querySelector('[name="provider"]').value = p;
            // Rebuild model options for this provider, then set saved model
            const modelSel = form.querySelector('[name="model"]');
            const modelList = MODEL_BY_PROVIDER[p] || [];
            if (modelSel && modelList.length) {
                modelSel.innerHTML = modelList.map(m => `<option value="${m.v}">${m.l}</option>`).join('');
            }
            if (modelSel) {
                // Preserve the agent's saved model even if it isn't in the
                // rebuilt option list (older/renamed model, or a provider not
                // in MODEL_BY_PROVIDER). Without this the select fell back to
                // an empty value → the save sent model:null → "must be a
                // string" and the whole form refused to save.
                const want = agent.model || modelList[0]?.v || '';
                if (want && !Array.from(modelSel.options).some(o => o.value === want)) {
                    modelSel.insertAdjacentHTML('afterbegin', `<option value="${want}">${want}</option>`);
                }
                modelSel.value = want;
            }
            form.querySelector('[name="tone"]').value = agent.tone || 'professional';
            form.querySelector('[name="system_prompt"]').value = agent.system_prompt || '';
            form.querySelector('[name="max_tokens"]').value = agent.max_tokens || 512;
            form.querySelector('[name="temperature"]').value = agent.temperature || 7;
            form.querySelector('[name="auto_respond"]').checked = !!agent.auto_respond;
            const useSaved = form.querySelector('[name="use_saved_replies"]');
            if (useSaved) useSaved.checked = !!agent.use_saved_replies;
            // Handoff settings
            const handoffEnabled = form.querySelector('[name="handoff_enabled"]');
            if (handoffEnabled) handoffEnabled.checked = agent.handoff_enabled !== false;
            const maxReplies = form.querySelector('[name="max_replies_per_conversation"]');
            if (maxReplies) maxReplies.value = agent.max_replies_per_conversation ?? 10;
            const lowScore = form.querySelector('[name="handoff_low_score_threshold"]');
            if (lowScore) lowScore.value = agent.handoff_low_score_threshold ?? 0;
            const lowWin = form.querySelector('[name="handoff_low_score_window"]');
            if (lowWin) lowWin.value = agent.handoff_low_score_window ?? 3;
            const kwCsv = form.querySelector('[name="handoff_keywords_csv"]');
            if (kwCsv) kwCsv.value = Array.isArray(agent.handoff_keywords) ? agent.handoff_keywords.join(', ') : '';
            // Voice-AI rehydrate. Booleans default off so a legacy
            // agent that pre-dates voice support stays text-only after
            // edit-and-save.
            const vNoteEl = form.querySelector('[name="voice_note_enabled"]');
            if (vNoteEl) vNoteEl.checked = !!agent.voice_note_enabled;
            const vCallEl = form.querySelector('[name="voice_call_enabled"]');
            if (vCallEl) vCallEl.checked = !!agent.voice_call_enabled;
            const vProv = form.querySelector('[name="voice_provider"]');
            if (vProv) vProv.value = agent.voice_provider || 'openai';
            const vId = form.querySelector('[name="voice_id"]');
            if (vId) vId.value = agent.voice_id || '';
            const vLang = form.querySelector('[name="voice_language"]');
            if (vLang) vLang.value = agent.voice_language || 'en';
            const vCap = form.querySelector('[name="max_voice_notes_per_day"]');
            if (vCap) vCap.value = agent.max_voice_notes_per_day ?? 200;
            // device scope (checkbox list rendered below) — restore
            // selections from the agent's stored device_ids.
            renderAgentDeviceScope(Array.isArray(agent.device_ids) ? agent.device_ids : []);
            // color
            const colorInput = form.querySelector('[name="avatar_color"]');
            if (colorInput) colorInput.value = agent.avatar_color || '#6366f1';
            $$('#agent-color-picker [data-color]').forEach(b => {
                b.classList.remove('ring-2', 'ring-offset-2');
                if (b.dataset.color === (agent.avatar_color || '#6366f1')) {
                    b.classList.add('ring-2', 'ring-offset-2');
                    b.style.setProperty('--tw-ring-color', b.dataset.color);
                }
            });
        }
    }
    function closeAgentForm() { $('#ai-agent-form-modal')?.classList.add('hidden'); }

    // Build the agent's device-scope checkbox list. Wrap stays hidden
    // when the workspace has 0–1 paired devices (single-device installs
    // have nothing to scope to). When 2+ devices exist, one row per
    // device renders with the pre-selected ids checked.
    function renderAgentDeviceScope(preselected = []) {
        const wrap = $('#agent-device-scope-wrap');
        const list = $('#agent-device-scope-list');
        if (!wrap || !list) return;
        const devices = state.devices || [];
        if (devices.length <= 1) {
            wrap.classList.add('hidden');
            list.innerHTML = '';
            return;
        }
        wrap.classList.remove('hidden');
        const picked = new Set((preselected || []).map(String));
        list.innerHTML = devices.map(d => `
            <label class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg border border-paper-200 hover:bg-paper-100 cursor-pointer has-[:checked]:bg-wa-mint/40">
                <input type="checkbox" name="agent_device_ids" value="${d.id}" class="w-3.5 h-3.5 rounded accent-wa-deep" ${picked.has(String(d.id)) ? 'checked' : ''}>
                <span class="flex-1 text-[12.5px]">${escape(d.label)}</span>
                <span class="font-mono text-[10.5px] text-ink-500">${escape(d.phone)}</span>
            </label>
        `).join('');
    }

    $$('[data-close-agent-form]').forEach(b => b.addEventListener('click', closeAgentForm));

    // Color picker inside agent form
    $$('#agent-color-picker [data-color]').forEach(btn => {
        btn.addEventListener('click', () => {
            const c = btn.dataset.color;
            const ci = document.querySelector('#ai-agent-form [name="avatar_color"]');
            if (ci) ci.value = c;
            $$('#agent-color-picker [data-color]').forEach(b => b.classList.remove('ring-2', 'ring-offset-2'));
            btn.classList.add('ring-2', 'ring-offset-2');
        });
    });

    // Provider → model selector sync
    $('#agent-provider')?.addEventListener('change', e => {
        const p = e.target.value;
        const models = MODEL_BY_PROVIDER[p] || [];
        const sel = $('#agent-model');
        if (!sel) return;
        sel.innerHTML = models.map(m => `<option value="${m.v}">${m.l}</option>`).join('');
    });

    // Test agent inline
    $('#agent-test-btn')?.addEventListener('click', async () => {
        const agentId = parseInt($('#agent-form-id')?.value || '0', 10);
        const input = $('#agent-test-input');
        const msg = input?.value?.trim();
        if (!msg) return;

        const btn = $('#agent-test-btn');
        const result = $('#agent-test-result');
        btn.disabled = true; btn.textContent = 'Testing…';
        result?.classList.remove('hidden');
        if (result) result.textContent = 'Thinking…';

        try {
            if (agentId) {
                // saved agent — use the test endpoint
                const data = await api(`/team-inbox/api/ai-agents/${agentId}/test`, {
                    method: 'POST', body: { message: msg },
                });
                if (result) result.textContent = data.reply || '(no response)';
            } else {
                if (result) result.textContent = 'Save the agent first to test it.';
            }
        } catch (e) {
            if (result) result.textContent = 'Error: ' + e.message;
        } finally {
            btn.disabled = false; btn.textContent = 'Send';
        }
    });

    $('#ai-agent-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const agentId = parseInt($('#agent-form-id')?.value || '0', 10);
        // Parse handoff keywords from comma-separated input; null/blank
        // means "use the server's sensible defaults".
        const kwRaw = (fd.get('handoff_keywords_csv') || '').toString().trim();
        const keywords = kwRaw === ''
            ? null
            : kwRaw.split(',').map(s => s.trim()).filter(Boolean);
        const body = {
            name:          fd.get('name'),
            provider:      fd.get('provider'),
            model:         fd.get('model'),
            tone:          fd.get('tone'),
            system_prompt: fd.get('system_prompt'),
            max_tokens:    parseInt(fd.get('max_tokens') || '512', 10),
            temperature:   parseInt(fd.get('temperature') || '7', 10),
            auto_respond:  fd.has('auto_respond') ? 1 : 0,
            use_saved_replies: fd.has('use_saved_replies') ? 1 : 0,
            avatar_color:  fd.get('avatar_color') || '#6366f1',
            is_active:     1,
            // Handoff config
            handoff_enabled:              fd.has('handoff_enabled') ? 1 : 0,
            max_replies_per_conversation: parseInt(fd.get('max_replies_per_conversation') || '10', 10),
            handoff_low_score_threshold:  parseInt(fd.get('handoff_low_score_threshold') || '0', 10),
            handoff_low_score_window:     parseInt(fd.get('handoff_low_score_window') || '3', 10),
            handoff_keywords:             keywords,
            // Voice-AI config. Booleans pass as 1/0 so the PHP validator
            // can use `boolean` rule consistently across the form.
            voice_note_enabled:      fd.has('voice_note_enabled') ? 1 : 0,
            voice_call_enabled:      fd.has('voice_call_enabled') ? 1 : 0,
            voice_provider:          fd.get('voice_provider') || 'openai',
            voice_id:                (fd.get('voice_id') || '').toString().trim() || null,
            voice_language:          fd.get('voice_language') || 'en',
            max_voice_notes_per_day: parseInt(fd.get('max_voice_notes_per_day') || '200', 10),
        };
        // Multi-device scope. The DOM only contains these checkboxes
        // when state.devices.length > 1, so on single-device installs
        // device_ids stays an empty array (= any device, the default).
        const deviceIds = Array.from(e.target.querySelectorAll('input[name="agent_device_ids"]:checked'))
            .map(cb => Number(cb.value))
            .filter(Number.isFinite);
        body.device_ids = deviceIds; // [] means "any device" per the model accessor
        const errEl = $('#agent-form-error');
        const submit = $('#agent-form-submit');
        submit.disabled = true; submit.textContent = 'Saving…';
        errEl?.classList.add('hidden');
        try {
            if (agentId) {
                await api(`/team-inbox/api/ai-agents/${agentId}`, { method: 'PATCH', body });
                toast('Agent updated.', 'success');
            } else {
                await api('/team-inbox/api/ai-agents', { method: 'POST', body });
                toast('Agent created.', 'success');
            }
            await refreshAgents();
            renderAiAgentsList();
            closeAgentForm();
        } catch (err) {
            if (errEl) { errEl.textContent = err.message; errEl.classList.remove('hidden'); }
        } finally {
            submit.disabled = false; submit.textContent = 'Save agent';
        }
    });

    // ── API Keys Modal ───────────────────────────────────────────────────
    function openKeysModal() {
        const m = $('#ai-keys-modal');
        if (!m) return;
        m.classList.remove('hidden');
        renderKeysList();
    }
    function closeKeysModal() { $('#ai-keys-modal')?.classList.add('hidden'); }

    $$('[data-close-keys]').forEach(b => b.addEventListener('click', closeKeysModal));
    $('#nav-ai-keys')?.addEventListener('click', openKeysModal);

    function renderKeysList() {
        const list = $('#ai-keys-list');
        if (!list) return;
        const keys = state.aiKeys || [];
        if (keys.length === 0) {
            list.innerHTML = `<div class="text-[12px] text-ink-500 text-center py-4">No keys saved yet.</div>`;
            return;
        }
        const providerLabel = { openai: 'OpenAI', anthropic: 'Anthropic', gemini: 'Google Gemini' };
        list.innerHTML = keys.map(k => `
            <div class="flex items-center gap-2 py-2 border-b border-paper-200 last:border-0">
                <span class="font-mono text-[11.5px] text-ink-700 flex-1">${escape(providerLabel[k.provider] || k.provider)}</span>
                <span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono ${k.is_active ? 'bg-wa-mint/40 text-wa-deep' : 'bg-paper-100 text-ink-500'}">${k.is_active ? 'active' : 'off'}</span>
                <button data-toggle-key="${k.id}" class="text-[10.5px] text-wa-deep hover:underline">${k.is_active ? 'Disable' : 'Enable'}</button>
                <button data-delete-key="${k.id}" class="text-[10.5px] text-accent-coral hover:underline">Delete</button>
            </div>`).join('');

        list.querySelectorAll('[data-toggle-key]').forEach(b => {
            b.addEventListener('click', async () => {
                try {
                    await api(`/team-inbox/api/ai-keys/${b.dataset.toggleKey}/toggle`, { method: 'PATCH' });
                    const keyData = await api('/team-inbox/api/ai-keys');
                    state.aiKeys = Array.isArray(keyData) ? keyData : [];
                    renderKeysList();
                } catch (e) { toast('Failed: ' + e.message, 'error'); }
            });
        });
        list.querySelectorAll('[data-delete-key]').forEach(b => {
            b.addEventListener('click', async () => {
                try {
                    await api(`/team-inbox/api/ai-keys/${b.dataset.deleteKey}`, { method: 'DELETE' });
                    const keyData = await api('/team-inbox/api/ai-keys');
                    state.aiKeys = Array.isArray(keyData) ? keyData : [];
                    renderKeysList();
                    toast('Key deleted.', 'success');
                } catch (e) { toast('Failed: ' + e.message, 'error'); }
            });
        });
    }

    $('#ai-key-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            await api('/team-inbox/api/ai-keys', {
                method: 'POST', body: { provider: fd.get('provider'), api_key: fd.get('api_key') },
            });
            const keyData = await api('/team-inbox/api/ai-keys');
            state.aiKeys = Array.isArray(keyData) ? keyData : [];
            renderKeysList();
            e.target.reset();
            toast('Key saved.', 'success');
        } catch (err) { toast('Failed: ' + err.message, 'error'); }
    });

    // Add tag — uses event delegation so the handler works even if
    // the contact panel was hidden when init ran (clicks inside a
    // momentarily-hidden node sometimes don't bubble in older bundles).
    // Also requires an active conversation; without one the server
    // would 404.
    async function handleAddTagClick(e) {
        e.preventDefault();
        e.stopPropagation();
        if (!state.activeId) { toast('Open a conversation first.', 'error'); return; }
        const raw = await themedPrompt({
            title: 'Add tag',
            placeholder: 'e.g. priority · sales · billing',
            confirmLabel: 'Add tag',
        });
        if (raw === null) return; // cancelled
        const name = String(raw || '').trim();
        if (!name) { toast('Tag name cannot be empty.', 'error'); return; }
        try {
            await api(`/team-inbox/api/conversations/${state.activeId}/tag`, {
                method: 'POST', body: { name },
            });
            await loadActive(state.activeId);
            toast(`Tag "${name}" added`, 'success');
        } catch (err) {
            toast('Tag failed: ' + err.message, 'error');
        }
    }
    // Event delegation (catches dynamically-rendered buttons + clicks
    // when the contact panel was collapsed at init time).
    document.addEventListener('click', (e) => {
        if (e.target.closest('#add-tag-btn')) handleAddTagClick(e);
    });

    // ── Quick label shortcuts ───────────────────────────────────────────
    // #48 — Inline label picker dropdown. Opens a menu of every tag in
    // the workspace + an "add new" input. Click → POST /tag (reuses
    // the same endpoint the quick-chips use). Auto-closes on body click.
    function renderLabelPicker() {
        const menu = $('#label-picker-menu');
        if (!menu) return;
        const tags = state.tags || [];
        const applied = new Set((state.active?.tags || []).map(t => t.id));
        const rows = tags.length === 0
            ? '<div class="px-2 py-3 text-[11px] text-ink-500 text-center italic">No tags yet.</div>'
            : tags.map(t => `
                <button type="button" data-label-id="${t.id}" data-label-name="${escape(t.name)}" data-label-color="${escape(t.color || '#075E54')}"
                    class="w-full flex items-center gap-2 px-2 py-1.5 rounded hover:bg-paper-50 text-left">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:${safeColor(t.color, '#075E54')}"></span>
                    <span class="text-[11.5px] flex-1 truncate">${escape(t.name)}</span>
                    ${applied.has(t.id) ? '<span class="text-[10px] text-wa-deep font-mono">✓</span>' : ''}
                </button>`).join('');
        menu.innerHTML = rows + `
            <div class="border-t border-paper-200 mt-1 pt-1">
                <form id="label-picker-create" class="flex items-center gap-1 px-1">
                    <input type="text" maxlength="40" placeholder="+ New label" class="flex-1 px-2 py-1 text-[11px] border border-paper-200 rounded focus:outline-none focus:border-wa-deep">
                    <button type="submit" class="px-2 py-1 rounded bg-wa-deep text-paper-0 text-[10px] font-mono">Add</button>
                </form>
            </div>`;
    }

    function toggleLabelPicker(show) {
        const menu = $('#label-picker-menu');
        if (!menu) return;
        if (show) { renderLabelPicker(); menu.classList.remove('hidden'); }
        else { menu.classList.add('hidden'); }
    }

    $('#label-picker-btn')?.addEventListener('click', (e) => {
        e.stopPropagation();
        const menu = $('#label-picker-menu');
        toggleLabelPicker(menu?.classList.contains('hidden'));
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#label-picker-menu') && !e.target.closest('#label-picker-btn')) {
            toggleLabelPicker(false);
        }
    });

    // Apply / toggle existing tag from the picker.
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('#label-picker-menu [data-label-id]');
        if (!btn) return;
        e.preventDefault();
        if (!state.activeId) { toast('Open a conversation first.', 'error'); return; }
        const id    = parseInt(btn.dataset.labelId, 10);
        const name  = btn.dataset.labelName;
        const color = btn.dataset.labelColor;
        const applied = new Set((state.active?.tags || []).map(t => t.id));
        try {
            if (applied.has(id)) {
                await api(`/team-inbox/api/conversations/${state.activeId}/tag/${id}`, { method: 'DELETE' });
                toast(`Removed "${name}".`, 'ok');
            } else {
                await api(`/team-inbox/api/conversations/${state.activeId}/tag`, {
                    method: 'POST', body: { name, color, tag_id: id },
                });
                toast(`Labeled "${name}".`, 'ok');
            }
            await loadActive(state.activeId);
            renderLabelPicker();
        } catch (err) {
            toast('Tag failed: ' + err.message, 'error');
        }
    });

    // Create-new-label inline (also applies to active conversation).
    document.addEventListener('submit', async (e) => {
        if (e.target?.id !== 'label-picker-create') return;
        e.preventDefault();
        if (!state.activeId) { toast('Open a conversation first.', 'error'); return; }
        const input = e.target.querySelector('input');
        const name = (input?.value || '').trim();
        if (!name) return;
        try {
            // Hits the existing tag endpoint with just a name — server
            // firstOrCreate's the tag (slug derived in PHP), then attaches.
            await api(`/team-inbox/api/conversations/${state.activeId}/tag`, {
                method: 'POST', body: { name },
            });
            input.value = '';
            // Refetch the workspace tags so the new one appears in the
            // picker on next open.
            const data = await api('/team-inbox/api/tags');
            if (Array.isArray(data)) state.tags = data;
            await loadActive(state.activeId);
            renderLabelPicker();
            toast(`Labeled "${name}".`, 'ok');
        } catch (err) {
            toast('Create failed: ' + err.message, 'error');
        }
    });

    // Click a chip in the thread-header shortcuts row to apply a preset
    // tag + priority combo in one action. Wired via event delegation so
    // it works after every renderActive() re-renders.
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-quick-label]');
        if (!btn) return;
        e.preventDefault();
        if (!state.activeId) { toast('Open a conversation first.', 'error'); return; }
        const name     = btn.dataset.quickLabel;
        const color    = btn.dataset.quickColor || '#075E54';
        const priority = btn.dataset.quickPriority || 'normal';
        // Disable while in-flight so a double-tap doesn't double-fire.
        btn.disabled = true; btn.classList.add('opacity-50');
        try {
            await api(`/team-inbox/api/conversations/${state.activeId}/tag`, {
                method: 'POST', body: { name, color },
            });
            if (priority && priority !== (state.active?.priority || 'normal')) {
                await api(`/team-inbox/api/conversations/${state.activeId}/priority`, {
                    method: 'POST', body: { priority },
                });
            }
            await loadActive(state.activeId);
            toast(`Labeled "${name}".`, 'success');
        } catch (err) {
            toast('Label failed: ' + err.message, 'error');
        } finally {
            btn.disabled = false; btn.classList.remove('opacity-50');
        }
    });
    // Contact panel show/hide. The aside lives in a CSS grid column —
    // collapsing it is done by adding `.no-contact` to #ti-frame. When
    // closed we also surface the round "open" button in the thread
    // header so the operator can bring it back. Choice is persisted to
    // localStorage so a reload keeps the user's preference.
    function setContactPanel(open) {
        const frame = $('#ti-frame');
        const openBtn = $('#contact-open');
        if (!frame) return;
        if (open) {
            frame.classList.remove('no-contact');
            openBtn?.classList.add('hidden');
        } else {
            frame.classList.add('no-contact');
            openBtn?.classList.remove('hidden');
        }
        try { localStorage.setItem('ti.contactOpen', open ? '1' : '0'); } catch (_) {}
    }
    $('#contact-close')?.addEventListener('click', () => setContactPanel(false));
    $('#contact-open')?.addEventListener('click', () => setContactPanel(true));

    // Composer show/hide. The operator can collapse the whole reply
    // area (Reply / Internal note / Template + textarea + Send) to
    // give the message thread more room when they just want to read.
    // A slim "+ Reply, note or template" tab takes its place; clicking
    // that brings the composer back. Choice persists across reloads
    // and across polling refreshes (loadActive reads ti.composerOpen).
    function setComposer(open) {
        const wrap = $('#composer-wrap');
        const opener = $('#composer-open');
        if (!wrap || !opener) return;
        if (open) {
            wrap.classList.remove('hidden');
            opener.classList.add('hidden');
        } else {
            wrap.classList.add('hidden');
            opener.classList.remove('hidden');
        }
        try { localStorage.setItem('ti.composerOpen', open ? '1' : '0'); } catch (_) {}
    }
    $('#composer-close')?.addEventListener('click', () => setComposer(false));
    $('#composer-open')?.addEventListener('click', () => setComposer(true));

    // mobile back button — exit the open thread, return to queue list
    $('#mobile-back-btn')?.addEventListener('click', () => {
        $('#ti-frame')?.classList.remove('mobile-thread-open');
        state.activeId = null;
        renderEmptyState();
    });

    // ── Invite teammate modal ────────────────────────────────────────────
    function openInviteModal() {
        const m = $('#invite-modal');
        if (!m) return;
        m.classList.remove('hidden');
        $('#invite-result')?.classList.add('hidden');
        $('#invite-form')?.reset();
        m.querySelector('input[name="name"]')?.focus();
    }
    function closeInviteModal() { $('#invite-modal')?.classList.add('hidden'); }

    $$('[data-open-invite]').forEach(b => b.addEventListener('click', openInviteModal));
    $$('[data-close-invite]').forEach(b => b.addEventListener('click', closeInviteModal));

    $('#invite-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd.entries());
        const submitBtn = $('#invite-submit');
        const result = $('#invite-result');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Sending…';
        try {
            const data = await api('/team-inbox/api/members/invite', {
                method: 'POST', body,
            });
            result.classList.remove('hidden', 'bg-accent-coral/10', 'text-accent-coral');
            result.classList.add('bg-wa-mint/40', 'text-wa-deep', 'border', 'border-wa-deep/20');
            // Show the temp password if a new user was created so the inviter
            // can hand it off — once. We don't store it, can't recover later.
            if (data.temp_password) {
                result.innerHTML = `
                  <div class="font-semibold mb-1">${escape(data.member.name)} added.</div>
                  <div class="text-[11.5px]">Share these credentials with them — this is the only time we'll show the password:</div>
                  <div class="font-mono text-[11.5px] bg-paper-0 border border-paper-200 rounded px-2 py-1.5 mt-1.5">
                    Email: ${escape(data.member.email)}<br/>
                    Password: ${escape(data.temp_password)}
                  </div>`;
            } else {
                result.textContent = data.message || 'Invited.';
            }
            // Refresh members list so they appear in the assign menu immediately
            await bootstrap();
            toast('Teammate invited.', 'success');
        } catch (err) {
            result.classList.remove('hidden', 'bg-wa-mint/40', 'text-wa-deep', 'border-wa-deep/20');
            result.classList.add('bg-accent-coral/10', 'text-accent-coral', 'border', 'border-accent-coral/20');
            result.textContent = err.message;
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send invite';
        }
    });

    // ── Create team modal ────────────────────────────────────────────────
    function openCreateTeamModal() {
        const m = $('#create-team-modal');
        if (!m) return;
        m.classList.remove('hidden');
        $('#create-team-form')?.reset();
        m.querySelector('input[name="color"]').value = '#075E54';
        // re-select default color swatch
        $$('#team-color-picker [data-color]').forEach(b => {
            b.classList.toggle('ring-2', b.dataset.color === '#075E54');
            b.classList.toggle('ring-offset-2', b.dataset.color === '#075E54');
            b.classList.toggle('ring-wa-deep', b.dataset.color === '#075E54');
        });
        renderTeamMemberCheckboxes();
        renderTeamDeviceCheckboxes('create-team', []);
        m.querySelector('input[name="name"]')?.focus();
    }
    function closeCreateTeamModal() { $('#create-team-modal')?.classList.add('hidden'); }

    // Shared team device-checkbox renderer. Used by both the create
    // and edit modals — `prefix` is either 'create-team' or
    // 'edit-team' (matches the ids defined in the blade). Empty
    // selection = handles every device (preserves single-device
    // behavior and keeps existing teams working without backfill).
    function renderTeamDeviceCheckboxes(prefix, preselected = []) {
        const wrap = $('#' + prefix + '-devices-wrap');
        const list = $('#' + prefix + '-devices-list');
        if (!wrap || !list) return;
        const devices = state.devices || [];
        if (devices.length <= 1) { wrap.classList.add('hidden'); list.innerHTML = ''; return; }
        wrap.classList.remove('hidden');
        const picked = new Set((preselected || []).map(String));
        list.innerHTML = devices.map(d => `
            <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-paper-100 cursor-pointer has-[:checked]:bg-wa-mint/40">
              <input type="checkbox" name="team_device_ids" value="${d.id}" class="rounded accent-wa-deep" ${picked.has(String(d.id)) ? 'checked' : ''}>
              <span class="text-[12.5px] flex-1 truncate">${escape(d.label)}</span>
              <span class="text-[10.5px] font-mono text-ink-500">${escape(d.phone)}</span>
            </label>
        `).join('');
    }

    $$('[data-open-create-team]').forEach(b => b.addEventListener('click', openCreateTeamModal));
    $$('[data-close-team]').forEach(b => b.addEventListener('click', closeCreateTeamModal));
    $('#create-team-btn')?.addEventListener('click', openCreateTeamModal);

    function renderTeamMemberCheckboxes() {
        const wrap = $('#team-members-list');
        if (!wrap) return;
        const members = state.members || [];
        if (members.length === 0) {
            wrap.innerHTML = `<div class="text-[11.5px] text-ink-500 text-center py-4">No teammates yet — invite some first, then come back.</div>`;
            return;
        }
        wrap.innerHTML = members.map(m => `
            <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-paper-100 cursor-pointer">
              <input type="checkbox" name="members[]" value="${m.id}" class="rounded" />
              <span class="w-6 h-6 rounded-full bg-wa-deep text-paper-0 text-[9px] font-semibold grid place-items-center">${escape((m.name||'?').slice(0,2).toUpperCase())}</span>
              <span class="text-[12.5px] flex-1 truncate">${escape(m.name)}</span>
              <span class="text-[10.5px] font-mono text-ink-500">${escape(m.status || 'offline')}</span>
            </label>
        `).join('');
    }

    $$('#team-color-picker [data-color]').forEach(btn => {
        btn.addEventListener('click', () => {
            const c = btn.dataset.color;
            $('#create-team-form input[name="color"]').value = c;
            $$('#team-color-picker [data-color]').forEach(b => {
                b.classList.remove('ring-2', 'ring-offset-2', 'ring-wa-deep');
            });
            btn.classList.add('ring-2', 'ring-offset-2', 'ring-wa-deep');
        });
    });

    $('#create-team-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const deviceIds = Array.from(e.target.querySelectorAll('input[name="team_device_ids"]:checked'))
            .map(cb => Number(cb.value)).filter(Number.isFinite);
        const body = {
            name:                fd.get('name'),
            color:               fd.get('color'),
            assignment_strategy: fd.get('assignment_strategy'),
            members:             fd.getAll('members[]').map(Number),
            device_ids:          deviceIds,
        };
        const submitBtn = $('#create-team-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating…';
        try {
            await api('/team-inbox/api/teams', { method: 'POST', body });
            toast('Team created.', 'success');
            closeCreateTeamModal();
            await bootstrap();
        } catch (err) {
            toast('Create team failed: ' + err.message, 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create team';
        }
    });
    // ── Edit team modal ──────────────────────────────────────────────────
    function openEditTeamModal(team) {
        const m = $('#edit-team-modal');
        if (!m) return;
        m.classList.remove('hidden');
        $('#edit-team-id') && ($('#edit-team-id').value = team.id);
        const form = $('#edit-team-form');
        if (!form) return;
        form.querySelector('[name="name"]').value = team.name || '';
        form.querySelector('[name="assignment_strategy"]').value = team.assignment_strategy || 'manual';
        const color = team.color || '#075E54';
        form.querySelector('[name="color"]').value = color;
        $$('#edit-team-color-picker [data-color]').forEach(b => {
            b.classList.remove('ring-2', 'ring-offset-2', 'ring-wa-deep');
            if (b.dataset.color === color) b.classList.add('ring-2', 'ring-offset-2', 'ring-wa-deep');
        });
        renderEditTeamMemberCheckboxes(team);
        renderTeamDeviceCheckboxes('edit-team', Array.isArray(team.device_ids) ? team.device_ids : []);
    }
    function closeEditTeamModal() { $('#edit-team-modal')?.classList.add('hidden'); }

    $$('[data-close-edit-team]').forEach(b => b.addEventListener('click', closeEditTeamModal));

    function renderEditTeamMemberCheckboxes(team) {
        const wrap = $('#edit-team-members-list');
        if (!wrap) return;
        const members = state.members || [];
        const currentMembers = (team.members || []).map(m => m.id || m);
        if (members.length === 0) {
            wrap.innerHTML = `<div class="text-[11.5px] text-ink-500 text-center py-4">No teammates yet.</div>`;
            return;
        }
        wrap.innerHTML = members.map(m => `
            <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-paper-100 cursor-pointer">
              <input type="checkbox" name="members[]" value="${m.id}" class="rounded" ${currentMembers.includes(m.id) ? 'checked' : ''} />
              <span class="w-6 h-6 rounded-full bg-wa-deep text-paper-0 text-[9px] font-semibold grid place-items-center">${escape((m.name||'?').slice(0,2).toUpperCase())}</span>
              <span class="text-[12.5px] flex-1 truncate">${escape(m.name)}</span>
              <span class="text-[10.5px] font-mono text-ink-500">${escape(m.status || 'offline')}</span>
            </label>`).join('');
    }

    $$('#edit-team-color-picker [data-color]').forEach(btn => {
        btn.addEventListener('click', () => {
            const c = btn.dataset.color;
            $('#edit-team-form [name="color"]').value = c;
            $$('#edit-team-color-picker [data-color]').forEach(b => b.classList.remove('ring-2','ring-offset-2','ring-wa-deep'));
            btn.classList.add('ring-2','ring-offset-2','ring-wa-deep');
        });
    });

    $('#edit-team-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const id = parseInt($('#edit-team-id')?.value || '0', 10);
        if (!id) return;
        const fd = new FormData(e.target);
        const deviceIds = Array.from(e.target.querySelectorAll('input[name="team_device_ids"]:checked'))
            .map(cb => Number(cb.value)).filter(Number.isFinite);
        const body = {
            name:                fd.get('name'),
            color:               fd.get('color'),
            assignment_strategy: fd.get('assignment_strategy'),
            members:             fd.getAll('members[]').map(Number),
            device_ids:          deviceIds,
        };
        const submit = $('#edit-team-submit');
        submit.disabled = true; submit.textContent = 'Saving…';
        try {
            await api(`/team-inbox/api/teams/${id}`, { method: 'PATCH', body });
            toast('Team updated.', 'success');
            closeEditTeamModal();
            await bootstrap();
        } catch (err) {
            toast('Update failed: ' + err.message, 'error');
        } finally {
            submit.disabled = false; submit.textContent = 'Save changes';
        }
    });

    $('#edit-team-delete')?.addEventListener('click', async () => {
        const id = parseInt($('#edit-team-id')?.value || '0', 10);
        if (!id) return;
        const team = (state.teams || []).find(t => t.id === id);
        if (!confirm(`Delete team "${team?.name}"? Conversations assigned to this team will become unassigned.`)) return;
        try {
            await api(`/team-inbox/api/teams/${id}`, { method: 'DELETE' });
            toast('Team deleted.', 'success');
            closeEditTeamModal();
            state.teamFilter = null;
            await bootstrap();
        } catch (err) { toast('Delete failed: ' + err.message, 'error'); }
    });

    $('#add-note-btn')?.addEventListener('click', async () => {
        const body = await themedPrompt({
            title: 'Add internal note',
            placeholder: 'Visible to your team only · use @name to ping a teammate (e.g. @sara — please follow up)',
            multiline: true,
            confirmLabel: 'Save note',
        });
        if (body) addNote(body);
    });
    $('#reassign-link')?.addEventListener('click', () => $('#assign-btn')?.click());

    // bulk
    $('#bulk-toggle')?.addEventListener('click', () => {
        state.bulkMode = !state.bulkMode;
        $('#bulk-toggle-label') && ($('#bulk-toggle-label').textContent = state.bulkMode ? 'Cancel' : 'Select');
        if (!state.bulkMode) state.selected.clear();
        updateBulkBar();
        renderQueue();
    });
    $('#bulk-cancel')?.addEventListener('click', () => $('#bulk-toggle')?.click());
    $('#bulk-close')?.addEventListener('click', () => bulkAction('resolve'));
    $('#bulk-tag')?.addEventListener('click', async () => {
        const name = await themedPrompt({
            title: 'Tag selected conversations',
            placeholder: 'e.g. priority · sales · billing',
            confirmLabel: 'Tag all',
        });
        if (!name) return;
        api('/team-inbox/api/tags', { method: 'POST', body: { name } })
            .then(r => bulkAction('tag', { tag_id: r.tag.id }))
            .catch(e => toast('Tag failed: ' + e.message, 'error'));
    });
    $('#bulk-assign')?.addEventListener('click', () => {
        const uid = state.me?.id;
        if (uid) bulkAction('assign', { user_id: uid });
    });
    // WhatsApp-style multi-select actions.
    $('#bulk-pin')?.addEventListener('click', () => bulkAction('pin'));
    $('#bulk-mute')?.addEventListener('click', () => bulkAction('mute'));
    $('#bulk-archive')?.addEventListener('click', () => bulkAction('archive'));
    $('#bulk-delete')?.addEventListener('click', async () => {
        if (state.selected.size === 0) return;
        const n = state.selected.size;
        const ok = await themedChoice({
            title: `Delete ${n} conversation${n > 1 ? 's' : ''}?`,
            body: 'This permanently deletes the selected chats and their messages. It cannot be undone.',
            options: [{ value: 'yes', label: 'Delete permanently', variant: 'danger' }],
        });
        if (ok === 'yes') bulkAction('delete');
    });

    // "Archived" row → toggle the archived view.
    $('#archived-row')?.addEventListener('click', () => {
        state.archivedView = !state.archivedView;
        state._lastQueueSig = null;   // force a re-render on view switch
        renderArchivedRow();
        loadQueue();
    });

    // Label filter dropdown → narrow the queue to one tag.
    $('#inbox-label-filter')?.addEventListener('change', (e) => {
        state.labelFilter = e.target.value || null;
        state._lastQueueSig = null;
        loadQueue();
    });

    function updateBulkBar() {
        const bar = $('#bulk-bar');
        if (!bar) return;
        bar.classList.toggle('hidden', state.selected.size === 0);
        $('#bulk-count') && ($('#bulk-count').textContent = state.selected.size);
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    function escape(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }

    // Colours come from user-editable team/agent settings that are validated
    // server-side only as a free-form string (max:16). When they are spliced
    // into a style="…" attribute they become a stored-XSS attribute-breakout
    // vector (e.g. `"onclick=…`). Allow-list strict CSS colours (hex or the
    // handful of safe functional/named forms) and fall back to a neutral
    // default so a malicious value can never break out of the attribute.
    function safeColor(v, fallback = '#6366f1') {
        const s = String(v ?? '').trim();
        if (/^#[0-9a-fA-F]{3,8}$/.test(s)) return s;
        if (/^rgba?\(\s*[0-9.,%\s]+\)$/.test(s)) return s;
        if (/^hsla?\(\s*[0-9.,%\s]+\)$/.test(s)) return s;
        if (/^[a-zA-Z]{1,20}$/.test(s)) return s; // named colours (red, teal, …)
        return fallback;
    }

    // Substitute {{device_phone}} and {{device_name}} from the open
    // conversation's pinned device. Lets one saved reply / quick
    // reply work across every paired number in the workspace.
    // Unknown placeholders fall back to an empty string so the
    // pattern doesn't leak into the composer if the convo has no
    // device (shouldn't happen in practice, but defensive).
    function expandDevicePlaceholders(text) {
        if (!text || typeof text !== 'string') return text;
        const active = state.active;
        const devId  = active?.device_id ?? null;
        const dev    = devId ? (state.devices || []).find(d => Number(d.id) === Number(devId)) : null;
        const phone  = dev?.phone || '';
        const label  = dev?.label || '';
        return text
            .replace(/\{\{\s*device_phone\s*\}\}/g, phone)
            .replace(/\{\{\s*device_name\s*\}\}/g, label);
    }
    function formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        const now = new Date();
        if (isSameDay(d, now)) return d.toTimeString().slice(0,5);
        const diffDays = Math.round((now - d) / 86400000);
        if (diffDays < 7) return d.toLocaleDateString(undefined, { weekday: 'short' });
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }
    function isSameDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }
    function humanDuration(iso) {
        const ms = new Date(iso).getTime() - Date.now();
        if (ms <= 0) return 'breached';
        const m = Math.floor(ms / 60000);
        if (m < 60) return `${m}m`;
        const h = Math.floor(m / 60);
        if (h < 24) return `${h}h ${m % 60}m`;
        return `${Math.floor(h / 24)}d`;
    }
    function parseSnoozeTarget(s) {
        const m = String(s).match(/^(\d+)([hmd])$/i);
        if (m) {
            const n = parseInt(m[1], 10);
            const unit = m[2].toLowerCase();
            const ms = unit === 'h' ? n * 3600e3 : unit === 'm' ? n * 60e3 : n * 86400e3;
            return new Date(Date.now() + ms).toISOString();
        }
        const d = new Date(s);
        return isNaN(d) ? null : d.toISOString();
    }
    function debounce(fn, ms) {
        let t;
        return function (...args) { clearTimeout(t); t = setTimeout(() => fn.apply(this, args), ms); };
    }

    // ── Routing Rules ──────────────────────────────────────────────────────
    // Built lazily by conditionFields() so the incoming_device option
    // only appears when the workspace has more than one paired device
    // (single-device workspaces never need to route by device).
    const BASE_CONDITION_FIELDS = [
        { v: 'message_text',           l: 'Message text' },
        { v: 'contact_phone',          l: 'Contact phone' },
        { v: 'channel',                l: 'Channel' },
        { v: 'priority',               l: 'Priority' },
        { v: 'time_of_day',            l: 'Hour of day (0–23)' },
        { v: 'day_of_week',            l: 'Day of week (0=Sun)' },
        { v: 'language',               l: 'Language' },
        { v: 'outside_business_hours', l: 'Outside business hours' },
    ];
    function conditionFields() {
        const fields = BASE_CONDITION_FIELDS.slice();
        if ((state.devices || []).length > 1) {
            fields.push({ v: 'incoming_device', l: 'Incoming device (number)' });
        }
        return fields;
    }
    const CONDITION_OPS = [
        { v: 'equals',       l: '= equals' },
        { v: 'not_equals',   l: '≠ not equals' },
        { v: 'contains',     l: 'contains' },
        { v: 'not_contains', l: 'not contains' },
        { v: 'starts_with',  l: 'starts with' },
        { v: 'ends_with',    l: 'ends with' },
        { v: 'matches',      l: 'matches regex' },
        { v: 'gt',           l: '> greater' },
        { v: 'gte',          l: '>= ≥' },
        { v: 'lt',           l: '< less' },
        { v: 'lte',          l: '<= ≤' },
    ];
    const ACTION_TYPES = [
        { v: 'assign_team',    l: 'Assign to team' },
        { v: 'assign_agent',   l: 'Assign AI agent' },
        { v: 'set_priority',   l: 'Set priority' },
        { v: 'add_tag',        l: 'Add tag' },
        { v: 'auto_reply',     l: 'Auto-reply (template or text)' },
        { v: 'trigger_flow',   l: 'Trigger flow (records pending intent)' },
        { v: 'mark_spam',      l: 'Mark as spam' },
        { v: 'set_escalation', l: 'Escalate if no reply' },
    ];

    function openRoutingModal() {
        $('#routing-modal')?.classList.remove('hidden');
        renderRulesList();
    }
    function closeRoutingModal() { $('#routing-modal')?.classList.add('hidden'); }

    async function refreshRoutingRules() {
        const data = await api('/team-inbox/api/routing');
        state.routingRules = Array.isArray(data) ? data : [];
        updateNavRoutingCount();
    }

    function renderRulesList() {
        const list = $('#routing-rules-list');
        if (!list) return;
        const rules = state.routingRules || [];
        if (rules.length === 0) {
            list.innerHTML = `<div class="text-[12px] text-ink-500 text-center py-8">No routing rules yet. Click "Add rule" to create your first one.</div>`;
            return;
        }
        list.innerHTML = rules.map(r => `
            <div class="bg-paper-0 border border-paper-200 rounded-xl p-3 flex items-start gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-semibold text-[13px] truncate">${escape(r.name)}</span>
                        <span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono ${r.is_active ? 'bg-wa-mint/40 text-wa-deep' : 'bg-paper-100 text-ink-500'}">${r.is_active ? 'active' : 'off'}</span>
                        ${r.stop_on_match ? `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono bg-accent-amber/20 text-[#7B5A14]">stop</span>` : ''}
                        ${r.is_fallback ? `<span class="px-1.5 py-0.5 rounded text-[9.5px] font-mono bg-[#5B3D8A]/15 text-[#5B3D8A]">fallback</span>` : ''}
                    </div>
                    <div class="text-[11px] text-ink-500 mt-1">${(r.conditions||[]).length} condition${(r.conditions||[]).length===1?'':'s'} · ${(r.actions||[]).length} action${(r.actions||[]).length===1?'':'s'}</div>
                    ${r.fired_count ? `<div class="font-mono text-[9.5px] text-ink-400 mt-0.5">Fired ${r.fired_count}× · last ${formatTime(r.last_fired_at)}</div>` : ''}
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button data-edit-rule="${r.id}" class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-600" title="Edit">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 2l3 3-8 8H3v-3l8-8z"/></svg>
                    </button>
                    <button data-toggle-rule="${r.id}" class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-600" title="${r.is_active ? 'Disable' : 'Enable'}">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5">${r.is_active ? '<path d="M4 4l8 8M12 4l-8 8"/>' : '<path d="M3 8l3 3 7-7"/>'}</svg>
                    </button>
                    <button data-delete-rule="${r.id}" class="w-7 h-7 rounded-full hover:bg-accent-coral/10 grid place-items-center text-accent-coral" title="Delete">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10M6 4V2h4v2M5 4l.5 9h5l.5-9"/></svg>
                    </button>
                </div>
            </div>`).join('');

        list.querySelectorAll('[data-edit-rule]').forEach(b => {
            b.addEventListener('click', () => {
                const rule = (state.routingRules||[]).find(r => r.id === parseInt(b.dataset.editRule, 10));
                if (rule) openRoutingForm(rule);
            });
        });
        list.querySelectorAll('[data-toggle-rule]').forEach(b => {
            b.addEventListener('click', async () => {
                const id = parseInt(b.dataset.toggleRule, 10);
                const rule = (state.routingRules||[]).find(r => r.id === id);
                try {
                    await api(`/team-inbox/api/routing/${id}`, { method: 'PATCH', body: { is_active: !rule?.is_active } });
                    await refreshRoutingRules();
                    renderRulesList();
                    toast(rule?.is_active ? 'Rule disabled.' : 'Rule enabled.', 'success');
                } catch (e) { toast('Failed: ' + e.message, 'error'); }
            });
        });
        list.querySelectorAll('[data-delete-rule]').forEach(b => {
            b.addEventListener('click', async () => {
                const id = parseInt(b.dataset.deleteRule, 10);
                const rule = (state.routingRules||[]).find(r => r.id === id);
                if (!confirm(`Delete rule "${rule?.name}"?`)) return;
                try {
                    await api(`/team-inbox/api/routing/${id}`, { method: 'DELETE' });
                    await refreshRoutingRules();
                    renderRulesList();
                    toast('Rule deleted.', 'success');
                } catch (e) { toast('Delete failed: ' + e.message, 'error'); }
            });
        });
    }

    function openRoutingForm(rule = null) {
        const m = $('#routing-form-modal');
        if (!m) return;
        m.classList.remove('hidden');
        $('#routing-form-title') && ($('#routing-form-title').textContent = rule ? 'Edit rule' : 'Create rule');
        $('#routing-form-id') && ($('#routing-form-id').value = rule?.id || '');
        const form = $('#routing-rule-form');
        if (!form) return;
        form.querySelector('[name="name"]').value = rule?.name || '';
        form.querySelector('[name="stop_on_match"]').checked = !!rule?.stop_on_match;
        form.querySelector('[name="is_fallback"]').checked   = !!rule?.is_fallback;
        form.querySelector('[name="is_active"]').checked = rule ? !!rule.is_active : true;
        const condList = $('#rule-conditions');
        if (condList) { condList.innerHTML = ''; (rule?.conditions?.length ? rule.conditions : [{}]).forEach(c => addConditionRow(c)); }
        const actList = $('#rule-actions');
        if (actList) { actList.innerHTML = ''; (rule?.actions?.length ? rule.actions : [{}]).forEach(a => addActionRow(a)); }
    }
    function closeRoutingForm() { $('#routing-form-modal')?.classList.add('hidden'); }

    function addConditionRow(c = {}) {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 flex-wrap';
        const fields = conditionFields();
        const fOpts = fields.map(f => `<option value="${f.v}" ${c.field===f.v?'selected':''}>${f.l}</option>`).join('');
        const oOpts = CONDITION_OPS.map(o => `<option value="${o.v}" ${c.op===o.v?'selected':''}>${o.l}</option>`).join('');

        // outside_business_hours is a boolean check — render a yes/no
        // dropdown so non-tech operators don't have to type "true"/"false".
        // incoming_device is a multi-pick of paired devices — render a
        // <select multiple> so the operator can route by one OR more
        // numbers without typing comma-separated ids.
        const buildCondValue = (field, val) => {
            if (field === 'outside_business_hours') {
                const truthy = String(val) === 'true' || val === true || val === 1 || val === '1';
                return `<select class="cond-value px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep flex-1 min-w-[100px]">
                    <option value="true"  ${truthy?'selected':''}>Yes (outside hours)</option>
                    <option value="false" ${!truthy?'selected':''}>No (inside hours)</option>
                </select>`;
            }
            if (field === 'incoming_device') {
                const picked = Array.isArray(val) ? val.map(String) : (val != null ? [String(val)] : []);
                const opts = (state.devices || []).map(d =>
                    `<option value="${d.id}" ${picked.includes(String(d.id))?'selected':''}>${escape(d.label)} · ${escape(d.phone)}</option>`
                ).join('');
                return `<select class="cond-value cond-value-multi flex-1 min-w-[180px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" multiple size="3">${opts}</select>`;
            }
            return `<input type="text" class="cond-value px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep flex-1 min-w-[100px]" placeholder="value" value="${escape(val||'')}"/>`;
        };

        const initialField = c.field || fields[0].v;
        row.innerHTML = `
            <select class="cond-field px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep flex-1 min-w-[140px]">${fOpts}</select>
            <select class="cond-op px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">${oOpts}</select>
            <div class="cond-value-wrap flex-1 min-w-[100px]">${buildCondValue(initialField, c.value)}</div>
            <button type="button" class="rm-cond w-6 h-6 rounded-full hover:bg-accent-coral/10 grid place-items-center text-accent-coral shrink-0">×</button>`;
        row.querySelector('.cond-field').addEventListener('change', e => {
            const w = row.querySelector('.cond-value-wrap');
            if (w) w.innerHTML = buildCondValue(e.target.value, '');
        });
        row.querySelector('.rm-cond').addEventListener('click', () => row.remove());
        $('#rule-conditions')?.appendChild(row);
    }

    function addActionRow(a = {}) {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2 flex-wrap';
        const tOpts = ACTION_TYPES.map(t => `<option value="${t.v}" ${a.type===t.v?'selected':''}>${t.l}</option>`).join('');

        const buildValInput = (type, val) => {
            if (type === 'assign_team') {
                const opts = (state.teams||[]).map(t => `<option value="${t.id}" ${String(val)===String(t.id)?'selected':''}>${escape(t.name)}</option>`).join('');
                return `<select class="act-value flex-1 min-w-[150px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep"><option value="">— team —</option>${opts}</select>`;
            }
            if (type === 'assign_agent') {
                const opts = (state.aiAgents||[]).map(ag => `<option value="${ag.id}" ${String(val)===String(ag.id)?'selected':''}>${escape(ag.name)}</option>`).join('');
                return `<select class="act-value flex-1 min-w-[150px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep"><option value="">— agent —</option>${opts}</select>`;
            }
            if (type === 'set_priority') {
                return `<select class="act-value flex-1 min-w-[120px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">${['low','normal','high','urgent'].map(p=>`<option value="${p}" ${val===p?'selected':''}>${p}</option>`).join('')}</select>`;
            }
            if (type === 'mark_spam') {
                return `<span class="text-[11.5px] text-ink-500 flex-1">(no value needed)</span><input type="hidden" class="act-value" value="1">`;
            }
            if (type === 'set_escalation') {
                // val is the existing action object when editing, '' on new.
                // Pulls minutes + then_action.team_id back into the form
                // so editing a saved rule doesn't lose the target team.
                const minutes  = (val && typeof val === 'object') ? (val.minutes || 5) : (parseInt(val, 10) || 5);
                const savedTid = (val && typeof val === 'object' && val.then_action && val.then_action.team_id)
                    ? String(val.then_action.team_id) : '';
                const teamOpts = (state.teams||[]).map(t => `<option value="${t.id}" ${savedTid===String(t.id)?'selected':''}>${escape(t.name)}</option>`).join('');
                return `<input type="number" min="1" max="1440" class="act-value w-20 px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" placeholder="min" value="${minutes}"/>
                    <span class="text-[10.5px] text-ink-500">min then →</span>
                    <select class="act-escalate-team flex-1 min-w-[120px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                        <option value="">— pick team —</option>${teamOpts}
                    </select>`;
            }
            if (type === 'auto_reply') {
                // val is the action object when editing (template_id + body).
                const savedTplId = (val && typeof val === 'object') ? String(val.template_id || '') : '';
                const savedBody  = (val && typeof val === 'object') ? (val.body || '') : '';
                const tplOpts = (state.templates||[]).map(t => `<option value="${t.id}" ${savedTplId===String(t.id)?'selected':''}>${escape(t.template_name)} · ${escape((t.language||'').toUpperCase())}</option>`).join('');
                return `<select class="act-value act-reply-template flex-1 min-w-[150px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                        <option value="">— pick template (optional) —</option>${tplOpts}
                    </select>
                    <input type="text" class="act-reply-body flex-[2] min-w-[180px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" placeholder="or free text…" value="${escape(savedBody)}"/>`;
            }
            if (type === 'trigger_flow') {
                const savedFlowId = (val && typeof val === 'object') ? String(val.flow_id || '') : String(val || '');
                const flowOpts = (state.flows||[]).map(f => `<option value="${f.id}" ${savedFlowId===String(f.id)?'selected':''}>${escape(f.flow_name)}</option>`).join('');
                if (!(state.flows||[]).length) {
                    return `<input type="hidden" class="act-value" value=""><span class="text-[11.5px] text-ink-500 flex-1">No active flows — build one in <a href="/flows" class="text-wa-deep underline">/flows</a></span>`;
                }
                return `<select class="act-value flex-1 min-w-[150px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep">
                        <option value="">— pick flow —</option>${flowOpts}
                    </select>`;
            }
            return `<input type="text" class="act-value flex-1 min-w-[150px] px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep" placeholder="${type==='add_tag'?'tag name':'value'}" value="${escape(val||'')}"/>`;
        };

        // Default type to the first action when adding a brand-new row,
        // so the value-input matches the dropdown's auto-selected option.
        const initialType = a.type || ACTION_TYPES[0].v;
        // For multi-field actions (escalation, auto_reply, trigger_flow)
        // we pass the whole `a` so the form can rehydrate every field
        // (minutes + team, template + body, flow_id).
        const isComposite = ['set_escalation', 'auto_reply', 'trigger_flow'].includes(initialType);
        const initialVal  = isComposite
            ? a
            : (a.team_id || a.agent_id || a.value || a.name || '');
        row.innerHTML = `
            <select class="act-type px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12px] focus:outline-none focus:border-wa-deep flex-1 min-w-[150px]">${tOpts}</select>
            <div class="act-value-wrap flex-1 min-w-[150px]">${buildValInput(initialType, initialVal)}</div>
            <button type="button" class="rm-act w-6 h-6 rounded-full hover:bg-accent-coral/10 grid place-items-center text-accent-coral shrink-0">×</button>`;
        row.querySelector('.act-type').addEventListener('change', e => {
            const vw = row.querySelector('.act-value-wrap');
            if (vw) vw.innerHTML = buildValInput(e.target.value, '');
        });
        row.querySelector('.rm-act').addEventListener('click', () => row.remove());
        $('#rule-actions')?.appendChild(row);
    }

    $$('[data-close-routing]').forEach(b => b.addEventListener('click', closeRoutingModal));
    $$('[data-close-routing-form]').forEach(b => b.addEventListener('click', closeRoutingForm));
    $('#nav-routing')?.addEventListener('click', openRoutingModal);
    // Empty-state feature tiles use data-open-modal to re-trigger the
    // sidebar modal openers without duplicating the handler logic.
    $$('[data-open-modal]').forEach(el => {
        el.addEventListener('click', () => {
            const target = el.dataset.openModal;
            const btn = target ? document.getElementById(target) : null;
            if (btn) btn.click();
        });
    });
    $('#routing-add-btn')?.addEventListener('click', () => openRoutingForm(null));

    // ── Business Hours ──────────────────────────────────────────────────────
    const BH_DAYS = [['mon','Monday'], ['tue','Tuesday'], ['wed','Wednesday'], ['thu','Thursday'], ['fri','Friday'], ['sat','Saturday'], ['sun','Sunday']];

    // Common IANA zones — focused on regions WaDesk workspaces actually
    // operate in. Add more as customers ask; an exhaustive list would
    // bloat the DOM with ~600 options for no real win.
    const TZ_OPTIONS = [
        'UTC',
        'Asia/Kolkata', 'Asia/Karachi', 'Asia/Dhaka', 'Asia/Colombo', 'Asia/Kathmandu',
        'Asia/Dubai', 'Asia/Riyadh', 'Asia/Tehran',
        'Asia/Singapore', 'Asia/Bangkok', 'Asia/Jakarta',
        'Asia/Manila', 'Asia/Tokyo', 'Asia/Seoul', 'Asia/Hong_Kong', 'Asia/Shanghai',
        'Europe/London', 'Europe/Berlin', 'Europe/Paris', 'Europe/Madrid', 'Europe/Moscow',
        'Africa/Cairo', 'Africa/Lagos', 'Africa/Johannesburg', 'Africa/Nairobi',
        'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles',
        'America/Mexico_City', 'America/Toronto', 'America/Sao_Paulo', 'America/Buenos_Aires',
        'Australia/Sydney', 'Australia/Melbourne', 'Australia/Perth', 'Pacific/Auckland',
    ];

    function renderBusinessHoursForm(bh, timezone) {
        const tzEl = $('#bh-timezone');
        if (tzEl) {
            const current = String(timezone || 'UTC');
            // Make sure the workspace's current TZ is in the option set
            // even if it's outside our curated list (don't silently
            // change the user's saved value when they open the modal).
            const opts = TZ_OPTIONS.includes(current) ? TZ_OPTIONS : [current, ...TZ_OPTIONS];
            tzEl.innerHTML = opts.map(tz => `<option value="${tz}"${tz === current ? ' selected' : ''}>${tz}</option>`).join('');
        }

        // Day rows
        const wrap = $('#bh-days');
        if (wrap) {
            wrap.innerHTML = '';
            BH_DAYS.forEach(([k, label]) => {
                const d = (bh?.days || {})[k] || { enabled: true, from: '09:00', to: '18:00' };
                const row = document.createElement('div');
                row.dataset.day = k;
                row.className = 'flex items-center gap-2 text-[12.5px]';
                row.innerHTML = `
                    <label class="flex items-center gap-2 w-32">
                        <input type="checkbox" class="bh-enabled w-4 h-4 rounded border-paper-200 accent-wa-deep" ${d.enabled?'checked':''}>
                        <span class="text-ink-700">${label}</span>
                    </label>
                    <input type="time" class="bh-from px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" value="${d.from || '09:00'}">
                    <span class="text-ink-500">→</span>
                    <input type="time" class="bh-to px-2 py-1 border border-paper-200 rounded-lg bg-paper-0 text-[12.5px] focus:outline-none focus:border-wa-deep" value="${d.to || '18:00'}">`;
                wrap.appendChild(row);
            });
        }

        // Outside-hours action + template select
        const act = $('#bh-outside-action');
        const tpl = $('#bh-outside-template');
        if (act) act.value = bh?.outside_action || 'none';
        if (tpl) {
            const tplOpts = (state.templates || []).map(t => `<option value="${t.id}">${escape(t.template_name)} · ${escape((t.language||'').toUpperCase())}</option>`).join('');
            tpl.innerHTML = '<option value="">— pick template —</option>' + tplOpts;
            tpl.value = String(bh?.outside_template_id || '');
            tpl.classList.toggle('hidden', (bh?.outside_action || 'none') !== 'template');
        }
        act?.addEventListener('change', () => {
            tpl?.classList.toggle('hidden', act.value !== 'template');
        });

        // Anti-spam controls — read from existing config or fall back
        // to the AutoReplyGuard service defaults (720 / 20).
        const cd  = $('#bh-cooldown');
        const sp  = $('#bh-spam-threshold');
        if (cd) cd.value = String(bh?.auto_reply_cooldown_min ?? 720);
        if (sp) sp.value = String(bh?.spam_threshold_msgs    ?? 20);
    }

    async function openBusinessHours() {
        $('#business-hours-modal')?.classList.remove('hidden');
        try {
            const data = await api('/team-inbox/api/business-hours');
            renderBusinessHoursForm(data.business_hours, data.timezone);
        } catch (e) {
            toast('Failed to load business hours', 'error');
        }
    }
    function closeBusinessHours() { $('#business-hours-modal')?.classList.add('hidden'); }
    $$('[data-close-business-hours]').forEach(b => b.addEventListener('click', closeBusinessHours));
    $('#business-hours-btn')?.addEventListener('click', openBusinessHours);

    $('#business-hours-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const days = {};
        $$('#bh-days > div').forEach(row => {
            days[row.dataset.day] = {
                enabled: row.querySelector('.bh-enabled')?.checked || false,
                from:    row.querySelector('.bh-from')?.value || '09:00',
                to:      row.querySelector('.bh-to')?.value || '18:00',
            };
        });
        const outsideAction = $('#bh-outside-action')?.value || 'none';
        const outsideTplId  = parseInt($('#bh-outside-template')?.value || '0', 10) || null;
        const cooldown      = Math.max(1, parseInt($('#bh-cooldown')?.value || '720', 10));
        const spamThreshold = Math.max(2, parseInt($('#bh-spam-threshold')?.value || '20', 10));
        const timezone      = $('#bh-timezone')?.value || 'UTC';
        const payload = {
            timezone,
            business_hours: {
                days,
                outside_action: outsideAction,
                outside_template_id: outsideAction === 'template' ? outsideTplId : null,
                auto_reply_cooldown_min: cooldown,
                spam_threshold_msgs: spamThreshold,
            },
        };
        try {
            const res = await api('/team-inbox/api/business-hours', { method: 'POST', body: payload });
            if (res?.ok) {
                state.businessHours = res.business_hours;
                toast('Business hours saved', 'ok');
                closeBusinessHours();
            } else {
                toast('Save failed', 'error');
            }
        } catch (e) {
            toast('Save failed', 'error');
        }
    });
    $('#add-condition-btn')?.addEventListener('click', () => addConditionRow());
    $('#add-action-btn')?.addEventListener('click', () => addActionRow());

    $('#routing-rule-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const id = parseInt($('#routing-form-id')?.value || '0', 10);
        const form = e.target;
        const conditions = $$('#rule-conditions > div').map(row => {
            const valueEl = row.querySelector('.cond-value');
            // The incoming_device condition renders a <select multiple>,
            // so .value only gives the first option. Detect the multi
            // case (cond-value-multi class) and pluck all selected
            // option values as an array of numeric ids — the engine's
            // `in`/`not_in` operators consume arrays natively.
            let value;
            if (valueEl?.classList?.contains('cond-value-multi') || valueEl?.multiple) {
                value = Array.from(valueEl.selectedOptions || []).map(o => {
                    const n = Number(o.value);
                    return Number.isFinite(n) ? n : o.value;
                });
            } else {
                value = valueEl?.value;
            }
            return {
                field: row.querySelector('.cond-field')?.value,
                op:    row.querySelector('.cond-op')?.value,
                value,
            };
        }).filter(c => c.field);
        const actions = $$('#rule-actions > div').map(row => {
            const type = row.querySelector('.act-type')?.value;
            const val  = row.querySelector('.act-value')?.value;
            const a = { type };
            if (type === 'assign_team')  a.team_id  = parseInt(val, 10) || null;
            else if (type === 'assign_agent') a.agent_id = parseInt(val, 10) || null;
            else if (type === 'set_priority') a.value = val;
            else if (type === 'add_tag') a.name = val;
            else if (type === 'auto_reply') {
                // act-value here is the template <select>, separate input
                // holds the optional free-text body. Either or both can be set;
                // the engine prefers template_id > body.
                a.template_id = parseInt(val, 10) || null;
                a.body        = (row.querySelector('.act-reply-body')?.value || '').trim();
            }
            else if (type === 'trigger_flow') {
                a.flow_id = parseInt(val, 10) || null;
            }
            else if (type === 'set_escalation') {
                const teamId = parseInt(row.querySelector('.act-escalate-team')?.value || '0', 10);
                a.minutes = parseInt(val, 10) || 5;
                a.then_action = teamId ? { type: 'assign_team', team_id: teamId } : null;
            }
            return a;
        }).filter(a => a.type);
        // Validate: every action must have its required value (team/agent/tag).
        // Without this the server stores a half-baked action that never fires.
        let invalidMsg = null;
        for (const a of actions) {
            if (a.type === 'assign_team'  && !a.team_id)  { invalidMsg = 'assign_team needs a team'; break; }
            if (a.type === 'assign_agent' && !a.agent_id) { invalidMsg = 'assign_agent needs an AI agent'; break; }
            if (a.type === 'add_tag'      && !a.name)     { invalidMsg = 'add_tag needs a tag name'; break; }
            if (a.type === 'auto_reply'   && !a.template_id && !a.body) {
                invalidMsg = 'auto_reply needs a template or some free text'; break;
            }
            if (a.type === 'trigger_flow' && !a.flow_id) {
                invalidMsg = 'trigger_flow needs a flow'; break;
            }
            if (a.type === 'set_escalation' && (!a.minutes || !a.then_action)) {
                invalidMsg = 'escalation needs a minutes value and a target team'; break;
            }
        }
        if (invalidMsg) {
            toast('Incomplete action: ' + invalidMsg + '.', 'error');
            return;
        }
        if (conditions.length === 0) {
            toast('Add at least one condition.', 'error');
            return;
        }
        if (actions.length === 0) {
            toast('Add at least one action.', 'error');
            return;
        }

        const body = {
            name:          form.querySelector('[name="name"]')?.value || '',
            conditions,
            actions,
            stop_on_match: form.querySelector('[name="stop_on_match"]')?.checked ? 1 : 0,
            is_fallback:   form.querySelector('[name="is_fallback"]')?.checked ? 1 : 0,
            is_active:     form.querySelector('[name="is_active"]')?.checked ? 1 : 0,
        };
        const submit = $('#routing-form-submit');
        submit.disabled = true; submit.textContent = 'Saving…';
        try {
            if (id) { await api(`/team-inbox/api/routing/${id}`, { method: 'PATCH', body }); }
            else     { await api('/team-inbox/api/routing', { method: 'POST', body }); }
            await refreshRoutingRules();
            renderRulesList();
            closeRoutingForm();
            toast('Rule saved.', 'success');
        } catch (err) {
            toast('Save failed: ' + err.message, 'error');
        } finally {
            submit.disabled = false; submit.textContent = 'Save rule';
        }
    });

    function updateNavRoutingCount() {
        const el = $('#nav-routing-count');
        if (!el) return;
        const n = (state.routingRules || []).filter(r => r.is_active).length;
        el.textContent = n;
        el.style.display = n > 0 ? '' : 'none';
    }

    // ── Team Performance Stats ─────────────────────────────────────────────
    let statsRefreshTimer = null;

    function openStatsModal() {
        $('#stats-modal')?.classList.remove('hidden');
        loadStats();
        statsRefreshTimer = setInterval(loadStats, 30000);
    }
    function closeStatsModal() {
        $('#stats-modal')?.classList.add('hidden');
        clearInterval(statsRefreshTimer);
        statsRefreshTimer = null;
    }

    async function loadStats() {
        try {
            const data = await api('/team-inbox/api/stats');
            renderStats(data);
        } catch (e) { toast('Stats error: ' + e.message, 'error'); }
    }

    function renderStats(data) {
        const q = data.queue || {};
        const set = (id, val) => { const el = $(id); if (el) el.textContent = val ?? '—'; };
        set('#stat-open',       q.open);
        set('#stat-awaiting',   q.awaiting_reply);
        set('#stat-unassigned', q.unassigned);
        set('#stat-sla',        q.sla_breached);
        set('#stat-resolved',   q.resolved_today);
        const fr = data.avg_first_response_minutes;
        set('#stat-frt', fr != null ? (fr < 60 ? `${fr}m` : `${Math.floor(fr/60)}h ${fr%60}m`) : null);

        const tbody = $('#stats-agent-tbody');
        if (!tbody) return;
        const agents = data.agents || [];
        if (agents.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-3 py-4 text-center text-[11.5px] text-ink-500">No agent records yet.</td></tr>`;
        } else {
            const sc = { online:'text-wa-deep', away:'text-accent-amber', busy:'text-accent-coral', offline:'text-ink-400' };
            tbody.innerHTML = agents.map(a => `
                <tr class="border-t border-paper-200">
                    <td class="px-3 py-2 text-[12.5px] font-medium">${escape(a.name)}</td>
                    <td class="px-3 py-2"><span class="font-mono text-[11px] ${sc[a.status]||'text-ink-500'}">${escape(a.status||'unknown')}</span></td>
                    <td class="px-3 py-2 text-center font-mono text-[12px]">${a.current_load ?? 0}</td>
                    <td class="px-3 py-2 text-center font-mono text-[12px]">${a.today_replies ?? 0}</td>
                    <td class="px-3 py-2 text-center font-mono text-[12px]">${a.today_resolutions ?? 0}</td>
                    <td class="px-3 py-2 font-mono text-[10px] text-ink-500">${a.last_seen_at ? formatTime(a.last_seen_at) : '—'}</td>
                </tr>`).join('');
        }
        $('#stats-updated') && ($('#stats-updated').textContent = 'Updated ' + new Date().toLocaleTimeString());
    }

    $$('[data-close-stats]').forEach(b => b.addEventListener('click', closeStatsModal));
    $('#nav-stats')?.addEventListener('click', openStatsModal);

    // ── Saved Replies Picker ───────────────────────────────────────────────
    const savedRepliesPicker = {
        open() {
            const panel = $('#quick-reply-panel');
            if (!panel) return;
            panel.classList.remove('hidden');
            this.render('');
            $('#qr-search-input')?.focus();
        },
        close() { $('#quick-reply-panel')?.classList.add('hidden'); },
        render(q = '') {
            const list = $('#quick-reply-list');
            if (!list) return;
            const replies = (state.savedReplies || []).filter(r => {
                if (!q) return true;
                const lq = q.toLowerCase();
                return (r.shortcut||'').toLowerCase().includes(lq)
                    || (r.title||'').toLowerCase().includes(lq)
                    || (r.body||'').toLowerCase().includes(lq);
            });
            // Inline "Create new shortcut" so the operator can build one
            // without leaving the composer. Opens the same modal as the
            // sidebar "Quick Replies" button.
            const createBtn = `<button type="button" data-create-shortcut class="w-full text-left px-3 py-2 hover:bg-wa-mint/30 border-b border-paper-200 flex items-center gap-2 text-wa-deep">
                <span class="w-5 h-5 rounded-full bg-wa-deep text-paper-0 grid place-items-center text-[10px] font-bold shrink-0">+</span>
                <span class="text-[12px] font-semibold">Create new shortcut</span>
                <span class="ml-auto text-[10px] text-ink-500 font-mono">${(state.savedReplies||[]).length} total</span>
            </button>`;
            if (replies.length === 0) {
                list.innerHTML = createBtn +
                    `<div class="px-3 py-3 text-[11.5px] text-ink-500 text-center">${q ? 'No shortcut matches "' + escape(q) + '".' : 'No shortcuts yet — click above to make your first one.'}</div>`;
                list.querySelector('[data-create-shortcut]')?.addEventListener('click', () => {
                    this.close();
                    openSavedReplyForm(null);
                });
                return;
            }
            list.innerHTML = createBtn + replies.map(r => `
                <button type="button" data-use-reply="${r.id}" class="w-full text-left px-3 py-2 hover:bg-paper-50 border-b border-paper-200 last:border-0">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-[10px] bg-wa-mint/40 text-wa-deep px-1.5 py-0.5 rounded">/${escape(r.shortcut)}</span>
                        <span class="text-[12px] font-medium truncate">${escape(r.title)}</span>
                    </div>
                    <div class="text-[11.5px] text-ink-500 mt-0.5 truncate">${escape((r.body||'').slice(0, 80))}</div>
                </button>`).join('');
            // Wire the create button here too (for the non-empty case).
            list.querySelector('[data-create-shortcut]')?.addEventListener('click', () => {
                this.close();
                openSavedReplyForm(null);
            });
            list.querySelectorAll('[data-use-reply]').forEach(b => {
                b.addEventListener('click', () => {
                    const id = parseInt(b.dataset.useReply, 10);
                    const reply = (state.savedReplies||[]).find(r => r.id === id);
                    if (reply?.body) {
                        // Multi-device placeholder expansion. Replaces
                        // {{device_phone}} and {{device_name}} with the
                        // ACTIVE conversation's pinned device. Lets one
                        // saved reply work across all paired numbers —
                        // e.g. "— Sales, {{device_phone}}" expands to
                        // whichever number the customer is talking to.
                        const expanded = expandDevicePlaceholders(reply.body);
                        const composer = $('#composer');
                        const rich = $('#composer-rich');
                        if (composer) composer.value = expanded;
                        if (rich) rich.value = expanded;
                        if (composer) composer.dispatchEvent(new Event('input', { bubbles: true }));
                        // Bump usage so the bootstrap sort surfaces this
                        // reply higher next time. Fire-and-forget — we
                        // don't block the UX on it.
                        api(`/team-inbox/api/saved-replies/${id}/used`, { method: 'POST' })
                            .then(data => {
                                if (data?.used_count != null) {
                                    const idx = state.savedReplies.findIndex(x => x.id === id);
                                    if (idx >= 0) state.savedReplies[idx].used_count = data.used_count;
                                }
                            })
                            .catch(() => {});
                    }
                    this.close();
                    toast(`Quick reply loaded.`, 'success');
                });
            });
        },
    };

    $('#quick-reply-picker-btn')?.addEventListener('click', () => {
        const panel = $('#quick-reply-panel');
        if (panel?.classList.contains('hidden')) savedRepliesPicker.open();
        else savedRepliesPicker.close();
    });

    $('#qr-close-btn')?.addEventListener('click', () => savedRepliesPicker.close());

    $('#qr-search-input')?.addEventListener('input', e => {
        const v = e.target.value;
        savedRepliesPicker.render(v.startsWith('/') ? v.slice(1) : v);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('#quick-reply-panel') && !e.target.closest('#quick-reply-picker-btn')) {
            savedRepliesPicker.close();
        }
    });

    // "/" as first character in composer triggers picker
    document.querySelector('#composer-rich')?.addEventListener('keydown', e => {
        if (e.key === '/' && !(document.querySelector('#composer-rich')?.value || '').trim()) {
            e.preventDefault();
            savedRepliesPicker.open();
            const inp = $('#qr-search-input');
            if (inp) { inp.value = ''; savedRepliesPicker.render(''); }
        }
    });

    // ── Quick Replies Management ───────────────────────────────────────────
    function openQuickRepliesModal() {
        $('#quick-replies-modal')?.classList.remove('hidden');
        renderSavedRepliesList();
    }
    function closeQuickRepliesModal() { $('#quick-replies-modal')?.classList.add('hidden'); }

    async function refreshSavedReplies() {
        const data = await api('/team-inbox/api/saved-replies');
        state.savedReplies = Array.isArray(data) ? data : [];
        updateNavQuickRepliesCount();
    }

    function renderSavedRepliesList() {
        const list = $('#saved-replies-list');
        if (!list) return;
        const replies = state.savedReplies || [];
        if (replies.length === 0) {
            list.innerHTML = `<div class="text-[12px] text-ink-500 text-center py-8">No quick replies yet. Click "Add reply" to create your first one.</div>`;
            return;
        }
        list.innerHTML = replies.map(r => `
            <div class="bg-paper-0 border border-paper-200 rounded-xl p-3 flex items-start gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-[10px] bg-wa-mint/40 text-wa-deep px-1.5 py-0.5 rounded">/${escape(r.shortcut)}</span>
                        <span class="font-semibold text-[12.5px] truncate">${escape(r.title)}</span>
                        ${r.category ? `<span class="font-mono text-[9.5px] text-ink-500">${escape(r.category)}</span>` : ''}
                        ${r.user_id ? `<span class="font-mono text-[9.5px] text-ink-400">personal</span>` : ''}
                    </div>
                    <div class="text-[11.5px] text-ink-600 mt-1 line-clamp-2">${escape((r.body||'').slice(0, 120))}</div>
                    ${r.used_count ? `<div class="font-mono text-[9.5px] text-ink-400 mt-0.5">Used ${r.used_count}×</div>` : ''}
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button data-edit-reply="${r.id}" class="w-7 h-7 rounded-full hover:bg-paper-100 grid place-items-center text-ink-600" title="Edit">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 2l3 3-8 8H3v-3l8-8z"/></svg>
                    </button>
                    <button data-delete-reply="${r.id}" class="w-7 h-7 rounded-full hover:bg-accent-coral/10 grid place-items-center text-accent-coral" title="Delete">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10M6 4V2h4v2M5 4l.5 9h5l.5-9"/></svg>
                    </button>
                </div>
            </div>`).join('');

        list.querySelectorAll('[data-edit-reply]').forEach(b => {
            b.addEventListener('click', () => {
                const id = parseInt(b.dataset.editReply, 10);
                const reply = (state.savedReplies||[]).find(r => r.id === id);
                if (reply) openSavedReplyForm(reply);
            });
        });
        list.querySelectorAll('[data-delete-reply]').forEach(b => {
            b.addEventListener('click', async () => {
                const id = parseInt(b.dataset.deleteReply, 10);
                const reply = (state.savedReplies||[]).find(r => r.id === id);
                if (!confirm(`Delete "${reply?.title}"?`)) return;
                try {
                    await api(`/team-inbox/api/saved-replies/${id}`, { method: 'DELETE' });
                    await refreshSavedReplies();
                    renderSavedRepliesList();
                    toast('Reply deleted.', 'success');
                } catch (e) { toast('Delete failed: ' + e.message, 'error'); }
            });
        });
    }

    function openSavedReplyForm(reply = null) {
        const m = $('#saved-reply-form-modal');
        if (!m) return;
        m.classList.remove('hidden');
        $('#sr-form-title') && ($('#sr-form-title').textContent = reply ? 'Edit quick reply' : 'New quick reply');
        $('#sr-form-id') && ($('#sr-form-id').value = reply?.id || '');
        const form = $('#saved-reply-form');
        if (!form) return;
        form.reset();
        if (reply) {
            form.querySelector('[name="shortcut"]').value = reply.shortcut || '';
            form.querySelector('[name="title"]').value    = reply.title    || '';
            form.querySelector('[name="body"]').value     = reply.body     || '';
            form.querySelector('[name="category"]').value = reply.category || '';
            const pb = form.querySelector('[name="personal"]');
            if (pb) pb.checked = !!reply.user_id;
        }
    }
    function closeSavedReplyForm() { $('#saved-reply-form-modal')?.classList.add('hidden'); }

    $$('[data-close-quick-replies]').forEach(b => b.addEventListener('click', closeQuickRepliesModal));
    $$('[data-close-sr-form]').forEach(b => b.addEventListener('click', closeSavedReplyForm));
    $('#nav-quick-replies')?.addEventListener('click', openQuickRepliesModal);
    $('#qr-add-btn')?.addEventListener('click', () => openSavedReplyForm(null));

    $('#saved-reply-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const id = parseInt($('#sr-form-id')?.value || '0', 10);
        const fd = new FormData(e.target);
        const body = {
            shortcut: fd.get('shortcut'),
            title:    fd.get('title'),
            body:     fd.get('body'),
            category: fd.get('category') || null,
            personal: fd.has('personal') ? 1 : 0,
        };
        const submit = $('#sr-form-submit');
        submit.disabled = true; submit.textContent = 'Saving…';
        try {
            if (id) { await api(`/team-inbox/api/saved-replies/${id}`, { method: 'PATCH', body }); }
            else     { await api('/team-inbox/api/saved-replies', { method: 'POST', body }); }
            await refreshSavedReplies();
            renderSavedRepliesList();
            closeSavedReplyForm();
            toast('Quick reply saved.', 'success');
        } catch (err) {
            toast('Save failed: ' + err.message, 'error');
        } finally {
            submit.disabled = false; submit.textContent = 'Save';
        }
    });

    function updateNavQuickRepliesCount() {
        const el = $('#nav-quick-replies-count');
        if (!el) return;
        const n = (state.savedReplies || []).length;
        el.textContent = n;
        el.style.display = n > 0 ? '' : 'none';
    }

    // ─────────────────────────────────────────────────────────────
    // Catalog send modal (Phase 4c) — three modes:
    //   spm  = single product (one selection)
    //   mpm  = multi-product list (up to 30)
    //   link = catalog link, no selection
    // ─────────────────────────────────────────────────────────────
    (function wireCatalogModal() {
        const btn   = $('#catalog-btn');
        const modal = $('#catalog-modal');
        if (!btn || !modal) return;
        const closeEls = modal.querySelectorAll('[data-catalog-close], [data-catalog-backdrop]');
        const modeBtns = modal.querySelectorAll('[data-catalog-mode] button[data-mode]');
        const listEl   = modal.querySelector('[data-catalog-list]');
        const searchEl = modal.querySelector('[data-catalog-search]');
        const summary  = modal.querySelector('[data-catalog-summary]');
        const sendBtn  = modal.querySelector('[data-catalog-send]');
        const bodyIn   = modal.querySelector('[data-catalog-body]');
        const warn     = modal.querySelector('[data-catalog-warn]');

        let mode = 'spm';
        let selectedIds = new Set();
        let selectedRetailers = new Map();
        let products = [];

        function open() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            loadProducts();
        }
        function close() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            selectedIds.clear();
            selectedRetailers.clear();
            warn.classList.add('hidden');
        }
        btn.addEventListener('click', open);
        closeEls.forEach(el => el.addEventListener('click', close));
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

        function setMode(next) {
            mode = next;
            modeBtns.forEach(b => {
                const on = b.dataset.mode === next;
                b.classList.toggle('bg-wa-deep', on);
                b.classList.toggle('text-paper-0', on);
                b.classList.toggle('font-semibold', on);
                b.classList.toggle('text-ink-700', !on);
                b.classList.toggle('font-medium', !on);
            });
            if (next === 'link') {
                listEl.innerHTML = '<div class="text-[12px] text-ink-600 py-6 px-4 text-center">A catalog link message will be sent — no product selection required. Optional message above.</div>';
                selectedIds.clear(); selectedRetailers.clear();
            } else if (next === 'spm' && selectedIds.size > 1) {
                const first = Array.from(selectedIds)[0];
                selectedIds = new Set([first]);
                selectedRetailers = new Map([[first, selectedRetailers.get(first)]]);
                renderProducts();
            }
            updateSummary();
            if (next !== 'link') loadProducts();
        }
        modeBtns.forEach(b => b.addEventListener('click', () => setMode(b.dataset.mode)));

        async function loadProducts() {
            listEl.innerHTML = '<div class="text-[12px] text-ink-500 py-6 text-center">Loading…</div>';
            const url = '/store/products/api/list' + (searchEl.value ? '?q=' + encodeURIComponent(searchEl.value) : '');
            try {
                const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const j = await r.json();
                if (!j.ok) throw new Error(j.error || 'load failed');
                products = j.products || [];
                renderProducts();
            } catch (e) {
                listEl.innerHTML = '<div class="text-[12px] text-accent-coral py-6 text-center">Failed to load: ' + e.message + '</div>';
            }
        }
        let searchTimer;
        searchEl.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadProducts, 250); });

        function renderProducts() {
            if (products.length === 0) {
                listEl.innerHTML = '<div class="text-[12px] text-ink-500 py-6 text-center">No products match. Add some in /store/products.</div>';
                return;
            }
            listEl.innerHTML = '<div class="grid grid-cols-2 md:grid-cols-3 gap-2"></div>';
            const grid = listEl.firstElementChild;
            products.forEach(p => {
                const picked = selectedIds.has(p.id);
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'text-left p-2 rounded-xl border transition ' +
                    (picked ? 'border-wa-deep bg-wa-mint/30' : 'border-paper-200 hover:bg-paper-50');
                card.innerHTML =
                    '<div class="aspect-square rounded-lg bg-paper-50 overflow-hidden mb-2 grid place-items-center">' +
                    (p.image_url ? '<img src="' + p.image_url + '" class="w-full h-full object-cover" onerror="this.outerHTML=\'<span class=&quot;text-[24px]&quot;>📦</span>\'">' : '<span class="text-[24px]">📦</span>') +
                    '</div>' +
                    '<div class="text-[12px] font-semibold truncate">' + escapeHtml(p.name) + '</div>' +
                    '<div class="font-mono text-[10.5px] text-ink-500">' + escapeHtml(p.price_display) + '</div>' +
                    (p.synced
                        ? '<div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[9.5px] font-mono">✓ synced</div>'
                        : '<div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-accent-amber/15 text-accent-amber text-[9.5px] font-mono">⚠ not synced</div>');
                card.addEventListener('click', () => togglePick(p));
                grid.appendChild(card);
            });
        }
        function togglePick(p) {
            const isPicked = selectedIds.has(p.id);
            if (mode === 'spm') {
                selectedIds = new Set(isPicked ? [] : [p.id]);
                selectedRetailers = new Map(isPicked ? [] : [[p.id, p.retailer_id]]);
            } else {
                if (isPicked) {
                    selectedIds.delete(p.id);
                    selectedRetailers.delete(p.id);
                } else {
                    if (selectedIds.size >= 30) {
                        warn.textContent = 'Multi-product messages support up to 30 items.';
                        warn.classList.remove('hidden');
                        return;
                    }
                    selectedIds.add(p.id);
                    selectedRetailers.set(p.id, p.retailer_id);
                }
            }
            warn.classList.add('hidden');
            renderProducts();
            updateSummary();
        }

        function updateSummary() {
            const n = selectedIds.size;
            summary.textContent = mode === 'link' ? 'Catalog link' : (n + ' selected' + (mode === 'mpm' ? ' / 30 max' : ''));
            sendBtn.disabled = mode !== 'link' && n === 0;
        }

        sendBtn.addEventListener('click', async () => {
            const convId = state.activeConvId;
            if (!convId) { toast('Pick a conversation first.', 'error'); return; }
            const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
            const payload = { mode, body: bodyIn.value || null };
            if (mode === 'spm') {
                const id = Array.from(selectedIds)[0];
                payload.product_id = id;
                payload.product_retailer_ids = [selectedRetailers.get(id)];
            } else if (mode === 'mpm') {
                payload.product_retailer_ids = Array.from(selectedRetailers.values());
            }
            sendBtn.disabled = true; sendBtn.textContent = 'Sending…';
            warn.classList.add('hidden');
            try {
                const r = await fetch(`/team-inbox/api/conversations/${convId}/catalog`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });
                const j = await r.json();
                if (!r.ok || !j.ok) {
                    warn.textContent = j.message || ('Failed (HTTP ' + r.status + ')');
                    warn.classList.remove('hidden');
                    return;
                }
                toast('Catalog sent ✓', 'success');
                close();
            } catch (e) {
                warn.textContent = e.message;
                warn.classList.remove('hidden');
            } finally {
                sendBtn.disabled = false; sendBtn.textContent = 'Send →';
            }
        });

        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
    })();

    // ─────────────────────────────────────────────────────────────
    // Google Meet composer modal — mints a Calendar event with a
    // Meet link via the workspace's connected Google account, then
    // drops the URL into the composer textarea (with the operator's
    // message template substituted) so they can review + Send.
    // Reuses the existing #composer hidden textarea + #composer-rich
    // pair so the rest of the send pipeline doesn't have to change.
    // ─────────────────────────────────────────────────────────────
    (function wireMeetModal() {
        const btn   = document.getElementById('meet-btn');
        const modal = document.getElementById('meet-modal');
        if (!btn || !modal) return;
        const closeEls = modal.querySelectorAll('[data-meet-close], [data-meet-backdrop]');
        const titleEl    = document.getElementById('meet-title');
        const startEl    = document.getElementById('meet-start');
        const durationEl = document.getElementById('meet-duration');
        const tplEl      = document.getElementById('meet-template');
        const inviteEl   = document.getElementById('meet-invite');
        const createBtn  = document.getElementById('meet-create-btn');
        const labelEl    = document.getElementById('meet-create-label');
        const errEl      = document.getElementById('meet-error');

        const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';

        function pickContactName() {
            // The right-side contact panel mirrors the active conversation.
            // Use its name to seed the title and the {{name}} substitution.
            return (document.getElementById('ct-name')?.textContent || '').trim()
                || 'WhatsApp customer';
        }
        function setError(msg) {
            if (!msg) { errEl.classList.add('hidden'); errEl.textContent = ''; return; }
            errEl.textContent = msg;
            errEl.classList.remove('hidden');
        }
        function busy(v) {
            createBtn.disabled = v;
            labelEl.textContent = v ? 'Creating…' : 'Create + insert into composer';
        }
        function open() {
            const customer = pickContactName();
            titleEl.value = `Call with ${customer}`;
            startEl.value = '15';
            durationEl.value = '30';
            inviteEl.checked = false;
            setError('');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => titleEl.focus(), 50);
        }
        function close() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            setError('');
        }

        btn.addEventListener('click', open);
        closeEls.forEach(el => el.addEventListener('click', close));
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
        });

        function startEndISO() {
            const leadMin = Math.max(0, parseInt(startEl.value, 10) || 15);
            const durMin  = Math.max(5, parseInt(durationEl.value, 10) || 30);
            const start = new Date(Date.now() + leadMin * 60_000);
            // The "Tomorrow 10 AM" option overrides — same dropdown value
            // path but shifts to tomorrow 10:00 local.
            if (startEl.value === '1440') {
                start.setHours(10, 0, 0, 0);
                start.setDate(start.getDate() + 1);
            }
            const end = new Date(start.getTime() + durMin * 60_000);
            return { start, end };
        }

        createBtn.addEventListener('click', async () => {
            const title = (titleEl.value || '').trim();
            if (!title) { setError('Title required.'); titleEl.focus(); return; }
            setError('');
            busy(true);
            try {
                const { start, end } = startEndISO();
                const res = await fetch('/team-inbox/api/google-meet', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     csrf(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        title,
                        start_at:     start.toISOString(),
                        end_at:       end.toISOString(),
                        send_invites: !!inviteEl.checked,
                        time_zone:    Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
                    }),
                });
                const j = await res.json().catch(() => null);
                if (!res.ok || !j?.ok) {
                    setError(j?.message || j?.error || ('Failed (' + res.status + ')'));
                    busy(false);
                    return;
                }
                // Substitute {{meet_link}} / {{meet_start}} into the
                // template, drop into the composer's rich + hidden
                // textareas so the existing Send button picks it up.
                const startNice = new Date(j.start).toLocaleString();
                const out = (tplEl.value || '')
                    .replace(/\{\{\s*meet_link\s*\}\}/g,  j.meet_url)
                    .replace(/\{\{\s*meet_start\s*\}\}/g, startNice);
                const plain = document.getElementById('composer');
                const rich  = document.getElementById('composer-rich');
                if (plain) plain.value = out;
                // Rich composer mirrors the hidden textarea — set its
                // contenteditable text + dispatch input so the char
                // counter + Send-button enable hook fire.
                if (rich) {
                    if (rich.tagName === 'TEXTAREA') rich.value = out;
                    else rich.innerText = out;
                    rich.dispatchEvent(new Event('input', { bubbles: true }));
                }
                close();
            } catch (e) {
                setError(e?.message || 'Network error');
            } finally {
                busy(false);
            }
        });
    })();

    // ─────────────────────────────────────────────────────────────
    // AI voice assistant takeover — picks one of the workspace's
    // assistants and pins it to the active conversation. The
    // inbound-message pipeline then runs the assistant's prompt on
    // every subsequent customer reply until the operator detaches.
    // Backed by:
    //   GET  /team-inbox/api/assistants
    //   POST /team-inbox/api/conversations/{id}/assign-assistant
    // ─────────────────────────────────────────────────────────────
    (function wireAiTakeover() {
        const btn   = document.getElementById('ai-takeover-btn');
        const modal = document.getElementById('ai-takeover-modal');
        if (!btn || !modal) return;
        const closes  = modal.querySelectorAll('[data-aita-close], [data-aita-backdrop]');
        const listEl  = modal.querySelector('[data-aita-list]');
        const detach  = modal.querySelector('[data-aita-detach]');
        const csrf    = () => document.querySelector('meta[name=csrf-token]')?.content || '';

        function activeConvId() {
            // state.activeId is the team-inbox SPA state object set on
            // every conversation open — same path the reply button uses.
            return (window.state && window.state.activeId) || state?.activeId || null;
        }
        function open() {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            load();
        }
        function close() {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        btn.addEventListener('click', () => {
            const cId = activeConvId();
            if (!cId) { window.toast?.('Open a conversation first', 'error'); return; }
            open();
        });
        closes.forEach(el => el.addEventListener('click', close));

        async function load() {
            listEl.innerHTML = '<div class="text-[12px] text-ink-500 py-6 text-center">Loading…</div>';
            try {
                const r = await fetch('/team-inbox/api/assistants', { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await r.json();
                if (!j?.ok) throw new Error('load failed');
                if (!j.assistants?.length) {
                    listEl.innerHTML = `
                        <div class="text-center py-8">
                            <div class="font-serif text-[15px] mb-1">No assistants yet</div>
                            <p class="text-[12px] text-ink-500 mb-3">Configure one first, then hand off a conversation to it.</p>
                            <a href="/ai-assistants/create" class="px-3 py-1.5 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold inline-flex items-center gap-1.5">Create assistant</a>
                        </div>`;
                    detach.classList.add('hidden');
                    return;
                }
                listEl.innerHTML = '';
                j.assistants.forEach(a => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-paper-50 border border-transparent hover:border-paper-200';
                    row.innerHTML = `
                        <span class="w-9 h-9 rounded-lg bg-wa-bubble text-wa-deep grid place-items-center">
                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="10" height="9" rx="1.5"/><path d="M3 6h10M5 2v3M11 2v3M6 9h2M9 9h1"/></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-[13px] truncate">${escapeHtml(a.name)}</div>
                            <div class="font-mono text-[10.5px] text-ink-500 truncate">${escapeHtml(a.provider)} · ${escapeHtml(a.model)}</div>
                        </div>
                        <span class="px-1.5 py-0.5 rounded font-mono text-[9.5px] uppercase tracking-[0.14em] ${a.status === 'live' ? 'bg-wa-mint text-wa-deep' : 'bg-paper-100 text-ink-500'}">${a.status}</span>
                    `;
                    row.addEventListener('click', () => attach(a.id, a.name));
                    listEl.appendChild(row);
                });
                detach.classList.remove('hidden');
                detach.onclick = () => attach(0, '');
            } catch (e) {
                listEl.innerHTML = `<div class="text-[12px] text-accent-coral py-6 text-center">Couldn't load assistants — ${escapeHtml(e?.message || 'error')}</div>`;
            }
        }
        async function attach(assistantId, name) {
            const cId = activeConvId();
            if (!cId) return;
            try {
                const r = await fetch(`/team-inbox/api/conversations/${cId}/assign-assistant`, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ assistant_id: assistantId || 0 }),
                });
                const j = await r.json();
                if (!j?.ok) throw new Error(j?.error || 'attach failed');
                window.toast?.(assistantId ? `${name} will now reply to this chat` : 'AI detached — you\'re in control', 'success');
                close();
            } catch (e) {
                window.toast?.(e?.message || 'Failed', 'error');
            }
        }

        function escapeHtml(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
    })();

    // ──────────────────────────────────────────────────────────────
    // Collision detection — presence + typing for the active thread
    //
    // No WebSockets in this project (BROADCAST_CONNECTION=log) so we
    // poll: heartbeat every 10s while a conversation is open, and a
    // separate short ping on each keystroke (debounced to 2s) to mark
    // the operator as currently typing. Snapshot comes back from the
    // same endpoint, so one round-trip both refreshes self-presence
    // AND fetches teammates' presence. UI sits in #th-presence under
    // the conversation header.
    // ──────────────────────────────────────────────────────────────
    const presence = {
        timerId: null,            // heartbeat interval
        typingTimerId: null,      // debounce for typing pulses
        lastConvId: null,         // which conv we're presently in
        composerObserver: null,   // listener for composer keystrokes
    };

    async function presencePing(convId, { typing = false } = {}) {
        if (!convId) return;
        try {
            const res = await fetch(`/team-inbox/api/conversations/${convId}/presence`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ typing: !!typing }),
            });
            if (!res.ok) return;
            const data = await res.json();
            // Only paint if this is still the active conversation —
            // a late response shouldn't overwrite the indicator for a
            // newly-opened thread.
            if (state.activeId === convId) renderPresence(data.presence || { viewers: [], typists: [] });
        } catch (e) {
            // Silent — presence is a nice-to-have, not critical path.
        }
    }

    function presenceLeave(convId) {
        if (!convId) return;
        const url = `/team-inbox/api/conversations/${convId}/presence/leave`;
        const body = new Blob(['{}'], { type: 'application/json' });
        // sendBeacon survives tab close; fetch doesn't.
        if (navigator.sendBeacon) {
            navigator.sendBeacon(url, body);
        } else {
            fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                keepalive: true,
            }).catch(() => {});
        }
    }

    function renderPresence(snap) {
        const slot = document.getElementById('th-presence');
        if (!slot) return;
        const viewers = Array.isArray(snap.viewers) ? snap.viewers : [];
        const typists = Array.isArray(snap.typists) ? snap.typists : [];

        if (viewers.length === 0 && typists.length === 0) {
            slot.innerHTML = '';
            return;
        }

        const palette = ['av-1','av-2','av-3','av-4','av-5','av-6'];
        const initials = (n) => String(n||'?').trim().split(/\s+/).map(s => s[0]||'').slice(0,2).join('').toUpperCase();

        // Typing wins visually — show "X is typing" in place of the
        // generic "viewing" pill when at least one teammate has the
        // composer focused. Up to 3 names, then "+N more".
        if (typists.length > 0) {
            const names = typists.slice(0, 3).map(t => t.name || ('User #' + t.user_id));
            const more  = typists.length > 3 ? ` +${typists.length - 3}` : '';
            slot.innerHTML = `
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-wa-mint/40 text-wa-deep text-[10px] font-mono">
                    <span class="typing-dots inline-flex gap-0.5">
                        <span class="w-1 h-1 rounded-full bg-wa-deep animate-pulse"></span>
                        <span class="w-1 h-1 rounded-full bg-wa-deep animate-pulse" style="animation-delay:.15s"></span>
                        <span class="w-1 h-1 rounded-full bg-wa-deep animate-pulse" style="animation-delay:.3s"></span>
                    </span>
                    ${escapeHtml(names.join(', '))}${more} ${typists.length === 1 ? 'is' : 'are'} typing
                </span>`;
            return;
        }

        const shown = viewers.slice(0, 4);
        const overflow = Math.max(0, viewers.length - 4);
        const avatars = shown.map((v, i) =>
            `<span class="w-5 h-5 rounded-full ${palette[i % palette.length]} text-paper-0 text-[8px] font-semibold grid place-items-center ring-2 ring-paper-0" title="${escapeHtml(v.name || '')}">${escapeHtml(initials(v.name))}</span>`
        ).join('');
        const overflowChip = overflow > 0
            ? `<span class="w-5 h-5 rounded-full bg-ink-700 text-paper-0 text-[8px] font-semibold grid place-items-center ring-2 ring-paper-0">+${overflow}</span>`
            : '';
        const label = viewers.length === 1
            ? `${escapeHtml(viewers[0].name || 'Teammate')} is also viewing`
            : `${viewers.length} teammates are viewing`;
        slot.innerHTML = `
            <span class="inline-flex items-center -space-x-1.5">${avatars}${overflowChip}</span>
            <span class="ml-1 text-[10px] font-mono text-wa-deep">${label}</span>`;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function startPresence(convId) {
        // Leaving any previous conversation first so the snapshot for
        // the OLD thread immediately drops this operator's entry.
        if (presence.lastConvId && presence.lastConvId !== convId) {
            presenceLeave(presence.lastConvId);
        }
        presence.lastConvId = convId;

        // Clear timers from a previous conversation.
        if (presence.timerId) clearInterval(presence.timerId);
        if (presence.typingTimerId) clearTimeout(presence.typingTimerId);

        // First ping is immediate so teammates see us within 1 RTT.
        presencePing(convId);
        presence.timerId = setInterval(() => {
            if (state.activeId === convId) presencePing(convId);
        }, 10000);

        // Composer keystroke → typing pulse. Debounced so a flurry of
        // keys results in at most one ping every 2s.
        const wrap = document.getElementById('composer-textarea-wrap');
        if (wrap && !presence.composerObserver) {
            const onInput = () => {
                if (!state.activeId) return;
                if (presence.typingTimerId) return; // already debounced
                presencePing(state.activeId, { typing: true });
                presence.typingTimerId = setTimeout(() => {
                    presence.typingTimerId = null;
                }, 2000);
            };
            wrap.addEventListener('input', onInput);
            presence.composerObserver = onInput;
        }
    }

    function stopPresenceOnUnload() {
        if (presence.lastConvId) presenceLeave(presence.lastConvId);
    }
    window.addEventListener('beforeunload', stopPresenceOnUnload);
    window.addEventListener('pagehide', stopPresenceOnUnload);

    // Watch state.activeId — when it changes, kick off presence for
    // the new conversation. Cheaper + safer than wrapping
    // openConversation (which is a function declaration and not
    // always reassignable across bundlers). Runs every 500ms — fast
    // enough that switching conversations updates "viewing" within
    // half a second.
    setInterval(() => {
        if (state.activeId && state.activeId !== presence.lastConvId) {
            startPresence(state.activeId);
        } else if (!state.activeId && presence.lastConvId) {
            // Closed the thread (no conversation open) — drop out.
            presenceLeave(presence.lastConvId);
            presence.lastConvId = null;
            if (presence.timerId) { clearInterval(presence.timerId); presence.timerId = null; }
            const slot = document.getElementById('th-presence');
            if (slot) slot.innerHTML = '';
        }
    }, 500);

    // kick off
    bootstrap();
}
