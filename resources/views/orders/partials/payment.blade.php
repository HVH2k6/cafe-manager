{{--
    Payment box of the order page. Expects $order (payment.processor loaded), $payosLink (latest link or null),
    $payosQrImage (?string) and $payosConfigured.
--}}
@php
    $payment = $order->payment;
    $total = (int) round((float) $order->total_amount);
    $waitingLink = $payosLink?->isAwaitingPayment() ? $payosLink : null;
    // A transfer that arrived although the order was settled another way (or closed) must be refunded by hand.
    $strayTransfer = $payosLink?->needsRefund() ?? false;
    $refundInputClasses = 'h-10 w-full rounded-lg border border-stone-300 px-3 focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none';
@endphp

<section id="payment" class="rounded-xl border border-stone-200 bg-white p-5">
    <div class="flex items-center justify-between gap-3">
        <h2 class="font-bold">Thanh toán</h2>
        <div class="flex gap-3 text-sm font-semibold text-latte-dark">
            <a href="{{ route('orders.receipt', ['order' => $order, 'print' => 1]) }}" target="_blank" rel="noopener" class="hover:underline">
                {{ $payment ? 'In hóa đơn' : 'In tạm tính' }}
            </a>
            <a href="{{ route('orders.receipt', ['order' => $order, 'e' => 1]) }}" target="_blank" rel="noopener" class="hover:underline" title="Không có máy in: khách quét QR để xem hóa đơn trên điện thoại">HĐ điện tử</a>
        </div>
    </div>

    @foreach ($errors->refund->all() as $error)
        <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
    @endforeach

    @if ($strayTransfer)
        <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            <p>
                Khách đã chuyển khoản <x-money :amount="$payosLink->amount_paid" /> qua payOS (mã GD {{ $payosLink->reference ?? '—' }})
                nhưng đơn {{ $payment ? 'đã thu bằng '.mb_strtolower($payment->method->label()) : 'đã đóng' }}. Cần hoàn tiền cho khách.
            </p>
            @can('payments.refund')
                <details class="mt-2">
                    <summary class="cursor-pointer font-semibold">Đã hoàn tiền cho khách</summary>
                    <form method="POST" action="{{ route('payos.refund', $payosLink) }}" class="mt-2 flex flex-col gap-2 text-ink">
                        @csrf
                        @include('orders.partials.refund-method')
                        <input name="refund_note" maxlength="255" placeholder="Ghi chú (không bắt buộc)" aria-label="Ghi chú" class="{{ $refundInputClasses }} bg-white">
                        <button type="submit" class="h-9 rounded-lg bg-amber-700 font-semibold text-white hover:bg-amber-800">Ghi nhận đã hoàn</button>
                    </form>
                </details>
            @endcan
        </div>
    @elseif ($payosLink?->refunded_at)
        <p class="mt-3 text-xs text-stone-500">
            Đã hoàn <x-money :amount="$payosLink->amount_paid" /> chuyển khoản nhầm qua payOS
            ({{ mb_strtolower($payosLink->refund_method?->label() ?? '') }}, {{ $payosLink->refunded_at->format('H:i d/m') }}).
        </p>
    @endif

    @if ($payment)
        <dl class="mt-3 space-y-1.5 text-sm">
            @if ($payment->isRefunded())
                <div class="flex items-center gap-2 font-semibold text-red-700">
                    <x-icon name="alert-circle" class="size-5" />
                    {{ $payment->status === \App\Models\Payment::STATUS_REFUNDED ? 'Đã hoàn tiền' : 'Đã hoàn một phần' }}
                </div>
            @else
                <div class="flex items-center gap-2 font-semibold text-emerald-700"><x-icon name="check-circle" class="size-5" /> Đã thanh toán</div>
            @endif
            <div class="flex justify-between"><dt class="text-stone-600">Hình thức</dt><dd>{{ $payment->method->label() }}</dd></div>
            <div class="flex justify-between"><dt class="text-stone-600">Số tiền</dt><dd><x-money :amount="$payment->amount" /></dd></div>
            @if ($payment->method === \App\PaymentMethod::Cash)
                <div class="flex justify-between"><dt class="text-stone-600">Khách đưa</dt><dd><x-money :amount="$payment->paid_amount" /></dd></div>
                <div class="flex justify-between"><dt class="text-stone-600">Tiền thừa</dt><dd><x-money :amount="$payment->change_amount" class="font-semibold" /></dd></div>
            @endif
            @if ($payment->card_last4)
                <div class="flex justify-between"><dt class="text-stone-600">Thẻ</dt><dd class="font-mono">•••• {{ $payment->card_last4 }}</dd></div>
            @endif
            @if ($payment->reference_number)
                <div class="flex justify-between gap-3"><dt class="text-stone-600">Mã giao dịch</dt><dd class="font-mono text-xs break-all">{{ $payment->reference_number }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-stone-600">Lúc</dt><dd>{{ $payment->paid_at->format('H:i d/m') }} · {{ $payment->processor?->name ?? 'payOS tự báo' }}</dd></div>
            @if ($payment->isRefunded())
                <div class="mt-2 space-y-1.5 border-t border-stone-100 pt-2">
                    <div class="flex justify-between font-semibold text-red-700"><dt>Đã hoàn</dt><dd><x-money :amount="$payment->refund_amount" /></dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-stone-600">Lý do</dt><dd class="text-right">{{ $payment->refund_reason }}</dd></div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-stone-600">Cách hoàn</dt>
                        <dd class="text-right">{{ $payment->refund_method?->label() }}{{ $payment->refund_reference ? " · {$payment->refund_reference}" : '' }}</dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-stone-600">Lúc</dt><dd>{{ $payment->refunded_at->format('H:i d/m') }} · {{ $payment->refunder?->name ?? '—' }}</dd></div>
                </div>
            @endif
        </dl>

        @if ($order->status->isOpen())
            <p class="mt-3 text-sm text-stone-600">Đơn tự hoàn thành khi mang đủ món.</p>
        @endif

        @if (! $payment->isRefunded() && ($order->status->isOpen() || $order->status === \App\OrderStatus::Completed))
            @can('payments.refund')
                @php $isOpen = $order->status->isOpen(); @endphp
                <details class="mt-3 border-t border-stone-100 pt-3 text-sm" @if ($errors->refund->any()) open @endif>
                    <summary class="cursor-pointer font-semibold text-red-700">{{ $isOpen ? 'Hủy đơn & hoàn tiền' : 'Hoàn tiền' }}</summary>
                    <form method="POST" action="{{ route('payments.refund', $payment) }}" class="mt-3 flex flex-col gap-2" data-confirm="{{ $isOpen ? 'Hủy đơn và ghi nhận đã hoàn toàn bộ tiền cho khách?' : 'Ghi nhận đã hoàn tiền cho khách?' }}">
                        @csrf
                        <p class="text-xs text-stone-500">payOS không tự trả tiền về được: hãy trả tiền mặt hoặc tự chuyển khoản cho khách, rồi ghi nhận ở đây.</p>
                        @if ($isOpen)
                            <input type="hidden" name="refund_amount" value="{{ (float) $payment->amount }}">
                            <p>Hoàn toàn bộ <x-money :amount="$payment->amount" class="font-semibold" /> và hủy đơn.</p>
                        @else
                            <label for="refund_amount" class="font-semibold">Số tiền hoàn</label>
                            <input id="refund_amount" name="refund_amount" type="number" min="1" max="{{ (float) $payment->amount }}" step="1000" value="{{ old('refund_amount', (int) $payment->amount) }}" required class="{{ $refundInputClasses }} font-mono">
                        @endif
                        <input name="refund_reason" value="{{ old('refund_reason') }}" maxlength="255" required placeholder="Lý do (bắt buộc)" aria-label="Lý do hoàn tiền" class="{{ $refundInputClasses }}">
                        @include('orders.partials.refund-method')
                        <button type="submit" class="h-9 rounded-lg border border-red-200 font-semibold text-red-700 hover:bg-red-50">{{ $isOpen ? 'Hủy đơn & ghi nhận hoàn tiền' : 'Ghi nhận hoàn tiền' }}</button>
                    </form>
                </details>
            @endcan
        @endif
    @elseif (! $order->status->isOpen())
        <p class="mt-2 text-sm text-stone-600">Không có thanh toán.</p>
    @elseif ($waitingLink)
        {{-- VietQR waiting for the transfer; the page polls our server, which asks payOS. --}}
        <div
            class="mt-3 text-center"
            data-payos-poll="{{ route('payos.status', $waitingLink) }}"
            data-payos-deadline="{{ ($waitingLink->expires_at ?? now()->addMinutes(15))->getTimestamp() }}"
        >
            @if ($payosQrImage)
                <img src="{{ $payosQrImage }}" alt="Mã QR chuyển khoản {{ number_format($waitingLink->amount, 0, ',', '.') }} đồng" class="mx-auto w-full max-w-64 rounded-lg border border-stone-200">
            @endif
            <p class="mt-3"><x-money :amount="$waitingLink->amount" class="text-2xl font-bold" /></p>
            <p class="text-sm text-stone-600">Nội dung: <b class="font-mono">{{ $waitingLink->description }}</b></p>
            @if ($waitingLink->account_name)
                <p class="text-xs text-stone-500">{{ $waitingLink->account_name }} · {{ $waitingLink->account_number }}</p>
            @endif
            <p class="mt-3 flex items-center justify-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-700" role="status" aria-live="polite">
                <span class="size-2 animate-pulse rounded-full bg-blue-500"></span>
                <span data-payos-label>{{ $waitingLink->statusLabel() }}</span>
            </p>
            <p class="mt-1 text-xs text-stone-500" data-payos-error hidden></p>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-2">
            @if ($waitingLink->checkout_url)
                <a href="{{ $waitingLink->checkout_url }}" target="_blank" rel="noopener" class="flex h-9 items-center justify-center rounded-lg border border-stone-300 text-sm font-semibold hover:bg-stone-50">Mở trang payOS</a>
            @endif
            <form method="POST" action="{{ route('payos.cancel', $waitingLink) }}" data-confirm="Hủy mã QR này? Khách sẽ không chuyển khoản được bằng mã này nữa.">
                @csrf
                <button type="submit" class="h-9 w-full rounded-lg border border-red-200 text-sm font-semibold text-red-700 hover:bg-red-50">Hủy mã QR</button>
            </form>
        </div>
    @elseif ($total <= 0)
        <p class="mt-2 text-sm text-stone-600">Đơn không có tiền cần thu.</p>
    @else
        @can('payments.create')
            @if ($payosLink && $payosLink->isFinal() && ! $strayTransfer)
                <p class="mt-2 text-xs text-stone-500">Mã QR gần nhất: {{ mb_strtolower($payosLink->statusLabel()) }}.</p>
            @endif

            @foreach ($errors->payment->all() as $error)
                <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
            @endforeach

            <div class="mt-3 flex flex-col gap-2">
                {{-- Cash --}}
                <details class="group rounded-lg border border-stone-200" @if (old('method', 'cash') === 'cash') open @endif>
                    <summary class="flex h-11 cursor-pointer list-none items-center justify-between px-3 font-semibold [&::-webkit-details-marker]:hidden">
                        Tiền mặt <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="flex flex-col gap-2 border-t border-stone-100 p-3" data-cash-form data-total="{{ $total }}">
                        @csrf
                        <input type="hidden" name="method" value="cash">
                        <label for="paid_amount" class="text-sm font-semibold">Khách đưa</label>
                        <input id="paid_amount" name="paid_amount" type="number" min="{{ $total }}" step="1000" value="{{ old('paid_amount', $total) }}" required class="h-11 rounded-lg border border-stone-300 px-3 font-mono text-lg focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach (collect([$total, (int) ceil($total / 10000) * 10000, (int) ceil($total / 50000) * 50000, (int) ceil($total / 100000) * 100000, 200000, 500000])->filter(fn ($amount) => $amount >= $total)->unique()->take(5) as $amount)
                                <button type="button" data-cash-amount="{{ $amount }}" class="h-8 rounded-md bg-stone-100 px-2.5 font-mono text-xs font-semibold hover:bg-stone-200">{{ number_format($amount, 0, ',', '.') }}</button>
                            @endforeach
                        </div>
                        <p class="flex justify-between text-sm"><span class="text-stone-600">Tiền thừa</span><b class="font-mono" data-cash-change>0 đ</b></p>
                        <button type="submit" class="h-11 rounded-lg bg-emerald-600 font-semibold text-white hover:bg-emerald-700">Thu tiền mặt</button>
                    </form>
                </details>

                {{-- Card on the bank's terminal --}}
                <details class="group rounded-lg border border-stone-200" @if (old('method') === 'card') open @endif>
                    <summary class="flex h-11 cursor-pointer list-none items-center justify-between px-3 font-semibold [&::-webkit-details-marker]:hidden">
                        Thẻ (máy POS ngân hàng) <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('orders.payments.store', $order) }}" class="flex flex-col gap-2 border-t border-stone-100 p-3">
                        @csrf
                        <input type="hidden" name="method" value="card">
                        <label for="card_last4" class="text-sm font-semibold">4 số cuối thẻ <span class="font-normal text-stone-500">(không bắt buộc)</span></label>
                        <input id="card_last4" name="card_last4" inputmode="numeric" maxlength="4" value="{{ old('card_last4') }}" class="h-10 rounded-lg border border-stone-300 px-3 font-mono focus:border-latte focus:ring-3 focus:ring-latte/15 focus:outline-none">
                        <button type="submit" class="h-10 rounded-lg bg-espresso font-semibold text-white hover:bg-espresso-line">Đã quẹt thẻ <x-money :amount="$total" /></button>
                    </form>
                </details>

                {{-- payOS --}}
                @if ($payosConfigured)
                    <form method="POST" action="{{ route('orders.payos.store', $order) }}">
                        @csrf
                        <button type="submit" class="flex h-11 w-full items-center justify-center gap-2 rounded-lg bg-latte font-semibold text-white hover:bg-latte-dark">
                            Chuyển khoản QR · <x-money :amount="$total" />
                        </button>
                    </form>
                @else
                    <p class="rounded-lg bg-stone-100 px-3 py-2 text-xs text-stone-600">Chuyển khoản QR chưa bật: điền <span class="font-mono">PAYOS_CLIENT_ID</span>, <span class="font-mono">PAYOS_API_KEY</span>, <span class="font-mono">PAYOS_CHECKSUM_KEY</span> trong <span class="font-mono">.env</span>.</p>
                @endif
            </div>
        @else
            <p class="mt-2 text-sm text-stone-600">Chưa thanh toán · <x-money :amount="$total" /></p>
        @endcan
    @endif
</section>
