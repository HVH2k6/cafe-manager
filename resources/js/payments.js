/*
 * Order page payments.
 *  - [data-payos-poll]: asks our server (never payOS directly) for the QR
 *    payment status every 3 s; reloads the page once it settles, stops at the deadline.
 *  - [data-cash-form]: shows the change to give back while typing the amount received.
 */
const POLL_MS = 3000;

export function initPayosPoll(box) {
    if (box.dataset.payosPolling) {
        return;
    }

    box.dataset.payosPolling = '1';

    const deadline = Number(box.dataset.payosDeadline) * 1000;
    const label = box.querySelector('[data-payos-label]');
    const error = box.querySelector('[data-payos-error]');
    let timer = null;

    const stop = () => clearInterval(timer);

    const poll = async () => {
        // The box was replaced by a realtime refresh: its replacement polls instead.
        if (! box.isConnected) {
            stop();

            return;
        }

        if (Date.now() > deadline + 60_000) {
            stop();
            label.textContent = 'Hết thời gian chờ — tải lại trang để kiểm tra.';

            return;
        }

        try {
            const response = await fetch(box.dataset.payosPoll, { headers: { Accept: 'application/json' } });
            const result = await response.json();

            label.textContent = result.label;
            error.hidden = ! result.error;
            error.textContent = result.error ?? '';

            if (result.final || result.orderPaid) {
                stop();
                window.location.reload();
            }
        } catch {
            error.hidden = false;
            error.textContent = 'Mất kết nối, đang thử lại…';
        }
    };

    timer = setInterval(poll, POLL_MS);
}

export function initCashForm(form) {
    const total = Number(form.dataset.total);
    const input = form.querySelector('[name="paid_amount"]');
    const change = form.querySelector('[data-cash-change]');

    const refresh = () => {
        const difference = Number(input.value || 0) - total;

        change.textContent = difference >= 0
            ? `${Math.round(difference).toLocaleString('vi-VN')} đ`
            : `Thiếu ${Math.round(-difference).toLocaleString('vi-VN')} đ`;
        change.classList.toggle('text-red-600', difference < 0);
    };

    form.querySelectorAll('[data-cash-amount]').forEach((button) => button.addEventListener('click', () => {
        input.value = button.dataset.cashAmount;
        refresh();
    }));
    input.addEventListener('input', refresh);
    refresh();
}
