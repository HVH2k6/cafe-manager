/**
 * Echo (Laravel Reverb) must load before Alpine so realtime components find window.Echo.
 */
import './echo';

import Alpine from 'alpinejs';
import { initCashForm, initPayosPoll } from './payments';
import { initPos } from './pos';
import { initQrCodes } from './qr-codes';
import { registerQrMenu } from './qr-menu';
import { registerRealtime } from './realtime';
import { initReceipt } from './receipt';

registerRealtime(Alpine);
registerQrMenu(Alpine);
window.Alpine = Alpine;
Alpine.start();

document.querySelectorAll('[data-pos]').forEach(initPos);
document.querySelectorAll('[data-payos-poll]').forEach(initPayosPoll);
document.querySelectorAll('[data-cash-form]').forEach(initCashForm);

// Blocks swapped in by a realtime refresh (liveRegion) may hold a new payOS QR to watch.
document.addEventListener('live:refreshed', ({ detail }) => detail.root.querySelectorAll('[data-payos-poll]').forEach(initPayosPoll));

if (document.querySelector('canvas[data-qr]')) {
    initQrCodes();
}

if (document.querySelector('[data-receipt-qr], [data-copy]')) {
    initReceipt();
}

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    const input = document.getElementById(toggle.dataset.passwordToggle);

    toggle.addEventListener('click', () => {
        const isVisible = input.type === 'text';

        input.type = isVisible ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', String(! isVisible));
        toggle.setAttribute('aria-label', isVisible ? 'Hiện mật khẩu' : 'Ẩn mật khẩu');
    });
});

document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;

    if (message && ! window.confirm(message)) {
        event.preventDefault();
    }
});

/*
 * Repeatable form rows: [data-repeater] holds a [data-repeater-list] of
 * [data-repeater-row]s and a <template data-repeater-template> whose
 * "__INDEX__" placeholders get a unique index for each new row.
 */
document.querySelectorAll('[data-repeater]').forEach((repeater) => {
    const list = repeater.querySelector('[data-repeater-list]');
    const template = repeater.querySelector('[data-repeater-template]');
    let nextIndex = Date.now();

    repeater.querySelector('[data-repeater-add]')?.addEventListener('click', () => {
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        list.lastElementChild?.querySelector('input, select')?.focus();
        repeater.dispatchEvent(new Event('input', { bubbles: true }));
    });

    repeater.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-repeater-remove]');

        if (removeButton) {
            removeButton.closest('[data-repeater-row]').remove();
            repeater.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
});

/*
 * Combo form: show what the chosen items cost bought separately and how much
 * the combo saves, from the data-price of each selected product.
 */
document.querySelectorAll('[data-bundle-form]').forEach((form) => {
    const formatVnd = (amount) => `${Math.round(amount).toLocaleString('vi-VN')} đ`;

    const refresh = () => {
        const individualTotal = [...form.querySelectorAll('[data-repeater-row]')].reduce((sum, row) => {
            const price = Number(row.querySelector('select')?.selectedOptions[0]?.dataset.price ?? 0);
            const quantity = Number(row.querySelector('input[type="number"]')?.value ?? 0);

            return sum + price * quantity;
        }, 0);
        const bundlePrice = Number(form.querySelector('[name="bundle_price"]').value || 0);

        form.querySelector('[data-individual-total]').textContent = formatVnd(individualTotal);
        form.querySelector('[data-saving]').textContent = formatVnd(Math.max(individualTotal - bundlePrice, 0));
    };

    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    refresh();
});

const sidebar = document.querySelector('[data-sidebar]');
const sidebarBackdrop = document.querySelector('[data-sidebar-backdrop]');
const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

function setSidebarOpen(isOpen) {
    sidebar.classList.toggle('-translate-x-full', ! isOpen);
    sidebarBackdrop.classList.toggle('hidden', ! isOpen);
    sidebarToggle.setAttribute('aria-expanded', String(isOpen));
}

if (sidebar && sidebarBackdrop && sidebarToggle) {
    sidebarToggle.addEventListener('click', () => setSidebarOpen(true));
    sidebarBackdrop.addEventListener('click', () => setSidebarOpen(false));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });
}
