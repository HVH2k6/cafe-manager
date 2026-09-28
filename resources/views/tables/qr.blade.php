<x-layouts.app :title="'Mã QR bàn '.$table->table_no">
    @php $code = $table->qrCode; @endphp

    <a href="{{ route('tables.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-600 hover:text-ink">
        <x-icon name="arrow-left" class="size-4" /> Sơ đồ bàn
    </a>
    <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-[32px] leading-tight font-bold tracking-tight">Mã QR bàn {{ $table->table_no }}</h1>
            <p class="mt-1 text-stone-600">Khách quét mã để xem menu, gọi món, gọi nhân viên và thanh toán.</p>
        </div>
        <a href="{{ route('tables.qr.index') }}" class="flex h-11 items-center gap-2 rounded-xl border border-stone-300 bg-white px-4 text-sm font-semibold hover:bg-stone-50">
            <x-icon name="printer" class="size-4.5" /> In mã tất cả các bàn
        </a>
    </div>

    @if (! $code)
        <div class="mt-6 flex flex-col items-center rounded-xl border border-dashed border-stone-300 bg-white px-6 py-16 text-center">
            <span class="flex size-12 items-center justify-center rounded-xl bg-cream/70 text-latte-dark"><x-icon name="qr-code" class="size-6" /></span>
            <p class="mt-4 font-semibold">Bàn này chưa có mã QR</p>
            <form method="POST" action="{{ route('tables.qr.store', $table) }}" class="mt-4">
                @csrf
                <button type="submit" class="h-11 rounded-xl bg-latte px-5 font-semibold text-white hover:bg-latte-dark">Tạo mã QR</button>
            </form>
        </div>
    @else
        <div class="mt-6 grid items-start gap-4 lg:grid-cols-[360px_1fr]">
            <section class="flex flex-col items-center rounded-xl border border-stone-200 bg-white p-6 text-center">
                <div @class(['rounded-xl border border-stone-200 p-3', 'opacity-40' => ! $code->is_active])>
                    <canvas data-qr="{{ $code->url() }}" data-qr-size="260" class="size-65" aria-label="Mã QR bàn {{ $table->table_no }}"></canvas>
                </div>
                <p class="mt-3 text-lg font-bold">Bàn {{ $table->table_no }}</p>
                <p class="text-sm text-stone-600">Quét để gọi món</p>
                @unless ($code->is_active)
                    <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">Đang tạm ngưng</p>
                @endunless

                <div class="mt-5 grid w-full grid-cols-2 gap-2">
                    <button type="button" data-qr-download="{{ $code->url() }}" data-qr-filename="qr-ban-{{ $table->table_no }}.png" class="flex h-10 items-center justify-center gap-1.5 rounded-lg border border-stone-300 text-sm font-semibold hover:bg-stone-50">
                        <x-icon name="download" class="size-4" /> Tải PNG
                    </button>
                    <a href="{{ route('tables.qr.index', ['table' => $table->id]) }}" target="_blank" class="flex h-10 items-center justify-center gap-1.5 rounded-lg border border-stone-300 text-sm font-semibold hover:bg-stone-50">
                        <x-icon name="printer" class="size-4" /> In
                    </a>
                </div>
            </section>

            <div class="flex flex-col gap-4">
                <section class="rounded-xl border border-stone-200 bg-white p-5">
                    <h2 class="font-bold">Đường dẫn</h2>
                    <div class="mt-2 flex gap-2">
                        <input value="{{ $code->url() }}" readonly aria-label="Đường dẫn mã QR" class="h-10 min-w-0 flex-1 rounded-lg border border-stone-300 bg-stone-50 px-3 font-mono text-sm">
                        <a href="{{ $code->url() }}" target="_blank" rel="noopener" class="flex h-10 items-center rounded-lg border border-stone-300 px-3 text-sm font-semibold hover:bg-stone-50">Mở thử</a>
                    </div>
                    @if (! config('app.public_url'))
                        <p class="mt-2 text-sm text-amber-700">
                            Chưa đặt <span class="font-mono">APP_PUBLIC_URL</span> trong <span class="font-mono">.env</span>: mã đang dùng <span class="font-mono">APP_URL</span>.
                            Điện thoại khách phải truy cập được địa chỉ này (domain, IP trong mạng LAN hoặc ngrok).
                        </p>
                    @endif
                </section>

                <section class="rounded-xl border border-stone-200 bg-white p-5">
                    <h2 class="font-bold">{{ $code->is_active ? 'Tạm ngưng mã' : 'Bật lại mã' }}</h2>
                    <form method="POST" action="{{ route('tables.qr.status', $table) }}" class="mt-3 flex flex-col gap-2">
                        @csrf
                        @method('PUT')
                        @if ($code->is_active)
                            <input type="hidden" name="is_active" value="0">
                            <input name="disabled_reason" maxlength="255" placeholder="Lời nhắn cho khách khi quét (VD: Bàn đang sửa, vui lòng gọi nhân viên)" aria-label="Lời nhắn khi tạm ngưng" class="h-10 rounded-lg border border-stone-300 px-3 text-sm focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                            <button type="submit" class="flex h-10 items-center justify-center gap-1.5 self-start rounded-lg border border-amber-300 px-4 text-sm font-semibold text-amber-800 hover:bg-amber-50">
                                <x-icon name="pause" class="size-4" /> Tạm ngưng
                            </button>
                        @else
                            @if ($code->disabled_reason)
                                <p class="text-sm text-stone-600">Lời nhắn hiện tại: “{{ $code->disabled_reason }}”</p>
                            @endif
                            <input type="hidden" name="is_active" value="1">
                            <button type="submit" class="h-10 self-start rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">Bật lại</button>
                        @endif
                    </form>
                </section>

                <section class="rounded-xl border border-stone-200 bg-white p-5">
                    <h2 class="font-bold">Tạo mã mới</h2>
                    <p class="mt-1 text-sm text-stone-600">Dùng khi mã bị chụp lại / lộ ra ngoài. Mã đang dán trên bàn sẽ <b>ngừng hoạt động ngay</b>, cần in và dán mã mới.</p>
                    <form method="POST" action="{{ route('tables.qr.store', $table) }}" class="mt-3" data-confirm="Tạo mã mới cho bàn {{ $table->table_no }}? Mã cũ đã in sẽ không dùng được nữa.">
                        @csrf
                        <button type="submit" class="flex h-10 items-center gap-1.5 rounded-lg border border-red-200 px-4 text-sm font-semibold text-red-700 hover:bg-red-50">
                            <x-icon name="refresh" class="size-4" /> Tạo mã mới
                        </button>
                    </form>
                </section>
            </div>
        </div>
    @endif
</x-layouts.app>
