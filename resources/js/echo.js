import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const LOOPBACK = ['localhost', '127.0.0.1', '::1'];
const configuredHost = import.meta.env.VITE_REVERB_HOST;
const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

/*
 * During development Reverb runs on the same machine as the app. When the
 * configured host is a loopback address, connect to the host the page was
 * opened with instead, so another device on the LAN (http://192.168.x.x:8000)
 * reaches Reverb too. A production host (domain) is used as configured.
 */
const wsHost = LOOPBACK.includes(configuredHost) ? window.location.hostname : configuredHost;

// An https page cannot open ws:// (e.g. dev app opened through an ngrok https URL): skip the WebSocket, pages poll instead.
const blockedByHttps = window.location.protocol === 'https:' && scheme !== 'https';

// Reverb speaks the Pusher protocol. Without a key (Reverb not set up) pages simply keep polling.
if (import.meta.env.VITE_REVERB_APP_KEY && ! blockedByHttps) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
