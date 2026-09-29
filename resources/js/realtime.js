/*
 * Real-time staff screens (step 7A) with Alpine + Laravel Echo (Reverb).
 *
 * Events are only signals: on an event a `liveRegion` re-fetches the page and
 * swaps its [data-live-refresh] blocks, so what is shown still comes from the
 * server (with its permission checks). When the WebSocket is not connected,
 * regions fall back to polling.
 */

const STATE_LABELS = {
    off: 'Realtime chưa bật',
    initialized: 'Đang kết nối…',
    connecting: 'Đang kết nối…',
    connected: 'Realtime: đã kết nối',
    unavailable: 'Mất kết nối — đang thử lại',
    failed: 'Không kết nối được realtime',
    disconnected: 'Mất kết nối realtime',
};

const readSetting = (key) => {
    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
};

const writeSetting = (key, value) => {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Private mode: the setting just is not remembered.
    }
};

const vibrate = (pattern) => navigator.vibrate?.(pattern);

export function registerRealtime(Alpine) {
    const pusher = window.Echo?.connector?.pusher;

    Alpine.store('realtime', {
        state: pusher ? pusher.connection.state : 'off',
        get connected() {
            return this.state === 'connected';
        },
        get label() {
            return STATE_LABELS[this.state] ?? this.state;
        },
    });

    pusher?.connection.bind('state_change', ({ current }) => {
        Alpine.store('realtime').state = current;
    });

    /*
     * Sidebar counts (pending orders, kitchen items, tables to clean, low stock).
     * The server renders them in <body data-badges>; after an event on a channel
     * the user may listen to they are fetched again from /sidebar-badges, and
     * polled as well (stock changes by hand have no event). A badge that goes up bounces.
     */
    const badgeSettings = JSON.parse(document.body.dataset.badgeSettings ?? '{}');

    Alpine.store('badges', {
        counts: JSON.parse(document.body.dataset.badges ?? '{}'),
        bumped: {},
        debounce: null,

        count(key) {
            return this.counts[key] ?? 0;
        },

        set(key, value) {
            if (value > this.count(key)) {
                this.bumped[key] = true;
                setTimeout(() => {
                    this.bumped[key] = false;
                }, 2000);
            }

            this.counts[key] = value;
        },

        refreshSoon() {
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => this.refresh(), 400);
        },

        async refresh() {
            if (! badgeSettings.url || document.hidden) {
                return;
            }

            try {
                const response = await fetch(badgeSettings.url, { headers: { Accept: 'application/json' } });

                if (response.ok && ! response.redirected) {
                    Object.entries(await response.json()).forEach(([key, value]) => this.set(key, value));
                }
            } catch {
                // The next event or poll corrects the badges.
            }
        },
    });

    const BADGE_EVENTS = {
        orders: ['order.created', 'order.status.updated', 'order.completed'],
        kitchen: ['order.created', 'order.status.updated', 'order.completed'],
        tables: ['table.updated'],
    };

    // One channel is enough to hear order changes: kitchen staff without orders.view listen to `kitchen`.
    (badgeSettings.channels ?? [])
        .filter((name) => name !== 'kitchen' || ! badgeSettings.channels.includes('orders'))
        .forEach((name) => {
            const channel = window.Echo?.private(name);
            BADGE_EVENTS[name]?.forEach((event) => channel?.listen(`.${event}`, () => Alpine.store('badges').refreshSoon()));
        });

    let badgeTimer = null;
    const scheduleBadgePoll = () => {
        clearInterval(badgeTimer);

        if (badgeSettings.url) {
            badgeTimer = setInterval(() => Alpine.store('badges').refresh(), (Alpine.store('realtime').connected ? 120 : 30) * 1000);
        }
    };

    pusher?.connection.bind('state_change', scheduleBadgePoll);
    scheduleBadgePoll();
    document.addEventListener('visibilitychange', () => {
        if (! document.hidden) {
            Alpine.store('badges').refresh();
        }
    });

    /*
     * Alert sounds made with the Web Audio API (no sound files). Browsers only
     * allow sound after the user interacted with the page, hence the opt-in button.
     */
    Alpine.store('sound', {
        enabled: readSetting('cafe.sound') === 'on',
        context: null,
        unlock() {
            this.context ??= new (window.AudioContext || window.webkitAudioContext)();
            this.context.resume?.();
        },
        toggle() {
            this.enabled = ! this.enabled;
            writeSetting('cafe.sound', this.enabled ? 'on' : 'off');

            if (this.enabled) {
                this.play('new');
            }
        },
        play(kind) {
            if (! this.enabled) {
                return;
            }

            this.unlock();
            const tones = kind === 'ready' ? [880, 1175, 1568] : [660, 880];

            tones.forEach((frequency, index) => {
                const start = this.context.currentTime + index * 0.18;
                const oscillator = this.context.createOscillator();
                const gain = this.context.createGain();

                oscillator.frequency.value = frequency;
                gain.gain.setValueAtTime(0.25, start);
                gain.gain.exponentialRampToValueAtTime(0.001, start + 0.16);
                oscillator.connect(gain).connect(this.context.destination);
                oscillator.start(start);
                oscillator.stop(start + 0.17);
            });
        },
    });

    document.addEventListener('pointerdown', () => {
        if (Alpine.store('sound').enabled) {
            Alpine.store('sound').unlock();
        }
    }, { once: true });

    /*
     * x-data="liveRegion({ channel: 'kitchen', events: ['order.created'], poll: 15, pollWhenConnected: 60, alerts: true })"
     *   poll: seconds between refreshes while realtime is down (false = never poll)
     *   reload: true → reload the whole page instead of swapping blocks
     *   guardTyping: false → refresh even while a field inside has focus (only when no field is swapped)
     */
    Alpine.data('liveRegion', (options) => ({
        stale: false,
        busy: false,
        missed: false,
        newOrderIds: [],
        timer: null,
        debounce: null,

        init() {
            // Customers' QR pages have no login: their channel is public (its name holds the secret session token).
            // No channel (the user may not listen to any): the region only polls.
            const channel = ! options.channel ? null
                : (options.public ? window.Echo?.channel(options.channel) : window.Echo?.private(options.channel));

            (options.events ?? []).forEach((name) => {
                channel?.listen(`.${name}`, (payload) => this.handle(name, payload));
            });

            this.$el.addEventListener('submit', () => {
                this.busy = true;
            });
            this.$watch('$store.realtime.state', () => this.schedulePolling());
            // The staff pop-ups' fallback poll noticed something new (QR order, ready item): catch up now.
            window.addEventListener('realtime:changed', () => {
                clearTimeout(this.debounce);
                this.debounce = setTimeout(() => this.refresh(false, true), 300);
            });
            document.addEventListener('visibilitychange', () => {
                if (! document.hidden && this.missed) {
                    this.refresh();
                }
            });
            this.schedulePolling();
        },

        destroy() {
            if (options.channel) {
                window.Echo?.leave(options.channel);
            }
            clearInterval(this.timer);
        },

        handle(name, payload) {
            // The kitchen only works on confirmed orders: a QR order rings once staff confirm it.
            const newForKitchen = (name === 'order.created' && payload.status !== 'pending')
                || (name === 'order.status.updated' && payload.old_status === 'pending' && payload.new_status === 'confirmed');

            if (options.alerts && newForKitchen) {
                this.newOrderIds.push(payload.id);
                Alpine.store('sound').play('new');
                vibrate([500, 200, 500]);
            }

            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => this.refresh(), 300);
        },

        isTyping() {
            const element = document.activeElement;

            // An expanded <details> panel (discount, cancel…) counts too: the user opened a form.
            return (this.$el.contains(element) && element.matches('input, textarea, select'))
                || this.$el.querySelector('details[open]') !== null;
        },

        /**
         * @param {boolean} force  apply even while the user is filling in a form (the "Tải lại" button)
         * @param {boolean} guess  a poll, not an event: in reload mode, only reload when the page's
         *                         [data-live-version] changed (onlyIfChanged)
         */
        async refresh(force = false, guess = false) {
            if (this.busy) {
                return;
            }

            // Hidden tab: skip the work, catch up once it is visible again.
            if (document.hidden) {
                this.missed = true;

                return;
            }

            this.missed = false;

            if (! force && options.guardTyping !== false && this.isTyping()) {
                this.stale = true;

                return;
            }

            if (options.reload) {
                if (guess && options.onlyIfChanged && ! (await this.pageChanged())) {
                    return;
                }

                window.location.reload();

                return;
            }

            try {
                const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } });

                if (! response.ok || response.redirected) {
                    return;
                }

                const page = new DOMParser().parseFromString(await response.text(), 'text/html');

                this.$el.querySelectorAll('[data-live-refresh]').forEach((block) => {
                    const fresh = page.querySelector(`[data-live-refresh="${block.dataset.liveRefresh}"]`);

                    if (fresh) {
                        block.replaceWith(fresh);
                    }
                });

                this.stale = false;
                this.flashNewOrders();
                // Let plain scripts (e.g. the payOS status poll) set up the blocks that were just swapped in.
                document.dispatchEvent(new CustomEvent('live:refreshed', { detail: { root: this.$el } }));
            } catch {
                // Network hiccup: the next event or poll tries again.
            }
        },

        async pageChanged() {
            try {
                const response = await fetch(window.location.href, { headers: { Accept: 'text/html' } });

                if (! response.ok || response.redirected) {
                    return false;
                }

                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const version = (root) => root.querySelector('[data-live-version]')?.dataset.liveVersion;

                return version(page) !== version(this.$el);
            } catch {
                return false;
            }
        },

        applyNow() {
            this.stale = false;
            document.activeElement?.blur();
            this.refresh(true);
        },

        flashNewOrders() {
            this.newOrderIds.splice(0).forEach((id) => {
                this.$el.querySelector(`[data-order-id="${id}"]`)?.classList.add('live-flash');
            });
        },

        schedulePolling() {
            clearInterval(this.timer);

            if (options.poll === false) {
                return;
            }

            // Without a channel no event ever arrives, so the WebSocket being up changes nothing.
            const connected = Boolean(options.channel) && Alpine.store('realtime').connected;

            if (connected && options.pollWhenConnected === false) {
                return;
            }

            const seconds = connected ? (options.pollWhenConnected ?? 60) : (options.poll ?? 15);
            this.timer = setInterval(() => this.refresh(false, true), seconds * 1000);
        },
    }));

    /*
     * Pop-ups on every staff page:
     *  - an order is ready to take out (disappears after 12 s),
     *  - a QR order waits for confirmation (disappears after 20 s),
     *  - a customer calls staff (stays until someone answers it; `calls` also
     *    holds the calls still waiting when the page loaded).
     */
    Alpine.data('staffAlerts', (waitingCalls = [], pollUrl = null) => ({
        toasts: [],
        calls: waitingCalls,
        knownPending: null,
        knownReady: null,
        timer: null,

        init() {
            const orders = window.Echo?.private('orders');

            // Without the WebSocket (Reverb not running…), ask the server every 15 s instead.
            const schedule = () => {
                clearInterval(this.timer);

                if (pollUrl && ! Alpine.store('realtime').connected) {
                    this.poll();
                    this.timer = setInterval(() => this.poll(), 15000);
                }
            };

            this.$watch('$store.realtime.state', schedule);
            schedule();

            orders?.listen('.order.ready', (payload) => this.ready(`ready-${payload.id}-${Date.now()}`, payload.message, payload.url));

            // One item done while the rest of the order is still being made.
            orders?.listen('.order.item.ready', (payload) => this.ready(`item-${payload.item_id}`, payload.message, payload.url));

            // A new order to confirm, or one confirmed / cancelled: recount the badge.
            orders?.listen('.order.status.updated', (payload) => {
                if (payload.old_status === 'pending' && payload.new_status !== 'pending') {
                    this.recount();
                }
            });

            orders?.listen('.order.created', (payload) => {
                if (payload.status !== 'pending') {
                    return;
                }

                this.recount();

                this.push({
                    id: `pending-${payload.id}`,
                    kind: 'pending',
                    message: `${payload.place} vừa gửi đơn ${payload.order_number} qua QR — cần xác nhận.`,
                    url: `/orders/${payload.id}`,
                }, 20000);
                Alpine.store('sound').play('new');
                vibrate([300, 150, 300]);
            });

            orders?.listen('.staff.called', (payload) => {
                if (! this.calls.some((call) => call.id === payload.id)) {
                    this.calls.push(payload);
                }

                Alpine.store('sound').play('new');
                vibrate([500, 200, 500, 200, 500]);
            });

            orders?.listen('.staff.answered', (payload) => {
                this.calls = this.calls.filter((call) => call.id !== payload.id);
            });
        },

        async poll() {
            if (document.hidden) {
                return;
            }

            try {
                const response = await fetch(pollUrl, { headers: { Accept: 'application/json' } });

                if (! response.ok || response.redirected) {
                    return;
                }

                const { calls, pending, ready, pending_count: pendingCount } = await response.json();

                Alpine.store('badges').set('pending', pendingCount);

                // Items the kitchen finished since the last poll (the first answer only records them).
                if (this.knownReady !== null) {
                    ready.filter((order) => order.item_ids.some((id) => ! this.knownReady.includes(id)))
                        .forEach((order) => this.ready(`ready-${order.id}-${order.item_ids.join('.')}`, order.message, order.url));
                }

                this.knownReady = ready.flatMap((order) => order.item_ids);
                const newCalls = calls.filter((call) => ! this.calls.some((known) => known.id === call.id));

                this.calls = calls;

                // The first answer only records what is already waiting (the sidebar badge shows it).
                const newPending = this.knownPending === null ? [] : pending.filter((order) => ! this.knownPending.includes(order.id));
                this.knownPending = pending.map((order) => order.id);

                newPending.forEach((order) => this.push({
                    id: `pending-${order.id}`,
                    kind: 'pending',
                    message: `${order.place} vừa gửi đơn ${order.order_number} qua QR — cần xác nhận.`,
                    url: order.url,
                }, 20000));

                const readyChanged = this.lastReadyKey !== undefined && this.lastReadyKey !== this.knownReady.join('.');
                this.lastReadyKey = this.knownReady.join('.');

                if (newPending.length || readyChanged) {
                    window.dispatchEvent(new CustomEvent('realtime:changed'));
                }

                if (newCalls.length || newPending.length) {
                    Alpine.store('sound').play('new');
                    vibrate([300, 150, 300]);
                }
            } catch {
                // Network hiccup: try again at the next tick.
            }
        },

        recount() {
            Alpine.store('badges').refreshSoon();
        },

        ready(id, message, url) {
            this.push({ id, kind: 'ready', message, url }, 12000);
            Alpine.store('sound').play('ready');
            vibrate([200, 100, 200]);
        },

        push(toast, lifetime) {
            this.toasts = [...this.toasts.filter((existing) => existing.id !== toast.id), toast];
            setTimeout(() => this.dismiss(toast.id), lifetime);
        },

        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },

        async answer(call) {
            const response = await fetch(call.answer_url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            }).catch(() => null);

            if (response?.ok) {
                this.calls = this.calls.filter((candidate) => candidate.id !== call.id);
            }
        },
    }));
}
