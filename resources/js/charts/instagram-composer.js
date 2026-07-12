// Instaflow composer — mode tabs, schedule toggle, caption counter, live phone
// preview, and submit guards. Page key: instagram-composer (registered in app.js).
export default function init() {
    const form = document.getElementById('composer-form');
    if (!form) return;

    const typeInput = document.getElementById('cmp-media-type');
    const modeTabs = Array.from(document.querySelectorAll('[data-mode]'));
    const mediaGroups = Array.from(document.querySelectorAll('[data-media-for]'));

    const showMedia = (mode) => {
        mediaGroups.forEach((g) => {
            const forList = (g.dataset.mediaFor || '').split(',');
            g.classList.toggle('hidden', !forList.includes(mode));
        });
    };
    const setMode = (mode) => {
        if (typeInput) typeInput.value = mode;
        modeTabs.forEach((t) => {
            const on = t.dataset.mode === mode;
            t.classList.toggle('active', on);
            t.classList.toggle('ig-grad-soft', on);
            t.classList.toggle('text-white', on);
        });
        showMedia(mode);
    };
    modeTabs.forEach((t) => t.addEventListener('click', () => setMode(t.dataset.mode)));
    setMode(typeInput?.value || 'image');

    // ── Single file upload → thumb + phone preview ──
    const fileInput = document.getElementById('cmp-file');
    const fileThumb = document.getElementById('cmp-file-thumb');
    const fileImg = document.getElementById('cmp-file-img');
    const fileName = document.getElementById('cmp-file-name');
    fileInput?.addEventListener('change', () => {
        const f = fileInput.files?.[0];
        if (!f) return;
        if (fileName) fileName.textContent = f.name;
        fileThumb?.classList.remove('hidden');
        fileThumb?.classList.add('flex');
        const pv = document.getElementById('pv-image');
        if (f.type.startsWith('image/')) {
            const r = new FileReader();
            r.onload = (e) => {
                const u = `url("${e.target.result}")`;
                if (fileImg) fileImg.style.backgroundImage = u;
                if (pv) { pv.style.backgroundImage = u; pv.style.backgroundSize = 'cover'; pv.style.backgroundPosition = 'center'; }
            };
            r.readAsDataURL(f);
        } else if (fileImg) {
            fileImg.style.backgroundImage = '';
        }
    });
    document.getElementById('cmp-file-clear')?.addEventListener('click', () => {
        if (fileInput) fileInput.value = '';
        fileThumb?.classList.add('hidden');
        fileThumb?.classList.remove('flex');
        if (fileImg) fileImg.style.backgroundImage = '';
    });

    // ── Carousel multi-file → thumbnails ──
    const filesInput = document.getElementById('cmp-files');
    const filesThumbs = document.getElementById('cmp-files-thumbs');
    filesInput?.addEventListener('change', () => {
        if (!filesThumbs) return;
        filesThumbs.innerHTML = '';
        const files = Array.from(filesInput.files || []).slice(0, 10);
        files.forEach((f) => {
            const d = document.createElement('div');
            d.className = 'w-14 h-14 rounded-lg bg-paper-100 bg-center bg-cover ring-1 ring-paper-200';
            if (f.type.startsWith('image/')) {
                const r = new FileReader();
                r.onload = (e) => { d.style.backgroundImage = `url("${e.target.result}")`; };
                r.readAsDataURL(f);
            }
            filesThumbs.appendChild(d);
        });
        filesThumbs.classList.toggle('hidden', files.length === 0);
        filesThumbs.classList.toggle('flex', files.length > 0);
    });

    // ── Drag & drop onto the upload zones ──
    const wireDrop = (zoneId, input) => {
        const zone = document.getElementById(zoneId);
        if (!zone || !input) return;
        ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => {
            e.preventDefault();
            zone.classList.add('border-ig-pink', 'bg-paper-50');
        }));
        ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => {
            e.preventDefault();
            zone.classList.remove('border-ig-pink', 'bg-paper-50');
        }));
        zone.addEventListener('drop', (e) => {
            const files = e.dataTransfer?.files;
            if (files && files.length) {
                input.files = files;
                input.dispatchEvent(new Event('change'));
            }
        });
    };
    wireDrop('cmp-drop', fileInput);
    wireDrop('cmp-drop-multi', filesInput);

    // ── When to publish: now vs schedule ──
    let whenMode = 'now';
    const whenBtns = Array.from(document.querySelectorAll('[data-when]'));
    const schedFields = document.getElementById('cmp-schedule-fields');
    const schedInput = document.getElementById('cmp-schedule-at');
    const submitLabel = document.getElementById('cmp-submit-label');
    if (schedInput) {
        // No scheduling in the past.
        const now = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
        schedInput.min = now.toISOString().slice(0, 16);
    }
    const setWhen = (mode) => {
        whenMode = mode;
        whenBtns.forEach((b) => {
            const on = b.dataset.when === mode;
            b.classList.toggle('bg-ig-pink/5', on);
            b.classList.toggle('border-ig-pink/40', on);
        });
        const sched = mode === 'schedule';
        schedFields?.classList.toggle('hidden', !sched);
        if (!sched && schedInput) schedInput.value = '';
        if (submitLabel) submitLabel.textContent = sched ? 'Schedule post' : 'Publish now';
    };
    whenBtns.forEach((b) => b.addEventListener('click', () => setWhen(b.dataset.when)));
    setWhen('now');

    // ── Caption counter + live preview ──
    const caption = document.getElementById('cmp-caption');
    const count = document.getElementById('cmp-count');
    const pvCaption = document.getElementById('pv-caption');
    const syncCaption = () => {
        const v = caption?.value || '';
        if (count) {
            count.textContent = `${v.length} / 2,200`;
            count.classList.toggle('text-accent-coral', v.length > 2200);
            count.classList.toggle('text-ink-400', v.length <= 2200);
        }
        if (pvCaption) pvCaption.textContent = v || 'Your caption preview…';
    };
    caption?.addEventListener('input', syncCaption);
    syncCaption();
    // (Media preview is driven by the file-upload handler above — composer is
    // upload-only, so there are no URL inputs to watch.)

    // ── Account radio → preview name/avatar ──
    const pvName = document.getElementById('pv-name');
    const pvAvatar = document.getElementById('pv-avatar');
    document.querySelectorAll('input[name="instagram_account_id"]').forEach((r) => {
        r.addEventListener('change', () => {
            if (pvName) pvName.textContent = r.dataset.username || 'your.account';
            if (pvAvatar) pvAvatar.textContent = r.dataset.initials || 'IG';
        });
    });

    // ── Submit guards ──
    form.addEventListener('submit', (e) => {
        if (!form.querySelector('input[name="instagram_account_id"]:checked')) {
            e.preventDefault();
            window.toast?.('Pick an account to publish to.', 'error');
            return;
        }
        if (whenMode === 'schedule' && !schedInput?.value) {
            e.preventDefault();
            window.toast?.('Pick a date & time to schedule, or choose Publish now.', 'error');
            schedInput?.focus();
        }
    });
}
