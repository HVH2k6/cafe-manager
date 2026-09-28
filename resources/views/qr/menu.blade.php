@php
    $methodStyles = [
        'cash' => ['banknote', 'bg-cream text-latte-dark'],
        'card' => ['credit-card', 'bg-blue-50 text-blue-600'],
        'e_wallet' => ['wallet', 'bg-pink-50 text-pink-600'],
        'bank_transfer' => ['landmark', 'bg-emerald-50 text-emerald-600'],
    ];

    $config = [
        'menu' => $menu,
        'cart' => $cart,
        'maxQuantity' => \App\Http\Requests\QrCartRequest::MAX_QUANTITY,
        'paymentMethods' => $paymentMethods->map(fn (\App\PaymentMethod $method): array => [
            'value' => $method->value,
            'label' => $method->forCustomers()[0],
            'hint' => $method->forCustomers()[1],
        ])->all(),
        'urls' => [
            'cart' => route('qr.cart.update', $session),
            'order' => route('qr.orders.store', $session),
            'back' => route('qr.show', $session->qr_token),
        ],
    ];

    $inputClasses = 'w-full rounded-xl border border-caramel bg-paper px-4 py-3 text-base focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none';
@endphp

<x-layouts.customer :title="'Menu · Bàn '.$table->table_no">
    <script type="application/json" id="qr-menu-config">@json($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>

    <div x-data="qrMenu(JSON.parse(document.getElementById('qr-menu-config').textContent))" class="flex flex-1 flex-col">

        {{-- ================= MENU ================= --}}
        <div x-show="view === 'menu'" class="flex flex-1 flex-col pb-28">
            <header class="sticky top-0 z-30 bg-paper/95 backdrop-blur">
                <div class="flex h-15 items-center justify-between gap-2 px-3">
                    <a href="{{ route('qr.show', $session->qr_token) }}" class="flex size-10 items-center justify-center rounded-full hover:bg-stone-100" aria-label="Quay lại">
                        <x-icon name="arrow-left" class="size-5" />
                    </a>
                    <div class="text-center leading-tight">
                        <p class="text-lg font-bold">Menu</p>
                        <p class="text-sm text-stone-600">{{ config('app.cafe_name') }} · Bàn {{ $table->table_no }}</p>
                    </div>
                    <button type="button" x-on:click="go('cart')" class="relative flex size-10 items-center justify-center rounded-full hover:bg-stone-100" aria-label="Giỏ hàng">
                        <x-icon name="shopping-cart" class="size-6" />
                        <span x-cloak x-show="itemCount > 0" x-text="itemCount" class="absolute -top-0.5 -right-0.5 flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1 text-xs font-bold text-white"></span>
                    </button>
                </div>

                <nav x-ref="tabs" class="flex gap-1 overflow-x-auto border-b border-stone-200 px-3 [scrollbar-width:none]" aria-label="Danh mục">
                    <template x-if="favoriteProducts.length">
                        <button type="button" data-tab="favorites" x-on:click="scrollToCategory('favorites')" :class="activeCategory === 'favorites' ? 'border-amber-500 text-ink' : 'border-transparent text-stone-600'" class="shrink-0 border-b-2 px-3 py-3 font-semibold whitespace-nowrap">Yêu thích</button>
                    </template>
                    <template x-if="bundles.length">
                        <button type="button" data-tab="bundles" x-on:click="scrollToCategory('bundles')" :class="activeCategory === 'bundles' ? 'border-amber-500 text-ink' : 'border-transparent text-stone-600'" class="shrink-0 border-b-2 px-3 py-3 font-semibold whitespace-nowrap">Combo</button>
                    </template>
                    <template x-for="category in categories" :key="category.id">
                        <button type="button" :data-tab="category.id" x-on:click="scrollToCategory(category.id)" :class="String(activeCategory) === String(category.id) ? 'border-amber-500 text-ink' : 'border-transparent text-stone-600'" class="shrink-0 border-b-2 px-3 py-3 font-semibold whitespace-nowrap" x-text="category.name"></button>
                    </template>
                </nav>
            </header>

            @if (! $isOpen)
                <p class="mx-4 mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">Quán đang ngoài giờ nhận đơn qua QR — bạn xem menu được nhưng chưa gửi đơn được. Vui lòng gọi nhân viên.</p>
            @endif

            @if ($openOrder)
                <a href="{{ route('qr.order', $session) }}" class="mx-4 mt-4 flex items-center justify-between gap-3 rounded-xl bg-cream/60 px-4 py-3 text-sm font-semibold text-espresso">
                    <span>Đơn {{ $openOrder->order_number }} · {{ $openOrder->status->label() }} — món gọi thêm sẽ vào đơn này</span>
                    <x-icon name="chevron-right" class="size-4" />
                </a>
            @endif

            <main class="flex flex-col gap-8 px-4 pt-5">
                <template x-if="! categories.length && ! bundles.length">
                    <p class="py-16 text-center text-stone-600">Menu đang được cập nhật. Vui lòng gọi nhân viên.</p>
                </template>

                {{-- Favourites (remembered on this phone) --}}
                <template x-if="favoriteProducts.length">
                    <section id="category-favorites" data-category-section="favorites" class="scroll-mt-32">
                        <h2 class="text-xl font-bold">Yêu thích</h2>
                        <div class="mt-3 flex flex-col gap-4">
                            <template x-for="product in favoriteProducts" :key="'fav-' + product.id">
                                @include('qr.partials.product-card')
                            </template>
                        </div>
                    </section>
                </template>

                {{-- Combos --}}
                <template x-if="bundles.length">
                    <section id="category-bundles" data-category-section="bundles" class="scroll-mt-32">
                        <div class="flex items-baseline justify-between">
                            <h2 class="text-xl font-bold">Combo tiết kiệm</h2>
                            <span class="text-sm text-stone-500" x-text="bundles.length + ' combo'"></span>
                        </div>
                        <div class="-mx-4 mt-3 flex snap-x gap-3 overflow-x-auto px-4 pb-2 [scrollbar-width:none]">
                            <template x-for="bundle in bundles" :key="bundle.id">
                                <article class="w-64 shrink-0 snap-start overflow-hidden rounded-2xl border border-stone-200 bg-white">
                                    <button type="button" x-on:click="openBundle(bundle)" class="relative block h-28 w-full bg-cream/70">
                                        <template x-if="bundle.image"><img :src="bundle.image" :alt="bundle.name" class="size-full object-cover"></template>
                                        <template x-if="! bundle.image">
                                            <span class="flex size-full items-center justify-center gap-2 text-latte" aria-hidden="true">
                                                <x-icon name="coffee" class="size-9" /><span class="text-lg">+</span><x-icon name="utensils" class="size-8" />
                                            </span>
                                        </template>
                                        <span x-show="bundle.saving > 0" class="absolute top-2.5 left-2.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700" x-text="'Tiết kiệm ' + money(bundle.saving)"></span>
                                    </button>
                                    <div class="p-4">
                                        <h3 class="font-bold" x-text="bundle.name"></h3>
                                        <p class="mt-0.5 line-clamp-1 text-sm text-stone-600" x-text="bundle.contents"></p>
                                        <div class="mt-3 flex items-end justify-between gap-2">
                                            <p class="font-mono leading-tight">
                                                <span class="block font-semibold text-latte-dark" x-text="money(bundle.price)"></span>
                                                <span x-show="bundle.priceSeparately > bundle.price" class="text-xs text-stone-400 line-through" x-text="money(bundle.priceSeparately)"></span>
                                            </p>
                                            <button type="button" x-on:click="quickAdd('bundle', bundle)" class="flex size-11 items-center justify-center rounded-xl bg-latte text-white hover:bg-latte-dark" :aria-label="'Thêm ' + bundle.name">
                                                <x-icon name="plus" class="size-5" />
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </section>
                </template>

                {{-- Categories --}}
                <template x-for="category in categories" :key="category.id">
                    <section :id="'category-' + category.id" :data-category-section="category.id" class="scroll-mt-32">
                        <div class="flex items-baseline justify-between">
                            <h2 class="text-xl font-bold" x-text="category.name"></h2>
                            <span class="text-sm text-stone-500" x-text="category.products.length + ' món'"></span>
                        </div>
                        <div class="mt-3 flex flex-col gap-4">
                            <template x-for="product in category.products" :key="product.id">
                                @include('qr.partials.product-card')
                            </template>
                        </div>
                    </section>
                </template>
            </main>

            {{-- Floating cart bar --}}
            <div x-cloak x-show="itemCount > 0" x-transition class="fixed inset-x-0 bottom-0 z-30 mx-auto w-full max-w-md px-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <div class="flex items-center gap-3 rounded-2xl bg-espresso py-2.5 pr-2.5 pl-5 text-white shadow-xl">
                    <x-icon name="shopping-cart" class="size-5" />
                    <span class="font-semibold" x-text="itemCount + ' món'"></span>
                    <span class="ml-auto font-mono font-semibold" x-text="money(total)"></span>
                    <button type="button" x-on:click="go('cart')" class="h-11 rounded-xl bg-amber-500 px-4 font-semibold text-espresso hover:bg-amber-400">Xem giỏ</button>
                </div>
            </div>
        </div>

        {{-- ================= CART ================= --}}
        <div x-cloak x-show="view === 'cart'" class="flex flex-1 flex-col pb-28">
            <header class="sticky top-0 z-30 flex h-15 items-center justify-between gap-2 border-b border-stone-200 bg-paper/95 px-3 backdrop-blur">
                <button type="button" x-on:click="go('menu')" class="flex size-10 items-center justify-center rounded-full hover:bg-stone-100" aria-label="Quay lại menu">
                    <x-icon name="arrow-left" class="size-5" />
                </button>
                <p class="text-lg font-bold">Giỏ hàng <span class="font-normal text-stone-500" x-text="'(' + itemCount + ')'"></span></p>
                <button type="button" x-on:click="clearCart()" x-show="cart.length" class="px-2 text-sm font-semibold text-red-600">Xóa tất cả</button>
                <span x-show="! cart.length" class="w-10"></span>
            </header>

            <main class="flex flex-col gap-4 px-4 pt-4">
                <p class="flex items-center gap-2 rounded-xl bg-cream/70 px-4 py-3 text-sm text-stone-700">
                    <x-icon name="map-pin" class="size-4.5" /> Phục vụ tại <b>Bàn {{ $table->table_no }}</b>
                </p>

                <template x-if="! cart.length">
                    <div class="flex flex-col items-center py-14 text-center">
                        <span class="flex size-16 items-center justify-center rounded-full bg-cream/70 text-latte"><x-icon name="shopping-cart" class="size-8" /></span>
                        <p class="mt-4 font-semibold">Giỏ hàng đang trống</p>
                        <button type="button" x-on:click="go('menu')" class="mt-4 h-11 rounded-xl bg-latte px-5 font-semibold text-white">Xem menu</button>
                    </div>
                </template>

                <template x-if="cart.length">
                    <section class="divide-y divide-stone-100 rounded-2xl border border-stone-200 bg-white">
                        <template x-for="line in cart" :key="line.key">
                            <article class="flex gap-4 p-4">
                                <span class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-cream/70 text-latte">
                                    <template x-if="info(line).image"><img :src="info(line).image" alt="" class="size-full object-cover"></template>
                                    <template x-if="! info(line).image"><x-icon name="coffee" class="size-7" /></template>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <h3 class="leading-snug font-bold" x-text="info(line).name"></h3>
                                        <p class="shrink-0 font-mono text-sm font-semibold" x-text="money(info(line).unitPrice * line.quantity)"></p>
                                    </div>
                                    <p x-show="info(line).summary" class="text-sm text-stone-600" x-text="info(line).summary"></p>
                                    <template x-if="info(line).saving > 0">
                                        <span class="mt-1 inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700" x-text="'Tiết kiệm ' + money(info(line).saving)"></span>
                                    </template>
                                    <template x-if="info(line).components.length">
                                        <ul class="mt-1.5 border-l-2 border-stone-200 pl-2.5 text-sm text-stone-600">
                                            <template x-for="component in info(line).components"><li x-text="component"></li></template>
                                        </ul>
                                    </template>
                                    <p x-show="line.note" class="text-sm text-latte-dark" x-text="'Ghi chú: ' + line.note"></p>

                                    <div class="mt-3 flex items-center justify-between">
                                        <div class="flex items-center rounded-xl bg-stone-100 p-1">
                                            <button type="button" x-on:click="changeQuantity(line, -1)" :disabled="line.quantity <= 1" class="flex size-9 items-center justify-center rounded-lg disabled:opacity-40" aria-label="Bớt 1"><x-icon name="minus" class="size-4" /></button>
                                            <span class="w-8 text-center font-mono font-semibold" x-text="line.quantity"></span>
                                            <button type="button" x-on:click="changeQuantity(line, 1)" class="flex size-9 items-center justify-center rounded-lg" aria-label="Thêm 1"><x-icon name="plus" class="size-4" /></button>
                                        </div>
                                        <button type="button" x-on:click="remove(line)" class="flex size-10 items-center justify-center rounded-lg text-red-600 hover:bg-red-50" :aria-label="'Bỏ ' + info(line).name">
                                            <x-icon name="trash" class="size-5" />
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </section>
                </template>

                <button type="button" x-on:click="go('menu')" class="flex items-center justify-center gap-2 py-2 font-semibold text-latte-dark">
                    <x-icon name="plus" class="size-4.5" /> Thêm món khác
                </button>

                <template x-if="cart.length">
                    <section class="rounded-2xl border border-stone-200 bg-white p-5">
                        <dl class="space-y-2">
                            <div class="flex justify-between text-stone-600"><dt x-text="'Tạm tính (' + itemCount + ' món)'"></dt><dd class="font-mono" x-text="money(total)"></dd></div>
                            <div class="flex items-baseline justify-between border-t border-stone-100 pt-3">
                                <dt class="font-bold tracking-wide uppercase">Thành tiền</dt>
                                <dd class="font-mono text-2xl font-bold" x-text="money(total)"></dd>
                            </div>
                        </dl>
                        <p class="mt-2 text-sm text-stone-500">Đã bao gồm VAT</p>
                    </section>
                </template>
            </main>

            <footer x-show="cart.length" class="fixed inset-x-0 bottom-0 z-30 mx-auto grid w-full max-w-md grid-cols-[auto_1fr] gap-3 border-t border-stone-200 bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                <button type="button" x-on:click="go('menu')" class="h-13 rounded-xl border-2 border-latte px-4 font-semibold text-latte-dark">Tiếp tục mua</button>
                <button type="button" x-on:click="go('checkout')" class="h-13 rounded-xl bg-latte px-4 font-semibold text-white hover:bg-latte-dark">
                    Thanh toán · <span class="font-mono" x-text="money(total)"></span>
                </button>
            </footer>
        </div>

        {{-- ================= CHECKOUT ================= --}}
        <div x-cloak x-show="view === 'checkout'" class="flex flex-1 flex-col pb-40">
            <header class="sticky top-0 z-30 flex h-15 items-center gap-2 border-b border-stone-200 bg-paper/95 px-3 backdrop-blur">
                <button type="button" x-on:click="history.back()" class="flex size-10 items-center justify-center rounded-full hover:bg-stone-100" aria-label="Quay lại giỏ hàng">
                    <x-icon name="arrow-left" class="size-5" />
                </button>
                <p class="text-lg font-bold">Thanh toán</p>
            </header>

            <main class="flex flex-col gap-5 px-4 pt-4">
                <section class="rounded-2xl bg-espresso p-6 text-white">
                    <p class="text-caramel">Số tiền cần thanh toán</p>
                    <p class="mt-1 font-mono text-4xl font-bold" x-text="money(total)"></p>
                    <p class="mt-2 text-sm text-cream/90">Bàn {{ $table->table_no }} · <span x-text="itemCount"></span> món · Đã gồm VAT</p>
                </section>

                <fieldset>
                    <legend class="text-sm font-bold tracking-wide text-latte-dark uppercase">Phương thức thanh toán</legend>
                    <div class="mt-3 flex flex-col gap-2.5">
                        @foreach ($paymentMethods as $method)
                            @php [$icon, $tone] = $methodStyles[$method->value]; [$label, $hint] = $method->forCustomers(); @endphp
                            <label class="flex cursor-pointer items-center gap-4 rounded-2xl border-2 bg-white p-4 transition" :class="paymentMethod === '{{ $method->value }}' ? 'border-latte bg-cream/30' : 'border-stone-200'">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $tone }}"><x-icon :name="$icon" class="size-6" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-bold">{{ $label }}</span>
                                    <span class="block text-sm text-stone-600">{{ $hint }}</span>
                                </span>
                                <input type="radio" name="payment_method" value="{{ $method->value }}" x-model="paymentMethod" class="size-6 accent-latte">
                            </label>
                        @endforeach
                    </div>
                    <p x-show="['e_wallet', 'bank_transfer'].includes(paymentMethod)" class="mt-2 text-sm text-stone-600">Mã VietQR hiện ngay sau khi gửi đơn — quét bằng app ngân hàng hoặc ví.</p>
                </fieldset>

                <div>
                    <label for="order-notes" class="text-sm font-bold tracking-wide text-latte-dark uppercase">Ghi chú đơn hàng</label>
                    <textarea id="order-notes" x-model="notes" rows="3" maxlength="500" placeholder="Nhập ghi chú cho quầy..." class="{{ $inputClasses }} mt-3"></textarea>
                </div>

                <label class="flex items-start gap-3">
                    <input type="checkbox" x-model="acceptTerms" class="mt-0.5 size-6 shrink-0 accent-latte">
                    <span>Tôi đồng ý với <button type="button" x-on:click="$refs.terms.showModal()" class="font-semibold text-latte-dark underline">điều khoản sử dụng</button> của quán</span>
                </label>

                <template x-if="errors.length">
                    <div class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        <template x-for="error in errors"><p x-text="error"></p></template>
                    </div>
                </template>
            </main>

            <footer class="fixed inset-x-0 bottom-0 z-30 mx-auto flex w-full max-w-md flex-col gap-1 border-t border-stone-200 bg-white px-4 pt-3 pb-[max(0.5rem,env(safe-area-inset-bottom))]">
                <button type="button" x-on:click="submit()" :disabled="submitting || ! cart.length" class="flex h-13 items-center justify-center gap-2 rounded-xl bg-latte text-lg font-semibold text-white hover:bg-latte-dark disabled:opacity-60">
                    <x-icon name="check" class="size-5" />
                    <span x-text="submitting ? 'Đang gửi đơn…' : 'Xác nhận thanh toán'">Xác nhận thanh toán</span>
                </button>
                <button type="button" x-on:click="history.back()" class="h-11 font-semibold text-latte-dark">Hủy</button>
            </footer>

            <dialog x-ref="terms" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl p-0 backdrop:bg-ink/50">
                <div class="p-6">
                    <h2 class="text-lg font-bold">Điều khoản sử dụng</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-stone-700">
                        <li>Đơn được gửi tới quầy và chỉ bắt đầu pha chế sau khi nhân viên xác nhận.</li>
                        <li>Giá tính theo menu tại thời điểm gửi đơn, đã gồm VAT.</li>
                        <li>Muốn đổi hoặc hủy món sau khi gửi, vui lòng bấm "Gọi nhân viên".</li>
                        <li>Chuyển khoản / ví điện tử: thanh toán bằng mã VietQR hiện trên trang đơn. Nếu quầy không phục vụ được đơn, nhân viên sẽ hoàn tiền cho bạn.</li>
                        <li>Quán không lưu thông tin thẻ hay tài khoản của bạn.</li>
                    </ul>
                    <form method="dialog" class="mt-5">
                        <button class="h-11 w-full rounded-xl bg-latte font-semibold text-white">Đã hiểu</button>
                    </form>
                </div>
            </dialog>
        </div>

        {{-- ================= PRODUCT SHEET ================= --}}
        <div x-cloak x-show="sheet" class="fixed inset-0 z-50" role="dialog" aria-modal="true" x-on:keydown.escape.window="sheet = null">
            <div x-show="sheet" x-transition.opacity x-on:click="sheet = null" class="absolute inset-0 bg-ink/50"></div>
            <template x-if="sheet">
                <div x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full" class="absolute inset-x-0 bottom-0 mx-auto flex max-h-[94dvh] w-full max-w-md flex-col rounded-t-3xl bg-white">
                    <div class="overflow-y-auto px-4 pt-3 pb-4">
                        <div class="mx-auto mb-3 h-1.5 w-12 rounded-full bg-stone-200"></div>
                        <div class="relative flex h-48 items-center justify-center overflow-hidden rounded-2xl bg-cream/80 text-latte">
                            <template x-if="sheet.item.image"><img :src="sheet.item.image" :alt="sheet.item.name" class="size-full object-cover"></template>
                            <template x-if="! sheet.item.image">
                                <span class="flex flex-col items-center gap-2"><x-icon name="coffee" class="size-12" /><span class="text-sm text-stone-600">Ảnh sản phẩm</span></span>
                            </template>
                            <button type="button" x-on:click="sheet = null" class="absolute top-3 right-3 flex size-11 items-center justify-center rounded-full bg-white text-ink shadow" aria-label="Đóng">
                                <x-icon name="x" class="size-5" />
                            </button>
                        </div>

                        <h2 class="mt-5 text-3xl font-bold" x-text="sheet.item.name"></h2>
                        <p x-show="sheet.item.description" class="mt-2 text-lg text-stone-600" x-text="sheet.item.description"></p>

                        <template x-if="sheet.type === 'bundle'">
                            <div class="mt-4 rounded-xl bg-stone-50 p-4">
                                <p class="text-sm font-bold tracking-wide text-latte-dark uppercase">Gồm</p>
                                <ul class="mt-2 space-y-1">
                                    <template x-for="component in sheet.item.components"><li class="flex items-center gap-2"><x-icon name="check" class="size-4 text-emerald-600" /><span x-text="component"></span></li></template>
                                </ul>
                                <p x-show="sheet.item.saving > 0" class="mt-3 text-sm font-semibold text-emerald-700" x-text="'Tiết kiệm ' + money(sheet.item.saving) + ' so với gọi lẻ'"></p>
                            </div>
                        </template>

                        <template x-if="sheet.type === 'product'">
                            <div>
                                <template x-for="group in sheet.item.variantGroups" :key="group.name">
                                    <fieldset class="mt-6">
                                        <legend class="text-sm font-bold tracking-wide text-latte-dark uppercase" x-text="group.name"></legend>
                                        <div :class="isChips(group) ? 'mt-3 grid grid-cols-4 gap-2' : 'mt-3 flex flex-col gap-2'">
                                            <template x-for="option in group.options" :key="option.id">
                                                <label
                                                    class="flex cursor-pointer items-center border-2 transition"
                                                    :class="isChips(group)
                                                        ? (sheet.choice[group.name] === option.id ? 'justify-center rounded-full border-latte bg-latte px-2 py-2.5 font-semibold text-white' : 'justify-center rounded-full border-stone-200 px-2 py-2.5 font-semibold')
                                                        : (sheet.choice[group.name] === option.id ? 'gap-3 rounded-xl border-latte bg-cream/30 p-4' : 'gap-3 rounded-xl border-stone-200 p-4')"
                                                >
                                                    <input type="radio" :name="'variant-' + group.name" :value="option.id" x-model.number="sheet.choice[group.name]" :class="isChips(group) ? 'sr-only' : 'size-5 accent-latte'">
                                                    <span class="font-semibold" :class="isChips(group) ? 'text-center text-sm' : 'flex-1'" x-text="option.value"></span>
                                                    <span x-show="! isChips(group)" class="font-mono text-sm text-stone-600" x-text="(option.priceModifier >= 0 ? '+' : '−') + money(Math.abs(option.priceModifier))"></span>
                                                </label>
                                            </template>
                                        </div>
                                    </fieldset>
                                </template>
                            </div>
                        </template>

                        <label for="sheet-note" class="mt-6 block text-sm font-bold tracking-wide text-latte-dark uppercase">Ghi chú <span class="font-normal normal-case text-stone-500">(không bắt buộc)</span></label>
                        <textarea id="sheet-note" x-model="sheet.note" rows="3" maxlength="255" placeholder="VD: ít đá, không kem, mang ra sau..." class="{{ $inputClasses }} mt-2"></textarea>

                        <div class="mt-6 flex items-center justify-between">
                            <span class="text-sm font-bold tracking-wide text-latte-dark uppercase">Số lượng</span>
                            <div class="flex items-center gap-1 rounded-xl bg-stone-100 p-1">
                                <button type="button" x-on:click="sheet.quantity = Math.max(1, sheet.quantity - 1)" class="flex size-11 items-center justify-center rounded-lg bg-white shadow-sm" aria-label="Bớt 1"><x-icon name="minus" class="size-5" /></button>
                                <span class="w-10 text-center font-mono text-lg font-semibold" x-text="sheet.quantity"></span>
                                <button type="button" x-on:click="sheet.quantity = Math.min({{ \App\Http\Requests\QrCartRequest::MAX_QUANTITY }}, sheet.quantity + 1)" class="flex size-11 items-center justify-center rounded-lg bg-latte text-white" aria-label="Thêm 1"><x-icon name="plus" class="size-5" /></button>
                            </div>
                        </div>
                    </div>

                    <footer class="flex items-center justify-between gap-4 border-t border-stone-200 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                        <div class="leading-tight">
                            <p class="text-sm text-stone-600">Tổng (đã gồm VAT)</p>
                            <p class="font-mono text-2xl font-bold" x-text="money(sheetUnitPrice * sheet.quantity)"></p>
                        </div>
                        <button type="button" x-on:click="addFromSheet()" class="flex h-13 flex-1 items-center justify-center gap-2 rounded-xl bg-latte px-4 text-lg font-semibold text-white hover:bg-latte-dark">
                            <x-icon name="shopping-cart" class="size-5" /> Thêm vào giỏ
                        </button>
                    </footer>
                </div>
            </template>
        </div>

        {{-- "Đã thêm…" --}}
        <div x-cloak x-show="toast" x-transition.opacity class="pointer-events-none fixed inset-x-0 bottom-28 z-40 mx-auto w-fit max-w-[calc(100%-2rem)] rounded-full bg-ink/90 px-4 py-2 text-sm font-medium text-white" role="status" x-text="toast"></div>
    </div>
</x-layouts.customer>
