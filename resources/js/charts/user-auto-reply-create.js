export default function init() {
    // Match-method segmented
      document.querySelectorAll('#seg-mm .seg-btn').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#seg-mm .seg-btn').forEach(x => {
          x.classList.remove('bg-wa-deep','text-paper-0');
          x.classList.add('text-ink-600','hover:bg-paper-100');
        });
        b.classList.add('bg-wa-deep','text-paper-0');
        b.classList.remove('text-ink-600','hover:bg-paper-100');
        const isFuzzy = b.dataset.mm === 'fuzzy';
        document.getElementById('fuzzy-row').style.opacity = isFuzzy ? '1' : '0.4';
        document.getElementById('fuzzy').disabled = !isFuzzy;
        // Show the regex example only when regex mode is active.
        document.getElementById('regex-hint')?.classList.toggle('hidden', b.dataset.mm !== 'regex');
      }));

      // Template picker — single-select. The Step-4 submit handler reads the
      // chosen template from [data-tpl-id].border-wa-deep, so clicking a chip
      // just toggles that class (mirrors the reply-type tiles below). Without
      // this, no chip was ever marked selected and template_id submitted empty.
      document.querySelectorAll('[data-tpl-id]').forEach(chip => chip.addEventListener('click', () => {
        document.querySelectorAll('[data-tpl-id]').forEach(c => {
          c.classList.remove('border-wa-deep','bg-[#F0F8F6]');
          c.classList.add('border-paper-200');
        });
        chip.classList.add('border-wa-deep','bg-[#F0F8F6]');
        chip.classList.remove('border-paper-200');
        // Live preview — mirror the chosen template body into the WhatsApp
        // bubble (the text tab does this via oninput; templates had nothing).
        const pp = document.getElementById('pp-body');
        if (pp && chip.dataset.tplBody) pp.textContent = chip.dataset.tplBody;
      }));

      // Reply-type tile
      document.querySelectorAll('.rt-tile').forEach(t => t.addEventListener('click', () => {
        document.querySelectorAll('.rt-tile').forEach(x => {
          x.classList.remove('border-wa-deep','bg-[#F0F8F6]');
          x.classList.add('border-paper-200','bg-white','hover:border-wa-deep');
          x.querySelector('.rt-check').classList.replace('opacity-100','opacity-0');
        });
        t.classList.add('border-wa-deep','bg-[#F0F8F6]');
        t.classList.remove('border-paper-200','bg-white','hover:border-wa-deep');
        t.querySelector('.rt-check').classList.replace('opacity-0','opacity-100');
        const rt = t.dataset.rt;
        // The Custom-reply pane (Step 4) is only relevant for type=custom;
        // every other type uses its own target picker below or none at all.
        document.getElementById('branch-custom').classList.toggle('hidden', rt !== 'custom');
        document.getElementById('branch-flow').classList.toggle('hidden', rt !== 'flow');
        // #19/#20/#23 — surface the matching target picker only.
        document.querySelectorAll('.rt-target').forEach(el => el.classList.add('hidden'));
        const map = {
            share_contact:   'rt-target-share-contact',
            send_catalog:    'rt-target-send-catalog',
            request_location:'rt-target-request-location',
        };
        const id = map[rt];
        if (id) document.getElementById(id)?.classList.remove('hidden');
      }));

      // Format tabs
      document.querySelectorAll('#ftabs .ftab').forEach(t => t.addEventListener('click', () => {
        document.querySelectorAll('#ftabs .ftab').forEach(x => {
          x.classList.remove('bg-wa-deep','text-paper-0');
          x.classList.add('text-ink-600','hover:bg-paper-100');
        });
        t.classList.add('bg-wa-deep','text-paper-0');
        t.classList.remove('text-ink-600','hover:bg-paper-100');
        document.querySelectorAll('.fpanel').forEach(p => p.classList.add('hidden'));
        document.querySelector(`.fpanel[data-panel="${t.dataset.tab}"]`).classList.remove('hidden');
      }));

      // Keyword chip add
      const kwInput = document.getElementById('kw-input');
      const kwList = document.getElementById('kw-list');
      kwInput.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ',') {
          e.preventDefault();
          const v = kwInput.value.trim().replace(/,$/, '');
          if (!v) return;
          const chip = document.createElement('span');
          chip.className = 'kw-chip inline-flex items-center gap-1.5 bg-paper-0 border border-paper-200 pl-2.5 pr-1 py-1 rounded-full text-[11.5px]';
          chip.innerHTML = v + ' <button type="button" class="w-[18px] h-[18px] rounded-full bg-paper-100 text-ink-600 grid place-items-center hover:bg-accent-coral hover:text-white">&times;</button>';
          chip.querySelector('button').onclick = () => chip.remove();
          kwList.insertBefore(chip, kwInput);
          kwInput.value = '';
        }
      });

      // Stepper
      const TOTAL = 5;
      let cur = 1;
      const panes = document.querySelectorAll('.step-pane');
      const nodes = document.querySelectorAll('.step-node');
      const btnPrev = document.getElementById('btn-prev');
      const btnNext = document.getElementById('btn-next');
      const btnSubmit = document.getElementById('btn-submit');
      const curLabel = document.getElementById('cur-step');

      function paintNode(node, state) {
        const dot = node.querySelector('.dot');
        const lab = node.querySelector('.lab');
        const bar = node.querySelector('.bar');
        dot.className = 'dot w-7 h-7 rounded-full grid place-items-center text-[11px] font-semibold font-mono shrink-0 transition border-[1.5px]';
        lab.className = 'lab text-[11.5px] whitespace-nowrap';
        if (state === 'active') {
          dot.classList.add('bg-paper-0','border-wa-deep','text-wa-deep','ring-4','ring-wa-deep/10');
          lab.classList.add('font-semibold','text-wa-deep');
        } else if (state === 'done') {
          dot.classList.add('bg-wa-deep','border-wa-deep','text-paper-0');
          lab.classList.add('font-medium','text-ink-700');
        } else {
          dot.classList.add('bg-paper-0','border-paper-200','text-ink-500');
          lab.classList.add('font-medium','text-ink-500');
        }
        if (bar) {
          bar.className = 'bar flex-1 h-[2px] mx-2 rounded ' + (state === 'done' ? 'bg-wa-deep' : 'bg-paper-200');
        }
      }

      function syncReview() {
        document.getElementById('rv-name').textContent = document.getElementById('rule-name').value || '—';
        // Sender summary — the unified <x-sender-picker> renders one
        // checkbox per connected sender (name="sender[]", composite
        // engine:id value). Show the ticked count. Legacy single-engine
        // forms posted name="device_ids[]"/<select id="device"> — kept as
        // a fallback so an un-migrated render still summarises.
        const checked = document.querySelectorAll('input[type="checkbox"][name="sender[]"]:checked, input[type="checkbox"][name="device_ids[]"]:checked');
        const single = document.getElementById('device');
        let devSummary = '—';
        if (checked.length > 0) {
          devSummary = checked.length === 1 ? '1 sender' : (checked.length + ' senders');
        } else if (single) {
          devSummary = single.value || '—';
        }
        document.getElementById('rv-dev').textContent = devSummary;
        document.getElementById('rv-kw').textContent = kwList.querySelectorAll('.kw-chip').length;
        const mmBtn = document.querySelector('#seg-mm .bg-wa-deep');
        const mm = mmBtn ? mmBtn.dataset.mm : 'fuzzy';
        const sim = document.getElementById('fuzzy').value;
        document.getElementById('rv-mm').textContent = mm === 'fuzzy' ? `Fuzzy ${sim}%` : (mm[0].toUpperCase() + mm.slice(1));
        document.getElementById('rv-cd').textContent = (document.getElementById('cooldown').value || '0') + ' s';
        document.getElementById('rv-td').textContent = (document.getElementById('timeout').value || '0') + ' s';
        const rtTile = document.querySelector('.rt-tile.border-wa-deep');
        const rt = rtTile ? rtTile.dataset.rt : 'custom';
        if (rt === 'flow') document.getElementById('rv-rt').textContent = 'Flow';
        else {
          const tabBtn = document.querySelector('#ftabs .bg-wa-deep');
          const tab = tabBtn ? tabBtn.dataset.tab : 'text';
          document.getElementById('rv-rt').textContent = 'Custom · ' + tab[0].toUpperCase() + tab.slice(1);
        }
      }

      function show(n) {
        panes.forEach(p => p.classList.toggle('hidden', +p.dataset.step !== n));
        nodes.forEach(node => {
          const i = +node.dataset.n;
          paintNode(node, i === n ? 'active' : (i < n ? 'done' : 'todo'));
        });
        curLabel.textContent = n;
        btnPrev.disabled = n === 1;
        btnNext.classList.toggle('hidden', n === TOTAL);
        // Also toggle inline-flex: Tailwind orders .inline-flex after .hidden, so
        // "hidden inline-flex" stays visible. Removing inline-flex lets .hidden win.
        btnNext.classList.toggle('inline-flex', n !== TOTAL);
        btnSubmit.classList.toggle('hidden', n !== TOTAL);
        btnSubmit.classList.toggle('inline-flex', n === TOTAL);
        if (n === TOTAL) syncReview();
        cur = n;
      }

      // ── Per-step validation gate ─────────────────────────────────────
      // Block forward navigation (Next button OR a step-node jump) until
      // the current step's required fields are filled. Backward moves are
      // always allowed. Returns {ok, msg, el} so the caller can toast +
      // highlight the offending field.
      const $id = (id) => document.getElementById(id);
      function flashInvalid(el) {
        if (!el) return;
        el.classList.add('ring-2', 'ring-accent-coral/60', 'border-accent-coral');
        try { el.focus({ preventScroll: false }); } catch (_) {}
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => el.classList.remove('ring-2', 'ring-accent-coral/60', 'border-accent-coral'), 2600);
      }
      function validateStep(n) {
        if (n === 1) {
          const name = $id('rule-name');
          if (!name || !name.value.trim()) return { ok: false, msg: 'Enter a rule name.', el: name };
          // Sender gate — at least one ticked sender checkbox. Falls back
          // to the legacy single <select id="device"> / device_ids[] when
          // the unified picker isn't present (un-migrated render).
          const single = $id('device');
          const pickerEl = document.querySelector('[data-sender-picker]');
          const senderChecked = document.querySelectorAll('input[name="sender[]"]:checked, input[name="device_ids[]"]:checked').length;
          if (pickerEl || document.querySelector('input[name="sender[]"], input[name="device_ids[]"]')) {
            if (senderChecked === 0) return { ok: false, msg: 'Pick at least one sender.', el: pickerEl || $id('device-list') };
          } else if (single) {
            if (!single.value) return { ok: false, msg: 'Select a sender.', el: single };
          }
          if (document.querySelectorAll('#kw-list .kw-chip').length === 0) {
            return { ok: false, msg: 'Add at least one keyword.', el: $id('kw-input') };
          }
        }
        if (n === 3) {
          const rt = document.querySelector('.rt-tile.border-wa-deep')?.dataset.rt || 'custom';
          if (rt === 'share_contact' && !$id('rt-target-contact')?.value) {
            return { ok: false, msg: 'Pick a contact to share.', el: $id('rt-target-contact') };
          }
          if (rt === 'send_catalog' && !$id('rt-target-catalog')?.value) {
            return { ok: false, msg: 'Pick a catalog to send.', el: $id('rt-target-catalog') };
          }
        }
        if (n === 4) {
          const rt = document.querySelector('.rt-tile.border-wa-deep')?.dataset.rt || 'custom';
          if (rt === 'flow') {
            const picked = document.querySelector('#branch-flow .ar-flow-radio:checked')
              || document.querySelector('#branch-flow [data-flow].border-wa-deep');
            if (!picked) return { ok: false, msg: 'Select a flow to run.', el: $id('ar-flow-search') };
          } else if (rt === 'custom') {
            const tab = document.querySelector('#ftabs .bg-wa-deep')?.dataset.tab || 'text';
            if (tab === 'text' && !$id('reply-text')?.value.trim()) {
              return { ok: false, msg: 'Write a reply message.', el: $id('reply-text') };
            }
          }
        }
        return { ok: true };
      }
      // Guard a forward move; returns true if it's allowed to proceed.
      function gateForward(from) {
        const v = validateStep(from);
        if (!v.ok) { toast(v.msg, 'error'); flashInvalid(v.el); }
        return v.ok;
      }

      btnPrev.addEventListener('click', () => cur > 1 && show(cur - 1));
      btnNext.addEventListener('click', () => { if (cur < TOTAL && gateForward(cur)) show(cur + 1); });
      nodes.forEach(node => node.addEventListener('click', () => {
        const target = +node.dataset.n;
        // Free to jump backward; forward jumps must pass the current step.
        if (target <= cur || gateForward(cur)) show(target);
      }));

      // ── Edit mode hydration ──────────────────────────────────────────
      // window.WA_AUTOREPLY_ROW is set by the blade in edit mode
      // (`/auto-reply/create?id=N`). When present, walk every form
      // surface and apply the row's saved values, then flip the
      // submit endpoint to PATCH /auto-reply/{id}.
      const editRow = window.WA_AUTOREPLY_ROW || null;

      function prefillFromRow(r) {
          if (!r) return;
          // 1. Keyword chips — recreate one per saved keyword.
          const kwList  = document.getElementById('kw-list');
          const kwInput = document.getElementById('kw-input');
          if (kwList && kwInput && Array.isArray(r.keywords)) {
              kwList.querySelectorAll('.kw-chip').forEach(c => c.remove());
              r.keywords.forEach(kw => {
                  const span = document.createElement('span');
                  span.className = 'kw-chip inline-flex items-center gap-1.5 bg-paper-0 border border-paper-200 pl-2.5 pr-1 py-1 rounded-full text-[11.5px]';
                  span.dataset.kw = kw;
                  span.textContent = kw + ' ';
                  const btn = document.createElement('button');
                  btn.type = 'button';
                  btn.className = 'w-[18px] h-[18px] rounded-full bg-paper-100 text-ink-600 grid place-items-center hover:bg-accent-coral hover:text-white';
                  btn.innerHTML = '&times;';
                  btn.addEventListener('click', () => span.remove());
                  span.appendChild(btn);
                  kwList.insertBefore(span, kwInput);
              });
          }

          // 2. Sender selection. The unified <x-sender-picker> renders
          //    name="sender[]" checkboxes whose value is the composite
          //    `engine:id` key — match the saved row's sender_key. Falls
          //    back to the legacy device_ids[] checkboxes / <select
          //    id="device"> (matched by bare device id) for an un-migrated
          //    render.
          const wantedKey = String(r.sender_key ?? '');
          const wantedId  = String(r.device_id ?? '');
          const senderBoxes = document.querySelectorAll('input[type="checkbox"][name="sender[]"]');
          const legacyBoxes = document.querySelectorAll('input[type="checkbox"][name="device_ids[]"]');
          if (senderBoxes.length) {
              senderBoxes.forEach(cb => { cb.checked = cb.value === wantedKey; });
          } else if (legacyBoxes.length) {
              legacyBoxes.forEach(cb => { cb.checked = cb.value === wantedId; });
          } else {
              const sel = document.getElementById('device');
              if (sel && wantedId) sel.value = wantedId;
          }

          // 3. Match method segment.
          document.querySelectorAll('#seg-mm .seg-btn').forEach(b => {
              if (b.dataset.mm === r.matching_method) b.click();
          });

          // 4. Similarity slider.
          const fuzzy = document.getElementById('fuzzy');
          if (fuzzy && r.fuzzy_similarity != null) fuzzy.value = String(r.fuzzy_similarity);

          // 5. Cooldown / timeout numerics.
          const cd = document.getElementById('cooldown'); if (cd) cd.value = r.cooldown    ?? '';
          const td = document.getElementById('timeout');  if (td) td.value = r.timeout     ?? '';

          // 6. Reply-type tile.
          document.querySelectorAll('.rt-tile').forEach(t => {
              if (t.dataset.rt === r.reply_type) t.click();
          });

          // 7. Custom-reply content. The pre-existing flow / catalog
          //    target inputs are populated from their respective ids
          //    if they exist in the blade.
          if (r.reply_type === 'custom' && Array.isArray(r.contents) && r.contents.length) {
              const first = r.contents[0];
              // Switch to the matching format tab.
              document.querySelectorAll('#ftabs .ftab').forEach(t => {
                  if (t.dataset.tab === first.content_type) t.click();
              });
              if (first.content_type === 'text') {
                  const body = document.getElementById('reply-text');
                  if (body) { body.value = first.content || ''; body.dispatchEvent(new Event('input')); }
              }
              // image/video/document captions get prefilled where
              // the existing input id matches the convention.
              if (first.content_type === 'image') {
                  const cap = document.getElementById('img-cap');
                  if (cap) cap.value = first.content || '';
              }
              if (first.content_type === 'video') {
                  const cap = document.getElementById('vid-cap');
                  if (cap) cap.value = first.content || '';
              }
              // Re-select the saved template chip so editing keeps the choice.
              if (first.content_type === 'template' && first.template_id) {
                  document.querySelector(`[data-tpl-id="${first.template_id}"]`)?.click();
              }
              // Note: file inputs can't be programmatically re-filled
              // for security reasons. Operator must re-upload if they
              // want to swap the media. Caption text persists fine.
          } else if (r.reply_type === 'flow' && r.flow_id) {
              const tile = document.querySelector(`#branch-flow [data-flow="${r.flow_id}"]`);
              tile?.click?.();
          } else if (r.reply_type === 'share_contact' && r.target_contact_id) {
              const tc = document.getElementById('rt-target-contact');
              if (tc) tc.value = String(r.target_contact_id);
          } else if (r.reply_type === 'send_catalog' && r.target_catalog_id) {
              const tc = document.getElementById('rt-target-catalog');
              if (tc) tc.value = String(r.target_catalog_id);
          }

          // 8. Rule name (legacy: keyword first part doubles as label).
          const rn = document.getElementById('rule-name');
          if (rn) rn.value = (r.keywords && r.keywords[0]) || r.name || '';

          // 9. Submit button copy — "Update" reads better than "Save".
          const btn = document.getElementById('btn-submit');
          if (btn) btn.textContent = 'Update auto reply';
      }
      // Hydrate after the existing seg-mm / rt-tile / ftab handlers
      // are wired, so .click() in prefill triggers their side-effects.
      prefillFromRow(editRow);

      // ── Submit ────────────────────────────────────────────────────────
      // Gather every field the form already collects, build FormData,
      // POST /auto-reply (create) or PATCH /auto-reply/{id} (edit).
      // ── Media upload wiring (image / document / video) ────────────────
      // Drop zones are <label for="{tab}-file"> so a click opens the native
      // picker with no JS. Here we add: show the chosen filename, and accept
      // drag-and-drop onto the zone. The submit handler reads the file from
      // [data-ar-file="{tab}"] for the active tab.
      document.querySelectorAll('[data-ar-file]').forEach((inp) => {
          const tab     = inp.dataset.arFile;
          const fnameEl = document.querySelector(`[data-ar-fname="${tab}"]`);
          const dropEl  = document.querySelector(`[data-ar-drop="${tab}"]`);
          const showName = () => {
              const f = inp.files && inp.files[0];
              if (fnameEl) {
                  fnameEl.textContent = f ? f.name : '';
                  fnameEl.classList.toggle('hidden', !f);
              }
          };
          inp.addEventListener('change', showName);
          if (dropEl) {
              ['dragover', 'dragenter'].forEach(ev => dropEl.addEventListener(ev, (e) => {
                  e.preventDefault(); dropEl.classList.add('border-wa-deep');
              }));
              ['dragleave', 'drop'].forEach(ev => dropEl.addEventListener(ev, (e) => {
                  e.preventDefault(); dropEl.classList.remove('border-wa-deep');
              }));
              dropEl.addEventListener('drop', (e) => {
                  if (e.dataTransfer?.files?.[0]) { inp.files = e.dataTransfer.files; showName(); }
              });
          }
      });

      const form = document.getElementById('autoreplyForm');
      const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
      const toast = (m, k = 'info') => window.toast ? window.toast(m, k === 'error' ? 'error' : 'success') : alert(m);

      const $val = id => (document.getElementById(id)?.value ?? '').toString().trim();
      const activeMatchMethod = () => document.querySelector('#seg-mm .seg-btn.bg-wa-deep')?.dataset.mm || 'exact';
      const activeReplyType   = () => document.querySelector('.rt-tile.border-wa-deep')?.dataset.rt || 'custom';
      const activeFormatTab   = () => document.querySelector('#ftabs button.bg-wa-deep')?.dataset.tab || 'text';

      function selectedFlowId() {
          // Flow picker — current selection has border-wa-deep + a data-flow id.
          const sel = document.querySelector('#branch-flow [data-flow].border-wa-deep');
          return sel?.dataset.flow || '';
      }

      function collectKeywords() {
          // Two equally valid chip shapes have shipped over time:
          //   - explicit data-kw attribute (clean machine-readable)
          //   - .kw-chip class with the keyword as the chip's text
          //     plus an inline × delete <button>
          // Read whichever the DOM gives us. Strip the trailing ×
          // button text and any whitespace so the keyword survives
          // both ways.
          const list = document.getElementById('kw-list');
          if (!list) return [];
          const attr  = Array.from(list.querySelectorAll('[data-kw]'))
              .map(c => (c.dataset.kw || '').trim())
              .filter(Boolean);
          if (attr.length) return attr;
          const text = Array.from(list.querySelectorAll('.kw-chip'))
              .map(c => {
                  // Clone so we can strip child buttons without
                  // mutating the live chip.
                  const clone = c.cloneNode(true);
                  clone.querySelectorAll('button').forEach(b => b.remove());
                  return clone.textContent.trim();
              })
              .filter(Boolean);
          if (text.length) return text;
          // Last-ditch — operator left the keyword unconverted in
          // the type-and-press-Enter input.
          const raw = document.getElementById('kw-input')?.value?.trim();
          return raw ? [raw] : [];
      }

      form?.addEventListener('submit', async (e) => {
          e.preventDefault();
          if (cur !== TOTAL) { show(TOTAL); return; }

          const keywords = collectKeywords();
          if (keywords.length === 0) {
              toast('Add at least one keyword.', 'error');
              show(1);
              return;
          }

          // Sender gate. Unified picker: at least one sender[] checkbox
          // must be ticked. Legacy fallback: device_ids[] checkbox or the
          // single <select id="device">. Either way we send the user back
          // to step 1 so they can correct without losing input.
          const senderChecked = document.querySelectorAll('input[type="checkbox"][name="sender[]"]:checked').length;
          const legacyChecked = document.querySelectorAll('input[type="checkbox"][name="device_ids[]"]:checked').length;
          const singleVal     = document.getElementById('device')?.value || '';
          const hasSenderList = document.querySelector('input[type="checkbox"][name="sender[]"]') !== null;
          const hasLegacyList = document.querySelector('input[type="checkbox"][name="device_ids[]"]') !== null;
          if (hasSenderList && senderChecked === 0) {
              toast('Pick at least one sender.', 'error');
              show(1);
              return;
          }
          if (!hasSenderList && hasLegacyList && legacyChecked === 0) {
              toast('Pick at least one sender.', 'error');
              show(1);
              return;
          }
          if (!hasSenderList && !hasLegacyList && !singleVal) {
              toast('Pick a sender.', 'error');
              show(1);
              return;
          }

          const fd = new FormData();
          // The legacy schema stored the comma-joined keyword list as a
          // single string — match that so our match scope reads the row
          // with one query per inbound.
          fd.append('keyword',          keywords.join(', '));

          // Sender: the unified picker submits composite engine:id keys via
          // sender[] (server resolves each to its sender + stamps provider =
          // chosen engine). Legacy single-engine renders still post
          // device_ids[]/device_id. We send WHATEVER the form rendered,
          // never both — the server accepts either path.
          const senderBoxes = document.querySelectorAll('input[type="checkbox"][name="sender[]"]:checked');
          const deviceBoxes = document.querySelectorAll('input[type="checkbox"][name="device_ids[]"]:checked');
          if (senderBoxes.length > 0) {
              senderBoxes.forEach(cb => fd.append('sender[]', cb.value));
          } else if (deviceBoxes.length > 0) {
              deviceBoxes.forEach(cb => fd.append('device_ids[]', cb.value));
          } else {
              fd.append('device_id',    $val('device'));
          }
          fd.append('matching_method',  activeMatchMethod());
          fd.append('fuzzy_similarity', $val('fuzzy') || '80');
          fd.append('cooldown',         $val('cooldown') || '');
          fd.append('timeout',          $val('timeout') || '');
          fd.append('reply_type',       activeReplyType());
          fd.append('status',           '1');

          const rt = activeReplyType();
          if (rt === 'flow') {
              fd.append('flow_id', selectedFlowId());
          } else if (rt === 'share_contact') {
              fd.append('target_contact_id', document.getElementById('rt-target-contact')?.value || '');
          } else if (rt === 'send_catalog') {
              fd.append('target_catalog_id', document.getElementById('rt-target-catalog')?.value || '');
          } else if (rt === 'request_location') {
              // No payload — the reply itself is the location prompt.
          } else {
              const tab = activeFormatTab();
              fd.append('contents[0][content_type]', tab);
              fd.append('contents[0][is_selected]',  '1');
              fd.append('contents[0][sort_order]',   '0');
              if (tab === 'text') {
                  fd.append('contents[0][content]', $val('reply-text'));
              } else if (tab === 'image' || tab === 'video' || tab === 'document') {
                  // Caption + file. The blade caption inputs have IDs
                  // matching the tab (e.g. media-cap, image-cap). Try a
                  // few common ids; fallback to reply-text.
                  const cap = $val(tab + '-cap') || $val('media-cap') || $val('reply-text');
                  fd.append('contents[0][content]', cap);
                  const fileInput = document.querySelector(`[data-ar-file="${tab}"]`)
                                 || document.querySelector(`[data-panel="${tab}"] input[type=file]`);
                  if (fileInput?.files?.[0]) fd.append('contents[0][media]', fileInput.files[0]);
              } else if (tab === 'template') {
                  // Template picker stores selected template id on the chip.
                  const tplId = document.querySelector('[data-tpl-id].border-wa-deep')?.dataset.tplId
                              || document.getElementById('tpl-sel-id')?.dataset.tplId
                              || '';
                  fd.append('contents[0][template_id]', tplId);
              }
          }

          const submitBtn = document.getElementById('btn-submit');
          const origLabel = submitBtn.textContent;
          submitBtn.disabled = true;
          submitBtn.textContent = editRow ? 'Updating…' : 'Saving…';

          // PATCH the existing row in edit mode (route `update`) or
          // POST a new one (route `store`). Both endpoints share the
          // same validator + redirect_url response shape.
          const isEdit = editRow && editRow.id;
          const url    = isEdit ? `/auto-reply/${editRow.id}` : '/auto-reply';
          if (isEdit) fd.append('_method', 'PATCH');

          try {
              const res = await fetch(url, {
                  method: 'POST', // FormData + _method spoofs PATCH/PUT
                  credentials: 'same-origin',
                  headers: {
                      'Accept': 'application/json',
                      'X-Requested-With': 'XMLHttpRequest',
                      'X-CSRF-TOKEN': csrf(),
                  },
                  body: fd,
              });
              const data = await res.json().catch(() => ({}));
              if (!res.ok) {
                  const firstErr = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                  throw new Error(firstErr || data?.message || `HTTP ${res.status}`);
              }
              toast(isEdit ? 'Auto reply updated.' : 'Auto reply saved.', 'success');
              window.location.href = data?.data?.redirect_url || '/auto-reply';
          } catch (err) {
              toast((isEdit ? 'Update' : 'Save') + ' failed: ' + err.message, 'error');
              submitBtn.disabled = false;
              submitBtn.textContent = origLabel;
          }
      });
}
