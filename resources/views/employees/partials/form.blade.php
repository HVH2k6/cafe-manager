{{-- Shared fields of the create / edit employee forms. Expects $employee (User), $roles, $contractTypes. --}}
@php
    $profile = $employee->employee;
    $fieldClasses = fn (string $field): string => \Illuminate\Support\Arr::toCssClasses([
        'h-11 w-full rounded-lg border bg-white px-4 text-ink placeholder:text-stone-400 focus:ring-3 focus:outline-none',
        'border-stone-300 focus:border-latte focus:ring-latte/15' => ! $errors->has($field),
        'border-red-400 focus:border-red-500 focus:ring-red-500/15' => $errors->has($field),
    ]);
@endphp

<section class="rounded-xl border border-stone-200 bg-white p-6">
    <h2 class="text-lg font-bold">Tài khoản đăng nhập</h2>

    <div class="mt-5 grid gap-5 sm:grid-cols-2">
        <div class="flex flex-col gap-2 sm:col-span-2">
            <label for="name" class="text-sm font-semibold">Họ tên</label>
            <input id="name" name="name" type="text" value="{{ old('name', $employee->name) }}" placeholder="Nguyễn Văn An" required maxlength="255" autocomplete="off" class="{{ $fieldClasses('name') }}">
            @error('name') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="email" class="text-sm font-semibold">Email đăng nhập</label>
            <input id="email" name="email" type="email" value="{{ old('email', $employee->email) }}" placeholder="an@cafeabc.vn" required maxlength="255" autocomplete="off" class="{{ $fieldClasses('email') }}">
            @error('email') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="phone" class="text-sm font-semibold">Số điện thoại <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone', $employee->phone) }}" placeholder="0901 234 567" maxlength="20" class="font-mono {{ $fieldClasses('phone') }}">
            @error('phone') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2 sm:col-span-2">
            <label for="role_id" class="text-sm font-semibold">Vai trò</label>
            <select id="role_id" name="role_id" required class="{{ $fieldClasses('role_id') }}">
                <option value="" disabled @selected(! old('role_id', $employee->role_id))>Chọn vai trò</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((int) old('role_id', $employee->role_id) === $role->id)>
                        {{ $role->name }}{{ $role->is_active ? '' : ' (đã khóa)' }}
                    </option>
                @endforeach
            </select>
            <p class="text-sm text-stone-600">Quyền của nhân viên lấy theo vai trò, chỉnh ở trang Phân quyền.</p>
            @error('role_id') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        @unless ($employee->exists)
            <div class="flex flex-col gap-2">
                <label for="password" class="text-sm font-semibold">Mật khẩu</label>
                <input id="password" name="password" type="password" required autocomplete="new-password" class="{{ $fieldClasses('password') }}">
                @error('password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="text-sm font-semibold">Nhập lại mật khẩu</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $fieldClasses('password') }}">
            </div>
            <p class="-mt-2 text-sm text-stone-600 sm:col-span-2">Ít nhất 8 ký tự. Gửi mật khẩu cho nhân viên để họ đăng nhập lần đầu.</p>
        @endunless
    </div>
</section>

<section class="mt-4 rounded-xl border border-stone-200 bg-white p-6">
    <h2 class="text-lg font-bold">Hồ sơ nhân viên</h2>

    <div class="mt-5 grid gap-5 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
            <label for="position" class="text-sm font-semibold">Vị trí công việc</label>
            <input id="position" name="position" type="text" list="position-options" value="{{ old('position', $profile?->position) }}" placeholder="Pha chế" required maxlength="100" class="{{ $fieldClasses('position') }}">
            <datalist id="position-options">
                @foreach (['Quản lý ca', 'Thu ngân', 'Pha chế', 'Phục vụ', 'Tạp vụ'] as $position)
                    <option value="{{ $position }}"></option>
                @endforeach
            </datalist>
            @error('position') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="department" class="text-sm font-semibold">Bộ phận <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
            <input id="department" name="department" type="text" list="department-options" value="{{ old('department', $profile?->department) }}" placeholder="Quầy bar" maxlength="100" class="{{ $fieldClasses('department') }}">
            <datalist id="department-options">
                @foreach (['Quầy bar', 'Sảnh', 'Thu ngân', 'Bếp'] as $department)
                    <option value="{{ $department }}"></option>
                @endforeach
            </datalist>
            @error('department') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="contract_type" class="text-sm font-semibold">Loại hợp đồng</label>
            <select id="contract_type" name="contract_type" required class="{{ $fieldClasses('contract_type') }}">
                @foreach ($contractTypes as $contractType)
                    <option value="{{ $contractType->value }}" @selected(old('contract_type', $profile?->contract_type?->value ?? 'full_time') === $contractType->value)>{{ $contractType->label() }}</option>
                @endforeach
            </select>
            @error('contract_type') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="hire_date" class="text-sm font-semibold">Ngày vào làm</label>
            <input id="hire_date" name="hire_date" type="date" value="{{ old('hire_date', $profile?->hire_date?->format('Y-m-d') ?? now()->toDateString()) }}" required class="{{ $fieldClasses('hire_date') }}">
            @error('hire_date') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="salary" class="text-sm font-semibold">Lương tháng</label>
            <div class="relative">
                <input id="salary" name="salary" type="number" min="0" step="1000" value="{{ old('salary', $profile ? (int) $profile->salary : '') }}" placeholder="6000000" required class="pr-16 font-mono {{ $fieldClasses('salary') }}">
                <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-sm text-stone-500">VNĐ</span>
            </div>
            @error('salary') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col gap-2 sm:col-span-2">
            <label for="notes" class="text-sm font-semibold">Ghi chú <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000" class="h-auto py-3 {{ $fieldClasses('notes') }}">{{ old('notes', $profile?->notes) }}</textarea>
            @error('notes') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </div>
</section>
