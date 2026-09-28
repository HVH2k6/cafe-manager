<x-layouts.app title="Nhân viên">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Nhân viên</h1>
            <p class="mt-1 text-stone-600">Tài khoản đăng nhập và hồ sơ của nhân viên trong quán.</p>
        </div>

        @can('employees.create')
            <a href="{{ route('employees.create') }}" class="flex h-11 items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark">
                <x-icon name="plus" class="size-4.5" />
                Thêm nhân viên
            </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('employees.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-xl border border-stone-200 bg-white p-4" role="search">
        <div class="flex min-w-60 flex-1 flex-col gap-1.5">
            <label for="search" class="text-xs font-semibold text-stone-600">Tìm kiếm</label>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4.5 -translate-y-1/2 text-stone-400" />
                <input id="search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="Tên, email hoặc số điện thoại" class="h-10 w-full rounded-lg border border-stone-300 bg-white pr-3 pl-10 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="role" class="text-xs font-semibold text-stone-600">Vai trò</label>
            <select id="role" name="role" class="h-10 rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                <option value="">Tất cả vai trò</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($filters['role'] === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="status" class="text-xs font-semibold text-stone-600">Trạng thái</label>
            <select id="status" name="status" class="h-10 rounded-lg border border-stone-300 bg-white px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                <option value="active" @selected($filters['status'] === 'active')>Đang làm</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Đã nghỉ</option>
                <option value="all" @selected($filters['status'] === 'all')>Tất cả</option>
            </select>
        </div>

        <button type="submit" class="h-10 rounded-lg bg-espresso px-4 text-sm font-semibold text-white hover:bg-espresso-line">Lọc</button>
        @if ($filters['search'] !== '' || $filters['role'] || $filters['status'] !== 'active')
            <a href="{{ route('employees.index') }}" class="flex h-10 items-center px-2 text-sm font-medium text-stone-600 hover:text-ink">Xóa bộ lọc</a>
        @endif
    </form>

    @if ($employees->isEmpty())
        <div class="mt-4 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark">
                <x-icon name="users" class="size-6" />
            </span>
            <p class="mt-4 font-semibold">Không tìm thấy nhân viên nào</p>
            <p class="mt-1 text-sm text-stone-600">Thử đổi từ khóa hoặc bộ lọc.</p>
        </div>
    @else
        <section class="mt-4 overflow-hidden rounded-xl border border-stone-200 bg-white">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm whitespace-nowrap">
                    <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                        <tr>
                            <th scope="col" class="px-6 py-3">Nhân viên</th>
                            <th scope="col" class="px-3 py-3">Điện thoại</th>
                            <th scope="col" class="px-3 py-3">Vai trò</th>
                            <th scope="col" class="px-3 py-3">Vị trí</th>
                            <th scope="col" class="px-3 py-3">Ngày vào làm</th>
                            <th scope="col" class="px-3 py-3">Trạng thái</th>
                            <th scope="col" class="px-3 py-3">Đăng nhập gần nhất</th>
                            <th scope="col" class="px-6 py-3"><span class="sr-only">Thao tác</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200">
                        @foreach ($employees as $employee)
                            <tr @class(['hover:bg-stone-50', 'text-stone-500' => ! $employee->is_active])>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <span @class([
                                            'flex size-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white',
                                            'bg-latte' => $employee->is_active,
                                            'bg-stone-400' => ! $employee->is_active,
                                        ])>{{ $employee->initials() }}</span>
                                        <div class="leading-tight">
                                            <p class="font-semibold text-ink">
                                                {{ $employee->name }}
                                                @if ($employee->is(auth()->user()))
                                                    <span class="ml-1 text-xs font-medium text-stone-500">(bạn)</span>
                                                @endif
                                            </p>
                                            <p class="text-stone-600">{{ $employee->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 font-mono">{{ $employee->phone ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <span class="rounded-md bg-stone-100 px-2 py-1 text-xs font-semibold text-stone-700">{{ $employee->role?->name }}</span>
                                </td>
                                <td class="px-3 py-3">{{ $employee->employee?->position ?? '—' }}</td>
                                <td class="px-3 py-3 font-mono">{{ $employee->employee?->hire_date?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    @if ($employee->is_active)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>Đang làm</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-600"><span class="size-1.5 rounded-full bg-stone-400"></span>Đã nghỉ</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-stone-600">{{ $employee->last_login_at?->diffForHumans() ?? 'Chưa đăng nhập' }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex justify-end gap-1">
                                        @can('manage-employee', $employee)
                                            @can('employees.update')
                                                <a href="{{ route('employees.edit', $employee) }}" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 hover:text-ink" title="Sửa" aria-label="Sửa nhân viên {{ $employee->name }}">
                                                    <x-icon name="pencil" class="size-4" />
                                                </a>
                                            @endcan
                                            @if (auth()->user()->can('employees.delete') && ! $employee->is(auth()->user()))
                                                <form
                                                    method="POST"
                                                    action="{{ route('employees.status.update', $employee) }}"
                                                    @if ($employee->is_active) data-confirm="Cho {{ $employee->name }} nghỉ việc? Tài khoản sẽ bị khóa và đăng xuất khỏi mọi thiết bị." @endif
                                                >
                                                    @csrf
                                                    @method('PUT')
                                                    @if ($employee->is_active)
                                                        <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-red-50 hover:text-red-600" title="Cho nghỉ việc" aria-label="Cho {{ $employee->name }} nghỉ việc">
                                                            <x-icon name="user-x" class="size-4" />
                                                        </button>
                                                    @else
                                                        <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-emerald-50 hover:text-emerald-700" title="Nhận lại" aria-label="Nhận lại {{ $employee->name }}">
                                                            <x-icon name="user-check" class="size-4" />
                                                        </button>
                                                    @endif
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-4">
            {{ $employees->links() }}
        </div>
    @endif
</x-layouts.app>
