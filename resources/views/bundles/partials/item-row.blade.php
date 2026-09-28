{{-- One combo item row. Expects $index (int or "__INDEX__"), $item (array) and $products. --}}
<div data-repeater-row class="grid grid-cols-[1fr_90px_36px] items-center gap-3">
    <select name="items[{{ $index }}][product_id]" aria-label="Sản phẩm" class="h-10 w-full rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
        <option value="" data-price="0">Chọn sản phẩm</option>
        @foreach ($products as $product)
            <option value="{{ $product->id }}" data-price="{{ (int) $product->selling_price }}" @selected((int) ($item['product_id'] ?? 0) === $product->id)>
                {{ $product->name }} — {{ number_format($product->selling_price, 0, ',', '.') }} đ{{ $product->is_available ? '' : ' (ngừng bán)' }}
            </option>
        @endforeach
    </select>
    <input name="items[{{ $index }}][quantity]" type="number" min="1" max="20" value="{{ $item['quantity'] ?? 1 }}" aria-label="Số lượng" class="h-10 w-full rounded-lg border border-stone-300 px-3 font-mono text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
    <button type="button" data-repeater-remove class="flex size-9 items-center justify-center rounded-lg text-stone-500 hover:bg-red-50 hover:text-red-600" aria-label="Bỏ món">
        <x-icon name="trash" class="size-4" />
    </button>

    @if (is_int($index))
        @foreach (['product_id', 'quantity'] as $field)
            @error("items.{$index}.{$field}") <p class="col-span-full text-sm text-red-600">{{ $message }}</p> @enderror
        @endforeach
    @endif
</div>
