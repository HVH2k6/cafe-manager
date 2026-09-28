<?php

use App\Models\Order;
use App\Models\Payment;
use App\Models\PayosPaymentLink;
use App\Models\Product;
use App\OrderItemStatus;
use App\OrderStatus;
use App\PaymentMethod;

/**
 * A confirmed order of one product at the given price, optionally already served.
 */
function orderOf(int $price, bool $served = false): Order
{
    $order = placeOrder([[Product::factory()->create(['selling_price' => $price])]]);

    if ($served) {
        $order->items()->update(['status' => OrderItemStatus::Served]);
        $order->syncStatusWithItems();
    }

    return $order->fresh();
}

test('staff without payments.create cannot take payment', function () {
    $order = orderOf(45000);

    $this->actingAs(staffWithPermissions('orders.view'))
        ->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 50000])
        ->assertForbidden();

    expect($order->payment)->toBeNull();
});

test('cash payment records what was handed over and the change', function () {
    $cashier = staffWithPermissions('orders.view', 'payments.create');
    $order = orderOf(45000);

    $this->actingAs($cashier)
        ->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 100000])
        ->assertSessionHas('status', "Đã thu Tiền mặt cho đơn {$order->order_number}. Tiền thừa: 55.000 đ.");

    expect($order->payment)
        ->method->toBe(PaymentMethod::Cash)
        ->status->toBe(Payment::STATUS_COMPLETED)
        ->amount->toBe('45000.00')
        ->paid_amount->toBe('100000.00')
        ->change_amount->toBe('55000.00')
        ->processed_by->toBe($cashier->id)
        ->and($order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

test('cash must cover the total', function () {
    $order = orderOf(45000);

    $this->actingAs(owner())
        ->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 40000])
        ->assertSessionHasErrors(['paid_amount' => 'Số tiền nhận chưa đủ 45.000 đ.']);

    $this->actingAs(owner())
        ->post(route('orders.payments.store', $order), ['method' => 'cash'])
        ->assertSessionHasErrorsIn('payment', ['paid_amount' => 'Nhập số tiền khách đưa.']);

    expect($order->payment()->exists())->toBeFalse();
});

test('card payment takes the order total', function () {
    $order = orderOf(45000, served: true);

    $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'card', 'card_last4' => '4242']);

    expect($order->payment)->method->toBe(PaymentMethod::Card)->amount->toBe('45000.00')->card_last4->toBe('4242')
        ->and($order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('payOS cannot be chosen through the counter form', function () {
    $order = orderOf(45000);

    $this->actingAs(owner())
        ->post(route('orders.payments.store', $order), ['method' => 'qr_pay'])
        ->assertSessionHasErrorsIn('payment', 'method');
});

test('an order is paid only once', function () {
    $order = orderOf(45000);
    $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 45000]);

    $this->actingAs(owner())
        ->post(route('orders.payments.store', $order), ['method' => 'card'])
        ->assertSessionHasErrors(['payment' => "Đơn {$order->order_number} đã được thanh toán (Tiền mặt)."]);

    expect(Payment::count())->toBe(1);
});

test('a paid order can no longer change items, discount or be cancelled', function () {
    $order = orderOf(45000);
    $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 45000]);
    $item = $order->items()->first();
    $message = "Đơn {$order->order_number} đã thanh toán, không sửa món hay giảm giá được nữa.";

    $this->actingAs(owner())->post(route('orders.items.store', $order), ['items' => [productLine(Product::factory()->create())]])->assertSessionHasErrors(['items' => $message]);
    $this->actingAs(owner())->delete(route('orders.items.destroy', [$order, $item]))->assertSessionHas('error', $message);
    $this->actingAs(owner())->put(route('orders.discount.update', $order), ['discount_amount' => 5000, 'discount_reason' => 'Quen'])->assertSessionHas('error', $message);
    $this->actingAs(owner())->put(route('orders.cancel', $order), ['cancelled_reason' => 'Đổi ý'])
        ->assertSessionHasErrors(['cancelled_reason' => "Đơn {$order->order_number} đã thanh toán. Dùng “Hủy đơn & hoàn tiền” để trả lại tiền cho khách."]);

    expect($order->fresh())->total_amount->toBe('45000.00')->status->toBe(OrderStatus::Confirmed);
});

test('cash is refused while a QR code is waiting for a transfer', function () {
    $order = orderOf(45000);
    PayosPaymentLink::create(['order_id' => $order->id, 'order_code' => 123, 'amount' => 45000, 'description' => 'DH260001', 'status' => 'PENDING']);

    $this->actingAs(owner())
        ->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 50000])
        ->assertSessionHas('error', 'Đơn đang có mã QR chờ chuyển khoản. Hãy hủy mã QR trước khi thu tiền cách khác.');

    expect($order->payment()->exists())->toBeFalse();
});

test('the order page offers the payment options', function () {
    config(['services.payos' => ['client_id' => 'id', 'api_key' => 'key', 'checksum_key' => 'sum'] + config('services.payos')]);
    $order = orderOf(45000);

    $this->actingAs(owner())
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertSee('Thu tiền mặt')
        ->assertSee('Đã quẹt thẻ')
        ->assertSee('Chuyển khoản QR');
});

test('without payOS keys the QR option explains what to configure', function () {
    config(['services.payos.client_id' => null]);

    $this->actingAs(owner())->get(route('orders.show', orderOf(45000)))->assertSee('Chuyển khoản QR chưa bật');
});
