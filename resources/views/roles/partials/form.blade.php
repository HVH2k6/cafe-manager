{{-- Shared fields of the create / edit role forms. Expects $role. --}}
@php
    $isOwnerRole = $role->exists && $role->name === \App\Models\Role::OWNER;
    $fieldClasses = fn (string $field): string => \Illuminate\Support\Arr::toCssClasses([
        'w-full rounded-lg border bg-white px-4 text-ink placeholder:text-stone-400 focus:ring-3 focus:outline-none disabled:bg-stone-100 disabled:text-stone-500',
        'border-stone-300 focus:border-latte focus:ring-latte/15' => ! $errors->has($field),
        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has($field),
    ]);
@endphp

<div class="flex flex-col gap-5">
    @if ($isOwnerRole)
        <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Vai trò {{ \App\Models\Role::OWNER }} luôn có toàn quyền, không thể đổi tên, khóa hay xóa.
        </p>
    @endif

    <div class="flex flex-col gap-2">
        <label for="name" class="text-sm font-semibold">Tên vai trò</label>
        @if ($isOwnerRole)
            <input type="hidden" name="name" value="{{ $role->name }}">
        @endif
        <input id="name" name="name" type="text" value="{{ old('name', $role->name) }}" placeholder="Thu ngân" required maxlength="100" @disabled($isOwnerRole) class="h-11 {{ $fieldClasses('name') }}">
        @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-2">
        <label for="description" class="text-sm font-semibold">Mô tả <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
        <textarea id="description" name="description" rows="2" maxlength="1000" class="py-3 {{ $fieldClasses('description') }}">{{ old('description', $role->description) }}</textarea>
        @error('description') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-col gap-2 sm:w-40">
        <label for="display_order" class="text-sm font-semibold">Thứ tự hiển thị</label>
        <input id="display_order" name="display_order" type="number" min="0" max="999" value="{{ old('display_order', $role->display_order) }}" class="h-11 font-mono {{ $fieldClasses('display_order') }}">
        @error('display_order') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    </div>

    @if ($isOwnerRole)
        <input type="hidden" name="is_active" value="1">
    @else
        <label for="is_active" class="flex cursor-pointer items-start gap-3 rounded-lg border border-stone-200 p-4">
            <input id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $role->is_active)) class="mt-0.5 size-4.5 accent-latte">
            <span>
                <span class="block text-sm font-semibold">Đang dùng</span>
                <span class="block text-sm text-stone-600">Khóa để thu hồi mọi quyền của vai trò này mà vẫn giữ nhân viên và phần gán quyền.</span>
            </span>
        </label>
    @endif
    @error('is_active') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
</div>
