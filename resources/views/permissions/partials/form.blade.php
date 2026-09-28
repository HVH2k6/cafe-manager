{{-- Shared fields of the create / edit permission forms. Expects $permission and $modules. --}}
@php
    $fieldClasses = fn (string $field): string => \Illuminate\Support\Arr::toCssClasses([
        'w-full rounded-lg border bg-white px-4 text-ink placeholder:text-stone-400 focus:ring-3 focus:outline-none',
        'border-stone-300 focus:border-latte focus:ring-latte/15' => ! $errors->has($field),
        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has($field),
    ]);
@endphp

<div class="flex flex-col gap-5">
    <div class="flex flex-col gap-2">
        <label for="name" class="text-sm font-semibold">Tên quyền</label>
        <input id="name" name="name" type="text" value="{{ old('name', $permission->name) }}" placeholder="Xem đơn hàng" required maxlength="100" class="h-11 {{ $fieldClasses('name') }}">
        @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
            <label for="module" class="text-sm font-semibold">Module</label>
            <input id="module" name="module" type="text" list="module-options" value="{{ old('module', $permission->module) }}" placeholder="orders" required maxlength="50" autocomplete="off" class="h-11 font-mono text-sm {{ $fieldClasses('module') }}">
            <datalist id="module-options">
                @foreach ($modules as $module => $label)
                    <option value="{{ $module }}">{{ $label }}</option>
                @endforeach
            </datalist>
            @error('module') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="action" class="text-sm font-semibold">Hành động</label>
            <input id="action" name="action" type="text" list="action-options" value="{{ old('action', $permission->action) }}" placeholder="view" required maxlength="50" autocomplete="off" class="h-11 font-mono text-sm {{ $fieldClasses('action') }}">
            <datalist id="action-options">
                <option value="view">Xem</option>
                <option value="create">Thêm</option>
                <option value="update">Sửa</option>
                <option value="delete">Xóa</option>
                <option value="cancel">Hủy</option>
                <option value="export">Xuất file</option>
            </datalist>
            @error('action') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <p class="-mt-2 text-sm text-stone-600">
        Key kiểm tra quyền là <span class="font-mono">module.hành_động</span>, ví dụ <span class="font-mono">@@can('orders.view')</span>.
        Chỉ dùng chữ thường không dấu, số và dấu <span class="font-mono">_</span>.
    </p>

    <div class="flex flex-col gap-2">
        <label for="description" class="text-sm font-semibold">Mô tả <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
        <textarea id="description" name="description" rows="3" maxlength="1000" class="py-3 {{ $fieldClasses('description') }}">{{ old('description', $permission->description) }}</textarea>
        @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <label for="is_active" class="flex cursor-pointer items-start gap-3 rounded-lg border border-stone-200 p-4">
        <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $permission->is_active)) class="mt-0.5 size-4.5 accent-latte">
        <span>
            <span class="block text-sm font-semibold">Đang bật</span>
            <span class="block text-sm text-stone-600">Tắt để tạm thu hồi quyền này khỏi mọi vai trò mà không mất phần gán quyền.</span>
        </span>
    </label>
</div>
