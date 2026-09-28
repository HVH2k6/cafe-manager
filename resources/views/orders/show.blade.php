<x-layouts.app :title="'Đơn '.$order->order_number">
    @php
        [$pill, $dot] = $order->status->badgeClasses();
        $isOpen = $order->status->isOpen();
        $hasReadyItems = $order->items->contains('status', \App\OrderItemStatus::Ready);
        $amountLock = $isOpen ? $order->amountLockReason() : null;
    @endphp

    {{-- Realtime: any change to this order (kitchen, payment, another cashier) reloads the page, unless a form is being filled in. --}}
    <div x-data="liveRegion({ channel: 'order.{{ $order->id }}', events: ['order.status.updated', 'order.ready', 'order.completed'], reload: true, poll: 20, pollWhenConnected: false, onlyIfChanged: true })">
    {{-- Fingerprint of what this page shows: a poll reloads the page only when it changed. --}}
    <span hidden data-live-version="{{ md5(json_encode([
        $order->status, $order->total_amount, $order->discount_amount, $order->items->map->only(['id', 'status', 'quantity']),
        $order->payment?->status, $order->payment?->refund_amount, $payosLink?->status,
    ])) }}"></span>
    <div x-cloak x-show="stale" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="status">
        <span class="font-medium">Đơn vừa có cập nhật mới.</span>
        <button type="button" x-on:click="applyNow()" class="h-8 rounded-lg bg-amber-600 px-3 font-semibold text-white hover:bg-amber-700">Tải lại</button>
    </div>

    <a href="{{ route('orders.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
        <x-icon name="arrow-left" class="size-4" /> Đơn hàng
    </a>
    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="font-mono text-[28px] leading-tight font-bold tracking-tight">{{ $order->order_number }}</h1>
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium {{ $pill }}"><span class="size-2 rounded-full {{ $dot }}"></span>{{ $order->status->label() }}</span>
    </div>
    <p class="mt-1 text-stone-600">
        {{ $order->placeLabel() }} · tạo lúc {{ $order->created_at->format('H:i d/m/Y') }} bởi {{ $order->creatorLabel() }}
        @if ($order->customer_name || $order->customer_phone)
            · Khách: {{ collect([$order->customer_name, $order->customer_phone])->filter()->implode(' — ') }}
        @endif
    </p>

    @if ($order->isFromQr() || $order->payment_preference)
        <p class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            @if ($order->isFromQr())
                <span class="inline-flex items-center gap-1 rounded-full bg-cream px-2.5 py-1 font-semibold text-latte-dark"><x-icon name="qr-code" class="size-3.5" /> Khách tự đặt qua QR</span>
            @endif
            @if ($order->payment_preference && ! $order->payment)
                <span class="rounded-full bg-stone-100 px-2.5 py-1 text-stone-700">Khách chọn thanh toán: <b>{{ $order->payment_preference->forCustomers()[0] }}</b></span>
            @endif
        </p>
    @endif

    @if ($order->status === \App\OrderStatus::Cancelled)
        <p class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            Đã hủy bởi {{ $order->canceller?->name ?? '—' }} lúc {{ $order->updated_at->format('H:i d/m') }}. Lý do: {{ $order->cancelled_reason }}
        </p>
    @endif

    <div class="mt-6 grid items-start gap-4 lg:grid-cols-[1fr_340px]">
        <section class="overflow-hidden rounded-xl border border-stone-200 bg-white">
            <div class="flex items-center justify-between gap-3 px-6 py-5">
                <h2 class="text-lg font-bold">Món</h2>
                @if ($isOpen && ! $amountLock)
                    @can('orders.update')
                        <a href="{{ route('orders.items.create', $order) }}" class="flex h-9 items-center gap-1.5 rounded-lg border border-stone-300 px-3 text-sm font-semibold hover:bg-stone-50">
                            <x-icon name="plus" class="size-4" /> Thêm món
                        </a>
                    @endcan
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] text-left text-sm">
                    <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                        <tr>
                            <th scope="col" class="px-6 py-3">Món</th>
                            <th scope="col" class="px-3 py-3 text-right">SL</th>
                            <th scope="col" class="px-3 py-3 text-right">Thành tiền</th>
                            <th scope="col" class="px-3 py-3">Trạng thái</th>
                            <th scope="col" class="px-6 py-3"><span class="sr-only">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($order->items as $item)
                            @php [$itemPill, $itemDot] = $item->status->badgeClasses(); @endphp
                            <tr @class(['text-stone-400' => $item->status === \App\OrderItemStatus::Cancelled])>
                                <td class="px-6 py-3">
                                    <p class="font-semibold">{{ $item->name() }}</p>
                                    @if ($item->bundle)
                                        <p class="text-xs text-stone-500">Combo: {{ $item->bundle->items->map(fn ($component) => $component->product->name)->implode(', ') }}</p>
                                    @endif
                                    @if ($item->variantSummary())
                                        <p class="text-xs text-stone-500">{{ $item->variantSummary() }}</p>
                                    @endif
                                    @if ($item->special_instructions)
                                        <p class="text-xs text-amber-700">“{{ $item->special_instructions }}”</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right font-mono">{{ $item->quantity }}</td>
                                <td class="px-3 py-3 text-right">
                                    <x-money :amount="$item->lineTotal()" />
                                    @if ($item->quantity > 1)
                                        <p class="font-mono text-xs text-stone-400">{{ number_format((float) $item->unit_price, 0, ',', '.') }} đ/món</p>
                                    @endif
                                </td>
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap {{ $itemPill }}"><span class="size-1.5 rounded-full {{ $itemDot }}"></span>{{ $item->status->label() }}</span>
                                    @if ($item->preparer && $item->status !== \App\OrderItemStatus::Cancelled)
                                        <p class="mt-0.5 text-xs text-stone-500">{{ $item->preparer->name }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if ($isOpen && ! $amountLock && $item->status === \App\OrderItemStatus::Pending)
                                        @can('orders.update')
                                            <form method="POST" action="{{ route('orders.items.destroy', [$order, $item]) }}" data-confirm="Bỏ “{{ $item->name() }}” khỏi đơn?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-stone-500 hover:bg-red-50 hover:text-red-600" title="Bỏ món" aria-label="Bỏ {{ $item->name() }}">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($order->notes)
                <p class="border-t border-stone-100 px-6 py-4 text-sm"><b>Ghi chú:</b> {{ $order->notes }}</p>
            @endif
        </section>

        <aside class="flex flex-col gap-4">
            {{-- Totals --}}
            <section class="rounded-xl border border-stone-200 bg-white p-5">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-stone-600">Tạm tính</dt><dd><x-money :amount="$order->subtotal" /></dd></div>
                    @if ((float) $order->discount_amount > 0)
                        <div class="flex justify-between text-emerald-700">
                            <dt>Giảm giá{{ $order->discount_reason ? " ({$order->discount_reason})" : '' }}</dt>
                            <dd><x-money :amount="-(float) $order->discount_amount" /></dd>
                        </div>
                    @endif
                    <div class="flex items-baseline justify-between border-t border-stone-100 pt-3">
                        <dt class="font-semibold">Tổng cộng</dt>
                        <dd><x-money :amount="$order->total_amount" class="text-2xl font-bold" /></dd>
                    </div>
                </dl>

                @if ($isOpen && ! $amountLock)
                    @can('orders.discount')
                        <details class="mt-4 border-t border-stone-100 pt-3 text-sm" @if ($errors->discount->any()) open @endif>
                            <summary class="cursor-pointer font-semibold text-latte-dark">Giảm giá</summary>
                            <form method="POST" action="{{ route('orders.discount.update', $order) }}" class="mt-3 flex flex-col gap-2">
                                @csrf
                                @method('PUT')
                                <input name="discount_amount" type="number" min="0" step="1000" value="{{ old('discount_amount', (int) $order->discount_amount) }}" aria-label="Số tiền giảm" class="h-10 rounded-lg border border-stone-300 px-3 font-mono focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                                <input name="discount_reason" value="{{ old('discount_reason', $order->discount_reason) }}" maxlength="255" placeholder="Lý do (khách quen, khuyến mãi…)" aria-label="Lý do giảm giá" class="h-10 rounded-lg border border-stone-300 px-3 focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                                @foreach ($errors->discount->all() as $error)
                                    <p class="text-red-600">{{ $error }}</p>
                                @endforeach
                                <button type="submit" class="h-9 rounded-lg border border-stone-300 font-semibold hover:bg-stone-50">Áp dụng</button>
                            </form>
                        </details>
                    @endcan
                @endif
            </section>

            @include('orders.partials.payment')

            {{-- Next step --}}
            @if ($isOpen)
                <section class="flex flex-col gap-2 rounded-xl border border-stone-200 bg-white p-5">
                    @can('orders.update')
                        @if ($order->status === \App\OrderStatus::Pending)
                            <form method="POST" action="{{ route('orders.confirm', $order) }}">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="h-11 w-full rounded-lg bg-latte font-semibold text-white hover:bg-latte-dark">Xác nhận đơn — gửi xuống bếp</button>
                            </form>
                        @endif
                        @if ($hasReadyItems)
                            <form method="POST" action="{{ route('orders.serve', $order) }}">
                                @csrf
                                @method('PUT')
                                <button type="submit" class="h-11 w-full rounded-lg bg-espresso font-semibold text-white hover:bg-espresso-line">Đã mang món ra cho khách</button>
                            </form>
                        @endif
                        @if ($order->status === \App\OrderStatus::Served)
                            <p class="text-sm text-stone-600">Đã mang đủ món. Đơn tự hoàn thành khi thu tiền xong.</p>
                        @endif
                        @if (in_array($order->status, [\App\OrderStatus::Confirmed, \App\OrderStatus::Preparing], true) && ! $hasReadyItems)
                            <p class="text-sm text-stone-600">Đang chờ bếp làm món. Món xong sẽ hiện nút “Đã mang món ra”.</p>
                        @endif
                    @endcan

                    @if ($order->payment)
                        <p class="mt-2 border-t border-stone-100 pt-3 text-xs text-stone-500">Đơn đã thanh toán: muốn hủy thì dùng “Hủy đơn & hoàn tiền” ở mục Thanh toán.</p>
                    @else
                    @can('orders.cancel')
                        <details class="mt-2 border-t border-stone-100 pt-3 text-sm" @if ($errors->cancel->any()) open @endif>
                            <summary class="cursor-pointer font-semibold text-red-700">Hủy đơn</summary>
                            <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-3 flex flex-col gap-2">
                                @csrf
                                @method('PUT')
                                <textarea name="cancelled_reason" rows="2" maxlength="255" required placeholder="Lý do hủy (bắt buộc)" aria-label="Lý do hủy" class="rounded-lg border border-stone-300 px-3 py-2 focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">{{ old('cancelled_reason') }}</textarea>
                                @foreach ($errors->cancel->all() as $error)
                                    <p class="text-red-600">{{ $error }}</p>
                                @endforeach
                                <button type="submit" class="h-9 rounded-lg border border-red-200 font-semibold text-red-700 hover:bg-red-50">Xác nhận hủy đơn</button>
                            </form>
                        </details>
                    @endcan
                    @endif
                </section>
            @endif
        </aside>
    </div>
    </div>
</x-layouts.app>
