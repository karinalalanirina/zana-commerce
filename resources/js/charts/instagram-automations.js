// Instaflow auto-reply / automations INDEX (list-only). Page key:
// instagram-automations (in app.js).
//
// The create + edit wizards now live on their own pages
// (instagram-automations-create / -edit → instagram-automations-form.js), so
// this module only drives the rules table's client-side search + type filter.
// Toggle (on/off) and delete are plain server forms — handled by the global
// confirm-form delegate in app.js, no JS needed here.
export default function init() {
    const rows = Array.from(document.querySelectorAll('tr[data-rule-type]'));
    const searchInput = document.querySelector('[data-rule-search]');
    const filterChips = Array.from(document.querySelectorAll('[data-rule-filter]'));
    const noResults = document.querySelector('[data-rule-noresults]');
    let activeFilter = 'all';

    const applyTable = () => {
        const q = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;
        rows.forEach((row) => {
            const matchesType = activeFilter === 'all' || row.dataset.ruleType === activeFilter;
            const matchesText = !q || (row.dataset.ruleName || '').includes(q);
            const on = matchesType && matchesText;
            row.classList.toggle('hidden', !on);
            if (on) visible += 1;
        });
        if (noResults) noResults.classList.toggle('hidden', visible !== 0 || rows.length === 0);
    };

    searchInput?.addEventListener('input', applyTable);
    filterChips.forEach((chip) => {
        chip.addEventListener('click', () => {
            activeFilter = chip.dataset.ruleFilter;
            filterChips.forEach((c) => {
                const on = c === chip;
                c.classList.toggle('bg-ink-900', on);
                c.classList.toggle('text-white', on);
                c.classList.toggle('text-ink-600', !on);
            });
            applyTable();
        });
    });
}
