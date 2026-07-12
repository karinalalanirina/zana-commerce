// Instaflow automation CREATE / EDIT wizard — the multi-step stepper that
// drives resources/views/instagram/automations/_form.blade.php (shared by the
// create + edit pages). Mirrors the WaDesk auto-reply create stepper, re-skinned
// in the Instaflow theme. Page keys: instagram-automations-create /
// instagram-automations-edit (both → this module in app.js).
//
// What it does:
//   • Step navigation: Back / Next / clicking a step node shows the right
//     .step-pane and marks .step-node done/active; final step swaps Next → Save.
//   • Trigger tiles set the hidden #ruleType and show/hide the match / comment /
//     ai / flow / message sections (mirrors automationStore validation).
//   • Match segmented control sets the hidden #ruleMatch.
//   • dm_message drives the live Instagram-DM preview (#igPpBody).
//   • A per-step required-field guard runs before advancing.
//   • Step 4 (Review) fills the summary <dd data-rv> cells from current values.
//
// Backend contract is untouched — this only drives UX over the existing field
// names (type, match_mode, trigger_keyword, dm_message, flow_id, …).

export default function init() {
    const root = document.querySelector('[data-ig-autoform]');
    if (!root) return;

    const TOTAL = 4;
    let step = 1;

    const stepper = document.getElementById('stepper');
    const nodes = Array.from(stepper?.querySelectorAll('.step-node') || []);
    const panes = Array.from(root.querySelectorAll('.step-pane'));
    const btnPrev = root.querySelector('[data-step-prev]');
    const btnNext = root.querySelector('[data-step-next]');
    const btnSubmit = root.querySelector('[data-step-submit]');
    const curEl = root.querySelector('[data-step-cur]');

    const typeInput = document.getElementById('ruleType');
    const matchInput = document.getElementById('ruleMatch');
    const trigGroup = root.querySelector('[data-trig-group]');
    const segGroup = root.querySelector('[data-seg-group]');
    const replyText = document.getElementById('igReplyText');
    const ppBody = document.getElementById('igPpBody');

    const sections = {
        match: root.querySelector('[data-rule-section="match"]'),
        comment: root.querySelector('[data-rule-section="comment"]'),
        nokeyword: root.querySelector('[data-rule-section="nokeyword"]'),
        message: root.querySelector('[data-rule-section="message"]'),
        ai: root.querySelector('[data-rule-section="ai"]'),
        flow: root.querySelector('[data-rule-section="flow"]'),
    };
    const msgLabel = root.querySelector('[data-msg-label]');
    const msgHint = root.querySelector('[data-msg-hint]');
    const trigHint = root.querySelector('[data-trig-hint]');
    const nokeywordText = root.querySelector('[data-nokeyword-text]');

    const show = (el, on) => el && el.classList.toggle('hidden', !on);
    const field = (name) => root.querySelector(`[name="${name}"]`);

    // ── Trigger type → conditional sections (mirrors automationStore rules) ──
    const applyType = (type) => {
        if (typeInput) typeInput.value = type;
        const keyworded = type === 'dm_keyword' || type === 'comment_to_dm' || type === 'story_reply';
        const isAi = type === 'ai_agent';
        const isFlow = type === 'flow';
        const isMention = type === 'mention';

        // Step 2 — keyword config only for keyword-driven + AI types; mention/flow
        // get the "no keyword" note.
        show(sections.match, keyworded || isAi);
        show(sections.comment, type === 'comment_to_dm');
        show(sections.nokeyword, isMention || isFlow);
        if (nokeywordText) {
            nokeywordText.textContent = isFlow
                ? 'Visual flows launch on the matched event — no keyword config here. Pick the flow on the reply step.'
                : 'Mentions fire whenever someone @-mentions you — nothing to match. Continue to the reply step.';
        }

        // Step 3 — message box for everyone except flow; AI + flow extras.
        show(sections.message, !isFlow);
        show(sections.ai, isAi);
        show(sections.flow, isFlow);

        // AI agent forces match_mode=any server-side; reflect it.
        if (isAi && matchInput) {
            matchInput.value = 'any';
            paintSeg('any');
        }

        if (msgLabel) msgLabel.textContent = isAi ? 'System prompt' : 'Auto-reply message';
        if (msgHint) {
            msgHint.textContent = isAi
                ? 'Tell the AI how to behave, e.g. “You are our support agent. Answer briefly.”'
                : 'Use {first_name} to personalise. This is the DM Instaflow sends back.';
        }
        if (trigHint) {
            const map = {
                dm_keyword: 'Fires when a DM contains your keyword.',
                comment_to_dm: 'Replies under the comment, then DMs the commenter.',
                story_reply: 'Fires when someone replies to your story.',
                mention: 'Fires when someone @-mentions your account.',
                ai_agent: 'Hands the conversation to your AI knowledge base.',
                flow: 'Launches a visual Instagram flow.',
            };
            trigHint.textContent = map[type] || trigHint.textContent;
        }
    };

    trigGroup?.querySelectorAll('[data-trig]').forEach((tile) => {
        tile.addEventListener('click', () => {
            trigGroup.querySelectorAll('[data-trig]').forEach((t) => t.classList.remove('active'));
            tile.classList.add('active');
            applyType(tile.dataset.trig);
        });
    });

    // ── Match-mode segmented control → hidden match_mode ──
    const paintSeg = (mode) => {
        segGroup?.querySelectorAll('[data-seg]').forEach((s) => s.classList.toggle('active', s.dataset.seg === mode));
    };
    segGroup?.querySelectorAll('[data-seg]').forEach((seg) => {
        seg.addEventListener('click', () => {
            if (matchInput) matchInput.value = seg.dataset.seg;
            paintSeg(seg.dataset.seg);
        });
    });

    // ── Live DM preview ──
    const renderPreview = () => {
        if (ppBody) ppBody.textContent = (replyText?.value || '').trim() || 'your DM appears here…';
    };
    replyText?.addEventListener('input', renderPreview);

    // ── Stepper rendering ──
    const paintNodes = () => {
        nodes.forEach((node) => {
            const n = parseInt(node.dataset.n, 10);
            const dot = node.querySelector('.dot');
            const lab = node.querySelector('.lab');
            const done = n < step;
            const active = n === step;
            if (dot) {
                dot.className = 'dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold mono shrink-0 transition border-[1.5px] '
                    + (active ? 'bg-white border-ig-pink text-ig-pink ring-4 ring-ig-pink/10'
                        : done ? 'ig-grad-soft border-transparent text-white'
                            : 'bg-white border-paper-200 text-ink-500');
                dot.innerHTML = done
                    ? '<svg viewBox="0 0 16 16" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M3 8l3 3 7-8"/></svg>'
                    : String(n);
            }
            if (lab) lab.className = 'lab text-[11.5px] whitespace-nowrap ' + (active ? 'font-semibold ig-text' : done ? 'font-medium text-ink-700' : 'font-medium text-ink-500');
        });
    };

    const render = () => {
        panes.forEach((p) => p.classList.toggle('hidden', parseInt(p.dataset.step, 10) !== step));
        paintNodes();
        if (curEl) curEl.textContent = String(step);
        if (btnPrev) btnPrev.disabled = step === 1;
        const last = step === TOTAL;
        btnNext?.classList.toggle('hidden', last);
        btnNext?.classList.toggle('inline-flex', !last);
        btnSubmit?.classList.toggle('hidden', !last);
        btnSubmit?.classList.toggle('inline-flex', last);
        if (last) fillReview();
    };

    // ── Per-step required-field guard ──
    const flash = (el) => {
        if (!el) return;
        el.classList.add('ring-2', 'ring-ig-pink');
        el.focus?.();
        setTimeout(() => el.classList.remove('ring-2', 'ring-ig-pink'), 1400);
    };
    const validateStep = (n) => {
        const type = typeInput?.value || 'dm_keyword';
        if (n === 1) {
            const acc = field('instagram_account_id');
            if (acc && !acc.value) { flash(acc); window.toast?.('Pick an Instagram account.', 'error'); return false; }
        }
        if (n === 2) {
            const keyworded = type === 'dm_keyword' || type === 'comment_to_dm' || type === 'story_reply';
            const mode = matchInput?.value || 'contains';
            if (keyworded && mode !== 'any') {
                const kw = field('trigger_keyword');
                if (kw && !kw.value.trim()) { flash(kw); window.toast?.('Add at least one keyword (or switch to “Any message”).', 'error'); return false; }
            }
        }
        if (n === 3) {
            if (type === 'flow') {
                const fl = field('flow_id');
                if (fl && !fl.value) { flash(fl); window.toast?.('Pick a flow to run.', 'error'); return false; }
            } else if (type !== 'mention') {
                const dm = field('dm_message');
                if (dm && !dm.value.trim()) { flash(dm); window.toast?.('Write the reply message.', 'error'); return false; }
            }
        }
        return true;
    };

    const go = (to) => {
        if (to > step) {
            for (let s = step; s < to; s++) if (!validateStep(s)) return;
        }
        step = Math.min(Math.max(to, 1), TOTAL);
        render();
        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    btnNext?.addEventListener('click', () => go(step + 1));
    btnPrev?.addEventListener('click', () => go(step - 1));
    nodes.forEach((node) => node.addEventListener('click', () => {
        go(parseInt(node.dataset.n, 10));
    }));

    // Block native submit unless the final step's required fields pass.
    btnSubmit?.closest('form')?.addEventListener('submit', (e) => {
        for (let s = 1; s <= TOTAL; s++) {
            if (!validateStep(s)) { e.preventDefault(); go(s); return; }
        }
    });

    // ── Review summary ──
    const labelFor = {
        dm_keyword: 'DM keyword', comment_to_dm: 'Comment → DM', story_reply: 'Story reply',
        mention: 'Mention', ai_agent: 'AI agent', flow: 'Visual flow',
    };
    const fillReview = () => {
        const set = (key, val) => { const el = root.querySelector(`[data-rv="${key}"]`); if (el) el.textContent = val || '—'; };
        const type = typeInput?.value || 'dm_keyword';
        const accSel = field('instagram_account_id');
        set('name', field('name')?.value?.trim());
        set('account', accSel ? (accSel.options[accSel.selectedIndex]?.text || '') : '');
        set('type', labelFor[type] || type);
        set('match', matchInput?.value || 'contains');
        set('keywords', (matchInput?.value === 'any') ? 'any message' : field('trigger_keyword')?.value?.trim());
        if (type === 'flow') {
            const fl = field('flow_id');
            set('reply', fl ? `Flow: ${fl.options[fl.selectedIndex]?.text || ''}` : 'flow');
        } else {
            set('reply', field('dm_message')?.value?.trim());
        }
    };

    // ── Boot: hydrate conditional sections from the pre-filled type/match ──
    applyType(typeInput?.value || 'dm_keyword');
    paintSeg(matchInput?.value || 'contains');
    renderPreview();
    render();
}
