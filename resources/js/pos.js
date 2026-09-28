/*
 * POS cart. Menu data comes from <script id="pos-data">; the cart is sent as
 * items[i][type|id|quantity|variant_ids][]|note] hidden inputs. Prices here
 * are a preview only — the server prices every line again.
 */
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
})[char]);

const formatVnd = (amount) => `${Math.round(amount).toLocaleString('vi-VN')} đ`;

export function initPos(root) {
    const data = JSON.parse(document.getElementById('pos-data').textContent);
    const products = new Map();
    data.menu.categories.forEach((category) => category.products.forEach((product) => products.set(product.id, product)));
    const bundles = new Map(data.menu.bundles.map((bundle) => [bundle.id, bundle]));

    const form = root.querySelector('[data-pos-form]');
    const cartList = root.querySelector('[data-pos-cart]');
    const warning = root.querySelector('[data-pos-warning]');
    const submit = root.querySelector('[data-pos-submit]');
    let cart = [];

    const variantsOf = (product, variantIds) => product.variantGroups
        .flatMap((group) => group.options.map((option) => ({ ...option, group: group.name })))
        .filter((option) => variantIds.includes(option.id));

    const unitPrice = (line) => {
        if (line.type === 'bundle') {
            return bundles.get(line.id).price;
        }

        const product = products.get(line.id);

        return product.price + variantsOf(product, line.variantIds).reduce((sum, option) => sum + option.priceModifier, 0);
    };

    const quantityInCart = (productId) => cart
        .filter((line) => line.type === 'product' && line.id === productId)
        .reduce((sum, line) => sum + line.quantity, 0);

    const showWarning = (message) => {
        warning.textContent = message;
        warning.hidden = ! message;
    };

    function addLine(type, id, quantity, variantIds = [], note = '') {
        const item = type === 'bundle' ? bundles.get(id) : products.get(id);

        if (! item) {
            return;
        }

        if (type === 'product' && item.stock !== null && quantityInCart(id) + quantity > item.stock) {
            showWarning(`“${item.name}” chỉ còn ${item.stock}.`);

            return;
        }

        const key = [type, id, [...variantIds].sort().join('-'), note.trim()].join('|');
        const existing = cart.find((line) => line.key === key);

        if (existing) {
            existing.quantity += quantity;
        } else {
            cart.push({ key, type, id, quantity, variantIds, note: note.trim() });
        }

        showWarning('');
        render();
    }

    function changeQuantity(key, delta) {
        const line = cart.find((candidate) => candidate.key === key);

        if (! line) {
            return;
        }

        if (delta > 0 && line.type === 'product') {
            const product = products.get(line.id);

            if (product.stock !== null && quantityInCart(line.id) + delta > product.stock) {
                showWarning(`“${product.name}” chỉ còn ${product.stock}.`);

                return;
            }
        }

        line.quantity += delta;
        cart = cart.filter((candidate) => candidate.quantity > 0);
        showWarning('');
        render();
    }

    function render() {
        const count = cart.reduce((sum, line) => sum + line.quantity, 0);
        const total = cart.reduce((sum, line) => sum + unitPrice(line) * line.quantity, 0);

        cartList.innerHTML = cart.map((line) => {
            const name = line.type === 'bundle' ? bundles.get(line.id).name : products.get(line.id).name;
            const details = [
                line.type === 'bundle' ? `Combo: ${bundles.get(line.id).contents}` : '',
                line.type === 'product' ? variantsOf(products.get(line.id), line.variantIds).map((option) => option.value).join(' · ') : '',
                line.note ? `“${line.note}”` : '',
            ].filter(Boolean).join(' — ');

            return `<li class="flex items-start gap-3 py-3">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold leading-snug">${escapeHtml(name)}</p>
                    ${details ? `<p class="text-xs text-stone-500">${escapeHtml(details)}</p>` : ''}
                    <p class="mt-1 font-mono text-sm">${formatVnd(unitPrice(line) * line.quantity)}</p>
                </div>
                <div class="flex items-center gap-1 rounded-lg bg-stone-100 p-0.5">
                    <button type="button" data-pos-step="-1" data-key="${escapeHtml(line.key)}" class="flex size-7 items-center justify-center rounded-md bg-white font-semibold" aria-label="Bớt ${escapeHtml(name)}">−</button>
                    <span class="w-7 text-center font-mono text-sm font-semibold">${line.quantity}</span>
                    <button type="button" data-pos-step="1" data-key="${escapeHtml(line.key)}" class="flex size-7 items-center justify-center rounded-md bg-white font-semibold" aria-label="Thêm ${escapeHtml(name)}">+</button>
                </div>
            </li>`;
        }).join('');

        root.querySelector('[data-pos-cart-empty]').hidden = cart.length > 0;
        root.querySelector('[data-pos-count]').textContent = `${count} món`;
        root.querySelector('[data-pos-total]').textContent = formatVnd(total);
        refreshSubmit();
    }

    function refreshSubmit() {
        const blockedByTable = ! root.querySelector('[data-pos-open-order]')?.hidden;

        submit.disabled = cart.length === 0 || blockedByTable;
    }

    // Menu: filter by category and search.
    let activeCategory = 'all';
    const searchInput = root.querySelector('[data-pos-search]');

    function filterMenu() {
        const term = searchInput.value.trim().toLowerCase();
        let visible = 0;

        root.querySelectorAll('[data-pos-add]').forEach((card) => {
            const matches = (activeCategory === 'all' || card.dataset.category === activeCategory)
                && (term === '' || card.dataset.name.includes(term));
            card.hidden = ! matches;
            visible += matches ? 1 : 0;
        });

        root.querySelector('[data-pos-empty]').hidden = visible > 0;
    }

    root.querySelectorAll('[data-pos-category]').forEach((button) => button.addEventListener('click', () => {
        activeCategory = button.dataset.posCategory;
        root.querySelectorAll('[data-pos-category]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        filterMenu();
    }));
    searchInput.addEventListener('input', filterMenu);

    // Options dialog.
    const dialog = root.querySelector('[data-pos-dialog]');
    let dialogProduct = null;
    let dialogQuantity = 1;

    const chosenVariantIds = () => [...dialog.querySelectorAll('[data-pos-dialog-groups] input:checked')].map((input) => Number(input.value));

    function refreshDialogPrice() {
        const price = unitPrice({ type: 'product', id: dialogProduct.id, variantIds: chosenVariantIds() });

        dialog.querySelector('[data-pos-dialog-quantity]').textContent = dialogQuantity;
        dialog.querySelector('[data-pos-dialog-price]').textContent = formatVnd(price * dialogQuantity);
    }

    function openDialog(product) {
        dialogProduct = product;
        dialogQuantity = 1;
        dialog.querySelector('[data-pos-dialog-name]').textContent = product.name;
        dialog.querySelector('[data-pos-dialog-base]').textContent = formatVnd(product.price);
        dialog.querySelector('[data-pos-dialog-note]').value = '';
        dialog.querySelector('[data-pos-dialog-groups]').innerHTML = product.variantGroups.map((group, groupIndex) => {
            const defaultOption = group.options.find((option) => option.isDefault) ?? group.options[0];

            return `<fieldset>
                <legend class="text-xs font-semibold tracking-wide text-stone-600 uppercase">${escapeHtml(group.name)}</legend>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    ${group.options.map((option) => `<label class="flex cursor-pointer items-center justify-between gap-2 rounded-lg border border-stone-200 px-3 py-2.5 text-sm has-checked:border-latte has-checked:bg-cream/40 has-checked:font-semibold">
                        <span class="flex items-center gap-2"><input type="radio" name="pos-group-${groupIndex}" value="${option.id}" ${option.id === defaultOption.id ? 'checked' : ''} class="accent-latte">${escapeHtml(option.value)}</span>
                        ${option.priceModifier ? `<span class="font-mono text-xs text-stone-500">${option.priceModifier > 0 ? '+' : ''}${formatVnd(option.priceModifier)}</span>` : ''}
                    </label>`).join('')}
                </div>
            </fieldset>`;
        }).join('');
        refreshDialogPrice();
        dialog.showModal();
    }

    dialog.addEventListener('change', refreshDialogPrice);
    dialog.querySelectorAll('[data-pos-dialog-step]').forEach((button) => button.addEventListener('click', () => {
        dialogQuantity = Math.max(1, Math.min(99, dialogQuantity + Number(button.dataset.posDialogStep)));
        refreshDialogPrice();
    }));
    dialog.addEventListener('close', () => {
        if (dialog.returnValue === 'add' && dialogProduct) {
            addLine('product', dialogProduct.id, dialogQuantity, chosenVariantIds(), dialog.querySelector('[data-pos-dialog-note]').value);
        }

        dialog.returnValue = '';
    });

    root.querySelectorAll('[data-pos-add]').forEach((card) => card.addEventListener('click', () => {
        const id = Number(card.dataset.id);

        if (card.dataset.posAdd === 'bundle') {
            addLine('bundle', id, 1);
        } else if (products.get(id).variantGroups.length > 0) {
            openDialog(products.get(id));
        } else {
            addLine('product', id, 1);
        }
    }));

    cartList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-pos-step]');

        if (button) {
            changeQuantity(button.dataset.key, Number(button.dataset.posStep));
        }
    });

    // Order type and table (new orders only).
    const tableField = root.querySelector('[data-pos-table-field]');
    const tableSelect = root.querySelector('[data-pos-table]');

    function refreshTable() {
        const isDineIn = root.querySelector('[data-pos-order-type]:checked')?.value === 'dine_in';
        const option = tableSelect?.selectedOptions[0];
        const notice = root.querySelector('[data-pos-open-order]');

        if (tableField) {
            tableField.hidden = ! isDineIn;
            notice.hidden = ! (isDineIn && option?.dataset.openOrder);

            if (! notice.hidden) {
                notice.querySelector('[data-pos-open-order-number]').textContent = option.dataset.openOrder;
                notice.querySelector('[data-pos-open-order-link]').href = option.dataset.addUrl;
            }
        }

        refreshSubmit();
    }

    root.querySelectorAll('[data-pos-order-type]').forEach((input) => input.addEventListener('change', refreshTable));
    tableSelect?.addEventListener('change', refreshTable);

    // Serialise the cart just before sending.
    form.addEventListener('submit', () => {
        const container = root.querySelector('[data-pos-lines]');
        const field = (name, value) => `<input type="hidden" name="${name}" value="${escapeHtml(value)}">`;

        container.innerHTML = cart.map((line, index) => [
            field(`items[${index}][type]`, line.type),
            field(`items[${index}][id]`, line.id),
            field(`items[${index}][quantity]`, line.quantity),
            ...line.variantIds.map((variantId) => field(`items[${index}][variant_ids][]`, variantId)),
            field(`items[${index}][note]`, line.note),
        ].join('')).join('');

        submit.disabled = true;
    });

    // Restore the cart after a validation error.
    data.oldItems.forEach((line) => addLine(line.type, Number(line.id), Number(line.quantity), (line.variant_ids ?? []).map(Number), line.note ?? ''));

    refreshTable();
    render();
}
