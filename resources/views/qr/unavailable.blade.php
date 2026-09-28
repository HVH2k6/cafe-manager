<x-layouts.customer title="Không gọi món được">
    <main class="flex flex-1 flex-col items-center justify-center px-6 py-16 text-center">
        <span class="flex size-16 items-center justify-center rounded-full bg-amber-50 text-amber-600">
            <x-icon name="alert-circle" class="size-8" />
        </span>
        @if ($tableNo)
            <p class="mt-6 text-sm font-semibold tracking-wider text-latte uppercase">Bàn {{ $tableNo }}</p>
        @endif
        <h1 class="mt-2 text-2xl font-bold">Chưa gọi món bằng QR được</h1>
        <p class="mt-3 text-stone-600">{{ $message }}</p>
        <p class="mt-8 text-sm text-stone-500">{{ config('app.cafe_name') }}</p>
    </main>
</x-layouts.customer>
