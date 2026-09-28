<x-layouts.app title="Danh mục">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Thực đơn</h1>
            <p class="mt-1 text-stone-600">Nhóm sản phẩm trên thực đơn, theo thứ tự hiển thị.</p>
        </div>

        @can('products.create')
            <a href="{{ route('categories.create') }}" class="flex h-11 items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark">
                <x-icon name="plus" class="size-4.5" />
                Thêm danh mục
            </a>
        @endcan
    </div>

    @include('products.partials.tabs')

    @if ($categories->isEmpty())
        <div class="mt-6 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark"><x-icon name="layout-grid" class="size-6" /></span>
            <p class="mt-4 font-semibold">Chưa có danh mục nào</p>
            <p class="mt-1 text-sm text-stone-600">Ví dụ: Cà phê, Trà, Nước ép, Bánh ngọt.</p>
        </div>
    @else
        <section class="mt-6 overflow-hidden rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                    <tr>
                        <th scope="col" class="w-16 px-6 py-3">#</th>
                        <th scope="col" class="px-3 py-3">Danh mục</th>
                        <th scope="col" class="px-3 py-3 text-right">Sản phẩm</th>
                        <th scope="col" class="px-3 py-3">Hiển thị</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @foreach ($categories as $category)
                        <tr class="hover:bg-stone-50">
                            <td class="px-6 py-4 font-mono text-stone-500">{{ $category->display_order }}</td>
                            <td class="px-3 py-4">
                                <p class="font-semibold">{{ $category->icon }} {{ $category->name }}</p>
                                @if ($category->description)
                                    <p class="mt-0.5 text-stone-600">{{ $category->description }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-4 text-right">
                                <a href="{{ route('products.index', ['category' => $category->id]) }}" class="font-mono hover:underline">{{ $category->products_count }}</a>
                            </td>
                            <td class="px-3 py-4">
                                @if ($category->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>Hiện</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-600"><span class="size-1.5 rounded-full bg-stone-400"></span>Ẩn</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-1">
                                    @can('products.update')
                                        <a href="{{ route('categories.edit', $category) }}" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 hover:text-ink" title="Sửa" aria-label="Sửa danh mục {{ $category->name }}">
                                            <x-icon name="pencil" class="size-4" />
                                        </a>
                                    @endcan
                                    @can('products.delete')
                                        <form method="POST" action="{{ route('categories.destroy', $category) }}" data-confirm="Xóa danh mục “{{ $category->name }}”?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-red-50 hover:text-red-600" title="Xóa" aria-label="Xóa danh mục {{ $category->name }}">
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
        </section>
    @endif
</x-layouts.app>
