<x-layouts.app title="Thêm nhân viên">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('employees.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
            <x-icon name="arrow-left" class="size-4" /> Nhân viên
        </a>
        <h1 class="mt-3 text-[32px] leading-tight font-bold tracking-tight">Thêm nhân viên</h1>

        <form method="POST" action="{{ route('employees.store') }}" class="mt-6">
            @csrf

            @include('employees.partials.form')

            <div class="mt-4 flex justify-end gap-3">
                <a href="{{ route('employees.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
                <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Thêm nhân viên</button>
            </div>
        </form>
    </div>
</x-layouts.app>
