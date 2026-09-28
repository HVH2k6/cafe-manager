<x-layouts.app title="Sửa quyền">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('permissions.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
            <x-icon name="arrow-left" class="size-4" /> Phân quyền
        </a>
        <h1 class="mt-3 text-[32px] leading-tight font-bold tracking-tight">Sửa quyền</h1>
        <p class="mt-1 font-mono text-sm text-stone-600">{{ $permission->key }}</p>

        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Đổi module hoặc hành động sẽ đổi key của quyền. Những chỗ trong code đang kiểm tra key cũ
            (<span class="font-mono">{{ $permission->key }}</span>) sẽ không còn khớp.
        </div>

        <form method="POST" action="{{ route('permissions.update', $permission) }}" class="mt-4 rounded-xl border border-stone-200 bg-white p-6">
            @csrf
            @method('PUT')

            @include('permissions.partials.form')

            <div class="mt-6 flex justify-end gap-3 border-t border-stone-200 pt-5">
                <a href="{{ route('permissions.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
                <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</x-layouts.app>
