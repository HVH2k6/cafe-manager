/*
 * Customer QR menu (step 9): menu → product sheet → cart (#cart) → checkout (#checkout).
 *
 * Prices shown here are only for display; the server prices the order again
 * from the menu. The cart is saved on the server (qr_sessions.cart_data) so a
 * reload or a closed tab does not lose it.
 */

const SAVE_DELAY_MS = 500;
const FAVORITES_KEY = 'cafe.favorites';

export const formatMoney = (amount) => `${Math.round(amount).toLocaleString('vi-VN')} đ`;

const readFavorites = () => {
    try {
        return JSON.parse(window.localStorage.getItem(FAVORITES_KEY) ?? '[]');
    } catch {
        return [];
    }
};

const lineKey = (line) => [line.type, line.id, [...(line.variant_ids ?? [])].sort((a, b) => a - b).join('.'), (line.note ?? '').trim()].join('|');

const viewFromHash = () => (['cart', 'checkout'].includes(window.location.hash.slice(1)) ? window.location.hash.slice(1) : 'menu');

export function registerQrMenu(Alpine) {
    Alpine.data('qrMenu', (config) => ({
        view: 'menu',
        categories: config.menu.categories,
        bundles: config.menu.bundles,
        paymentMethods: config.paymentMethods,
        cart: [],
        sheet: null,
        activeCategory: null,
        favorites: readFavorites(),
        customerName: config.cart.customerName ?? '',
        paymentMethod: config.paymentMethods[0]?.value ?? 'cash',
        notes: '',
        acceptTerms: false,
        errors: [],
        submitting: false,
        toast: '',
        saveTimer: null,
        toastTimer: null,

        init() {
            this.products = new Map(this.categories.flatMap((category) => category.products.map((product) => [product.id, product])));
            this.bundleMap = new Map(this.bundles.map((bundle) => [bundle.id, bundle]));

            const saved = config.cart.items ?? [];
            this.cart = saved.filter((line) => this.lookup(line)).map((line) => ({ ...line, key: lineKey(line) }));

            if (this.cart.length < saved.length) {
                this.showToast('Một số món trong giỏ đã hết hoặc ngừng bán nên đã được bỏ ra.');
                this.saveCart();
            }

            this.view = viewFromHash();
            window.addEventListener('hashchange', () => {
                this.view = viewFromHash();
                this.errors = [];
                window.scrollTo({ top: 0 });
            });

            this.$watch('sheet', (sheet) => document.body.classList.toggle('overflow-hidden', sheet !== null));
            this.activeCategory = this.favoriteProducts.length ? 'favorites' : (this.bundles.length ? 'bundles' : this.categories[0]?.id);
            this.$nextTick(() => this.watchSections());
        },

        // ---------- Navigation ----------

        go(view) {
            if (view === 'menu') {
                if (window.location.hash) {
                    history.replaceState(null, '', window.location.pathname);
                }

                this.view = 'menu';
            } else {
                window.location.hash = view;
            }

            window.scrollTo({ top: 0 });
        },

        scrollToCategory(id) {
            this.activeCategory = id;
            document.getElementById(`category-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        watchSections() {
            const observer = new IntersectionObserver((entries) => {
                entries.filter((entry) => entry.isIntersecting).forEach((entry) => {
                    this.activeCategory = entry.target.dataset.categorySection;
                    this.$refs.tabs?.querySelector(`[data-tab="${entry.target.dataset.categorySection}"]`)?.scrollIntoView({ block: 'nearest', inline: 'center' });
                });
            }, { rootMargin: '-130px 0px -65% 0px' });

            this.$root.querySelectorAll('[data-category-section]').forEach((section) => observer.observe(section));
        },

        // ---------- Menu data ----------

        lookup(line) {
            return line.type === 'bundle' ? this.bundleMap.get(line.id) : this.products.get(line.id);
        },

        isSoldOut(product) {
            return product.stock !== null && product.stock !== undefined && product.stock <= 0;
        },

        get favoriteProducts() {
            return this.favorites.map((id) => this.products.get(id)).filter(Boolean);
        },

        isFavorite(id) {
            return this.favorites.includes(id);
        },

        toggleFavorite(id) {
            this.favorites = this.isFavorite(id) ? this.favorites.filter((favorite) => favorite !== id) : [...this.favorites, id];

            try {
                window.localStorage.setItem(FAVORITES_KEY, JSON.stringify(this.favorites));
            } catch {
                // Private mode: favourites are just not remembered.
            }
        },

        // ---------- Product sheet ----------

        openProduct(product) {
            if (this.isSoldOut(product)) {
                return;
            }

            this.sheet = {
                type: 'product',
                item: product,
                choice: Object.fromEntries(product.variantGroups.map((group) => [
                    group.name,
                    (group.options.find((option) => option.isDefault) ?? group.options[0]).id,
                ])),
                note: '',
                quantity: 1,
            };
        },

        openBundle(bundle) {
            this.sheet = { type: 'bundle', item: bundle, choice: {}, note: '', quantity: 1 };
        },

        /** "+" on a card: straight into the cart when there is nothing to choose. */
        quickAdd(type, item) {
            if (type === 'product' && (item.variantGroups.length || this.isSoldOut(item))) {
                this.openProduct(item);

                return;
            }

            this.addLine({ type, id: item.id, quantity: 1, variant_ids: [], note: null });
        },

        isChips(group) {
            return group.options.every((option) => option.priceModifier === 0);
        },

        get sheetUnitPrice() {
            if (! this.sheet) {
                return 0;
            }

            if (this.sheet.type === 'bundle') {
                return this.sheet.item.price;
            }

            return this.sheet.item.price + this.sheet.item.variantGroups.reduce((sum, group) => {
                const option = group.options.find((candidate) => candidate.id === this.sheet.choice[group.name]);

                return sum + (option?.priceModifier ?? 0);
            }, 0);
        },

        addFromSheet() {
            this.addLine({
                type: this.sheet.type,
                id: this.sheet.item.id,
                quantity: this.sheet.quantity,
                variant_ids: Object.values(this.sheet.choice),
                note: this.sheet.note.trim() || null,
            });
            this.sheet = null;
        },

        // ---------- Cart ----------

        addLine(line) {
            const key = lineKey(line);
            const existing = this.cart.find((candidate) => candidate.key === key);

            if (existing) {
                existing.quantity = Math.min(existing.quantity + line.quantity, config.maxQuantity);
            } else {
                this.cart.push({ ...line, key });
            }

            this.showToast(`Đã thêm ${this.lookup(line).name} vào giỏ`);
            this.saveCart();
        },

        info(line) {
            const item = this.lookup(line);

            if (line.type === 'bundle') {
                return { name: item.name, image: item.image, unitPrice: item.price, summary: '', saving: item.saving, components: item.components };
            }

            const options = item.variantGroups.flatMap((group) => group.options.map((option) => ({ ...option, group: group.name })))
                .filter((option) => line.variant_ids.includes(option.id));

            return {
                name: item.name,
                image: item.image,
                unitPrice: item.price + options.reduce((sum, option) => sum + option.priceModifier, 0),
                summary: options.map((option) => option.value).join(' · '),
                saving: 0,
                components: [],
            };
        },

        changeQuantity(line, delta) {
            line.quantity = Math.max(1, Math.min(config.maxQuantity, line.quantity + delta));
            this.saveCart();
        },

        remove(line) {
            this.cart = this.cart.filter((candidate) => candidate.key !== line.key);
            this.saveCart();
        },

        clearCart() {
            if (window.confirm('Xóa tất cả món trong giỏ?')) {
                this.cart = [];
                this.saveCart();
            }
        },

        get itemCount() {
            return this.cart.reduce((sum, line) => sum + line.quantity, 0);
        },

        get total() {
            return this.cart.reduce((sum, line) => sum + this.info(line).unitPrice * line.quantity, 0);
        },

        payload() {
            return this.cart.map(({ type, id, quantity, variant_ids, note }) => ({ type, id, quantity, variant_ids, note }));
        },

        saveCart() {
            clearTimeout(this.saveTimer);
            this.saveTimer = setTimeout(() => this.send('PUT', config.urls.cart, {
                items: this.payload(),
                customer_name: this.customerName || null,
            }).catch(() => {}), SAVE_DELAY_MS);
        },

        // ---------- Checkout ----------

        async submit() {
            if (this.submitting) {
                return;
            }

            this.errors = [];

            if (! this.acceptTerms) {
                this.errors = ['Vui lòng đồng ý với điều khoản sử dụng.'];

                return;
            }

            this.submitting = true;
            clearTimeout(this.saveTimer);

            try {
                const response = await this.send('POST', config.urls.order, {
                    items: this.payload(),
                    payment_method: this.paymentMethod,
                    notes: this.notes || null,
                    customer_name: this.customerName || null,
                    accept_terms: this.acceptTerms,
                });
                const result = await response.json().catch(() => ({}));

                if (response.ok) {
                    this.cart = [];
                    window.location.href = result.redirect;

                    return;
                }

                this.errors = result.errors ? [...new Set(Object.values(result.errors).flat())] : [result.message ?? 'Chưa gửi được đơn, vui lòng thử lại.'];
            } catch {
                this.errors = ['Mất kết nối mạng, vui lòng thử lại.'];
            }

            this.submitting = false;
        },

        async send(method, url, body) {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(body),
            });

            // Session expired: back to the table's QR page to start again.
            if (response.status === 410) {
                const result = await response.clone().json().catch(() => ({}));
                window.location.href = result.redirect ?? config.urls.back;
            }

            return response;
        },

        // ---------- Helpers ----------

        money: formatMoney,

        showToast(message) {
            this.toast = message;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.toast = '';
            }, 2200);
        },
    }));
}
