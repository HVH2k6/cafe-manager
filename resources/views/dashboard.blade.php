<x-layouts.app title="Tổng quan">
    @php
        // Only a period that includes today can change while the page is open.
        $isLive = $period->to->isToday() || $period->to->isFuture();
        $canSeeOrders = auth()->user()->can('orders.view');
    @endphp

    {{-- Realtime: every order event refreshes the figures (staff without orders.view poll instead). --}}
    <div x-data="liveRegion({
        channel: @js($isLive && $canSeeOrders ? 'orders' : null),
        events: ['order.created', 'order.status.updated', 'order.completed'],
        poll: @js($isLive ? 30 : false),
        pollWhenConnected: @js($isLive ? 120 : false),
        guardTyping: false,
    })">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">{{ $period->key === 'today' ? 'Tổng quan hôm nay' : 'Tổng quan' }}</h1>
            <p class="mt-1 text-stone-600">
                @if ($period->key === 'today')
                    {{ Str::ucfirst(now()->locale('vi')->isoFormat('dddd, DD/MM/YYYY')) }} ·
                    {{ $openingHours['isOpen'] ? 'Quán đang mở' : 'Quán đã đóng' }} ({{ $openingHours['opens'] }} – {{ $openingHours['closes'] }})
                @else
                    {{ $period->label() }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            @if ($isLive)
                <span class="flex items-center gap-2 rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs text-stone-600" role="status">
                    <span class="size-2 rounded-full" :class="$store.realtime.connected ? 'bg-emerald-500' : 'bg-stone-400'"></span>
                    <span x-text="$store.realtime.connected ? 'Cập nhật trực tiếp' : 'Tự cập nhật mỗi 30 giây'">Tự cập nhật mỗi 30 giây</span>
                </span>
            @endif
            @can('reports.view')
                <a href="{{ route('reports.index', $period->query()) }}" class="flex h-11 items-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-medium hover:bg-stone-50">
                    <x-icon name="chart-bar" class="size-4.5" /> Báo cáo
                </a>
            @endcan
            <x-period-filter :period="$period" route="dashboard" />
        </div>
    </div>

    {{-- Stats --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-live-refresh="stats">
        @foreach (array_filter([
            ['icon' => 'receipt', 'tint' => 'bg-cream/70 text-latte-dark', 'label' => 'Đơn hàng', 'value' => number_format($stats['orders'], 0, ',', '.'), 'unit' => 'đơn', 'hint' => $stats['cancelledOrders'] > 0 ? "{$stats['cancelledOrders']} đơn đã hủy (không tính)" : null],
            $canSeeMoney ? ['icon' => 'banknote', 'tint' => 'bg-orange-50 text-orange-700', 'label' => 'Doanh thu', 'value' => number_format($stats['revenue'], 0, ',', '.'), 'unit' => 'đ', 'hint' => $stats['net'] !== $stats['revenue'] ? 'Sau hoàn tiền: '.number_format($stats['net'], 0, ',', '.').' đ' : 'Từ đơn đã hoàn thành'] : null,
            $canSeeMoney ? ['icon' => 'wallet', 'tint' => 'bg-blue-50 text-blue-600', 'label' => 'Trung bình / đơn', 'value' => number_format($stats['average'], 0, ',', '.'), 'unit' => 'đ', 'hint' => null] : null,
            ['icon' => 'coffee', 'tint' => 'bg-emerald-50 text-emerald-700', 'label' => 'Món đã bán', 'value' => number_format($stats['itemsSold'], 0, ',', '.'), 'unit' => 'phần', 'hint' => null],
        ]) as $card)
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <div class="flex items-center gap-3 text-stone-700">
                    <span class="flex size-9 items-center justify-center rounded-lg {{ $card['tint'] }}">
                        <x-icon :name="$card['icon']" class="size-4.5" />
                    </span>
                    {{ $card['label'] }}
                </div>
                <p class="mt-4 flex items-baseline gap-1.5">
                    <span class="font-mono text-[28px] leading-none font-bold">{{ $card['value'] }}</span>
                    <span class="text-sm text-stone-600">{{ $card['unit'] }}</span>
                </p>
                @if ($card['hint'])
                    <p class="mt-2 text-xs text-stone-500">{{ $card['hint'] }}</p>
                @endif
            </div>
        @endforeach

        @php $completedPercent = $stats['orders'] > 0 ? round($stats['completedOrders'] / $stats['orders'] * 100) : 0; @endphp
        <div class="rounded-xl border border-stone-200 bg-white p-5">
            <p class="text-stone-700">Tiến độ đơn</p>
            <div class="mt-4 flex h-2 gap-0.5 overflow-hidden rounded-full bg-stone-100" role="img" aria-label="{{ $completedPercent }}% đơn đã hoàn thành">
                <span class="bg-emerald-500" style="width: {{ $completedPercent }}%"></span>
                @if ($stats['openOrders'] > 0)
                    <span class="flex-1 bg-blue-500"></span>
                @endif
            </div>
            <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm">
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span>Hoàn thành <b class="font-mono">{{ $stats['completedOrders'] }}</b></span>
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-blue-500"></span>Đang xử lý <b class="font-mono">{{ $stats['openOrders'] }}</b></span>
            </div>
        </div>
    </div>

    {{-- Chart + best sellers --}}
    <div class="mt-6 grid gap-4 xl:grid-cols-[1fr_370px]" data-live-refresh="charts">
        @if ($canSeeMoney)
            <section class="rounded-xl border border-stone-200 bg-white p-6">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-bold">{{ $chartTitle }}</h2>
                    <span class="text-xs text-stone-600">Đơn vị: nghìn đồng</span>
                </div>
                <div class="mt-4">
                    <x-sales-chart :sales="$chart" />
                </div>

                @if ($paymentMethods->isNotEmpty())
                    <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 border-t border-stone-200 pt-4 text-sm">
                        @foreach ($paymentMethods as $method)
                            <span class="text-stone-600">{{ $method['label'] }}: <b class="font-mono text-ink">{{ number_format($method['amount'], 0, ',', '.') }} đ</b> <span class="text-xs">({{ $method['share'] }}%)</span></span>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        <section @class(['rounded-xl border border-stone-200 bg-white p-6', 'xl:col-span-2' => ! $canSeeMoney])>
            <h2 class="text-lg font-bold">Sản phẩm bán chạy</h2>
            @if ($topProducts->isEmpty())
                <p class="mt-5 text-sm text-stone-600">Chưa có món nào bán ra trong khoảng này.</p>
            @else
                @php $bestSellerQuantity = $topProducts->max('quantity') ?: 1; @endphp
                <ol class="mt-5 flex flex-col gap-5">
                    @foreach ($topProducts as $rank => $product)
                        <li>
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'flex size-7 items-center justify-center rounded-md text-sm font-semibold',
                                    'bg-espresso text-white' => $rank === 0,
                                    'bg-stone-100 text-stone-700' => $rank > 0,
                                ])>{{ $rank + 1 }}</span>
                                <span class="flex-1 font-semibold">{{ $product['name'] }}</span>
                                <span class="text-sm"><b class="font-semibold">{{ $product['quantity'] }}</b> <span class="text-stone-600">phần</span></span>
                            </div>
                            <div class="mt-3 h-1.5 rounded-full bg-stone-100">
                                <div class="h-full rounded-full bg-latte" style="width: {{ round($product['quantity'] / $bestSellerQuantity * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    {{-- Recent orders --}}
    @if ($canSeeOrders)
        <section class="mt-6 overflow-hidden rounded-xl border border-stone-200 bg-white" data-live-refresh="recent">
            <div class="flex items-center justify-between gap-4 px-6 py-5">
                <h2 class="text-lg font-bold">Đơn hàng gần đây</h2>
                <a href="{{ route('orders.index', ['tab' => 'all']) }}" class="flex items-center gap-1 text-sm font-semibold text-latte-dark hover:underline">
                    Xem tất cả <x-icon name="arrow-right" class="size-4" />
                </a>
            </div>

            @if ($recentOrders->isEmpty())
                <p class="px-6 pb-6 text-sm text-stone-600">Chưa có đơn nào trong khoảng này.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm whitespace-nowrap">
                        <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                            <tr>
                                <th scope="col" class="px-6 py-3">Mã đơn</th>
                                <th scope="col" class="px-3 py-3">Bàn</th>
                                <th scope="col" class="px-3 py-3">Món</th>
                                <th scope="col" class="px-3 py-3 text-right">Tổng tiền</th>
                                <th scope="col" class="px-3 py-3">Trạng thái</th>
                                <th scope="col" class="px-6 py-3 text-right">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-200">
                            @foreach ($recentOrders as $order)
                                @php
                                    [$pillClasses, $dotClasses] = $order->status->badgeClasses();
                                    $items = $order->status === \App\OrderStatus::Cancelled ? $order->items : $order->items->where('status', '!=', \App\OrderItemStatus::Cancelled);
                                    $summary = $items->take(2)->map(fn ($item) => $item->quantity > 1 ? "{$item->name()} ×{$item->quantity}" : $item->name())->implode(', ');
                                @endphp
                                <tr class="hover:bg-stone-50">
                                    <td class="px-6 py-4"><a href="{{ route('orders.show', $order) }}" class="font-mono font-medium hover:underline">{{ $order->order_number }}</a></td>
                                    <td class="px-3 py-4">{{ $order->placeLabel() }}</td>
                                    <td class="min-w-56 px-3 py-4 whitespace-normal text-stone-700">{{ $summary }}{{ $items->count() > 2 ? ' +'.($items->count() - 2).' món' : '' }}</td>
                                    <td class="px-3 py-4 text-right font-mono font-medium whitespace-nowrap">{{ number_format((float) $order->total_amount, 0, ',', '.') }} đ</td>
                                    <td class="px-3 py-4">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap {{ $pillClasses }}">
                                            <span class="size-1.5 rounded-full {{ $dotClasses }}"></span>
                                            {{ $order->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right font-mono text-stone-700">{{ $order->created_at->isToday() ? $order->created_at->format('H:i:s') : $order->created_at->format('d/m H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
    </div>
</x-layouts.app>
