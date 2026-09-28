<x-layouts.app title="Sửa vai trò">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('roles.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
            <x-icon name="arrow-left" class="size-4" /> Vai trò
        </a>
        <h1 class="mt-3 text-[32px] leading-tight font-bold tracking-tight">Sửa vai trò</h1>

        <form method="POST" action="{{ route('roles.update', $role) }}" class="mt-6 rounded-xl border border-stone-200 bg-white p-6">
            @csrf
            @method('PUT')

            @include('roles.partials.form')

            <div class="mt-6 flex justify-end gap-3 border-t border-stone-200 pt-5">
                <a href="{{ route('roles.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
                <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</x-layouts.app>
