<x-layouts.app title="Thêm sản phẩm">
    <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
        <x-icon name="arrow-left" class="size-4" /> Sản phẩm
    </a>
    <h1 class="mt-3 text-[32px] leading-tight font-bold tracking-tight">Thêm sản phẩm</h1>

    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="mt-6">
        @csrf

        @include('products.partials.form')

        <div class="mt-4 flex justify-end gap-3">
            <a href="{{ route('products.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
            <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Thêm sản phẩm</button>
        </div>
    </form>
</x-layouts.app>
