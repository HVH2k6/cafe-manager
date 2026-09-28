{{-- Shared fields of the create / edit combo forms. Expects $bundle and $products. --}}
@php
    $fieldClasses = fn (string $field): string => \Illuminate\Support\Arr::toCssClasses([
        'h-11 w-full rounded-lg border bg-white px-4 text-ink placeholder:text-stone-400 focus:ring-3 focus:outline-none',
        'border-stone-300 focus:border-latte focus:ring-latte/15' => ! $errors->has($field),
        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has($field),
    ]);
    $items = old('items', $bundle->relationLoaded('items')
        ? $bundle->items->map(fn ($item) => ['product_id' => $item->product_id, 'quantity' => $item->quantity])->all()
        : [['product_id' => null, 'quantity' => 1], ['product_id' => null, 'quantity' => 1]]);
@endphp

<div class="grid items-start gap-4 lg:grid-cols-[1fr_320px]">
    <div class="flex flex-col gap-4">
        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-bold">Thông tin combo</h2>
            <div class="mt-5 flex flex-col gap-5">
                <div class="flex flex-col gap-2">
                    <label for="name" class="text-sm font-semibold">Tên combo</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $bundle->name) }}" placeholder="Combo Cà phê + Bánh mì" required maxlength="255" class="{{ $fieldClasses('name') }}">
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="description" class="text-sm font-semibold">Mô tả <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
                    <textarea id="description" name="description" rows="2" maxlength="2000" class="h-auto py-3 {{ $fieldClasses('description') }}">{{ old('description', $bundle->description) }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-6" data-repeater>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">Món trong combo</h2>
                    <p class="mt-1 text-sm text-stone-600">Ít nhất 2 món (hoặc 1 món số lượng từ 2).</p>
                </div>
                <button type="button" data-repeater-add class="flex h-9 items-center gap-1.5 rounded-lg border border-stone-300 px-3 text-sm font-semibold hover:bg-stone-50">
                    <x-icon name="plus" class="size-4" /> Thêm món
                </button>
            </div>
            @error('items') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

            <div data-repeater-list class="mt-4 flex flex-col gap-3">
                @foreach ($items as $index => $item)
                    @include('bundles.partials.item-row', ['index' => $index, 'item' => $item])
                @endforeach
            </div>

            <template data-repeater-template>
                @include('bundles.partials.item-row', ['index' => '__INDEX__', 'item' => ['product_id' => null, 'quantity' => 1]])
            </template>
        </section>
    </div>

    <aside class="flex flex-col gap-4">
        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="font-bold">Giá</h2>
            <div class="mt-4 flex flex-col gap-2">
                <label for="bundle_price" class="text-sm font-semibold">Giá combo</label>
                <div class="relative">
                    <input id="bundle_price" name="bundle_price" type="number" min="0" step="1000" value="{{ old('bundle_price', $bundle->bundle_price !== null ? (int) $bundle->bundle_price : '') }}" required class="pr-10 font-mono {{ $fieldClasses('bundle_price') }}">
                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-500">đ</span>
                </div>
                @error('bundle_price') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <dl class="mt-4 space-y-1.5 border-t border-stone-100 pt-4 text-sm">
                <div class="flex justify-between"><dt class="text-stone-600">Mua lẻ</dt><dd class="font-mono" data-individual-total>—</dd></div>
                <div class="flex justify-between font-semibold text-emerald-700"><dt>Khách tiết kiệm</dt><dd class="font-mono" data-saving>—</dd></div>
            </dl>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="font-bold">Ảnh</h2>
            @if ($bundle->imageUrl())
                <img src="{{ $bundle->imageUrl() }}" alt="" class="mt-4 aspect-square w-full rounded-lg object-cover">
                <label class="mt-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remove_image" value="1" class="size-4 accent-latte"> Xóa ảnh hiện tại
                </label>
            @endif
            <input name="image" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Ảnh combo" class="mt-4 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-stone-200">
            @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <section class="flex flex-col gap-3 rounded-xl border border-stone-200 bg-white p-5">
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bundle->is_active)) class="mt-0.5 size-4.5 accent-latte">
                <span class="text-sm"><b class="block">Đang bán</b><span class="text-stone-600">Tắt để tạm ngừng combo.</span></span>
            </label>
            <div class="flex flex-col gap-1.5">
                <label for="display_order" class="text-sm font-semibold">Thứ tự hiển thị</label>
                <input id="display_order" name="display_order" type="number" min="0" max="999" value="{{ old('display_order', $bundle->display_order) }}" class="font-mono {{ $fieldClasses('display_order') }}">
            </div>
        </section>
    </aside>
</div>
