// Instaflow analytics — per-post insights drill-down. Page key: instagram-analytics.
export default function init() {
    const grid = document.querySelector('[data-acct]');
    const panel = document.getElementById('ig-post-insights');
    if (!grid || !panel) return;
    const acct = grid.getAttribute('data-acct');
    const out = panel.querySelector('[data-pi-grid]');
    const title = panel.querySelector('[data-pi-title]');

    const label = (k) => k.replace(/_/g, ' ').replace(/\big reels\b/i, '').trim();

    grid.querySelectorAll('[data-post-insights]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const media = btn.getAttribute('data-media');
            const type = btn.getAttribute('data-type') || '';
            panel.classList.remove('hidden');
            out.innerHTML = `<div class="text-[12px] text-ink-500">Loading…</div>`;
            try {
                const url = `/instagram/analytics/post/${encodeURIComponent(media)}?account=${encodeURIComponent(acct)}&type=${encodeURIComponent(type)}`;
                const r = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = await r.json();
                const metrics = (data && data.metrics) || {};
                const keys = Object.keys(metrics);
                if (!keys.length) {
                    out.innerHTML = `<div class="text-[12px] text-ink-500">No insights for this post (albums and very new posts return none).</div>`;
                    return;
                }
                title.textContent = `${type || 'Post'} insights`;
                out.innerHTML = keys.map((k) => `
                    <div class="text-center">
                        <div class="serif text-[22px] tabular leading-none">${Number(metrics[k]).toLocaleString()}</div>
                        <div class="mono text-[9px] uppercase tracking-widest text-ink-500 mt-1">${label(k)}</div>
                    </div>`).join('');
            } catch (e) {
                out.innerHTML = `<div class="text-[12px] text-accent-coral">Couldn't load insights.</div>`;
            }
        });
    });
}
