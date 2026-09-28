{{-- Shared fields of the create / edit product forms. Expects $product and $categories. --}}
@php
    $fieldClasses = fn (string $field): string => \Illuminate\Support\Arr::toCssClasses([
        'h-11 w-full rounded-lg border bg-white px-4 text-ink placeholder:text-stone-400 focus:ring-3 focus:outline-none',
        'border-stone-300 focus:border-latte focus:ring-latte/15' => ! $errors->has($field),
        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has($field),
    ]);
    $money = fn ($value): string => $value === null || $value === '' ? '' : (string) (int) $value;
    $variants = old('variants', $product->relationLoaded('variants')
        ? $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'name' => $variant->name,
            'value' => $variant->value,
            'price_modifier' => (int) $variant->price_modifier,
            'is_default' => $variant->is_default,
        ])->all()
        : []);
@endphp

<div class="grid items-start gap-4 lg:grid-cols-[1fr_320px]">
    <div class="flex flex-col gap-4">
        {{-- General --}}
        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-bold">Thông tin chung</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-2 sm:col-span-2">
                    <label for="name" class="text-sm font-semibold">Tên sản phẩm</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $product->name) }}" placeholder="Cà phê sữa đá" required maxlength="255" class="{{ $fieldClasses('name') }}">
                    @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label for="category_id" class="text-sm font-semibold">Danh mục</label>
                    <select id="category_id" name="category_id" required class="{{ $fieldClasses('category_id') }}">
                        <option value="" disabled @selected(! old('category_id', $product->category_id))>Chọn danh mục</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (đang ẩn)' }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label for="sku" class="text-sm font-semibold">Mã SKU <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
                    <input id="sku" name="sku" type="text" value="{{ old('sku', $product->sku) }}" placeholder="CF-SUA-DA" maxlength="50" class="font-mono uppercase {{ $fieldClasses('sku') }}">
                    @error('sku') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2 sm:col-span-2">
                    <label for="description" class="text-sm font-semibold">Mô tả <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
                    <textarea id="description" name="description" rows="3" maxlength="2000" class="h-auto py-3 {{ $fieldClasses('description') }}">{{ old('description', $product->description) }}</textarea>
                </div>
            </div>
        </section>

        {{-- Prices --}}
        <section class="rounded-xl border border-stone-200 bg-white p-6">
            <h2 class="text-lg font-bold">Giá</h2>
            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                @foreach ([
                    ['field' => 'selling_price', 'label' => 'Giá bán', 'hint' => 'Giá khách trả.', 'required' => true],
                    ['field' => 'base_price', 'label' => 'Giá niêm yết', 'hint' => 'Để trống = bằng giá bán. Cao hơn giá bán thì hiện gạch ngang như đang giảm giá.', 'required' => false],
                    ['field' => 'cost_price', 'label' => 'Giá vốn', 'hint' => 'Chi phí nguyên liệu, dùng để tính lãi.', 'required' => false],
                ] as $price)
                    <div class="flex flex-col gap-2">
                        <label for="{{ $price['field'] }}" class="text-sm font-semibold">{{ $price['label'] }}</label>
                        <div class="relative">
                            <input id="{{ $price['field'] }}" name="{{ $price['field'] }}" type="number" min="0" step="1000" value="{{ old($price['field'], $money($product->{$price['field']})) }}" @required($price['required']) class="pr-10 font-mono {{ $fieldClasses($price['field']) }}">
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-500">đ</span>
                        </div>
                        <p class="text-xs text-stone-500">{{ $price['hint'] }}</p>
                        @error($price['field']) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Variants --}}
        <section class="rounded-xl border border-stone-200 bg-white p-6" data-repeater>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold">Biến thể</h2>
                    <p class="mt-1 text-sm text-stone-600">Ví dụ nhóm <b>Size</b>: Nhỏ (+0đ), Vừa (+5.000đ), Lớn (+10.000đ); nhóm <b>Đường</b>: Ít, Vừa, Nhiều.</p>
                </div>
                <button type="button" data-repeater-add class="flex h-9 items-center gap-1.5 rounded-lg border border-stone-300 px-3 text-sm font-semibold hover:bg-stone-50">
                    <x-icon name="plus" class="size-4" /> Thêm lựa chọn
                </button>
            </div>
            @error('variants') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-4 hidden grid-cols-[1fr_1fr_140px_80px_36px] gap-3 px-1 text-xs font-semibold text-stone-600 sm:grid" aria-hidden="true">
                <span>Nhóm</span><span>Lựa chọn</span><span>Cộng thêm</span><span class="text-center">Mặc định</span><span></span>
            </div>

            <div data-repeater-list class="mt-2 flex flex-col gap-3">
                @foreach ($variants as $index => $variant)
                    @include('products.partials.variant-row', ['index' => $index, 'variant' => $variant])
                @endforeach
            </div>

            <template data-repeater-template>
                @include('products.partials.variant-row', ['index' => '__INDEX__', 'variant' => []])
            </template>

            <datalist id="variant-groups">
                @foreach (['Size', 'Đường', 'Đá', 'Topping'] as $group)
                    <option value="{{ $group }}"></option>
                @endforeach
            </datalist>
        </section>
    </div>

    <aside class="flex flex-col gap-4">
        {{-- Image --}}
        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="font-bold">Ảnh</h2>
            @if ($product->imageUrl())
                <img src="{{ $product->imageUrl() }}" alt="" class="mt-4 aspect-square w-full rounded-lg object-cover">
                <label class="mt-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remove_image" value="1" class="size-4 accent-latte"> Xóa ảnh hiện tại
                </label>
            @endif
            <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-4 block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:text-sm file:font-semibold hover:file:bg-stone-200">
            <p class="mt-2 text-xs text-stone-500">JPG, PNG hoặc WEBP, tối đa 2 MB.</p>
            @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        {{-- Selling --}}
        <section class="flex flex-col gap-3 rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="font-bold">Bán hàng</h2>
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="is_available" value="1" @checked(old('is_available', $product->is_available)) class="mt-0.5 size-4.5 accent-latte">
                <span class="text-sm"><b class="block">Đang bán</b><span class="text-stone-600">Bỏ chọn để tạm ngừng bán (hết nguyên liệu, ngoài mùa…).</span></span>
            </label>
            <label class="flex cursor-pointer items-start gap-3">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured)) class="mt-0.5 size-4.5 accent-latte">
                <span class="text-sm"><b class="block">Món nổi bật</b><span class="text-stone-600">Ưu tiên hiển thị trên thực đơn.</span></span>
            </label>

            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col gap-1.5">
                    <label for="prep_time_minutes" class="text-sm font-semibold">Pha chế (phút)</label>
                    <input id="prep_time_minutes" name="prep_time_minutes" type="number" min="0" max="240" value="{{ old('prep_time_minutes', $product->prep_time_minutes) }}" class="font-mono {{ $fieldClasses('prep_time_minutes') }}">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="display_order" class="text-sm font-semibold">Thứ tự</label>
                    <input id="display_order" name="display_order" type="number" min="0" max="999" value="{{ old('display_order', $product->display_order) }}" class="font-mono {{ $fieldClasses('display_order') }}">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="calories" class="text-sm font-semibold">Calo</label>
                    <input id="calories" name="calories" type="number" min="0" max="5000" value="{{ old('calories', $product->calories) }}" class="font-mono {{ $fieldClasses('calories') }}">
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="barcode" class="text-sm font-semibold">Mã vạch</label>
                    <input id="barcode" name="barcode" type="text" maxlength="100" value="{{ old('barcode', $product->barcode) }}" class="font-mono {{ $fieldClasses('barcode') }}">
                </div>
            </div>
            @error('prep_time_minutes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @error('calories') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        {{-- Stock --}}
        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="font-bold">Tồn kho</h2>
            @if ($product->exists)
                @if ($product->inventory)
                    <p class="mt-2 text-sm text-stone-600">Đang theo dõi: còn <b class="text-ink">{{ $product->inventory->quantity_on_hand }} {{ $product->inventory->unitLabel() }}</b>.</p>
                @else
                    <p class="mt-2 text-sm text-stone-600">Không theo dõi tồn kho.</p>
                @endif
                @can('inventory.view')
                    <a href="{{ route('inventory.show', $product) }}" class="mt-3 inline-block text-sm font-semibold text-latte-dark hover:underline">Mở trang tồn kho →</a>
                @endcan
            @else
                <label class="mt-3 flex cursor-pointer items-start gap-3">
                    <input type="checkbox" name="track_stock" value="1" @checked(old('track_stock')) class="mt-0.5 size-4.5 accent-latte">
                    <span class="text-sm"><b class="block">Theo dõi tồn kho</b><span class="text-stone-600">Dành cho hàng đếm được (bánh, nước đóng chai…). Món pha chế tại chỗ thường không cần.</span></span>
                </label>
                <div class="mt-3 flex flex-col gap-1.5">
                    <label for="unit_of_measure" class="text-sm font-semibold">Đơn vị tính</label>
                    <input id="unit_of_measure" name="unit_of_measure" type="text" list="unit-options" value="{{ old('unit_of_measure', 'cái') }}" maxlength="20" class="{{ $fieldClasses('unit_of_measure') }}">
                    <datalist id="unit-options">
                        @foreach (array_unique(\App\Models\Inventory::UNIT_LABELS) as $unit)
                            <option value="{{ $unit }}"></option>
                        @endforeach
                    </datalist>
                </div>
            @endif
        </section>
    </aside>
</div>
