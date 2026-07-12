import './lib/base-url'; // sub-folder base-path: prefix root-relative fetch/XHR when served under /public — MUST be first
import './bootstrap';
import './wadesk';
import './wa-editor';
import './wa-toaster';
import './attribute-picker';
import './lib/ui-modal'; // window.uiAlert/uiConfirm/uiPrompt + data-confirm / data-prompt-reason delegation
import './lib/password-reveal'; // eye toggle on every <input type="password"> + [data-reveal]
import './cookie-consent';     // GDPR/CCPA modal + window.wadeskCookieConsent
import './locale-switcher';   // header language dropdown — moved out of the blade's @push so it doesn't accumulate in the scripts stack
import './tour/guided-tour';  // first-run guided product tour (Driver.js) — auto-runs once per user via users.has_seen_intro
import initAdminSidebar from './admin-sidebar';
import initListGridToggles from './list-grid-toggle';
import Toastify from 'toastify-js';
import 'toastify-js/src/toastify.css';

window.toast = function (message, type = 'success') {
    const palette = {
        success: { bg: 'linear-gradient(135deg, #075E54, #128C7E)', color: '#FBFAF6' },
        error:   { bg: 'linear-gradient(135deg, #E87A5D, #C25744)', color: '#FBFAF6' },
        info:    { bg: 'linear-gradient(135deg, #13478A, #0F8556)', color: '#FBFAF6' },
    };
    const p = palette[type] || palette.success;
    Toastify({
        text: message,
        duration: 2500,
        gravity: 'top',
        position: 'right',
        close: true,
        stopOnFocus: true,
        style: {
            background: p.bg,
            color: p.color,
            fontFamily: 'Plus Jakarta Sans, system-ui, sans-serif',
            fontSize: '13px',
            fontWeight: '500',
            padding: '12px 18px',
            borderRadius: '12px',
            boxShadow: '0 12px 32px -10px rgba(11,31,28,0.32)',
        },
    }).showToast();
};

/**
 * Programmatic confirm modal — replaces window.confirm() with the
 * chat page's themed dialog (resources/views/user/chat/index.blade.php
 * #confirm-modal). The modal mirrors the <x-confirm-modal> Blade
 * component; if a page didn't include the component, a fallback DOM
 * node is appended to <body> on first call.
 *
 *      window.confirmDialog({
 *          title: 'Delete contact?',
 *          message: 'This action cannot be undone.',
 *          confirmText: 'Delete',
 *          cancelText: 'Cancel',
 *          tone: 'danger',           // 'danger' | 'info'
 *          onConfirm: () => doIt(),
 *          onCancel: () => undefined,
 *      });
 */
window.confirmDialog = function ({
    title       = 'Are you sure?',
    message     = 'This action cannot be undone.',
    confirmText = 'Delete',
    cancelText  = 'Cancel',
    eyebrow     = 'Confirm',
    tone        = 'danger',
    onConfirm   = () => {},
    onCancel    = () => {},
} = {}) {
    let modal = document.getElementById('globalConfirmModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'globalConfirmModal';
        modal.setAttribute('data-confirm-modal', '');
        modal.className = 'modal hidden fixed inset-0 z-[60] items-center justify-center p-5 bg-[rgba(11,31,28,0.46)] [&.open]:flex';
        modal.innerHTML = `
            <div class="modal-panel w-full max-w-md bg-paper-0 border border-paper-200 rounded-2xl shadow-[0_28px_80px_-35px_rgba(11,31,28,0.55)] overflow-hidden">
                <div class="px-5 py-4 flex items-start gap-3 border-b border-paper-200">
                    <div data-confirm-icon class="w-10 h-10 rounded-2xl grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4h10M5 4V2.5h6V4M4 4l1 10h6l1-10"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div data-confirm-eyebrow class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500"></div>
                        <h3 data-confirm-title class="font-serif text-[20px] leading-tight tracking-[-0.01em] mt-0.5"></h3>
                        <p data-confirm-message class="mt-1.5 text-[13px] text-ink-600 leading-relaxed"></p>
                    </div>
                </div>
                <div class="px-5 py-3 flex justify-end gap-2 bg-paper-50/60">
                    <button type="button" data-confirm-cancel class="px-4 py-2 rounded-full border border-paper-200 bg-white text-[12px] font-semibold hover:border-wa-deep"></button>
                    <button type="button" data-confirm-accept class="px-4 py-2 rounded-full text-paper-0 text-[12px] font-semibold"></button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }

    const eyebrowEl = modal.querySelector('[data-confirm-eyebrow]');
    const titleEl   = modal.querySelector('[data-confirm-title]');
    const msgEl     = modal.querySelector('[data-confirm-message]');
    const iconEl    = modal.querySelector('[data-confirm-icon]');
    const cancelBtn = modal.querySelector('[data-confirm-cancel]');
    const acceptBtn = modal.querySelector('[data-confirm-accept]');

    if (eyebrowEl) eyebrowEl.textContent = eyebrow;
    if (titleEl)   titleEl.textContent   = title;
    if (msgEl)     msgEl.textContent     = message;
    if (cancelBtn) cancelBtn.textContent = cancelText;
    if (acceptBtn) acceptBtn.textContent = confirmText;

    if (iconEl) {
        iconEl.className = `w-10 h-10 rounded-2xl grid place-items-center shrink-0 ${tone === 'danger' ? 'bg-accent-coral/15 text-accent-coral' : 'bg-wa-mint text-wa-deep'}`;
    }
    if (acceptBtn) {
        acceptBtn.className = `px-4 py-2 rounded-full text-paper-0 text-[12px] font-semibold ${tone === 'danger' ? 'bg-accent-coral hover:bg-[#A1431F]' : 'bg-wa-deep hover:bg-wa-teal'}`;
    }

    const close = () => {
        modal.classList.remove('open');
        document.removeEventListener('keydown', onKey);
    };
    const accept = () => { close(); try { onConfirm(); } catch (e) { console.error(e); } };
    const cancel = () => { close(); try { onCancel(); } catch (e) { console.error(e); } };
    const onKey  = (e) => {
        if (e.key === 'Escape') cancel();
        else if (e.key === 'Enter') accept();
    };

    if (cancelBtn) cancelBtn.onclick = cancel;
    if (acceptBtn) acceptBtn.onclick = accept;
    modal.onclick = (e) => { if (e.target === modal) cancel(); };
    document.addEventListener('keydown', onKey);

    modal.classList.add('open');
    setTimeout(() => acceptBtn?.focus(), 30);
};

const PAGE_INITIALIZERS = {
    'install-wizard':  () => import('./charts/install-wizard.js').then((m) => m.default()),
    'admin-overview':  () => import('./charts/admin-overview.js').then((m) => m.default()),
    'admin-financial': () => import('./charts/admin-financial.js').then((m) => m.default()),
    'admin-premium':   () => import('./charts/admin-premium.js').then((m) => m.default()),
    'admin-users-create':      () => import('./charts/admin-users-form.js').then((m) => m.default()),
    'admin-users-edit':        () => import('./charts/admin-users-form.js').then((m) => m.default()),
    'admin-workspaces-create': () => import('./charts/admin-workspaces-form.js').then((m) => m.default()),
    'admin-workspaces-detail': () => import('./charts/admin-workspaces-detail.js').then((m) => m.default()),
    'admin-packages-create':   () => import('./charts/admin-packages-create.js').then((m) => m.default()),
    'admin-packages-edit':     () => import('./charts/admin-packages-create.js').then((m) => m.default()),
    'admin-coupons-index':     () => import('./charts/packages-index.js').then((m) => m.default()),
    'admin-coupons-create':    () => import('./charts/admin-coupons-form.js').then((m) => m.default()),
    'admin-coupons-edit':      () => import('./charts/admin-coupons-form.js').then((m) => m.default()),
    'admin-billing-history-index':     () => import('./charts/admin-billing-history-index.js').then((m) => m.default()),
    'admin-billing-history-analytics': () => import('./charts/admin-billing-history-analytics.js').then((m) => m.default()),
    'admin-announcements-index':  () => import('./charts/packages-index.js').then((m) => m.default()),
    'admin-announcements-create': () => import('./charts/admin-announcements-form.js').then((m) => m.default()),
    'admin-announcements-edit':   () => import('./charts/admin-announcements-form.js').then((m) => m.default()),
    'admin-legal-pages-edit':     () => import('./charts/admin-legal-pages-edit.js').then((m) => m.default()),
    'admin-campaigns-create':     () => import('./charts/admin-campaigns-create.js').then((m) => m.default()),
    'admin-audit-log-index':      () => import('./charts/admin-audit-log-index.js').then((m) => m.default()),
    'admin-settings-general':     () => import('./charts/admin-settings-general.js').then((m) => m.default()),
    'admin-wallet-rules':         () => import('./charts/admin-wallet-rules.js').then((m) => m.default()),
    'admin-settings-seo':         () => import('./charts/admin-settings-seo.js').then((m) => m.default()),
    'admin-settings-shopify':     () => import('./charts/admin-settings-shopify.js').then((m) => m.default()),
    'admin-settings-hubspot':     () => import('./charts/admin-settings-hubspot.js').then((m) => m.default()),
    'admin-settings-social-login': () => import('./charts/admin-settings-hubspot.js').then((m) => m.default()),
    'admin-languages-index':      () => import('./charts/packages-index.js').then((m) => m.default()),
    'admin-payment-gateways-index': () => import('./charts/admin-payment-gateways-index.js').then((m) => m.default()),
    'admin-support-index':         () => import('./charts/admin-support-index.js').then((m) => m.default()),
    'admin-support-team':          () => import('./charts/admin-support-team.js').then((m) => m.default()),
    'admin-analytics-index':       () => import('./charts/admin-analytics-index.js').then((m) => m.default()),
    'user-overview':  () => import('./charts/user-overview.js').then((m) => m.default()),
    'users-index': () => import('./charts/users-index.js').then((m) => m.default()),
    'roles-index': () => import('./charts/roles-index.js').then((m) => m.default()),
    'roles-create': () => import('./charts/roles-create.js').then((m) => m.default()),
    'roles-edit': () => import('./charts/roles-edit.js').then((m) => m.default()),
    'permissions-create': () => import('./charts/permissions-create.js').then((m) => m.default()),
    'workspaces-index': () => import('./charts/workspaces-index.js').then((m) => m.default()),
    'workspaces-detail': () => import('./charts/workspaces-detail.js').then((m) => m.default()),
    'devices-index': () => import('./charts/devices-index.js').then((m) => m.default()),
    'devices-detail': () => import('./charts/devices-detail.js').then((m) => m.default()),
    'packages-index': () => import('./charts/packages-index.js').then((m) => m.default()),
    'packages-create': () => import('./charts/packages-create.js').then((m) => m.default()),
    'packages-edit': () => import('./charts/packages-edit.js').then((m) => m.default()),
    'packages-analytics': () => import('./charts/packages-analytics.js').then((m) => m.default()),
    'campaigns-analytics': () => import('./charts/campaigns-analytics.js').then((m) => m.default()),
    'user-meta-ads-index': () => import('./charts/user-meta-ads-index.js').then((m) => m.default()),
    'user-meta-ads-show':  () => import('./charts/user-meta-ads-show.js').then((m) => m.default()),
    'billing-history-index': () => import('./charts/billing-history-index.js').then((m) => m.default()),
    'billing-history-analytics': () => import('./charts/billing-history-analytics.js').then((m) => m.default()),
    'order-history-analytics': () => import('./charts/order-history-analytics.js').then((m) => m.default()),
    'invoices-index': () => import('./charts/invoices-index.js').then((m) => m.default()),
    'invoices-view': () => import('./charts/invoices-view.js').then((m) => m.default()),
    'meta-ads-analytics': () => import('./charts/meta-ads-analytics.js').then((m) => m.default()),
    'meta-ads-analytics-detail': () => import('./charts/meta-ads-analytics-detail.js').then((m) => m.default()),
    'analytics-index': () => import('./charts/analytics-index.js').then((m) => m.default()),
    'audit-log-index': () => import('./charts/audit-log-index.js').then((m) => m.default()),
    'settings-wadesk-message': () => import('./charts/settings-wadesk-message.js').then((m) => m.default()),
    'support-index': () => import('./charts/support-index.js').then((m) => m.default()),
    'user-account-index': () => import('./charts/user-account-index.js').then((m) => m.default()),
    'user-analytics-index': () => import('./charts/user-analytics-index.js').then((m) => m.default()),
    'user-auto-reply-index': () => import('./charts/user-auto-reply-index.js').then((m) => m.default()),
    'user-auto-reply-create': () => import('./charts/user-auto-reply-create.js').then((m) => m.default()),
    'user-auto-reply-keyword': () => import('./charts/user-auto-reply-keyword.js').then((m) => m.default()),
    'user-broadcasts-index': () => import('./charts/user-broadcasts-index.js').then((m) => m.default()),
    'user-broadcasts-create': () => import('./charts/user-broadcasts-create.js').then((m) => m.default()),
    'user-broadcasts-show': () => import('./charts/user-broadcasts-show.js').then((m) => m.default()),
    'user-campaigns-create': () => import('./charts/user-campaigns-create.js').then((m) => m.default()),
    'user-campaigns-edit': () => import('./charts/user-campaigns-edit.js').then((m) => m.default()),
    'user-chat-index': () => import('./charts/user-chat-index.js').then((m) => m.default()),
    'user-connect-index': () => import('./charts/user-connect-index.js').then((m) => m.default()),
    'user-connect-wa-store': () => import('./charts/user-connect-wa-store.js').then((m) => m.default()),
    'user-developers-index': () => import('./charts/user-developers-index.js').then((m) => m.default()),
    'user-store-index': () => import('./charts/user-store-index.js').then((m) => m.default()),
    'user-store-products-create': () => import('./charts/user-store-products-create.js').then((m) => m.default()),
    'user-store-products-edit': () => import('./charts/user-store-products-edit.js').then((m) => m.default()),
    'user-contacts-index': () => import('./charts/user-contacts-index.js').then((m) => m.default()),
    'user-deals-board': () => import('./charts/user-deals-board.js').then((m) => m.default()),
    'user-deals-reports': () => import('./charts/user-deals-reports.js').then((m) => m.default()),
    'user-devices-index': () => import('./charts/user-devices-index.js').then((m) => m.default()),
    'user-devices-detail': () => import('./charts/user-devices-detail.js').then((m) => m.default()),
    'user-flows-index':   () => import('./charts/user-flows-index.js').then((m) => m.default()),
    'user-flows-builder': () => import('./charts/user-flows-builder.js').then((m) => m.default()),
    'user-ai-assistants-wizard': () => import('./charts/user-ai-assistants-wizard.js').then((m) => m.default()),
    'user-wa-forms-builder': () => import('./charts/user-wa-forms-builder.js').then((m) => m.default()),
    'user-wa-links-index':          () => import('./charts/user-wa-links-index.js').then((m) => m.default()),
    'user-wa-links-builder':        () => import('./charts/user-wa-links-builder.js').then((m) => m.default()),
    'user-chatbot-widgets-index':   () => import('./charts/user-chatbot-widgets-index.js').then((m) => m.default()),
    'user-chatbot-widgets-builder': () => import('./charts/user-chatbot-widgets-builder.js').then((m) => m.default()),
    'user-ai-training-index':       () => import('./charts/user-ai-training-index.js').then((m) => m.default()),
    'user-ai-training-builder':     () => import('./charts/user-ai-training-builder.js').then((m) => m.default()),
    'user-guidebook-index': () => import('./charts/user-guidebook-index.js').then((m) => m.default()),
    'user-integrations-index': () => import('./charts/user-integrations-index.js').then((m) => m.default()),
    'user-activity-log-index': () => import('./charts/user-activity-log-index.js').then((m) => m.default()),
    'user-affiliate-history-index': () => import('./charts/user-affiliate-history-index.js').then((m) => m.default()),
    'user-message-history-index': () => import('./charts/user-message-history-index.js').then((m) => m.default()),
    'user-meta-ads-analytics': () => import('./charts/user-meta-ads-analytics.js').then((m) => m.default()),
    'user-more-index': () => import('./charts/user-more-index.js').then((m) => m.default()),
    'user-notifications-index': () => import('./charts/user-notifications-index.js').then((m) => m.default()),
    'user-scheduled-index': () => import('./charts/user-scheduled-index.js').then((m) => m.default()),
    'user-scheduled-create': () => import('./charts/user-scheduled-create.js').then((m) => m.default()),
    'user-scheduled-detail': () => import('./charts/user-scheduled-detail.js').then((m) => m.default()),
    'user-settings-index': () => import('./charts/user-settings-index.js').then((m) => m.default()),
    'user-shopify-dashboard': () => import('./charts/user-shopify-dashboard.js').then((m) => m.default()),
    'user-support-index': () => import('./charts/user-support-index.js').then((m) => m.default()),
    'user-team-inbox-index':   () => import('./charts/user-team-inbox-index.js').then((m) => m.default()),
    'user-team-inbox-members': () => import('./charts/user-team-inbox-members.js').then((m) => m.default()),
    'user-team-chat':          () => import('./charts/user-team-chat.js').then((m) => m.default()),
    'admin-team-inbox-index':  () => import('./charts/admin-team-inbox-index.js').then((m) => m.default()),
    'user-templates-index': () => import('./charts/user-templates-index.js').then((m) => m.default()),
    'user-templates-create': () => import('./charts/user-templates-create.js').then((m) => m.default()),
    'user-templates-edit': () => import('./charts/user-templates-edit.js').then((m) => m.default()),
    'user-templates-show': () => import('./charts/user-templates-show.js').then((m) => m.default()),
    'user-wa-campaigns-index':  () => import('./charts/user-wa-campaigns-index.js').then((m) => m.default()),
    'user-wa-campaigns-create': () => import('./charts/user-wa-campaigns-create.js').then((m) => m.default()),
    'user-wa-campaigns-edit': () => import('./charts/user-wa-campaigns-edit.js').then((m) => m.default()),
    'user-wa-campaigns-detail': () => import('./charts/user-wa-campaigns-detail.js').then((m) => m.default()),
    'user-webhooks-index': () => import('./charts/user-webhooks-index.js').then((m) => m.default()),
    'user-webhooks-create': () => import('./charts/user-webhooks-create.js').then((m) => m.default()),
    'user-webhooks-detail': () => import('./charts/user-webhooks-detail.js').then((m) => m.default()),
    'user-webhooks-incoming': () => import('./charts/user-webhooks-incoming.js').then((m) => m.default()),
    'user-woocommerce-dashboard': () => import('./charts/user-woocommerce-dashboard.js').then((m) => m.default()),
    'auth-login': () => import('./charts/auth-login.js').then((m) => m.default()),
    'auth-register': () => import('./charts/auth-register.js').then((m) => m.default()),
    'auth-register-step1': () => import('./charts/auth-register.js').then((m) => m.default()),
    'auth-register-step2': () => import('./charts/auth-register-step2.js').then((m) => m.default()),
    'user-workspaces-create': () => import('./charts/auth-register-step2.js').then((m) => m.default()),
    'pricing-index': () => import('./charts/pricing-index.js').then((m) => m.default()),
    'checkout-index': () => import('./charts/checkout-index.js').then((m) => m.default()),
    'checkout-success': () => import('./charts/checkout-success.js').then((m) => m.default()),
    'checkout-failed': () => import('./charts/checkout-failed.js').then((m) => m.default()),
    'user-dashboard-index': () => import('./charts/user-dashboard-index.js').then((m) => m.default()),
    'instagram-composer': () => import('./charts/instagram-composer.js').then((m) => m.default()),
    'instagram-inbox': () => import('./charts/instagram-inbox.js').then((m) => m.default()),
    'instagram-automations': () => import('./charts/instagram-automations.js').then((m) => m.default()),
    'instagram-automations-create': () => import('./charts/instagram-automations-form.js').then((m) => m.default()),
    'instagram-automations-edit':   () => import('./charts/instagram-automations-form.js').then((m) => m.default()),
    'instagram-analytics': () => import('./charts/instagram-analytics.js').then((m) => m.default()),
};

/**
 * Global delegate for plain (non-AJAX) HTML forms decorated with
 * `data-confirm-form`. Replaces the inline `onsubmit="return confirm()"`
 * pattern with the themed confirmDialog. The form submits natively on
 * accept; cancel just closes the modal.
 *
 *      <form data-confirm-form
 *            data-confirm-title="Delete campaign?"
 *            data-confirm-message="This can't be undone.">…</form>
 */
function wireConfirmForms() {
    document.querySelectorAll('form[data-confirm-form]').forEach((form) => {
        if (form.__confirmWired) return;
        form.__confirmWired = true;
        form.addEventListener('submit', (e) => {
            if (form.__confirmAccepted) return; // already approved, allow native submit
            e.preventDefault();
            window.confirmDialog?.({
                title: form.dataset.confirmTitle || 'Are you sure?',
                message: form.dataset.confirmMessage || 'This action cannot be undone.',
                confirmText: form.dataset.confirmAccept || 'Delete',
                cancelText: form.dataset.confirmCancel || 'Cancel',
                tone: form.dataset.confirmTone || 'danger',
                onConfirm: () => {
                    form.__confirmAccepted = true;
                    form.submit();
                },
            });
        });
    });
}

// Global quick-access edge drawer — grip on the right edge slides out the
// user's pinned shortcuts on every page. No-op when the drawer isn't rendered
// (e.g. admin / guest pages).
function initQuickAccessDrawer() {
    const handle = document.getElementById('qad-handle');
    const panel = document.getElementById('qad-panel');
    if (!handle || !panel) return;
    const backdrop = document.getElementById('qad-backdrop');

    let isOpen = false;
    const open = () => {
        isOpen = true;
        panel.classList.remove('translate-x-full');
        backdrop?.classList.remove('opacity-0', 'pointer-events-none');
    };
    const close = () => {
        isOpen = false;
        panel.classList.add('translate-x-full');
        backdrop?.classList.add('opacity-0', 'pointer-events-none');
    };

    // Tap the grip to toggle the drawer; DRAG it up/down to reposition it
    // anywhere on the right edge. Position persists in localStorage. Default
    // is near the top (top-24 class); a saved value overrides it.
    const HANDLE_KEY = 'qadHandleTop';
    const HANDLE_DEFAULT = 120; // px from top (≈ below the header). Set explicitly
                                // so the position never depends on a Tailwind class.
    const clampTop = (y) => Math.max(8, Math.min(window.innerHeight - 60, y));
    // ALWAYS set an explicit top (saved value or the default) + clear bottom, so
    // the grip sits at a known spot regardless of the blade's utility class.
    let savedTop = HANDLE_DEFAULT;
    try {
        const s = parseInt(localStorage.getItem(HANDLE_KEY) || '', 10);
        if (!isNaN(s)) savedTop = s;
    } catch (_) { /* ignore */ }
    handle.style.top = clampTop(savedTop) + 'px';
    handle.style.bottom = 'auto';

    let dragging = false, moved = false, startY = 0, startTop = 0;
    handle.addEventListener('pointerdown', (e) => {
        dragging = true; moved = false;
        startY = e.clientY;
        startTop = handle.getBoundingClientRect().top;
        try { handle.setPointerCapture(e.pointerId); } catch (_) { /* ignore */ }
        handle.classList.add('cursor-grabbing');
    });
    handle.addEventListener('pointermove', (e) => {
        if (!dragging) return;
        const dy = e.clientY - startY;
        if (Math.abs(dy) > 4) moved = true;
        if (moved) { handle.style.top = clampTop(startTop + dy) + 'px'; e.preventDefault(); }
    });
    const endDrag = (e) => {
        if (!dragging) return;
        dragging = false;
        handle.classList.remove('cursor-grabbing');
        try { handle.releasePointerCapture(e.pointerId); } catch (_) { /* ignore */ }
        if (moved) { try { localStorage.setItem(HANDLE_KEY, String(parseInt(handle.style.top, 10) || 0)); } catch (_) { /* ignore */ } }
    };
    handle.addEventListener('pointerup', endDrag);
    handle.addEventListener('pointercancel', endDrag);
    // Click toggles — unless we just finished a drag.
    handle.addEventListener('click', (e) => {
        if (moved) { e.preventDefault(); e.stopImmediatePropagation(); moved = false; return; }
        isOpen ? close() : open();
    });
    backdrop?.addEventListener('click', close);
    document.getElementById('qad-close')?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && isOpen) close(); });
}

// Global Quick-access editor modal (#qa-modal lives in the user layout). Opened
// by ANY [data-qa-open] trigger — the dashboard pencil + the edge-drawer pencil.
// Lets the user pin/unpin app pages and add/remove custom links (≤10). No-op
// when the modal isn't on the page.
function initQuickAccessModal() {
    const modal = document.getElementById('qa-modal');
    if (!modal || modal.dataset.qaInit) return;
    modal.dataset.qaInit = '1';

    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); updateCount(); };
    const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    document.querySelectorAll('[data-qa-open]').forEach((b) => b.addEventListener('click', open));
    modal.querySelectorAll('[data-qa-close]').forEach((b) => b.addEventListener('click', close));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

    const cats = () => Array.from(modal.querySelectorAll('.qa-cat'));
    const customRows = () => Array.from(modal.querySelectorAll('.qa-custom-row'));
    const countEl = modal.querySelector('#qa-count');
    const selectedCount = () => {
        const c = cats().filter((x) => x.checked).length;
        const cu = customRows().filter((r) =>
            r.querySelector('.qa-cu-label').value.trim() && r.querySelector('.qa-cu-url').value.trim()).length;
        return c + cu;
    };
    const updateCount = () => { if (countEl) countEl.textContent = `${selectedCount()} / 10 pinned`; };

    cats().forEach((c) => c.addEventListener('change', () => {
        if (c.checked && selectedCount() > 10) {
            c.checked = false;
            window.toast?.('You can pin up to 10 shortcuts.', 'error');
        }
        updateCount();
    }));

    modal.querySelector('#qa-add-custom')?.addEventListener('click', () => {
        if (selectedCount() >= 10) { window.toast?.('You can pin up to 10 shortcuts.', 'error'); return; }
        const list = modal.querySelector('#qa-custom-list');
        const row = document.createElement('div');
        row.className = 'qa-custom-row flex items-center gap-2';
        row.innerHTML =
            '<input type="text" class="qa-cu-label flex-1 rounded-xl border border-paper-200 px-3 py-2 text-[12.5px]" placeholder="Label">' +
            '<input type="text" class="qa-cu-url flex-1 rounded-xl border border-paper-200 px-3 py-2 text-[12.5px] font-mono" placeholder="https://… or /path">' +
            '<button type="button" class="qa-cu-del text-ink-400 hover:text-accent-coral"><svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4.5h10M6 4.5V3h4v1.5M5 4.5l.5 8h5l.5-8"/></svg></button>';
        list.appendChild(row);
        updateCount();
    });

    modal.addEventListener('click', (e) => {
        const del = e.target.closest('.qa-cu-del');
        if (del) { del.closest('.qa-custom-row')?.remove(); updateCount(); }
    });
    modal.addEventListener('input', (e) => { if (e.target.closest('.qa-custom-row')) updateCount(); });

    modal.querySelector('#qa-save')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const items = [];
        cats().filter((c) => c.checked).forEach((c) => items.push({ key: c.value }));
        customRows().forEach((r) => {
            const label = r.querySelector('.qa-cu-label').value.trim();
            const url = r.querySelector('.qa-cu-url').value.trim();
            if (label && url) items.push({ label, url });
        });
        if (items.length > 10) { window.toast?.('Up to 10 shortcuts only.', 'error'); return; }

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        btn.disabled = true; btn.style.opacity = '0.6';
        try {
            const res = await fetch(btn.dataset.url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ items }),
            });
            if (res.ok) { window.toast?.('Quick access updated.', 'success'); location.reload(); }
            else { window.toast?.('Could not save. Please try again.', 'error'); btn.disabled = false; btn.style.opacity = ''; }
        } catch (_) {
            window.toast?.('Network error. Please try again.', 'error');
            btn.disabled = false; btn.style.opacity = '';
        }
    });
}

// Instaflow rail dark-mode toggle (sun/moon). Mirrors the same wa-theme
// localStorage+cookie the global switcher uses, so it persists app-wide.
function initIgThemeToggle() {
    const btn = document.querySelector('[data-ig-theme-toggle]');
    if (!btn) return;
    const sun = btn.querySelector('[data-ig-sun]');
    const moon = btn.querySelector('[data-ig-moon]');
    const sync = () => {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        sun?.classList.toggle('hidden', dark);
        moon?.classList.toggle('hidden', !dark);
    };
    sync();
    btn.addEventListener('click', () => {
        const dark = document.documentElement.getAttribute('data-theme') === 'dark';
        const next = dark ? 'paper' : 'dark';
        if (next === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        else document.documentElement.removeAttribute('data-theme');
        try { localStorage.setItem('wa-theme', next); } catch (e) {}
        try { document.cookie = 'wa-theme=' + next + '; path=/; max-age=31536000; SameSite=Lax'; } catch (e) {}
        sync();
        // Persist to the server too — the server theme (user.theme_preference)
        // is the source of truth on reload, so a client-only toggle would
        // otherwise revert. Fire-and-forget.
        try {
            const base = (document.querySelector('meta[name=app-base]')?.content || '').replace(/\/$/, '');
            fetch(base + '/settings/appearance', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'theme_preference=' + encodeURIComponent(next),
                credentials: 'same-origin',
            }).catch(() => {});
        } catch (e) {}
    });
}

// ===== Global "Connect device" popover =====
// Opened from any device picker via a [data-connect-device] click or
// window.openConnectDevice(). It iframes the devices connect flow in embed mode
// so the user can add a number (QR / WABA / Twilio) without leaving the page;
// on success it closes + dispatches `device:connected` so pickers refresh.
function initConnectDevice() {
    const sheet = document.getElementById('connect-device-sheet');
    if (!sheet) return;
    const frame = sheet.querySelector('[data-cd-frame]');
    const loading = sheet.querySelector('[data-cd-loading]');
    const base = (document.querySelector('meta[name=app-base]')?.content || '').replace(/\/$/, '');
    const EMBED_URL = base + '/devices?embed=1';

    // The floating Quick-Access drawer (#qad-handle/#qad-panel) is fixed-position
    // and sits above page content, so it bleeds ON TOP of this sheet — looking
    // like a stray icon bar "inside" the modal. Collapse + hide it while the
    // sheet is open, and restore it on close.
    function hideQuickAccess() {
        document.getElementById('qad-handle')?.classList.add('hidden');
        document.getElementById('qad-panel')?.classList.add('translate-x-full');
        document.getElementById('qad-backdrop')?.classList.add('opacity-0', 'pointer-events-none');
    }
    function showQuickAccess() {
        document.getElementById('qad-handle')?.classList.remove('hidden');
    }

    function open() {
        if (frame && !frame.getAttribute('src')) {
            if (loading) loading.style.display = '';
            frame.setAttribute('src', EMBED_URL);
        }
        sheet.classList.remove('hidden');
        document.documentElement.style.overflow = 'hidden';
        hideQuickAccess();
    }
    function close() {
        sheet.classList.add('hidden');
        document.documentElement.style.overflow = '';
        if (frame) frame.removeAttribute('src'); // tear down → stops the QR poller
        showQuickAccess();
    }
    window.openConnectDevice = open;
    window.closeConnectDevice = close;

    frame?.addEventListener('load', () => { if (loading) loading.style.display = 'none'; });
    sheet.querySelectorAll('[data-cd-close],[data-cd-overlay]').forEach((b) => b.addEventListener('click', close));
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-connect-device]')) { e.preventDefault(); open(); }
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !sheet.classList.contains('hidden')) close(); });
    // In-place refresh: re-fetch THIS page and swap only the [data-device-picker]
    // nodes, so a half-filled campaign / broadcast form is preserved. Falls back
    // to a reload if the page has no marked picker.
    async function refreshDevicePickers() {
        const pickers = document.querySelectorAll('[data-device-picker]');
        if (!pickers.length) { location.reload(); return; }
        try {
            const res = await fetch(location.href, { headers: { Accept: 'text/html' }, cache: 'no-store', credentials: 'same-origin' });
            if (!res.ok) { location.reload(); return; }
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const fresh = doc.querySelectorAll('[data-device-picker]');
            pickers.forEach((el, i) => { if (fresh[i]) el.innerHTML = fresh[i].innerHTML; });
            document.dispatchEvent(new CustomEvent('device:pickers-refreshed'));
        } catch (e) { location.reload(); }
    }

    window.addEventListener('message', (e) => {
        const t = e.data && e.data.type;
        if (t === 'wadesk:device-connected') {
            document.dispatchEvent(new CustomEvent('device:connected'));
            window.WaToaster?.success?.('Device connected.');
            close();
            setTimeout(refreshDevicePickers, 500);
        } else if (t === 'wadesk:connect-close') {
            close();
        }
    });
}

function boot() {
    if (document.body.hasAttribute('data-admin')) {
        initAdminSidebar();
    }

    wireConfirmForms();
    initListGridToggles();
    initQuickAccessDrawer();
    initQuickAccessModal();
    initConnectDevice();
    initIgThemeToggle();

    const page = document.body.dataset.page;
    if (page && PAGE_INITIALIZERS[page]) {
        PAGE_INITIALIZERS[page]();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
