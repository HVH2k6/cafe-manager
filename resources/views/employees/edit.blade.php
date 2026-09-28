<x-layouts.app title="Sửa nhân viên">
    <a href="{{ route('employees.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
        <x-icon name="arrow-left" class="size-4" /> Nhân viên
    </a>
    <div class="mt-3 flex items-center gap-4">
        <span @class([
            'flex size-14 items-center justify-center rounded-full text-lg font-bold text-white',
            'bg-latte' => $employee->is_active,
            'bg-stone-400' => ! $employee->is_active,
        ])>{{ $employee->initials() }}</span>
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">{{ $employee->name }}</h1>
            <p class="text-stone-600">{{ $employee->role?->name }} · {{ $employee->is_active ? 'Đang làm' : 'Đã nghỉ' }}</p>
        </div>
    </div>

    @if (! $employee->employee)
        <p class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Tài khoản này chưa có hồ sơ nhân viên. Điền phần "Hồ sơ nhân viên" rồi lưu để tạo.
        </p>
    @endif

    <div class="mt-6 grid items-start gap-4 lg:grid-cols-[1fr_320px]">
        <form method="POST" action="{{ route('employees.update', $employee) }}">
            @csrf
            @method('PUT')

            @include('employees.partials.form')

            <div class="mt-4 flex justify-end gap-3">
                <a href="{{ route('employees.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
                <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Lưu thay đổi</button>
            </div>
        </form>

        <aside class="flex flex-col gap-4">
            <form method="POST" action="{{ route('employees.password.update', $employee) }}" class="rounded-xl border border-stone-200 bg-white p-5">
                @csrf
                @method('PUT')

                <h2 class="flex items-center gap-2 font-bold"><x-icon name="key" class="size-4.5" /> Đặt lại mật khẩu</h2>
                <p class="mt-1 text-sm text-stone-600">
                    @if ($employee->is(auth()->user()))
                        Đổi mật khẩu của chính bạn.
                    @else
                        Nhân viên sẽ bị đăng xuất khỏi mọi thiết bị.
                    @endif
                </p>

                <div class="mt-4 flex flex-col gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label for="new_password" class="text-sm font-semibold">Mật khẩu mới</label>
                        <input id="new_password" name="password" type="password" required autocomplete="new-password" class="h-10 w-full rounded-lg border border-stone-300 px-3 focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="new_password_confirmation" class="text-sm font-semibold">Nhập lại</label>
                        <input id="new_password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="h-10 w-full rounded-lg border border-stone-300 px-3 focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                    </div>
                    @error('password', 'password') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button type="submit" class="h-10 rounded-lg border border-stone-300 text-sm font-semibold hover:bg-stone-50">Đặt lại mật khẩu</button>
                </div>
            </form>

            @if (auth()->user()->can('employees.delete') && ! $employee->is(auth()->user()))
                <form
                    method="POST"
                    action="{{ route('employees.status.update', $employee) }}"
                    class="rounded-xl border border-stone-200 bg-white p-5"
                    @if ($employee->is_active) data-confirm="Cho {{ $employee->name }} nghỉ việc? Tài khoản sẽ bị khóa và đăng xuất khỏi mọi thiết bị." @endif
                >
                    @csrf
                    @method('PUT')

                    @if ($employee->is_active)
                        <h2 class="font-bold">Cho nghỉ việc</h2>
                        <p class="mt-1 text-sm text-stone-600">Khóa tài khoản và đăng xuất ngay. Lịch sử đơn hàng vẫn được giữ; có thể nhận lại sau.</p>
                        <button type="submit" class="mt-4 flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-red-200 text-sm font-semibold text-red-700 hover:bg-red-50">
                            <x-icon name="user-x" class="size-4" /> Cho nghỉ việc
                        </button>
                    @else
                        <h2 class="font-bold">Nhận lại nhân viên</h2>
                        <p class="mt-1 text-sm text-stone-600">Mở lại tài khoản để nhân viên đăng nhập được.</p>
                        <button type="submit" class="mt-4 flex h-10 w-full items-center justify-center gap-2 rounded-lg border border-emerald-200 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                            <x-icon name="user-check" class="size-4" /> Nhận lại
                        </button>
                    @endif
                </form>
            @endif
        </aside>
    </div>
</x-layouts.app>
