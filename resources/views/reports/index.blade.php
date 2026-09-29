<x-layouts.app title="Báo cáo">
    @php
        $money = fn ($amount) => number_format((float) $amount, 0, ',', '.');
        $isLive = $period->to->isToday();
        $keep = $group === 'month' ? ['group' => 'month'] : [];
        $spansMonths = $period->from->format('Y-m') !== $period->to->format('Y-m');
    @endphp

    {{-- Realtime: a completed / refunded order changes the figures of a period that includes today. --}}
    <div x-data="liveRegion({
        channel: @js($isLive && auth()->user()->can('orders.view') ? 'orders' : null),
        events: ['order.completed', 'order.status.updated'],
        poll: @js($isLive ? 60 : false),
        pollWhenConnected: @js($isLive ? 300 : false),
        guardTyping: false,
    })">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Báo cáo doanh thu</h1>
            <p class="mt-1 text-stone-600">{{ $period->label() }} · tính theo đơn đã hoàn thành</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <x-period-filter :period="$period" route="reports.index" :keep="$keep" />

            @can('reports.export')
                <details class="group relative">
                    <summary class="flex h-11 cursor-pointer list-none items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark [&::-webkit-details-marker]:hidden">
                        <x-icon name="download" class="size-4.5" /> Xuất Excel
                        <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                    </summary>
                    <div class="absolute right-0 z-20 mt-2 flex w-64 flex-col rounded-xl border border-stone-200 bg-white p-1 shadow-lg">
                        @foreach (['revenue' => 'Doanh thu theo '.($group === 'month' ? 'tháng' : 'ngày'), 'products' => 'Sản phẩm bán chạy', 'orders' => 'Danh sách đơn hàng'] as $type => $label)
                            <a href="{{ route('reports.export', ['type' => $type] + $period->query() + $keep) }}" class="rounded-lg px-3 py-2 text-sm hover:bg-stone-100">{{ $label }} <span class="text-xs text-stone-500">(.csv)</span></a>
                        @endforeach
                    </div>
                </details>
            @endcan
        </div>
    </div>

    <div data-live-refresh="report">
    {{-- Summary --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Doanh thu', 'value' => $money($summary['revenue']).' đ', 'hint' => "{$summary['orders']} đơn hoàn thành"],
            ['label' => 'Thực thu', 'value' => $money($summary['net']).' đ', 'hint' => $summary['refund_count'] > 0 ? "Đã trừ {$money($summary['refunds'])} đ hoàn tiền ({$summary['refund_count']} lần)" : 'Không có hoàn tiền'],
            ['label' => 'Trung bình / đơn', 'value' => $money($summary['average']).' đ', 'hint' => $summary['discount'] > 0 ? "Giảm giá: {$money($summary['discount'])} đ" : 'Không có giảm giá'],
            ['label' => 'Đơn đã hủy', 'value' => $summary['cancelled'], 'hint' => 'Không tính vào doanh thu'],
        ] as $card)
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <p class="text-stone-700">{{ $card['label'] }}</p>
                <p class="mt-3 font-mono text-[26px] leading-none font-bold">{{ $card['value'] }}</p>
                <p class="mt-2 text-xs text-stone-500">{{ $card['hint'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- Chart + payment methods --}}
    <div class="mt-6 grid gap-4 xl:grid-cols-[1fr_360px]">
        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-lg font-bold">{{ $period->isSingleDay() ? 'Doanh thu theo giờ' : 'Doanh thu theo ngày' }}</h2>
                <span class="text-xs text-stone-600">Đơn vị: nghìn đồng</span>
            </div>
            <div class="mt-4"><x-sales-chart :sales="$chart" /></div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-bold">Theo phương thức thanh toán</h2>
            @if ($paymentMethods->isEmpty())
                <p class="mt-4 text-sm text-stone-600">Chưa có khoản thu nào.</p>
            @else
                <ul class="mt-4 flex flex-col gap-4">
                    @foreach ($paymentMethods as $method)
                        <li>
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-semibold">{{ $method['label'] }}</span>
                                <span><b class="font-mono">{{ $money($method['amount']) }} đ</b> <span class="text-stone-500">· {{ $method['share'] }}%</span></span>
                            </div>
                            <div class="mt-2 h-1.5 rounded-full bg-stone-100">
                                <div class="h-full rounded-full bg-latte" style="width: {{ $method['share'] }}%"></div>
                            </div>
                            <p class="mt-1 text-xs text-stone-500">{{ $method['orders'] }} đơn</p>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($orderTypes->isNotEmpty())
                <h3 class="mt-6 border-t border-stone-200 pt-4 text-sm font-bold">Theo hình thức</h3>
                <ul class="mt-2 flex flex-col gap-1 text-sm">
                    @foreach ($orderTypes as $type)
                        <li class="flex justify-between gap-3"><span class="text-stone-700">{{ $type['label'] }} ({{ $type['orders'] }} đơn)</span><b class="font-mono">{{ $money($type['amount']) }} đ</b></li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Revenue table --}}
    <section class="mt-6 overflow-hidden rounded-xl border border-stone-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
            <h2 class="text-lg font-bold">Doanh thu theo {{ $group === 'month' ? 'tháng' : 'ngày' }}</h2>
            @if ($spansMonths)
                <nav class="flex rounded-lg border border-stone-200 p-0.5 text-sm" aria-label="Nhóm theo">
                    @foreach (['day' => 'Ngày', 'month' => 'Tháng'] as $key => $label)
                        <a href="{{ route('reports.index', $period->query() + ($key === 'month' ? ['group' => 'month'] : [])) }}"
                            @if ($group === $key) aria-current="true" @endif
                            @class(['rounded-md px-3 py-1 font-medium', 'bg-espresso text-white' => $group === $key, 'text-stone-600 hover:text-ink' => $group !== $key])>{{ $label }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-sm whitespace-nowrap">
                <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                    <tr>
                        <th scope="col" class="px-6 py-3">{{ $group === 'month' ? 'Tháng' : 'Ngày' }}</th>
                        <th scope="col" class="px-3 py-3 text-right">Số đơn</th>
                        <th scope="col" class="px-3 py-3 text-right">Tiền mặt</th>
                        <th scope="col" class="px-3 py-3 text-right">Thẻ</th>
                        <th scope="col" class="px-3 py-3 text-right">Chuyển khoản</th>
                        <th scope="col" class="px-3 py-3 text-right">Hoàn tiền</th>
                        <th scope="col" class="px-6 py-3 text-right">Doanh thu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 font-mono">
                    {{-- Newest first; empty days of a long period are hidden. --}}
                    @foreach ($timeline->reverse()->filter(fn ($row) => $group === 'month' || $period->days() <= 31 || $row['orders'] > 0 || $row['refunds'] > 0) as $row)
                        <tr @class(['hover:bg-stone-50', 'text-stone-400' => $row['orders'] === 0 && $row['refunds'] == 0])>
                            <td class="px-6 py-3 font-sans">{{ $row['label'] }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['orders'] }}</td>
                            <td class="px-3 py-3 text-right">{{ $money($row['cash']) }}</td>
                            <td class="px-3 py-3 text-right">{{ $money($row['card']) }}</td>
                            <td class="px-3 py-3 text-right">{{ $money($row['transfer']) }}</td>
                            <td class="px-3 py-3 text-right">{{ $row['refunds'] > 0 ? '−'.$money($row['refunds']) : '0' }}</td>
                            <td class="px-6 py-3 text-right font-semibold">{{ $money($row['revenue']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t-2 border-stone-300 bg-stone-50 font-mono font-bold">
                    <tr>
                        <td class="px-6 py-3 font-sans">Tổng</td>
                        <td class="px-3 py-3 text-right">{{ $timeline->sum('orders') }}</td>
                        <td class="px-3 py-3 text-right">{{ $money($timeline->sum('cash')) }}</td>
                        <td class="px-3 py-3 text-right">{{ $money($timeline->sum('card')) }}</td>
                        <td class="px-3 py-3 text-right">{{ $money($timeline->sum('transfer')) }}</td>
                        <td class="px-3 py-3 text-right">{{ $timeline->sum('refunds') > 0 ? '−'.$money($timeline->sum('refunds')) : '0' }}</td>
                        <td class="px-6 py-3 text-right">{{ $money($timeline->sum('revenue')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Best sellers --}}
    <section class="mt-6 overflow-hidden rounded-xl border border-stone-200 bg-white">
        <h2 class="px-6 py-5 text-lg font-bold">Sản phẩm bán chạy</h2>
        @if ($topProducts->isEmpty())
            <p class="px-6 pb-6 text-sm text-stone-600">Chưa có món nào bán ra trong khoảng này.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm whitespace-nowrap">
                    <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                        <tr>
                            <th scope="col" class="px-6 py-3">#</th>
                            <th scope="col" class="px-3 py-3">Sản phẩm</th>
                            <th scope="col" class="px-3 py-3">Danh mục</th>
                            <th scope="col" class="px-3 py-3 text-right">Số lượng</th>
                            <th scope="col" class="px-3 py-3 text-right">Số đơn</th>
                            <th scope="col" class="px-6 py-3 text-right">Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($topProducts as $rank => $product)
                            <tr class="hover:bg-stone-50">
                                <td class="px-6 py-3 font-mono text-stone-500">{{ $rank + 1 }}</td>
                                <td class="px-3 py-3 font-semibold">{{ $product['name'] }}</td>
                                <td class="px-3 py-3 text-stone-600">{{ $product['category'] }}</td>
                                <td class="px-3 py-3 text-right font-mono">{{ $product['quantity'] }}</td>
                                <td class="px-3 py-3 text-right font-mono">{{ $product['orders'] }}</td>
                                <td class="px-6 py-3 text-right font-mono font-semibold">{{ $money($product['revenue']) }} đ</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-stone-200 px-6 py-3 text-xs text-stone-500">Doanh thu món tính theo giá bán, trước giảm giá cả đơn.</p>
        @endif
    </section>
    </div>
    </div>
</x-layouts.app>
