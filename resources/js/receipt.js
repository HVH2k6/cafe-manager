/*
 * Receipt page: draws the e-receipt QR code locally (the signed link never
 * leaves the browser), toggles the e-receipt panel and copies the link.
 */
import QRCode from 'qrcode';

export function initReceipt() {
    const panel = document.querySelector('[data-e-receipt]');
    const canvas = document.querySelector('[data-receipt-qr]');

    if (canvas) {
        QRCode.toCanvas(canvas, canvas.dataset.receiptQr, { width: 220, margin: 1 });
    }

    document.querySelector('[data-toggle-e-receipt]')?.addEventListener('click', () => {
        panel.hidden = ! panel.hidden;
    });

    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
        await navigator.clipboard.writeText(button.dataset.copy);
        button.textContent = 'Đã sao chép';
    }));
}
