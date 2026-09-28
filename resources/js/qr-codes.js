/*
 * Table QR codes on staff pages: drawn in the browser from the URL
 * (<canvas data-qr="https://…/qr/token">), downloadable as PNG.
 */
import QRCode from 'qrcode';

export function initQrCodes() {
    document.querySelectorAll('canvas[data-qr]').forEach((canvas) => {
        QRCode.toCanvas(canvas, canvas.dataset.qr, {
            width: Number(canvas.dataset.qrSize ?? 260),
            margin: 1,
            errorCorrectionLevel: 'M',
            color: { dark: '#1f1a17', light: '#ffffff' },
        });
    });

    document.querySelectorAll('[data-qr-download]').forEach((button) => button.addEventListener('click', async () => {
        const url = await QRCode.toDataURL(button.dataset.qrDownload, { width: 1024, margin: 2, errorCorrectionLevel: 'M' });
        const link = document.createElement('a');

        link.href = url;
        link.download = button.dataset.qrFilename ?? 'qr.png';
        link.click();
    }));
}
