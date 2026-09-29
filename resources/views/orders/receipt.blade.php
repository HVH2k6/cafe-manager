{{--
    Receipt for 80 mm thermal printers. Staff version: ?print=1 opens the print dialog right away,
    ?e=1 opens the e-receipt QR (for when there is no printer). $isPublic: the customer's copy opened from that QR.
--}}
@php
    $payment = $order->payment;
    $items = $order->items->where('status', '!=', \App\OrderItemStatus::Cancelled);
    $title = match (true) {
        $order->status === \App\OrderStatus::Cancelled => 'ĐƠN ĐÃ HỦY',
        $payment !== null => 'HÓA ĐƠN THANH TOÁN',
        default => 'PHIẾU TẠM TÍNH',
    };
    $vnd = fn ($amount): string => number_format((float) $amount, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} {{ $order->order_number }}</title>
        <style>
            @page { size: 80mm auto; margin: 3mm; }
            * { box-sizing: border-box; }
            body { margin: 0; background: #e7e5e4; color: #000; font: 12px/1.4 "Segoe UI", Arial, sans-serif; }
            .paper { width: 74mm; margin: 16px auto; padding: 4mm 3mm; background: #fff; box-shadow: 0 1px 6px rgb(0 0 0 / .15); }
            .center { text-align: center; }
            .shop { font-size: 16px; font-weight: 700; }
            .muted { color: #444; font-size: 11px; }
            .title { margin: 8px 0 6px; font-size: 14px; font-weight: 700; letter-spacing: .5px; }
            .rule { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
            .row { display: flex; justify-content: space-between; gap: 8px; }
            .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
            .item { margin: 4px 0; }
            .item-name { font-weight: 600; }
            .total { font-size: 15px; font-weight: 700; }
            .toolbar { display: flex; gap: 8px; justify-content: center; margin: 16px; font-family: sans-serif; }
            .toolbar button, .toolbar a { padding: 8px 14px; border: 1px solid #a8a29e; border-radius: 8px; background: #fff; color: #000; font-size: 14px; text-decoration: none; cursor: pointer; }
            .toolbar .primary { background: #8b6f47; border-color: #8b6f47; color: #fff; font-weight: 600; }
            .e-receipt { width: 74mm; margin: 0 auto 16px; padding: 12px; background: #fff; border-radius: 12px; text-align: center; font-family: sans-serif; box-shadow: 0 1px 6px rgb(0 0 0 / .15); }
            .e-receipt canvas { display: block; margin: 8px auto; }
            .e-receipt .link { display: block; margin: 8px 0; font-size: 11px; color: #555; word-break: break-all; }
            @media print {
                body { background: #fff; }
                .paper { margin: 0; width: auto; padding: 0; box-shadow: none; }
                .toolbar, .e-receipt { display: none !important; }
            }
        </style>
        @vite('resources/js/app.js')
    </head>
    <body>
        <div class="toolbar">
            @if ($isPublic)
                <button type="button" class="primary" onclick="window.print()">Lưu / In hóa đơn</button>
            @else
                <button type="button" class="primary" onclick="window.print()">In hóa đơn</button>
                <button type="button" data-toggle-e-receipt>Hóa đơn điện tử</button>
                <a href="{{ route('orders.show', $order) }}">Quay lại đơn</a>
            @endif
        </div>

        @unless ($isPublic)
            <section class="e-receipt" data-e-receipt @unless (request()->boolean('e')) hidden @endunless>
                <strong>Hóa đơn điện tử</strong>
                <div class="muted">Không có máy in? Mời khách quét mã để xem và lưu hóa đơn trên điện thoại.</div>
                <canvas data-receipt-qr="{{ $eReceiptUrl }}" width="220" height="220" aria-label="Mã QR hóa đơn điện tử"></canvas>
                <a class="link" href="{{ $eReceiptUrl }}" target="_blank" rel="noopener">{{ $eReceiptUrl }}</a>
                <button type="button" data-copy="{{ $eReceiptUrl }}">Sao chép link</button>
            </section>
        @endunless

        <main class="paper">
            <div class="center">
                <div class="shop">{{ config('app.cafe_name') }}</div>
                @if (config('app.cafe_address'))
                    <div class="muted">{{ config('app.cafe_address') }}</div>
                @endif
                @if (config('app.cafe_phone'))
                    <div class="muted">ĐT: {{ config('app.cafe_phone') }}</div>
                @endif
                <div class="title">{{ $title }}</div>
            </div>

            <div class="row"><span>Số:</span><span class="num">{{ $order->order_number }}</span></div>
            <div class="row"><span>Ngày:</span><span class="num">{{ ($payment?->paid_at ?? $order->created_at)->format('d/m/Y H:i') }}</span></div>
            <div class="row"><span>{{ $order->diningTable ? 'Bàn:' : 'Hình thức:' }}</span><span>{{ $order->diningTable?->table_no ?? $order->order_type->label() }}</span></div>
            @if ($order->customer_name)
                <div class="row"><span>Khách:</span><span>{{ $order->customer_name }}</span></div>
            @endif
            <div class="row"><span>Nhân viên:</span><span>{{ $order->creatorLabel() }}</span></div>

            <hr class="rule">

            @foreach ($items as $item)
                <div class="item">
                    <div class="item-name">{{ $item->name() }}</div>
                    @if ($item->variantSummary())
                        <div class="muted">{{ $item->variantSummary() }}</div>
                    @endif
                    @if ($item->bundle)
                        <div class="muted">{{ $item->bundle->items->map(fn ($component) => $component->product->name)->implode(' + ') }}</div>
                    @endif
                    @if ($item->special_instructions)
                        <div class="muted">“{{ $item->special_instructions }}”</div>
                    @endif
                    <div class="row"><span class="num">{{ $item->quantity }} × {{ $vnd($item->unit_price) }}</span><span class="num">{{ $vnd($item->lineTotal()) }}</span></div>
                </div>
            @endforeach

            <hr class="rule">

            <div class="row"><span>Tạm tính</span><span class="num">{{ $vnd($order->subtotal) }}</span></div>
            @if ((float) $order->discount_amount > 0)
                <div class="row"><span>Giảm giá{{ $order->discount_reason ? " ({$order->discount_reason})" : '' }}</span><span class="num">-{{ $vnd($order->discount_amount) }}</span></div>
            @endif
            <div class="row total"><span>TỔNG CỘNG</span><span class="num">{{ $vnd($order->total_amount) }} đ</span></div>

            @if ($payment)
                <hr class="rule">
                <div class="row"><span>Thanh toán</span><span>{{ $payment->method->label() }}</span></div>
                @if ($payment->method === \App\PaymentMethod::Cash)
                    <div class="row"><span>Khách đưa</span><span class="num">{{ $vnd($payment->paid_amount) }}</span></div>
                    <div class="row"><span>Tiền thừa</span><span class="num">{{ $vnd($payment->change_amount) }}</span></div>
                @endif
                @if ($payment->reference_number)
                    <div class="row"><span>Mã GD</span><span class="num">{{ $payment->reference_number }}</span></div>
                @endif
                @if ($payment->isRefunded())
                    <div class="row"><span>Đã hoàn tiền</span><span class="num">-{{ $vnd($payment->refund_amount) }}</span></div>
                    <div class="muted">Lý do: {{ $payment->refund_reason }}</div>
                @endif
            @elseif ($order->status->isOpen())
                <p class="center muted">Chưa thanh toán</p>
            @endif

            <hr class="rule">
            @if ($footer = \App\Models\Setting::get('receipt_footer'))
                <p class="center">{{ $footer }}</p>
            @endif
            @if ($note = \App\Models\Setting::get('receipt_note'))
                <p class="center muted" style="white-space: pre-line">{{ $note }}</p>
            @endif
        </main>

        @if (! $isPublic && request()->boolean('print'))
            <script>window.addEventListener('load', () => window.print());</script>
        @endif
    </body>
</html>
