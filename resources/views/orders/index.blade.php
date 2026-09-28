<x-layouts.app title="Đơn hàng">
    {{-- Realtime: on an order event the summary, tabs and list are re-fetched; the filter form is left alone. --}}
    <div x-data="liveRegion({ channel: 'orders', events: ['order.created', 'order.status.updated', 'order.completed'], poll: 15, pollWhenConnected: 60, guardTyping: false })">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Đơn hàng</h1>
            <p class="mt-1 text-stone-600" data-live-refresh="summary">{{ $tabCounts['open'] }} đơn đang xử lý{{ $tabCounts['pending'] > 0 ? " · {$tabCounts['pending']} đơn chờ xác nhận" : '' }}</p>
        </div>

        @can('orders.create')
            <a href="{{ route('orders.create') }}" class="flex h-11 items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark">
                <x-icon name="plus" class="size-4.5" />
                Tạo đơn
            </a>
        @endcan
    </div>

    <nav class="mt-6 flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Lọc theo trạng thái" data-live-refresh="tabs">
        @foreach (['open' => 'Đang xử lý', 'pending' => 'Chờ xác nhận', 'completed' => 'Hoàn thành', 'cancelled' => 'Đã hủy', 'all' => 'Tất cả'] as $key => $label)
            <a
                href="{{ route('orders.index', ['tab' => $key] + array_filter($filters)) }}"
                @if ($tab === $key) aria-current="page" @endif
                @class([
                    '-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold whitespace-nowrap',
                    'border-latte text-ink' => $tab === $key,
                    'border-transparent text-stone-600 hover:text-ink' => $tab !== $key,
                ])
            >
                {{ $label }}
                @if (isset($tabCounts[$key]) && $tabCounts[$key] > 0)
                    <span @class(['rounded-full px-1.5 text-xs', 'bg-amber-600 text-white' => $key === 'pending', 'bg-stone-200 text-stone-700' => $key !== 'pending'])>{{ $tabCounts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('orders.index') }}" class="mt-4 flex flex-wrap items-end gap-3" role="search">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="relative min-w-60 flex-1">
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4.5 -translate-y-1/2 text-stone-400" />
            <input name="search" type="search" value="{{ $filters['search'] }}" placeholder="Mã đơn, tên hoặc SĐT khách" aria-label="Tìm đơn" class="h-10 w-full rounded-lg border border-stone-300 bg-white pr-3 pl-10 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
        </div>
        <input name="date" type="date" value="{{ $filters['date'] }}" aria-label="Ngày" class="h-10 rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
        <button type="submit" class="h-10 rounded-lg bg-espresso px-4 text-sm font-semibold text-white hover:bg-espresso-line">Lọc</button>
        @if ($filters['search'] !== '' || $filters['date'])
            <a href="{{ route('orders.index', ['tab' => $tab]) }}" class="flex h-10 items-center px-2 text-sm font-medium text-stone-600 hover:text-ink">Xóa bộ lọc</a>
        @endif
    </form>

    <div data-live-refresh="list">
    @if ($orders->isEmpty())
        <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark"><x-icon name="receipt" class="size-6" /></span>
            <p class="mt-4 font-semibold">Không có đơn nào</p>
            <p class="mt-1 text-sm text-stone-600">{{ $tab === 'open' ? 'Chưa có đơn đang xử lý.' : 'Thử đổi bộ lọc.' }}</p>
        </div>
    @else
        <section class="mt-4 overflow-hidden rounded-xl border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide whitespace-nowrap text-stone-600 uppercase">
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
                        @foreach ($orders as $order)
                            @php
                                [$pill, $dot] = $order->status->badgeClasses();
                                // A cancelled order shows what it held; other orders only their remaining items.
                                $activeItems = $order->status === \App\OrderStatus::Cancelled
                                    ? $order->items
                                    : $order->items->where('status', '!=', \App\OrderItemStatus::Cancelled);
                                $summary = $activeItems->take(2)->map(fn ($item) => ($item->quantity > 1 ? "{$item->name()} ×{$item->quantity}" : $item->name()))->implode(', ');
                            @endphp
                            <tr class="hover:bg-stone-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="{{ route('orders.show', $order) }}" class="font-mono font-semibold hover:underline">{{ $order->order_number }}</a>
                                    @if ($order->isFromQr())
                                        <span class="ml-1 rounded bg-cream px-1.5 py-0.5 text-[11px] font-semibold text-latte-dark" title="Khách tự đặt qua QR">QR</span>
                                    @endif
                                    @if ($order->customer_name)
                                        <p class="text-xs text-stone-500">{{ $order->customer_name }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-4 whitespace-nowrap">{{ $order->placeLabel() }}</td>
                                <td class="px-3 py-4 text-stone-700">{{ $summary }}{{ $activeItems->count() > 2 ? ' +'.($activeItems->count() - 2).' món' : '' }}</td>
                                <td class="px-3 py-4 text-right"><x-money :amount="$order->total_amount" class="font-medium" /></td>
                                <td class="px-3 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap {{ $pill }}"><span class="size-1.5 rounded-full {{ $dot }}"></span>{{ $order->status->label() }}</span>
                                </td>
                                <td class="px-6 py-4 text-right font-mono whitespace-nowrap text-stone-600">
                                    {{ $order->created_at->isToday() ? $order->created_at->format('H:i') : $order->created_at->format('d/m H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4">{{ $orders->links() }}</div>
    @endif
    </div>
    </div>
</x-layouts.app>
