<x-layouts.app title="Combo">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Thực đơn</h1>
            <p class="mt-1 text-stone-600">Combo bán theo một giá, rẻ hơn mua lẻ.</p>
        </div>

        @can('products.create')
            <a href="{{ route('bundles.create') }}" class="flex h-11 items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark">
                <x-icon name="plus" class="size-4.5" />
                Thêm combo
            </a>
        @endcan
    </div>

    @include('products.partials.tabs')

    @if ($bundles->isEmpty())
        <div class="mt-6 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark"><x-icon name="layers" class="size-6" /></span>
            <p class="mt-4 font-semibold">Chưa có combo nào</p>
            <p class="mt-1 text-sm text-stone-600">Ví dụ: Cà phê + Bánh mì, Combo sinh viên.</p>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($bundles as $bundle)
                <article @class(['flex flex-col rounded-xl border border-stone-200 bg-white p-5', 'opacity-60' => ! $bundle->is_active])>
                    <div class="flex items-start gap-3">
                        @if ($bundle->imageUrl())
                            <img src="{{ $bundle->imageUrl() }}" alt="" class="size-14 shrink-0 rounded-lg object-cover">
                        @else
                            <span class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-cream/60 text-latte-dark"><x-icon name="layers" class="size-6" /></span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <h2 class="font-bold">{{ $bundle->name }}</h2>
                            <p class="mt-0.5 flex flex-wrap items-baseline gap-x-2">
                                <x-money :amount="$bundle->bundle_price" class="text-lg font-bold" />
                                @if ((float) $bundle->individual_price > (float) $bundle->bundle_price)
                                    <x-money :amount="$bundle->individual_price" class="text-sm text-stone-400 line-through" />
                                @endif
                            </p>
                        </div>
                        @unless ($bundle->is_active)
                            <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">Đang tắt</span>
                        @endunless
                    </div>

                    <ul class="mt-4 flex-1 space-y-1 text-sm text-stone-700">
                        @foreach ($bundle->items as $item)
                            <li class="flex justify-between gap-2"><span>{{ $item->product->name }}</span><span class="font-mono text-stone-500">×{{ $item->quantity }}</span></li>
                        @endforeach
                    </ul>

                    <div class="mt-4 flex items-center justify-between border-t border-stone-100 pt-3">
                        @if ((float) $bundle->discount_amount > 0)
                            <span class="text-sm font-semibold text-emerald-700">Tiết kiệm <x-money :amount="$bundle->discount_amount" /></span>
                        @else
                            <span></span>
                        @endif
                        <div class="flex gap-1">
                            @can('products.update')
                                <a href="{{ route('bundles.edit', $bundle) }}" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 hover:text-ink" title="Sửa" aria-label="Sửa combo {{ $bundle->name }}">
                                    <x-icon name="pencil" class="size-4" />
                                </a>
                            @endcan
                            @can('products.delete')
                                <form method="POST" action="{{ route('bundles.destroy', $bundle) }}" data-confirm="Xóa combo “{{ $bundle->name }}”?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-red-50 hover:text-red-600" title="Xóa" aria-label="Xóa combo {{ $bundle->name }}">
                                        <x-icon name="trash" class="size-4" />
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.app>
