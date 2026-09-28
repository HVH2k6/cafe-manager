{{--
    POS: pick items and send the order. Used for a new order ($order = null)
    and for adding items to an open order. The cart lives in resources/js/pos.js;
    prices shown here are only a preview — the server prices every line again.
--}}
<x-layouts.app :title="$order ? 'Thêm món · '.$order->order_number : 'Tạo đơn'">
    <script type="application/json" id="pos-data">
        {!! json_encode([
            'menu' => $menu,
            'oldItems' => array_values(old('items', [])),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}
    </script>

    <div data-pos class="grid items-start gap-6 xl:grid-cols-[1fr_400px]">
        {{-- Menu --}}
        <section>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    @if ($order)
                        <a href="{{ route('orders.show', $order) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
                            <x-icon name="arrow-left" class="size-4" /> {{ $order->order_number }}
                        </a>
                        <h1 class="mt-1 text-[32px] leading-tight font-bold tracking-tight">Thêm món · {{ $order->placeLabel() }}</h1>
                    @else
                        <h1 class="text-[32px] leading-tight font-bold tracking-tight">Tạo đơn</h1>
                    @endif
                </div>
                <div class="relative w-full sm:w-72">
                    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4.5 -translate-y-1/2 text-stone-400" />
                    <input type="search" data-pos-search placeholder="Tìm món…" aria-label="Tìm món" class="h-10 w-full rounded-lg border border-stone-300 bg-white pr-3 pl-10 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                </div>
            </div>

            <nav class="mt-5 flex gap-2 overflow-x-auto pb-1" aria-label="Danh mục">
                <button type="button" data-pos-category="all" aria-pressed="true" class="h-9 shrink-0 rounded-full border border-stone-300 bg-white px-4 text-sm font-semibold aria-pressed:border-espresso aria-pressed:bg-espresso aria-pressed:text-white">Tất cả</button>
                @if ($menu['bundles'] !== [])
                    <button type="button" data-pos-category="bundles" aria-pressed="false" class="h-9 shrink-0 rounded-full border border-stone-300 bg-white px-4 text-sm font-semibold aria-pressed:border-espresso aria-pressed:bg-espresso aria-pressed:text-white">Combo</button>
                @endif
                @foreach ($menu['categories'] as $category)
                    <button type="button" data-pos-category="{{ $category['id'] }}" aria-pressed="false" class="h-9 shrink-0 rounded-full border border-stone-300 bg-white px-4 text-sm font-semibold aria-pressed:border-espresso aria-pressed:bg-espresso aria-pressed:text-white">
                        {{ $category['icon'] }} {{ $category['name'] }}
                    </button>
                @endforeach
            </nav>

            @if ($menu['categories'] === [] && $menu['bundles'] === [])
                <div class="mt-6 rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
                    <p class="font-semibold">Chưa có món nào đang bán</p>
                    <p class="mt-1 text-sm text-stone-600">Thêm sản phẩm ở trang Thực đơn trước.</p>
                </div>
            @endif

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($menu['bundles'] as $bundle)
                    <button type="button" data-pos-add="bundle" data-id="{{ $bundle['id'] }}" data-category="bundles" data-name="{{ mb_strtolower($bundle['name']) }}" class="group flex flex-col overflow-hidden rounded-xl border border-stone-200 bg-white text-left hover:border-latte focus-visible:ring-3 focus-visible:ring-latte/30 focus-visible:outline-none">
                        @include('orders.partials.pos-image', ['image' => $bundle['image'], 'icon' => 'layers'])
                        <span class="flex flex-1 flex-col p-3">
                            <span class="font-semibold leading-snug">{{ $bundle['name'] }}</span>
                            <span class="mt-0.5 line-clamp-2 text-xs text-stone-500">{{ $bundle['contents'] }}</span>
                            <x-money :amount="$bundle['price']" class="mt-auto pt-2 text-sm font-semibold" />
                        </span>
                    </button>
                @endforeach

                @foreach ($menu['categories'] as $category)
                    @foreach ($category['products'] as $product)
                        @php $soldOut = $product['stock'] !== null && $product['stock'] <= 0; @endphp
                        <button type="button" data-pos-add="product" data-id="{{ $product['id'] }}" data-category="{{ $category['id'] }}" data-name="{{ mb_strtolower($product['name']) }}" @disabled($soldOut) class="group flex flex-col overflow-hidden rounded-xl border border-stone-200 bg-white text-left hover:border-latte focus-visible:ring-3 focus-visible:ring-latte/30 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50">
                            @include('orders.partials.pos-image', ['image' => $product['image'], 'icon' => 'coffee'])
                            <span class="flex flex-1 flex-col p-3">
                                <span class="font-semibold leading-snug">{{ $product['name'] }}</span>
                                @if ($product['variantGroups'] !== [])
                                    <span class="mt-0.5 text-xs text-stone-500">{{ collect($product['variantGroups'])->pluck('name')->implode(' · ') }}</span>
                                @endif
                                <span class="mt-auto flex items-center justify-between gap-2 pt-2">
                                    <x-money :amount="$product['price']" class="text-sm font-semibold" />
                                    @if ($soldOut)
                                        <span class="text-xs font-semibold text-red-600">Hết hàng</span>
                                    @elseif ($product['stock'] !== null)
                                        <span class="text-xs text-stone-500">Còn {{ $product['stock'] }}</span>
                                    @endif
                                </span>
                            </span>
                        </button>
                    @endforeach
                @endforeach
            </div>
            <p data-pos-empty hidden class="mt-6 text-center text-sm text-stone-600">Không có món nào khớp.</p>
        </section>

        {{-- Cart --}}
        <aside class="xl:sticky xl:top-6">
            <form method="POST" action="{{ $order ? route('orders.items.store', $order) : route('orders.store') }}" data-pos-form class="flex flex-col rounded-xl border border-stone-200 bg-white">
                @csrf

                @if ($errors->any())
                    <div role="alert" class="m-4 mb-0 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <ul class="list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @unless ($order)
                    <div class="border-b border-stone-100 p-4">
                        <fieldset class="grid grid-cols-2 gap-2">
                            <legend class="sr-only">Hình thức</legend>
                            @foreach (['dine_in' => 'Tại bàn', 'takeaway' => 'Mang về'] as $value => $label)
                                <label class="flex h-10 cursor-pointer items-center justify-center rounded-lg border border-stone-300 text-sm font-semibold has-checked:border-espresso has-checked:bg-espresso has-checked:text-white">
                                    <input type="radio" name="order_type" value="{{ $value }}" @checked(old('order_type', 'dine_in') === $value) class="sr-only" data-pos-order-type>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </fieldset>

                        <div class="mt-3" data-pos-table-field>
                            <label for="table_id" class="sr-only">Bàn</label>
                            <select id="table_id" name="table_id" data-pos-table class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                                <option value="">Chọn bàn…</option>
                                @foreach (collect($tables)->groupBy('section') as $section => $sectionTables)
                                    <optgroup label="{{ $section }}">
                                        @foreach ($sectionTables as $table)
                                            <option
                                                value="{{ $table['id'] }}"
                                                @selected((int) old('table_id') === $table['id'])
                                                @disabled($table['status'] === 'maintenance')
                                                @if ($table['openOrder']) data-open-order="{{ $table['openOrder']['number'] }}" data-add-url="{{ $table['openOrder']['addUrl'] }}" @endif
                                            >
                                                Bàn {{ $table['number'] }} · {{ $table['capacity'] }} chỗ{{ $table['openOrder'] ? ' — đang có đơn '.$table['openOrder']['number'] : ($table['status'] !== 'available' ? ' — '.mb_strtolower($table['statusLabel']) : '') }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p data-pos-open-order hidden class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                Bàn này đang có đơn <b data-pos-open-order-number></b>.
                                <a data-pos-open-order-link href="#" class="font-semibold underline">Thêm món vào đơn đó →</a>
                            </p>
                        </div>

                        <details class="mt-3 text-sm" @if (old('customer_name') || old('customer_phone')) open @endif>
                            <summary class="cursor-pointer font-semibold text-stone-600">Thông tin khách (không bắt buộc)</summary>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <input name="customer_name" value="{{ old('customer_name') }}" placeholder="Tên khách" maxlength="255" aria-label="Tên khách" class="h-10 rounded-lg border border-stone-300 px-3 focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                                <input name="customer_phone" value="{{ old('customer_phone') }}" placeholder="Số điện thoại" maxlength="20" aria-label="Số điện thoại" class="h-10 rounded-lg border border-stone-300 px-3 font-mono focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                            </div>
                        </details>
                    </div>
                @endunless

                <div class="flex items-center justify-between px-4 pt-4">
                    <h2 class="font-bold">Món đã chọn</h2>
                    <span class="text-sm text-stone-500" data-pos-count>0 món</span>
                </div>
                <ul data-pos-cart class="max-h-[45vh] divide-y divide-stone-100 overflow-y-auto px-4"></ul>
                <p data-pos-cart-empty class="px-4 py-10 text-center text-sm text-stone-500">Bấm vào món bên trái để thêm vào đơn.</p>
                <p data-pos-warning hidden role="status" class="mx-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800"></p>

                @unless ($order)
                    <div class="px-4 pt-3">
                        <label for="notes" class="sr-only">Ghi chú đơn</label>
                        <textarea id="notes" name="notes" rows="2" maxlength="1000" placeholder="Ghi chú cho cả đơn…" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">{{ old('notes') }}</textarea>
                    </div>
                @endunless

                <div data-pos-lines hidden></div>

                <div class="mt-3 border-t border-stone-200 p-4">
                    <div class="flex items-baseline justify-between">
                        <span class="text-stone-600">Tạm tính</span>
                        <span class="font-mono text-2xl font-bold" data-pos-total>0 đ</span>
                    </div>
                    <button type="submit" data-pos-submit disabled class="mt-3 h-12 w-full rounded-lg bg-latte font-semibold text-white hover:bg-latte-dark disabled:cursor-not-allowed disabled:opacity-50">
                        {{ $order ? 'Thêm vào đơn '.$order->order_number : 'Tạo đơn' }}
                    </button>
                </div>
            </form>
        </aside>

        {{-- Options dialog for products with variants --}}
        <dialog data-pos-dialog class="m-auto w-[min(440px,calc(100vw-2rem))] rounded-2xl p-0 backdrop:bg-ink/50">
            <form method="dialog" class="flex max-h-[85vh] flex-col">
                <div class="flex items-start justify-between gap-4 border-b border-stone-100 p-5">
                    <div>
                        <h2 class="text-xl font-bold" data-pos-dialog-name></h2>
                        <p class="text-sm text-stone-500" data-pos-dialog-base></p>
                    </div>
                    <button type="submit" value="cancel" class="flex size-9 items-center justify-center rounded-full bg-stone-100 hover:bg-stone-200" aria-label="Đóng">×</button>
                </div>
                <div class="flex-1 space-y-5 overflow-y-auto p-5">
                    <div data-pos-dialog-groups class="space-y-5"></div>
                    <div>
                        <label for="pos-note" class="text-xs font-semibold tracking-wide text-stone-600 uppercase">Ghi chú</label>
                        <input id="pos-note" data-pos-dialog-note maxlength="255" placeholder="VD: ít đá, không kem…" class="mt-2 h-10 w-full rounded-lg border border-stone-300 px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold tracking-wide text-stone-600 uppercase">Số lượng</span>
                        <div class="flex items-center gap-1 rounded-lg bg-stone-100 p-1">
                            <button type="button" data-pos-dialog-step="-1" class="flex size-9 items-center justify-center rounded-md bg-white text-lg font-semibold" aria-label="Bớt">−</button>
                            <span data-pos-dialog-quantity class="w-10 text-center font-mono font-semibold">1</span>
                            <button type="button" data-pos-dialog-step="1" class="flex size-9 items-center justify-center rounded-md bg-latte text-lg font-semibold text-white" aria-label="Thêm">+</button>
                        </div>
                    </div>
                </div>
                <div class="border-t border-stone-100 p-5">
                    <button type="submit" value="add" class="flex h-12 w-full items-center justify-center gap-2 rounded-lg bg-latte font-semibold text-white hover:bg-latte-dark">
                        Thêm vào đơn · <span data-pos-dialog-price class="font-mono"></span>
                    </button>
                </div>
            </form>
        </dialog>
    </div>
</x-layouts.app>
