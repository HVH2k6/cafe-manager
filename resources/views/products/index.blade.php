<x-layouts.app title="Sản phẩm">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Thực đơn</h1>
            <p class="mt-1 text-stone-600">Sản phẩm, danh mục và combo bán tại quán.</p>
        </div>

        @can('products.create')
            <a href="{{ route('products.create') }}" class="flex h-11 items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark">
                <x-icon name="plus" class="size-4.5" />
                Thêm sản phẩm
            </a>
        @endcan
    </div>

    @include('products.partials.tabs')

    <form method="GET" action="{{ route('products.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-stone-200 bg-white p-4" role="search">
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
            <label for="status" class="text-xs font-semibold text-stone-600">Trạng thái</label>
            <select id="status" name="status" class="h-10 rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                <option value="all" @selected($filters['status'] === 'all')>Tất cả</option>
                <option value="available" @selected($filters['status'] === 'available')>Đang bán</option>
                <option value="unavailable" @selected($filters['status'] === 'unavailable')>Ngừng bán</option>
            </select>
        </div>
        <button type="submit" class="h-10 rounded-lg bg-espresso px-4 text-sm font-semibold text-white hover:bg-espresso-line">Lọc</button>
        @if ($filters['search'] !== '' || $filters['category'] || $filters['status'] !== 'all')
            <a href="{{ route('products.index') }}" class="flex h-10 items-center px-2 text-sm font-medium text-stone-600 hover:text-ink">Xóa bộ lọc</a>
        @endif
    </form>

    @if ($products->isEmpty())
        <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark"><x-icon name="coffee" class="size-6" /></span>
            @if ($categories->isEmpty())
                <p class="mt-4 font-semibold">Chưa có danh mục nào</p>
                <p class="mt-1 text-sm text-stone-600">Tạo danh mục trước (ví dụ: Cà phê, Trà, Bánh), rồi thêm sản phẩm vào.</p>
                @can('products.create')
                    <a href="{{ route('categories.create') }}" class="mt-4 text-sm font-semibold text-latte-dark hover:underline">Tạo danh mục đầu tiên →</a>
                @endcan
            @else
                <p class="mt-4 font-semibold">Không có sản phẩm nào</p>
                <p class="mt-1 text-sm text-stone-600">Thử đổi bộ lọc hoặc thêm sản phẩm mới.</p>
            @endif
        </div>
    @else
        <section class="mt-4 overflow-hidden rounded-xl border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm whitespace-nowrap">
                    <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                        <tr>
                            <th scope="col" class="px-6 py-3">Sản phẩm</th>
                            <th scope="col" class="px-3 py-3">Danh mục</th>
                            <th scope="col" class="px-3 py-3 text-right">Giá bán</th>
                            <th scope="col" class="px-3 py-3 text-right">Lãi / món</th>
                            <th scope="col" class="px-3 py-3">Tồn kho</th>
                            <th scope="col" class="px-3 py-3">Trạng thái</th>
                            <th scope="col" class="px-6 py-3"><span class="sr-only">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($products as $product)
                            <tr class="hover:bg-stone-50">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        @if ($product->imageUrl())
                                            <img src="{{ $product->imageUrl() }}" alt="" class="size-11 shrink-0 rounded-lg object-cover">
                                        @else
                                            <span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-stone-100 text-stone-400"><x-icon name="image" class="size-5" /></span>
                                        @endif
                                        <div class="leading-tight">
                                            <p class="flex items-center gap-1.5 font-semibold">
                                                {{ $product->name }}
                                                @if ($product->is_featured)
                                                    <x-icon name="star" class="size-3.5 fill-amber-400 text-amber-500" />
                                                @endif
                                            </p>
                                            <p class="text-xs text-stone-500">
                                                {{ $product->sku ? "SKU {$product->sku} · " : '' }}{{ $product->variants_count > 0 ? "{$product->variants_count} biến thể" : 'Không có biến thể' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">{{ $product->category->name }}</td>
                                <td class="px-3 py-3 text-right">
                                    <x-money :amount="$product->selling_price" class="font-medium" />
                                    @if ((float) $product->base_price > (float) $product->selling_price)
                                        <x-money :amount="$product->base_price" class="block text-xs text-stone-400 line-through" />
                                    @endif
                                </td>
                                <td @class(['px-3 py-3 text-right', 'text-red-600' => $product->margin() < 0])>
                                    <x-money :amount="$product->margin()" />
                                </td>
                                <td class="px-3 py-3">
                                    @include('inventory.partials.stock-badge', ['inventory' => $product->inventory])
                                </td>
                                <td class="px-3 py-3">
                                    @if ($product->is_available)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>Đang bán</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-600"><span class="size-1.5 rounded-full bg-stone-400"></span>Ngừng bán</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-end gap-1">
                                        @can('products.update')
                                            <a href="{{ route('products.edit', $product) }}" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 hover:text-ink" title="Sửa" aria-label="Sửa {{ $product->name }}">
                                                <x-icon name="pencil" class="size-4" />
                                            </a>
                                        @endcan
                                        @can('products.delete')
                                            <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="Xóa sản phẩm “{{ $product->name }}”?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-red-50 hover:text-red-600" title="Xóa" aria-label="Xóa {{ $product->name }}">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
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
