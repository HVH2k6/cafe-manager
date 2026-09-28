<x-layouts.app title="Tồn kho">
    <div>
        <h1 class="text-[32px] leading-tight font-bold tracking-tight">Tồn kho</h1>
        <p class="mt-1 text-stone-600">Số lượng hàng đếm được. Món pha chế tại chỗ không cần theo dõi.</p>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['label' => 'Đang theo dõi', 'value' => $summary['tracked'], 'filter' => 'tracked', 'tint' => 'bg-cream/70 text-latte-dark', 'icon' => 'package'],
            ['label' => 'Sắp hết', 'value' => $summary['low'], 'filter' => 'low', 'tint' => 'bg-amber-50 text-amber-700', 'icon' => 'alert-circle'],
            ['label' => 'Hết hàng', 'value' => $summary['out'], 'filter' => 'out', 'tint' => 'bg-red-50 text-red-600', 'icon' => 'alert-circle'],
        ] as $card)
            <a href="{{ route('inventory.index', ['stock' => $card['filter']]) }}" @class([
                'rounded-xl border bg-white p-5 hover:border-latte/50',
                'border-latte ring-2 ring-latte/15' => $filters['stock'] === $card['filter'],
                'border-stone-200' => $filters['stock'] !== $card['filter'],
            ])>
                <div class="flex items-center gap-3 text-stone-700">
                    <span class="flex size-9 items-center justify-center rounded-lg {{ $card['tint'] }}"><x-icon :name="$card['icon']" class="size-4.5" /></span>
                    {{ $card['label'] }}
                </div>
                <p class="mt-3 font-mono text-[28px] leading-none font-bold">{{ $card['value'] }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('inventory.index') }}" class="mt-4 flex flex-wrap items-end gap-3 rounded-xl border border-stone-200 bg-white p-4" role="search">
        <div class="flex min-w-60 flex-1 flex-col gap-1.5">
            <label for="search" class="text-xs font-semibold text-stone-600">Tìm kiếm</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4.5 -translate-y-1/2 text-stone-400" />
                <input id="search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Tên sản phẩm hoặc SKU" class="h-10 w-full rounded-lg border border-stone-300 bg-white pr-3 pl-10 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
            </div>
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="category" class="text-xs font-semibold text-stone-600">Danh mục</label>
            <select id="category" name="category" class="h-10 rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                <option value="">Tất cả danh mục</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1.5">
            <label for="stock" class="text-xs font-semibold text-stone-600">Tình trạng</label>
            <select id="stock" name="stock" class="h-10 rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                @foreach (['all' => 'Tất cả sản phẩm', 'tracked' => 'Đang theo dõi', 'low' => 'Sắp hết & hết hàng', 'out' => 'Hết hàng', 'untracked' => 'Không theo dõi'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['stock'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="h-10 rounded-lg bg-espresso px-4 text-sm font-semibold text-white hover:bg-espresso-line">Lọc</button>
        @if ($filters['search'] !== '' || $filters['category'] || $filters['stock'] !== 'all')
            <a href="{{ route('inventory.index') }}" class="flex h-10 items-center px-2 text-sm font-medium text-stone-600 hover:text-ink">Xóa bộ lọc</a>
        @endif
    </form>

    @if ($products->isEmpty())
        <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark"><x-icon name="package" class="size-6" /></span>
            <p class="mt-4 font-semibold">Không có sản phẩm nào</p>
            <p class="mt-1 text-sm text-stone-600">Thử đổi bộ lọc, hoặc thêm sản phẩm ở trang Thực đơn.</p>
        </div>
    @else
        <section class="mt-4 overflow-hidden rounded-xl border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm whitespace-nowrap">
                    <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                        <tr>
                            <th scope="col" class="px-6 py-3">Sản phẩm</th>
                            <th scope="col" class="px-3 py-3 text-right">Tồn kho</th>
                            <th scope="col" class="px-3 py-3 text-right">Giữ cho đơn</th>
                            <th scope="col" class="px-3 py-3 text-right">Tối thiểu</th>
                            <th scope="col" class="px-3 py-3">Nhập gần nhất</th>
                            <th scope="col" class="px-3 py-3">Tình trạng</th>
                            <th scope="col" class="px-6 py-3"><span class="sr-only">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($products as $product)
                            @php $inventory = $product->inventory; @endphp
                            <tr @class(['hover:bg-stone-50', 'text-stone-500' => ! $inventory])>
                                <td class="px-6 py-3">
                                    <p class="font-semibold text-ink">{{ $product->name }}</p>
                                    <p class="text-xs text-stone-500">{{ $product->category->name }}</p>
                                </td>
                                @if ($inventory)
                                    <td class="px-3 py-3 text-right font-mono font-semibold">{{ $inventory->quantity_on_hand }} <span class="font-sans text-xs font-normal text-stone-500">{{ $inventory->unitLabel() }}</span></td>
                                    <td class="px-3 py-3 text-right font-mono">{{ $inventory->quantity_reserved }}</td>
                                    <td class="px-3 py-3 text-right font-mono">{{ $inventory->min_stock }}</td>
                                    <td class="px-3 py-3 text-stone-600">{{ $inventory->last_restock?->format('d/m/Y H:i') ?? '—' }}</td>
                                @else
                                    <td colspan="4" class="px-3 py-3 text-center text-xs">—</td>
                                @endif
                                <td class="px-3 py-3">@include('inventory.partials.stock-badge', ['inventory' => $inventory])</td>
                                <td class="px-6 py-3 text-right">
                                    @if ($inventory)
                                        <a href="{{ route('inventory.show', $product) }}" class="text-sm font-semibold text-latte-dark hover:underline">Chi tiết</a>
                                    @else
                                        @can('inventory.update')
                                            <form method="POST" action="{{ route('inventory.store', $product) }}">
                                                @csrf
                                                <button type="submit" class="text-sm font-semibold text-latte-dark hover:underline">Theo dõi kho</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4">{{ $products->links() }}</div>
    @endif
</x-layouts.app>
