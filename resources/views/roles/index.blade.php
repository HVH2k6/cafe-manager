<x-layouts.app title="Vai trò">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Phân quyền</h1>
            <p class="mt-1 text-stone-600">Các vai trò trong quán. Thứ tự ở đây cũng là thứ tự cột trong ma trận quyền.</p>
        </div>

        <a href="{{ route('roles.create') }}" class="flex h-11 items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark">
            <x-icon name="plus" class="size-4.5" />
            Thêm vai trò
        </a>
    </div>

    @include('permissions.partials.tabs')

    <section class="mt-6 overflow-hidden rounded-xl border border-stone-200 bg-white">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-left text-sm">
                <thead class="bg-stone-100/70 text-xs font-semibold tracking-wide text-stone-600 uppercase">
                    <tr>
                        <th scope="col" class="px-6 py-3">Vai trò</th>
                        <th scope="col" class="px-3 py-3 text-right">Nhân viên</th>
                        <th scope="col" class="px-3 py-3 text-right">Quyền</th>
                        <th scope="col" class="px-3 py-3">Trạng thái</th>
                        <th scope="col" class="px-6 py-3"><span class="sr-only">Thao tác</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200">
                    @foreach ($roles as $role)
                        @php $isOwnerRole = $role->name === \App\Models\Role::OWNER; @endphp
                        <tr class="hover:bg-stone-50">
                            <td class="px-6 py-4">
                                <p class="flex items-center gap-2 font-semibold">
                                    {{ $role->name }}
                                    @if ($isOwnerRole)
                                        <x-icon name="lock" class="size-3.5 text-latte-dark" />
                                    @endif
                                </p>
                                @if ($role->description)
                                    <p class="mt-0.5 text-stone-600">{{ $role->description }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-4 text-right font-mono">{{ $role->users_count }}</td>
                            <td class="px-3 py-4 text-right font-mono">{{ $isOwnerRole ? 'Tất cả' : $role->permissions_count }}</td>
                            <td class="px-3 py-4">
                                @if ($role->is_active)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><span class="size-1.5 rounded-full bg-emerald-500"></span>Đang dùng</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-600"><span class="size-1.5 rounded-full bg-stone-400"></span>Đã khóa</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-1">
                                    <a href="{{ route('roles.edit', $role) }}" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 hover:text-ink" title="Sửa" aria-label="Sửa vai trò {{ $role->name }}">
                                        <x-icon name="pencil" class="size-4" />
                                    </a>
                                    @unless ($isOwnerRole)
                                        <form method="POST" action="{{ route('roles.destroy', $role) }}" data-confirm="Xóa vai trò “{{ $role->name }}”?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-red-50 hover:text-red-600" title="Xóa" aria-label="Xóa vai trò {{ $role->name }}">
                                                <x-icon name="trash" class="size-4" />
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
