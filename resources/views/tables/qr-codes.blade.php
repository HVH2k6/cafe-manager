@php
    // ?table=ID prints one table's card only.
    $only = request()->integer('table');
    $printable = $tables->filter(fn ($table) => $table->qrCode?->is_active && (! $only || $table->id === $only));
@endphp

<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>In mã QR · {{ config('app.cafe_name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            @page { size: A4; margin: 10mm; }
            @media print {
                .no-print { display: none !important; }
                body { background: #fff; }
                .qr-card { break-inside: avoid; }
            }
        </style>
    </head>
    <body class="bg-paper font-sans text-ink antialiased">
        <div class="no-print mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-6 pt-6">
            <div>
                <a href="{{ route('tables.index') }}" class="text-sm font-medium text-stone-600 hover:text-ink">← Sơ đồ bàn</a>
                <h1 class="mt-1 text-2xl font-bold">In mã QR gọi món</h1>
                <p class="text-sm text-stone-600">{{ $printable->count() }} mã · khổ A4, 6 mã/trang. Mã đang tạm ngưng không được in.</p>
            </div>
            <div class="flex gap-2">
                @if ($missingCount > 0 && ! $only)
                    <form method="POST" action="{{ route('tables.qr.store-missing') }}">
                        @csrf
                        <button type="submit" class="h-11 rounded-xl border border-stone-300 bg-white px-4 text-sm font-semibold hover:bg-stone-50">Tạo mã cho {{ $missingCount }} bàn chưa có</button>
                    </form>
                @endif
                <button type="button" onclick="window.print()" class="h-11 rounded-xl bg-latte px-5 text-sm font-semibold text-white hover:bg-latte-dark">In</button>
            </div>
        </div>

        @session('status')
            <p class="no-print mx-auto mt-4 max-w-5xl px-6 text-sm font-medium text-emerald-700">{{ $value }}</p>
        @endsession

        <main class="mx-auto grid max-w-5xl grid-cols-2 gap-6 p-6 sm:grid-cols-3 print:grid-cols-3 print:gap-4 print:p-0">
            @forelse ($printable as $table)
                <article class="qr-card flex flex-col items-center rounded-2xl border-2 border-dashed border-stone-300 bg-white p-5 text-center">
                    <p class="text-sm font-semibold tracking-wider text-latte uppercase">{{ config('app.cafe_name') }}</p>
                    <p class="mt-1 text-3xl font-bold">Bàn {{ $table->table_no }}</p>
                    <canvas data-qr="{{ $table->qrCode->url() }}" data-qr-size="200" class="mt-3 size-50"></canvas>
                    <p class="mt-3 font-semibold">Quét để xem menu & gọi món</p>
                    <p class="text-xs text-stone-500">Gọi nhân viên · Thanh toán ngay trên điện thoại</p>
                </article>
            @empty
                <p class="col-span-full py-16 text-center text-stone-600">Chưa có mã QR nào đang hoạt động.</p>
            @endforelse
        </main>
    </body>
</html>
