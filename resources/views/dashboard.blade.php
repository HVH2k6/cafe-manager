<x-layouts.app title="Tổng quan">
    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Tổng quan hôm nay</h1>
            <p class="mt-1 text-stone-600">
                {{ Str::ucfirst(now()->locale('vi')->isoFormat('dddd, DD/MM/YYYY')) }} ·
                {{ $openingHours['isOpen'] ? 'Quán đang mở' : 'Quán đã đóng' }} ({{ $openingHours['opens'] }} – {{ $openingHours['closes'] }})
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" class="flex h-11 items-center gap-2 rounded-xl border border-stone-200 bg-white px-4 text-sm font-medium hover:bg-stone-50">
                <x-icon name="calendar" class="size-4.5" />
                Hôm nay
                <x-icon name="chevron-down" class="size-4" />
            </button>
            <button type="button" class="relative flex size-11 items-center justify-center rounded-xl border border-stone-200 bg-white hover:bg-stone-50" aria-label="Thông báo">
                <x-icon name="bell" class="size-5" />
                <span class="absolute top-2.5 right-2.5 size-2 rounded-full bg-red-500 ring-2 ring-white"></span>
            </button>
        </div>
    </div>

    {{-- Stats --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['icon' => 'receipt', 'tint' => 'bg-cream/70 text-latte-dark', 'label' => 'Đơn hàng', 'value' => $stats['orders'], 'unit' => 'đơn'],
            ['icon' => 'banknote', 'tint' => 'bg-orange-50 text-orange-700', 'label' => 'Doanh số', 'value' => number_format($stats['revenue'], 0, ',', '.'), 'unit' => 'VNĐ'],
            ['icon' => 'users', 'tint' => 'bg-blue-50 text-blue-600', 'label' => 'Khách', 'value' => $stats['customers'], 'unit' => 'khách'],
        ] as $card)
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <div class="flex items-center gap-3 text-stone-700">
                    <span class="flex size-9 items-center justify-center rounded-lg {{ $card['tint'] }}">
                        <x-icon :name="$card['icon']" class="size-4.5" />
                    </span>
                    {{ $card['label'] }}
                </div>
                <p class="mt-4 flex items-baseline gap-1.5">
                    <span class="font-mono text-[32px] leading-none font-bold">{{ $card['value'] }}</span>
                    <span class="text-sm text-stone-600">{{ $card['unit'] }}</span>
                </p>
            </div>
        @endforeach

        @php $completedPercent = $stats['orders'] > 0 ? round($stats['completedOrders'] / $stats['orders'] * 100) : 0; @endphp
        <div class="rounded-xl border border-stone-200 bg-white p-5">
            <p class="text-stone-700">Tiến độ đơn</p>
            <div class="mt-4 flex h-2 gap-0.5 overflow-hidden rounded-full bg-stone-100" role="img" aria-label="{{ $completedPercent }}% đơn đã hoàn thành">
                <span class="bg-emerald-500" style="width: {{ $completedPercent }}%"></span>
                @if ($stats['pendingOrders'] > 0)
                    <span class="flex-1 bg-blue-500"></span>
                @endif
            </div>
            <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm">
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span>Hoàn thành <b class="font-mono">{{ $stats['completedOrders'] }}</b></span>
                <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-blue-500"></span>Chờ xử lý <b class="font-mono">{{ $stats['pendingOrders'] }}</b></span>
            </div>
        </div>
    </div>

    {{-- Chart + best sellers --}}
    <div class="mt-6 grid gap-4 xl:grid-cols-[1fr_370px]">
        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-bold">Doanh số theo giờ</h2>
                <span class="text-xs text-stone-600">Đơn vị: nghìn đồng</span>
            </div>
            <div class="mt-4">
                <x-sales-chart :sales="$hourlySales" />
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-bold">Sản phẩm bán chạy</h2>
            @php $bestSellerQuantity = max(array_column($topProducts, 'quantity')) ?: 1; @endphp
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
                            <span class="text-sm"><b class="font-semibold">{{ $product['quantity'] }}</b> <span class="text-stone-600">{{ $product['unit'] }}</span></span>
                        </div>
                        <div class="mt-3 h-1.5 rounded-full bg-stone-100">
                            <div class="h-full rounded-full bg-latte" style="width: {{ round($product['quantity'] / $bestSellerQuantity * 100) }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>

    {{-- Recent orders --}}
    <section class="mt-6 overflow-hidden rounded-xl border border-stone-200 bg-white">
        <div class="flex items-center justify-between gap-4 px-6 py-5">
            <h2 class="text-lg font-bold">Đơn hàng gần đây</h2>
            <a href="#" class="flex items-center gap-1 text-sm font-semibold text-latte-dark hover:underline">
                Xem tất cả <x-icon name="arrow-right" class="size-4" />
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm whitespace-nowrap">
                <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                    <tr>
                        <th class="px-6 py-3">Mã đơn</th>
                        <th class="px-3 py-3">Bàn</th>
                        <th class="px-3 py-3">Món</th>
                        <th class="px-3 py-3 text-right">Tổng tiền</th>
                        <th class="px-3 py-3">Trạng thái</th>
                        <th class="px-6 py-3 text-right">Thời gian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @foreach ($recentOrders as $order)
                        @php [$pillClasses, $dotClasses] = $order['status']->badgeClasses(); @endphp
                        <tr class="hover:bg-stone-50">
                            <td class="px-6 py-4 font-mono font-medium">{{ $order['number'] }}</td>
                            <td class="px-3 py-4">{{ $order['table'] }}</td>
                            <td class="min-w-56 px-3 py-4 whitespace-normal text-stone-700">{{ $order['items'] }}</td>
                            <td class="px-3 py-4 text-right font-mono font-medium whitespace-nowrap">{{ number_format($order['total'], 0, ',', '.') }} đ</td>
                            <td class="px-3 py-4">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap {{ $pillClasses }}">
                                    <span class="size-1.5 rounded-full {{ $dotClasses }}"></span>
                                    {{ $order['status']->label() }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-stone-700">{{ $order['time'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
