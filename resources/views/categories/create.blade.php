<x-layouts.app title="Thêm danh mục">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('categories.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
            <x-icon name="arrow-left" class="size-4" /> Danh mục
        </a>
        <h1 class="mt-3 text-[32px] leading-tight font-bold tracking-tight">Thêm danh mục</h1>

        <form method="POST" action="{{ route('categories.store') }}" class="mt-6 rounded-xl border border-stone-200 bg-white p-6">
            @csrf

            @include('categories.partials.form')

            <div class="mt-6 flex justify-end gap-3 border-t border-stone-200 pt-5">
                <a href="{{ route('categories.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
                <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Thêm danh mục</button>
            </div>
        </form>
    </div>
</x-layouts.app>
