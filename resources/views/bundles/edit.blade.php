<x-layouts.app title="Sửa combo">
    <a href="{{ route('bundles.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
        <x-icon name="arrow-left" class="size-4" /> Combo
    </a>
    <h1 class="mt-3 text-[32px] leading-tight font-bold tracking-tight">{{ $bundle->name }}</h1>

    <form method="POST" action="{{ route('bundles.update', $bundle) }}" enctype="multipart/form-data" class="mt-6" data-bundle-form>
        @csrf
        @method('PUT')

        @include('bundles.partials.form')

        <div class="mt-4 flex justify-end gap-3">
            <a href="{{ route('bundles.index') }}" class="flex h-10 items-center rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold hover:bg-stone-50">Hủy</a>
            <button type="submit" class="h-10 rounded-lg bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">Lưu thay đổi</button>
        </div>
    </form>
</x-layouts.app>
