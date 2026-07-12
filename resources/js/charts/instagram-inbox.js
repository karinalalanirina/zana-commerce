// Instaflow Direct inbox — thread search filter, message auto-scroll, detail
// panel toggle. Page key: instagram-inbox (registered in app.js).
export default function init() {
    // ── Auto-scroll the open thread to the newest message ──
    const scroll = document.getElementById('ig-message-scroll');
    if (scroll) scroll.scrollTop = scroll.scrollHeight;

    // ── Live poll — fetch new messages for the open thread from our DB (cheap;
    //    the webhook keeps the DB live), then append. Same feel as Team Inbox. ──
    if (scroll && scroll.dataset.accountId && scroll.dataset.igsid) {
        let lastId = parseInt(scroll.dataset.lastId || '0', 10);
        let busy = false;
        const esc = (s) => { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
        const poll = async () => {
            if (busy || document.hidden) return;
            busy = true;
            try {
                const url = `/instagram/inbox/poll?account_id=${encodeURIComponent(scroll.dataset.accountId)}`
                    + `&igsid=${encodeURIComponent(scroll.dataset.igsid)}&after=${lastId}`;
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                const j = await r.json();
                const atBottom = scroll.scrollHeight - scroll.scrollTop - scroll.clientHeight < 80;
                (j.messages || []).forEach((m) => {
                    const out = m.dir === 'out';
                    const row = document.createElement('div');
                    row.className = 'flex ' + (out ? 'justify-end' : 'justify-start');
                    row.innerHTML = `<div class="max-w-[72%] rounded-2xl px-3.5 py-2 ${out ? 'bg-wa-mint text-wa-deep' : 'bg-paper-0 border border-paper-200'}">`
                        + `<div class="text-[13.5px] whitespace-pre-wrap break-words">${esc(m.body)}</div>`
                        + `<div class="text-[10px] text-ink-500 mt-1 text-right">${esc(m.time)}</div></div>`;
                    scroll.appendChild(row);
                });
                if (j.last_id && j.last_id > lastId) {
                    lastId = j.last_id;
                    if (atBottom) scroll.scrollTop = scroll.scrollHeight;
                }
            } catch (e) {
                /* transient — ignore */
            } finally {
                busy = false;
            }
        };
        setInterval(poll, 4000);
    }

    // ── Client-side thread search (filters the rendered list) ──
    const search = document.getElementById('ig-thread-search');
    const list = document.getElementById('ig-thread-list');
    const empty = document.getElementById('ig-thread-empty');
    if (search && list) {
        const rows = Array.from(list.querySelectorAll('.ig-thread'));
        search.addEventListener('input', () => {
            const q = search.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach((r) => {
                const hit = !q || (r.dataset.search || '').includes(q);
                r.classList.toggle('hidden', !hit);
                if (hit) shown += 1;
            });
            if (empty) empty.classList.toggle('hidden', shown !== 0 || rows.length === 0);
        });
    }

    // ── Detail panel show/hide ──
    const toggle = document.getElementById('ig-detail-toggle');
    const panel = document.getElementById('ig-detail-panel');
    if (toggle && panel) {
        toggle.addEventListener('click', () => panel.classList.toggle('hidden'));
    }

    // ── Reply composer — file chip + submit guard ──
    const form = document.getElementById('ig-reply-form');
    if (form) {
        const body = form.querySelector('input[name="body"]');
        const file = document.getElementById('ig-reply-file');
        const fname = document.getElementById('ig-reply-fname');
        // Show the chosen attachment's name.
        file?.addEventListener('change', () => {
            const f = file.files && file.files[0];
            if (f && fname) { fname.textContent = f.name; fname.classList.remove('hidden'); }
            else if (fname) { fname.classList.add('hidden'); }
        });
        // Allow submit when there's text OR an attachment.
        form.addEventListener('submit', (e) => {
            const hasText = body && body.value.trim();
            const hasFile = file && file.files && file.files.length > 0;
            if (!hasText && !hasFile) {
                e.preventDefault();
                window.toast?.('Type a message or attach a file before sending.', 'error');
                body?.focus();
            }
        });
    }
}
