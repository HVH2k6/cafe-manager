{{-- Shared fields of the create / edit category forms. Expects $category. --}}
@php
    $fieldClasses = fn (string $field): string => \Illuminate\Support\Arr::toCssClasses([
        'w-full rounded-lg border bg-white px-4 text-ink placeholder:text-stone-400 focus:ring-3 focus:outline-none',
        'border-stone-300 focus:border-latte focus:ring-latte/15' => ! $errors->has($field),
        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has($field),
    ]);
@endphp

<div class="flex flex-col gap-5">
    <div class="grid gap-5 sm:grid-cols-[1fr_120px]">
        <div class="flex flex-col gap-2">
            <label for="name" class="text-sm font-semibold">Tên danh mục</label>
            <input id="name" name="name" type="text" value="{{ old('name', $category->name) }}" placeholder="Cà phê" required maxlength="100" class="h-11 {{ $fieldClasses('name') }}">
            @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="flex flex-col gap-2">
            <label for="icon" class="text-sm font-semibold">Biểu tượng</label>
            <input id="icon" name="icon" type="text" value="{{ old('icon', $category->icon) }}" placeholder="☕" maxlength="100" class="h-11 text-center {{ $fieldClasses('icon') }}">
        </div>
    </div>

    <div class="flex flex-col gap-2">
        <label for="description" class="text-sm font-semibold">Mô tả <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
        <textarea id="description" name="description" rows="2" maxlength="1000" class="py-3 {{ $fieldClasses('description') }}">{{ old('description', $category->description) }}</textarea>
    </div>

    <div class="flex flex-col gap-2 sm:w-40">
        <label for="display_order" class="text-sm font-semibold">Thứ tự hiển thị</label>
        <input id="display_order" name="display_order" type="number" min="0" max="999" value="{{ old('display_order', $category->display_order) }}" class="h-11 font-mono {{ $fieldClasses('display_order') }}">
        @error('display_order') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <label for="is_active" class="flex cursor-pointer items-start gap-3 rounded-lg border border-stone-200 p-4">
        <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $category->is_active)) class="mt-0.5 size-4.5 accent-latte">
        <span>
            <span class="block text-sm font-semibold">Hiện trên thực đơn</span>
            <span class="block text-sm text-stone-600">Ẩn danh mục sẽ ẩn cả nhóm khỏi thực đơn mà không xóa sản phẩm.</span>
        </span>
    </label>
</div>
