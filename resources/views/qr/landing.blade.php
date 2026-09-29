<x-layouts.customer :title="'Bàn '.$table->table_no" class="bg-gradient-to-b from-[#ecd4b0] via-[#f5e7d3] via-45% to-paper">
    <div
        @if ($session)
            {{-- Staff answering a call shows up here right away. --}}
            x-data="liveRegion({ channel: '{{ $session->channelName() }}', public: true, events: ['staff.answered'], reload: true, poll: false })"
        @endif
        class="flex flex-1 flex-col"
    >
        <header class="flex items-center justify-between gap-3 px-5 pt-5">
            <div class="flex items-center gap-3">
                <span class="flex size-11 items-center justify-center rounded-xl bg-latte text-white shadow-sm">
                    <x-icon name="coffee" class="size-6" />
                </span>
                <div class="leading-tight">
                    <p class="text-lg font-bold">{{ config('app.cafe_name') }}</p>
                    <p class="text-sm text-stone-600">Gọi món tại bàn</p>
                </div>
            </div>
            @if ($isOpen)
                <span class="flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700">
                    <span class="size-2 rounded-full bg-emerald-500"></span> Đang mở cửa
                </span>
            @else
                <span class="flex items-center gap-1.5 rounded-full bg-stone-100 px-3 py-1.5 text-sm font-semibold text-stone-600">
                    <span class="size-2 rounded-full bg-stone-400"></span> Ngoài giờ nhận đơn
                </span>
            @endif
        </header>

        {{-- Cup illustration --}}
        <div class="flex justify-center pt-10 pb-8" aria-hidden="true">
            <div class="flex size-52 items-center justify-center rounded-full bg-[#f6ebdc]">
                <svg viewBox="0 0 200 150" class="w-44">
                    <ellipse cx="92" cy="128" rx="80" ry="11" fill="none" stroke="#d9ae78" stroke-width="3" />
                    <path d="M150 62h10a22 22 0 0 1 0 44h-14" fill="none" stroke="#7a5f3c" stroke-width="12" stroke-linecap="round" />
                    <path d="M28 44h128v28c0 32-26 54-64 54S28 104 28 72z" fill="url(#cup)" />
                    <ellipse cx="92" cy="44" rx="64" ry="12" fill="#5c432a" />
                    <ellipse cx="92" cy="45" rx="54" ry="8" fill="#3d2e20" />
                    <path d="M92 50c-5-5-10-4-10 0 0 3 6 6 10 9 4-3 10-6 10-9 0-4-5-5-10 0z" fill="#f0dfc4" />
                    <defs>
                        <linearGradient id="cup" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" stop-color="#8b6f47" />
                            <stop offset="1" stop-color="#6b5234" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
        </div>

        <main class="px-5 pb-8">
            <section class="rounded-3xl bg-white p-6 shadow-[0_8px_30px_rgb(61_46_32/0.08)]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold tracking-wider text-latte uppercase">Bạn đang ngồi tại</p>
                        <h1 class="mt-1 text-[40px] leading-none font-bold tracking-tight text-espresso">Bàn {{ $table->table_no }}</h1>
                    </div>
                    <span class="relative flex size-14 items-center justify-center rounded-2xl bg-cream text-latte-dark">
                        <x-icon name="qr-code" class="size-7" />
                        <span class="absolute -right-1.5 -bottom-1.5 flex size-6 items-center justify-center rounded-full border-2 border-white bg-emerald-500 text-white">
                            <x-icon name="check" class="size-3.5" />
                        </span>
                    </span>
                </div>
                <p class="mt-4 text-stone-600">Đã quét mã QR thành công. Chọn món, thanh toán và theo dõi đơn ngay trên điện thoại của bạn.</p>

                @if (! $isOpen)
                    <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Quán đang ngoài giờ nhận đơn qua QR. Bạn vẫn xem được menu và gọi nhân viên.
                    </p>
                @endif

                @if ($lastCall)
                    <div class="mt-4">@include('qr.partials.call-status')</div>
                @endif


                <form method="POST" action="{{ route('qr.sessions.store', $qrCode->qr_token) }}" class="mt-5 flex flex-col gap-3">
                    @csrf
                    <label for="customer_name" class="font-semibold">Tên của bạn <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
                    <input
                        id="customer_name"
                        name="customer_name"
                        value="{{ old('customer_name', $session?->cart_data['customer_name'] ?? '') }}"
                        maxlength="100"
                        autocomplete="given-name"
                        placeholder="VD: Minh"
                        class="h-12 rounded-xl border border-caramel bg-paper px-4 text-base focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none"
                    >
                    @error('customer_name')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <button type="submit" name="intent" value="menu" class="mt-2 flex h-13 items-center justify-center gap-2 rounded-xl bg-latte text-lg font-semibold text-white hover:bg-latte-dark">
                        Xem Menu <x-icon name="arrow-right" class="size-5" />
                    </button>
                    <button type="submit" name="intent" value="call" class="flex h-13 items-center justify-center gap-2 rounded-xl border-2 border-latte text-lg font-semibold text-latte-dark hover:bg-cream/40">
                        <x-icon name="concierge-bell" class="size-5" /> Gọi nhân viên
                    </button>
                </form>
            </section>

            {{-- What this phone ordered here: open orders, and closed ones for a few minutes (Setting qr_history_minutes). --}}
            @if ($history->isNotEmpty())
                <section class="mt-5 rounded-3xl bg-white p-5 shadow-[0_8px_30px_rgb(61_46_32/0.08)]" aria-labelledby="history-title">
                    <h2 id="history-title" class="flex items-center gap-2 font-bold">
                        <x-icon name="history" class="size-5 text-latte" /> Món bạn đã gọi
                    </h2>
                    <ul class="mt-3 flex flex-col gap-3">
                        @foreach ($history as $past)
                            @php
                                $pastOrder = $past->order;
                                [$pill, $dot] = $pastOrder->status->badgeClasses();
                                $items = $pastOrder->items->where('status', '!=', \App\OrderItemStatus::Cancelled);
                                $expiresAt = $past->historyExpiresAt();
                            @endphp
                            <li class="rounded-2xl border border-stone-200">
                                <a href="{{ route('qr.order', $past) }}" class="flex flex-col gap-1.5 p-4 hover:bg-stone-50">
                                    <span class="flex items-center justify-between gap-3">
                                        <span class="font-mono text-sm font-bold">{{ $pastOrder->order_number }}</span>
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $pill }}"><span class="size-1.5 rounded-full {{ $dot }}"></span>{{ $pastOrder->status->label() }}</span>
                                    </span>
                                    <span class="text-sm text-stone-700">{{ $items->map(fn ($item) => "{$item->quantity}× {$item->name()}")->implode(', ') ?: '—' }}</span>
                                    <span class="flex items-center justify-between gap-3 text-sm">
                                        <x-money :amount="$pastOrder->total_amount" class="font-mono font-semibold" />
                                        <span class="text-xs text-stone-500">
                                            {{ $pastOrder->payment ? 'Đã thanh toán' : ($pastOrder->status->isOpen() ? 'Chưa thanh toán' : '') }}
                                            @if ($expiresAt)
                                                · xem được đến {{ $expiresAt->format('H:i') }}
                                            @endif
                                        </span>
                                    </span>
                                </a>
                                @if ($pastOrder->payment)
                                    <a href="{{ \App\Http\Controllers\ReceiptController::publicUrl($pastOrder) }}" class="flex items-center justify-center gap-1.5 border-t border-stone-200 py-2.5 text-sm font-semibold text-latte-dark hover:bg-stone-50">
                                        <x-icon name="receipt" class="size-4" /> Xem hóa đơn
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-stone-500">Đơn đã xong được giữ lại {{ $historyMinutes }} phút trên điện thoại này rồi tự ẩn.</p>
                </section>
            @endif

            @if ($hoursToday)
                <p class="mt-6 flex items-center justify-center gap-1.5 text-sm text-stone-600">
                    <x-icon name="clock" class="size-4" /> Giờ nhận đơn hôm nay: {{ $hoursToday }}
                </p>
            @endif
        </main>
    </div>
</x-layouts.customer>
