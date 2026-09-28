<x-layouts.app title="Sửa sản phẩm">
    <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
        <x-icon name="arrow-left" class="size-4" /> Sản phẩm
    </a>
    <h1 class="mt-3 text-[32px] leading-tight font-bold tracking-tight">{{ $product->name }}</h1>

    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data" class="mt-6">
        @csrf
        @method('PUT')

        @include('products.partials.form')

        <div class="mt-4 flex justify-end gap-3">
            <a href="{{ route('products.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
            <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Lưu thay đổi</button>
        </div>
    </form>
</x-layouts.app>
