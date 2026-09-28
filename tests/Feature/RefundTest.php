<?php

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayosPaymentLink;
use App\Models\Product;
use App\OrderItemStatus;
use App\OrderStatus;
use App\PaymentMethod;
use App\TableStatus;

/**
 * An order of one product, paid in cash; completed when $served.
 */
function paidOrder(int $price = 45000, bool $served = false, ?DiningTable $table = null, ?Product $product = null): Order
{
    $order = placeOrder([[$product ?? Product::factory()->create(['selling_price' => $price])]], $table);

    if ($served) {
        $order->items()->update(['status' => OrderItemStatus::Served]);
        $order->syncStatusWithItems();
    }

    test()->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => $price]);

    return $order->fresh();
}

/**
 * @return array<string, mixed>
 */
function refundData(array $overrides = []): array
{
    return ['refund_amount' => 45000, 'refund_reason' => 'Hết món', 'refund_method' => 'cash', ...$overrides];
}

test('staff without payments.refund cannot refund', function () {
    $order = paidOrder(served: true);

    $this->actingAs(staffWithPermissions('orders.view', 'payments.create'))
        ->post(route('payments.refund', $order->payment), refundData())
        ->assertForbidden();

    expect($order->payment->fresh()->isRefunded())->toBeFalse();
});

test('refunding an open order refunds it in full and cancels it', function () {
    $manager = staffWithPermissions('orders.view', 'payments.refund');
    $table = DiningTable::factory()->create();
    $bread = Product::factory()->stocked(onHand: 10)->create(['selling_price' => 45000]);
    $order = paidOrder(table: $table, product: $bread);

    $this->actingAs($manager)
        ->post(route('payments.refund', $order->payment), refundData(['refund_method' => 'bank_transfer', 'refund_reference' => 'FT999']))
        ->assertSessionHas('status', "Đã ghi nhận hoàn 45.000 đ và hủy đơn {$order->order_number}.");

    expect($order->payment->fresh())
        ->status->toBe(Payment::STATUS_REFUNDED)
        ->refund_amount->toBe('45000.00')
        ->refund_reason->toBe('Hết món')
        ->refund_method->toBe(PaymentMethod::BankTransfer)
        ->refund_reference->toBe('FT999')
        ->refunded_by->toBe($manager->id)
        ->refunded_at->not->toBeNull()
        ->and($order->fresh())->status->toBe(OrderStatus::Cancelled)->cancelled_reason->toBe('Hoàn tiền: Hết món')
        ->and($bread->inventory->fresh()->quantity_reserved)->toBe(0)
        ->and($table->fresh()->status)->toBe(TableStatus::Available);
});

test('an open order cannot be refunded in part', function () {
    $order = paidOrder();

    $this->actingAs(owner())
        ->post(route('payments.refund', $order->payment), refundData(['refund_amount' => 10000]))
        ->assertSessionHasErrors(['refund_amount' => 'Đơn chưa hoàn thành chỉ hoàn được toàn bộ (và đơn sẽ bị hủy).']);

    expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('a completed order can be refunded in part and stays completed', function () {
    $order = paidOrder(served: true);

    $this->actingAs(owner())
        ->post(route('payments.refund', $order->payment), refundData(['refund_amount' => 15000, 'refund_reason' => 'Đồ uống bị đổ']))
        ->assertSessionHas('status', "Đã ghi nhận hoàn 15.000 đ cho đơn {$order->order_number}.");

    expect($order->payment->fresh())
        ->status->toBe(Payment::STATUS_PARTIALLY_REFUNDED)
        ->netAmount()->toBe(30000.0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('a completed order can be refunded in full', function () {
    $order = paidOrder(served: true);

    $this->actingAs(owner())->post(route('payments.refund', $order->payment), refundData());

    expect($order->payment->fresh()->status)->toBe(Payment::STATUS_REFUNDED)
        ->and($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('invalid refunds are rejected', function (array $overrides, string $bag, string $field, string $message) {
    $order = paidOrder(served: true);

    $response = $this->actingAs(owner())->post(route('payments.refund', $order->payment), refundData($overrides));

    $bag === 'default'
        ? $response->assertSessionHasErrors([$field => $message])
        : $response->assertSessionHasErrorsIn($bag, [$field => $message]);

    expect($order->payment->fresh()->isRefunded())->toBeFalse();
})->with([
    'more than was paid' => [['refund_amount' => 50000], 'default', 'refund_amount', 'Số tiền hoàn phải từ 1 đ đến 45.000 đ.'],
    'no reason' => [['refund_reason' => ''], 'refund', 'refund_reason', 'Ghi lý do hoàn tiền.'],
    'refunded by card' => [['refund_method' => 'card'], 'refund', 'refund_method', 'Chỉ hoàn bằng tiền mặt hoặc chuyển khoản.'],
]);

test('a payment is refunded only once', function () {
    $order = paidOrder(served: true);
    $this->actingAs(owner())->post(route('payments.refund', $order->payment), refundData(['refund_amount' => 5000]));

    $this->actingAs(owner())
        ->post(route('payments.refund', $order->payment), refundData(['refund_amount' => 5000]))
        ->assertSessionHasErrors(['refund_amount' => "Khoản thanh toán của đơn {$order->order_number} đã được hoàn tiền trước đó."]);

    expect($order->payment->fresh()->refund_amount)->toBe('5000.00');
});

test('a paid order is cancelled through the refund, not the plain cancel', function () {
    $order = paidOrder();

    $this->actingAs(owner())
        ->put(route('orders.cancel', $order), ['cancelled_reason' => 'Đổi ý'])
        ->assertSessionHasErrors(['cancelled_reason' => "Đơn {$order->order_number} đã thanh toán. Dùng “Hủy đơn & hoàn tiền” để trả lại tiền cho khách."]);

    $this->actingAs(owner())
        ->get(route('orders.show', $order))
        ->assertSee('Hủy đơn &amp; hoàn tiền', false)
        ->assertDontSee('Xác nhận hủy đơn');
});

test('the order page shows the refund after it is recorded', function () {
    $order = paidOrder(served: true);
    $this->actingAs(owner())->post(route('payments.refund', $order->payment), refundData(['refund_amount' => 15000, 'refund_reason' => 'Đồ uống bị đổ']));

    $this->actingAs(owner())
        ->get(route('orders.show', $order))
        ->assertSee('Đã hoàn một phần')
        ->assertSee('Đồ uống bị đổ')
        ->assertSee('15.000 đ')
        ->assertDontSee('Ghi nhận hoàn tiền');
});

describe('money transferred to an order settled another way', function () {
    function strayTransfer(): PayosPaymentLink
    {
        $order = paidOrder();

        return PayosPaymentLink::create([
            'order_id' => $order->id, 'order_code' => 777, 'amount' => 45000, 'amount_paid' => 45000,
            'description' => 'DH260001', 'status' => 'PAID', 'reference' => 'FT777',
        ]);
    }

    test('it can be marked as given back', function () {
        $link = strayTransfer();
        expect($link->needsRefund())->toBeTrue();

        $this->actingAs(owner())
            ->post(route('payos.refund', $link), ['refund_method' => 'bank_transfer', 'refund_reference' => 'FT888', 'refund_note' => 'Chuyển lại qua MB'])
            ->assertSessionHas('status', 'Đã ghi nhận hoàn 45.000 đ cho khách.');

        expect($link->fresh())->needsRefund()->toBeFalse()->refund_method->toBe(PaymentMethod::BankTransfer)->refund_note->toBe('Chuyển lại qua MB');

        $this->actingAs(owner())->get(route('orders.show', $link->order_id))->assertDontSee('Cần hoàn tiền cho khách');
    });

    test('it is recorded only once and not for the order\'s own payment', function () {
        $link = strayTransfer();
        $this->actingAs(owner())->post(route('payos.refund', $link), ['refund_method' => 'cash']);

        $this->actingAs(owner())->post(route('payos.refund', $link), ['refund_method' => 'cash'])
            ->assertSessionHas('error', 'Khoản chuyển khoản này không cần hoàn hoặc đã hoàn rồi.');
    });
});
