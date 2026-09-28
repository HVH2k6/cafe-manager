{{-- One variant row. Expects $index (int or "__INDEX__" in the JS template) and $variant (array). --}}
@php $prefix = "variants.{$index}"; @endphp

<div data-repeater-row class="grid grid-cols-2 gap-3 rounded-lg border border-stone-200 p-3 sm:grid-cols-[1fr_1fr_140px_80px_36px] sm:items-center sm:border-0 sm:p-0">
    @if (! empty($variant['id']))
        <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant['id'] }}">
    @endif
    <input name="variants[{{ $index }}][name]" type="text" list="variant-groups" value="{{ $variant['name'] ?? '' }}" placeholder="Size" maxlength="100" aria-label="Nhóm biến thể" class="h-10 w-full rounded-lg border border-stone-300 px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
    <input name="variants[{{ $index }}][value]" type="text" value="{{ $variant['value'] ?? '' }}" placeholder="Lớn" maxlength="100" aria-label="Lựa chọn" class="h-10 w-full rounded-lg border border-stone-300 px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
    <div class="relative">
        <input name="variants[{{ $index }}][price_modifier]" type="number" step="1000" value="{{ $variant['price_modifier'] ?? 0 }}" aria-label="Giá cộng thêm" class="h-10 w-full rounded-lg border border-stone-300 pr-8 pl-3 font-mono text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-stone-500">đ</span>
    </div>
    <label class="flex items-center gap-2 text-sm sm:justify-center">
        <input name="variants[{{ $index }}][is_default]" type="checkbox" value="1" @checked(! empty($variant['is_default'])) class="size-4.5 accent-latte">
        <span class="sm:sr-only">Mặc định</span>
    </label>
    <button type="button" data-repeater-remove class="flex size-9 items-center justify-center justify-self-end rounded-lg text-stone-500 hover:bg-red-50 hover:text-red-600" aria-label="Xóa lựa chọn">
        <x-icon name="trash" class="size-4" />
    </button>

    @if (is_int($index))
        @foreach (['name', 'value', 'price_modifier'] as $field)
            @error("{$prefix}.{$field}") <p class="col-span-full text-sm text-red-600">{{ $message }}</p> @enderror
        @endforeach
    @endif
</div>
