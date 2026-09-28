@php
    use App\OrderItemStatus;
    use App\OrderStatus;
    use App\PaymentMethod;

    $steps = [
        ['Chờ xác nhận', 'Quầy đang xác nhận đơn · '.$order->created_at->format('H:i')],
        ['Đang chuẩn bị', $order->status === OrderStatus::Preparing ? 'Quầy đang pha chế món của bạn' : 'Quầy đã nhận đơn, sắp pha chế'],
        ['Sẵn sàng phục vụ', 'Món đã xong, nhân viên đang mang ra'],
        ['Hoàn thành', $order->status === OrderStatus::Completed ? 'Cảm ơn bạn đã ghé quán!' : 'Đã mang món ra — chúc bạn ngon miệng!'],
    ];
    $currentStep = match ($order->status) {
        OrderStatus::Pending => 0,
        OrderStatus::Confirmed, OrderStatus::Preparing => 1,
        OrderStatus::Ready => 2,
        OrderStatus::Served => 3,
        OrderStatus::Completed => 4, // every step done
        OrderStatus::Cancelled => -1,
    };
    $minutesLeft = in_array($order->status, [OrderStatus::Pending, OrderStatus::Confirmed, OrderStatus::Preparing], true) && $order->estimated_ready_at
        ? (int) ceil(now()->diffInMinutes($order->estimated_ready_at, false))
        : null;
    $preference = $order->payment_preference;
    $paysOnline = in_array($preference, [PaymentMethod::BankTransfer, PaymentMethod::EWallet], true);
    $activeItems = $order->items->where('status', '!=', OrderItemStatus::Cancelled);
@endphp

<x-layouts.customer :title="'Đơn '.$order->order_number">
    <div
        x-data="liveRegion({ channel: '{{ $session->channelName() }}', public: true, events: ['order.status.updated', 'staff.answered'], poll: 20, pollWhenConnected: 90 })"
        class="flex flex-1 flex-col pb-36"
    >
        <main class="flex flex-col gap-4 px-4 pt-8">
            <div class="text-center">
                @if ($justPlaced)
                    <span class="mx-auto flex size-18 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                        <x-icon name="check" class="size-9" />
                    </span>
                    <h1 class="mt-4 text-3xl font-bold">Đặt món thành công</h1>
                    <p class="mt-2 text-stone-600">Đơn của bạn đã được gửi tới quầy pha chế.</p>
                @else
                    <h1 class="text-3xl font-bold">Đơn của bạn</h1>
                    <p class="mt-1 text-stone-600">{{ config('app.cafe_name') }}</p>
                @endif
            </div>

            <dl class="grid grid-cols-3 divide-x divide-stone-200 rounded-2xl border border-stone-200 bg-white">
                <div class="p-4"><dt class="text-sm text-stone-600">Mã đơn</dt><dd class="mt-1 font-mono text-sm font-bold">{{ $order->order_number }}</dd></div>
                <div class="p-4"><dt class="text-sm text-stone-600">Thời gian</dt><dd class="mt-1 font-mono text-sm font-bold">{{ $order->created_at->format('H:i') }}</dd></div>
                <div class="p-4"><dt class="text-sm text-stone-600">Bàn</dt><dd class="mt-1 font-mono text-sm font-bold">{{ $order->diningTable?->table_no ?? '—' }}</dd></div>
            </dl>

            <div data-live-refresh="call">
                @include('qr.partials.call-status')
            </div>

            {{-- Status timeline --}}
            <section data-live-refresh="status" class="rounded-2xl border border-stone-200 bg-white p-5" aria-live="polite">
                @if ($order->status === OrderStatus::Cancelled)
                    <div class="flex items-start gap-3 text-red-700">
                        <x-icon name="alert-circle" class="size-6" />
                        <div>
                            <h2 class="text-lg font-bold">Đơn đã bị hủy</h2>
                            @if ($order->cancelled_reason)
                                <p class="mt-1 text-sm">Lý do: {{ $order->cancelled_reason }}</p>
                            @endif
                            <p class="mt-1 text-sm text-stone-600">Bấm "Gọi nhân viên" nếu bạn cần hỗ trợ.</p>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold">Trạng thái đơn hàng</h2>
                        @if ($minutesLeft !== null)
                            <span class="flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-700">
                                <x-icon name="clock" class="size-4" /> {{ $minutesLeft > 0 ? "Dự kiến {$minutesLeft} phút" : 'Sắp xong' }}
                            </span>
                        @endif
                    </div>

                    <ol class="mt-4">
                        @foreach ($steps as $index => [$label, $detail])
                            @php $state = $index < $currentStep ? 'done' : ($index === $currentStep ? 'current' : 'todo'); @endphp
                            <li class="relative flex gap-4 pb-6 last:pb-0">
                                @unless ($loop->last)
                                    <span @class(['absolute top-7 bottom-1 left-[13px] w-0.5', 'bg-latte' => $state === 'done', 'bg-stone-200' => $state !== 'done']) aria-hidden="true"></span>
                                @endunless
                                @if ($state === 'done')
                                    <span class="relative flex size-7 shrink-0 items-center justify-center rounded-full bg-latte text-white"><x-icon name="check" class="size-4" /></span>
                                @elseif ($state === 'current')
                                    <span class="relative flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-200/70"><span class="size-3.5 rounded-full bg-amber-600"></span></span>
                                @else
                                    <span class="relative size-7 shrink-0 rounded-full border-2 border-stone-300 bg-white"></span>
                                @endif
                                <div class="pt-0.5">
                                    <p @class(['font-bold' => $state === 'current', 'font-semibold text-stone-600' => $state !== 'current'])>{{ $label }}</p>
                                    @if ($state === 'current')
                                        <p class="text-sm text-stone-600">{{ $detail }}</p>
                                    @endif
                                </div>
                                @if ($state === 'current')
                                    <span class="sr-only">(bước hiện tại)</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Items and totals --}}
            <section data-live-refresh="items" class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="text-lg font-bold">Chi tiết đơn hàng</h2>
                <ul class="mt-3 space-y-2.5">
                    @foreach ($order->items as $item)
                        @php $cancelled = $item->status === OrderItemStatus::Cancelled; @endphp
                        <li @class(['flex items-start justify-between gap-3', 'text-stone-400 line-through' => $cancelled])>
                            <div class="min-w-0">
                                <p>
                                    {{ $item->quantity }}× {{ $item->name() }}
                                    @if ($item->variant_values)
                                        <span class="text-stone-500">({{ collect($item->variant_values)->pluck('value')->implode(', ') }})</span>
                                    @endif
                                </p>
                                @if ($item->special_instructions)
                                    <p class="text-sm text-latte-dark">“{{ $item->special_instructions }}”</p>
                                @endif
                                @if (in_array($item->status, [OrderItemStatus::Ready, OrderItemStatus::Served], true))
                                    <p class="text-xs font-semibold text-emerald-700">{{ $item->status === OrderItemStatus::Served ? 'Đã mang ra' : 'Đã xong' }}</p>
                                @endif
                            </div>
                            <x-money :amount="$item->lineTotal()" class="shrink-0 font-mono" />
                        </li>
                    @endforeach
                </ul>

                <dl class="mt-3 space-y-2">
                    @if ((float) $order->discount_amount > 0)
                        <div class="flex justify-between text-emerald-700">
                            <dt>Giảm giá</dt>
                            <dd class="font-mono"><x-money :amount="-(float) $order->discount_amount" /></dd>
                        </div>
                    @endif
                    <div class="flex items-baseline justify-between border-t border-stone-100 pt-3">
                        <dt class="font-bold tracking-wide uppercase">Thành tiền</dt>
                        <dd><x-money :amount="$order->total_amount" class="font-mono text-2xl font-bold" /></dd>
                    </div>
                </dl>

                <div id="payment" class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-stone-100 px-4 py-3 text-sm">
                    @if ($order->payment)
                        <span class="text-stone-600">Thanh toán: {{ $order->payment->method->label() }}</span>
                        <span class="flex items-center gap-1 font-semibold text-emerald-700"><x-icon name="check-circle" class="size-4" /> Đã thanh toán</span>
                    @else
                        <span class="text-stone-600">Thanh toán: {{ $preference?->forCustomers()[0] ?? 'Tại quầy' }}</span>
                        <span class="font-semibold text-latte-dark">{{ $paysOnline ? 'Quét VietQR' : 'Thu tại bàn' }}</span>
                    @endif
                </div>
            </section>

            {{-- VietQR payment --}}
            <div data-live-refresh="payment">
                @if (! $order->payment && $order->status->isOpen())
                    @if ($waitingLink)
                        <section
                            class="rounded-2xl border border-stone-200 bg-white p-5 text-center"
                            data-payos-poll="{{ route('qr.pay.status', $session) }}"
                            data-payos-deadline="{{ ($waitingLink->expires_at ?? now()->addMinutes(15))->getTimestamp() }}"
                        >
                            <h2 class="text-lg font-bold">Quét mã để chuyển khoản</h2>
                            @if ($qrImage)
                                <img src="{{ $qrImage }}" alt="Mã VietQR chuyển khoản {{ number_format($waitingLink->amount, 0, ',', '.') }} đồng" class="mx-auto mt-3 w-full max-w-64 rounded-xl border border-stone-200">
                            @endif
                            <p class="mt-3"><x-money :amount="$waitingLink->amount" class="font-mono text-2xl font-bold" /></p>
                            <p class="text-sm text-stone-600">Nội dung: <b class="font-mono">{{ $waitingLink->description }}</b></p>
                            @if ($waitingLink->account_name)
                                <p class="text-xs text-stone-500">{{ $waitingLink->account_name }} · {{ $waitingLink->account_number }}</p>
                            @endif
                            <p class="mt-3 flex items-center justify-center gap-2 rounded-xl bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700" role="status" aria-live="polite">
                                <span class="size-2 animate-pulse rounded-full bg-blue-500"></span>
                                <span data-payos-label>{{ $waitingLink->statusLabel() }}</span>
                            </p>
                            <p class="mt-1 text-xs text-stone-500" data-payos-error hidden></p>
                            <p class="mt-3 text-xs text-stone-500">Lưu ảnh mã QR rồi mở app ngân hàng / MoMo / ZaloPay để quét. Trang tự cập nhật khi nhận được tiền.</p>
                            @if ($waitingLink->checkout_url)
                                <a href="{{ $waitingLink->checkout_url }}" target="_blank" rel="noopener" class="mt-3 inline-block font-semibold text-latte-dark underline">Mở trang thanh toán payOS</a>
                            @endif
                        </section>
                    @elseif ($canPayOnline)
                        <form method="POST" action="{{ route('qr.pay', $session) }}">
                            @csrf
                            <button type="submit" @class([
                                'flex h-13 w-full items-center justify-center gap-2 rounded-xl text-lg font-semibold',
                                'bg-emerald-600 text-white hover:bg-emerald-700' => $paysOnline,
                                'border-2 border-stone-300 bg-white text-ink hover:bg-stone-50' => ! $paysOnline,
                            ])>
                                <x-icon name="qr-code" class="size-5" /> Chuyển khoản ngay · <x-money :amount="$order->total_amount" class="font-mono" />
                            </button>
                        </form>
                    @elseif ($paysOnline && $payOnlineHint)
                        <p class="rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ $payOnlineHint }} Mã VietQR sẽ hiện tại đây.</p>
                    @endif
                @endif
            </div>
        </main>

        <footer class="fixed inset-x-0 bottom-0 z-30 mx-auto flex w-full max-w-md flex-col gap-2.5 bg-gradient-to-t from-paper via-paper to-transparent px-4 pt-6 pb-[max(1rem,env(safe-area-inset-bottom))]">
            <form method="POST" action="{{ route('qr.call', $session) }}">
                @csrf
                <button type="submit" class="flex h-13 w-full items-center justify-center gap-2 rounded-xl border-2 border-latte bg-white text-lg font-semibold text-latte-dark hover:bg-cream/40">
                    <x-icon name="concierge-bell" class="size-5" /> Gọi nhân viên
                </button>
            </form>
            <a href="{{ route('qr.menu', $session) }}" class="flex h-13 items-center justify-center rounded-xl bg-latte text-lg font-semibold text-white hover:bg-latte-dark">
                {{ $canOrderMore ? 'Gọi thêm món' : 'Quay lại Menu' }}
            </a>
        </footer>
    </div>
</x-layouts.customer>
