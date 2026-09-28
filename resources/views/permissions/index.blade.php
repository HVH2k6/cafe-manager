<x-layouts.app title="Phân quyền">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Phân quyền</h1>
            <p class="mt-1 text-stone-600">Chọn quyền cho từng vai trò. {{ \App\Models\Role::OWNER }} luôn có toàn quyền.</p>
        </div>

        <a href="{{ route('permissions.create') }}" class="flex h-11 items-center gap-2 rounded-xl bg-latte px-4 text-sm font-semibold text-white hover:bg-latte-dark">
            <x-icon name="plus" class="size-4.5" />
            Thêm quyền
        </a>
    </div>

    @include('permissions.partials.tabs')

    @if ($permissionsByModule->isEmpty())
        <div class="mt-6 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark">
                <x-icon name="shield-check" class="size-6" />
            </span>
            <p class="mt-4 font-semibold">Chưa có quyền nào</p>
            <p class="mt-1 max-w-sm text-sm text-stone-600">Tạo quyền theo dạng <span class="font-mono">module.hành_động</span>, ví dụ <span class="font-mono">orders.view</span>, rồi gán cho các vai trò.</p>
        </div>
    @else
        <form method="POST" action="{{ route('role-permissions.update') }}" class="mt-6">
            @csrf
            @method('PUT')

            <section class="overflow-hidden rounded-xl border border-stone-200 bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-stone-100/70">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-semibold tracking-wide text-stone-600 uppercase">Quyền</th>
                                @foreach ($roles as $role)
                                    <th scope="col" class="w-32 px-3 py-3 text-center">
                                        <span class="block font-semibold">{{ $role->name }}</span>
                                        @if ($role->name === \App\Models\Role::OWNER)
                                            <span class="mt-0.5 inline-flex items-center gap-1 text-xs font-medium text-latte-dark">
                                                <x-icon name="lock" class="size-3" /> Toàn quyền
                                            </span>
                                        @elseif (! $role->is_active)
                                            <span class="mt-0.5 block text-xs font-medium text-red-600">Vai trò đang khóa</span>
                                        @else
                                            <span class="mt-0.5 block text-xs font-normal text-stone-500">{{ $role->permissions->count() }} quyền</span>
                                        @endif
                                    </th>
                                @endforeach
                                <th scope="col" class="w-24 px-6 py-3 text-right text-xs font-semibold tracking-wide text-stone-600 uppercase">
                                    <span class="sr-only">Thao tác</span>
                                </th>
                            </tr>
                        </thead>

                        @foreach ($permissionsByModule as $module => $permissions)
                            <tbody class="border-t border-stone-200">
                                <tr class="bg-stone-50">
                                    <th scope="colgroup" colspan="{{ $roles->count() + 2 }}" class="px-6 py-2.5 text-left">
                                        <span class="font-semibold">{{ \App\Models\Permission::moduleLabel($module) }}</span>
                                        <span class="ml-2 font-mono text-xs font-normal text-stone-500">{{ $module }}</span>
                                    </th>
                                </tr>

                                @foreach ($permissions as $permission)
                                    <tr class="border-t border-stone-100 hover:bg-stone-50/60">
                                        <th scope="row" class="px-6 py-3 text-left font-normal">
                                            <span class="flex flex-wrap items-center gap-2">
                                                <span @class(['font-medium', 'text-stone-400' => ! $permission->is_active])>{{ $permission->name }}</span>
                                                @unless ($permission->is_active)
                                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600">Đã tắt</span>
                                                @endunless
                                            </span>
                                            <span class="mt-0.5 block font-mono text-xs text-stone-500">{{ $permission->key }}</span>
                                        </th>

                                        @foreach ($roles as $role)
                                            <td class="px-3 py-3 text-center">
                                                @if ($role->name === \App\Models\Role::OWNER)
                                                    <input type="checkbox" checked disabled class="size-4.5 accent-latte opacity-50" aria-label="{{ $role->name }} luôn có quyền {{ $permission->name }}">
                                                @else
                                                    <input
                                                        type="checkbox"
                                                        name="roles[{{ $role->id }}][]"
                                                        value="{{ $permission->id }}"
                                                        @checked($role->permissions->contains('id', $permission->id))
                                                        class="size-4.5 cursor-pointer accent-latte"
                                                        aria-label="{{ $role->name }}: {{ $permission->name }}"
                                                    >
                                                @endif
                                            </td>
                                        @endforeach

                                        <td class="px-6 py-3">
                                            <div class="flex justify-end gap-1">
                                                <a href="{{ route('permissions.edit', $permission) }}" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-stone-100 hover:text-ink" title="Sửa" aria-label="Sửa quyền {{ $permission->name }}">
                                                    <x-icon name="pencil" class="size-4" />
                                                </a>
                                                <button type="submit" form="delete-permission-{{ $permission->id }}" class="flex size-8 items-center justify-center rounded-lg text-stone-600 hover:bg-red-50 hover:text-red-600" title="Xóa" aria-label="Xóa quyền {{ $permission->name }}">
                                                    <x-icon name="trash" class="size-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            </section>

            <div class="sticky bottom-4 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-stone-200 bg-white/95 px-5 py-3 shadow-sm backdrop-blur">
                <p class="text-sm text-stone-600">Thay đổi chỉ có hiệu lực sau khi bấm lưu.</p>
                <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">
                    Lưu phân quyền
                </button>
            </div>
        </form>

        {{-- Delete forms live outside the matrix form because forms cannot be nested. --}}
        @foreach ($permissionsByModule->flatten() as $permission)
            <form
                id="delete-permission-{{ $permission->id }}"
                method="POST"
                action="{{ route('permissions.destroy', $permission) }}"
                data-confirm="Xóa quyền “{{ $permission->name }}”? Các vai trò đang có quyền này sẽ bị gỡ."
                hidden
            >
                @csrf
                @method('DELETE')
            </form>
        @endforeach
    @endif
</x-layouts.app>
