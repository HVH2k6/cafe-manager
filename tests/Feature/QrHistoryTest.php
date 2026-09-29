<?php

use App\Actions\Orders\CancelOrder;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\QrSession;
use App\Models\Setting;
use App\OrderItemStatus;

/**
 * A customer's phone: scan the table's code, open the menu, send an order.
 */
function orderFromPhone(QrCode $code, Product $product): QrSession
{
    test()->post(route('qr.sessions.store', $code->qr_token), ['intent' => 'menu']);
    $session = QrSession::latest('id')->firstOrFail();

    test()->postJson(route('qr.orders.store', $session), [
        'items' => [['type' => 'product', 'id' => $product->id, 'quantity' => 1, 'variant_ids' => []]],
        'payment_method' => 'cash',
        'accept_terms' => true,
    ])->assertCreated();

    return $session->fresh();
}

function completeOrder(Order $order): void
{
    $order->update(['status' => 'confirmed']);
    $order->items()->update(['status' => OrderItemStatus::Served]);
    $order->syncStatusWithItems();
    test()->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => (float) $order->total_amount]);
    auth()->logout();
}

beforeEach(function () {
    $this->code = QrCode::issueFor(DiningTable::factory()->create(['table_no' => 4]));
    $this->latte = Product::factory()->create(['name' => 'Latte', 'selling_price' => 40000]);
});

test('the phone sees its open order on the table page', function () {
    $session = orderFromPhone($this->code, $this->latte);

    $this->get(route('qr.show', $this->code->qr_token))
        ->assertOk()
        ->assertSee('Món bạn đã gọi')
        ->assertSee($session->order->order_number)
        ->assertSee('1× Latte')
        ->assertSee('Chưa thanh toán');
});

test('a finished order stays visible for the configured minutes, with its receipt', function () {
    Setting::putMany(['qr_history_minutes' => 15]);
    $session = orderFromPhone($this->code, $this->latte);
    completeOrder($session->order);

    $this->travel(14)->minutes();

    $this->get(route('qr.show', $this->code->qr_token))
        ->assertSee($session->order->order_number)
        ->assertSee('Hoàn thành')
        ->assertSee('Xem hóa đơn')
        ->assertSee('giữ lại 15 phút');
    $this->get(route('qr.order', $session))->assertOk()->assertSee('Hóa đơn')->assertSee('Gọi món mới');

    $this->travel(2)->minutes();

    $this->get(route('qr.show', $this->code->qr_token))
        ->assertDontSee($session->order->order_number)
        ->assertDontSee('Món bạn đã gọi');
    $this->get(route('qr.order', $session))->assertStatus(410)->assertSee('không còn xem được');
});

test('changing the setting changes how long history is kept', function () {
    $session = orderFromPhone($this->code, $this->latte);
    completeOrder($session->order);
    $this->travel(25)->minutes();

    Setting::putMany(['qr_history_minutes' => 20]);
    $this->get(route('qr.order', $session))->assertStatus(410);

    Setting::putMany(['qr_history_minutes' => 30]);
    $this->get(route('qr.order', $session))->assertOk();
});

test('a cancelled order is kept for the same time, with its reason', function () {
    $session = orderFromPhone($this->code, $this->latte);
    app(CancelOrder::class)->handle($session->order, owner(), 'Hết sữa');
    auth()->logout();

    $this->get(route('qr.show', $this->code->qr_token))->assertSee('Đã hủy');
    $this->get(route('qr.order', $session))->assertOk()->assertSee('Hết sữa');
});

test('history lists every order the phone sent at the table, newest first', function () {
    $first = orderFromPhone($this->code, $this->latte);
    completeOrder($first->order);
    $second = orderFromPhone($this->code, Product::factory()->create(['name' => 'Bạc xỉu']));

    expect($second->order_id)->not->toBe($first->order_id);

    $this->get(route('qr.show', $this->code->qr_token))
        ->assertSeeInOrder([$second->order->order_number, $first->order->order_number]);
});

test('another phone at the same table does not see this phone\'s history', function () {
    $session = orderFromPhone($this->code, $this->latte);
    completeOrder($session->order);

    // A new browser session = the next customer's phone.
    $this->flushSession();

    $this->get(route('qr.show', $this->code->qr_token))
        ->assertOk()
        ->assertDontSee($session->order->order_number)
        ->assertDontSee('Món bạn đã gọi');
});

test('history is per table', function () {
    $session = orderFromPhone($this->code, $this->latte);
    $other = QrCode::issueFor(DiningTable::factory()->create(['table_no' => 9]));

    $this->get(route('qr.show', $other->qr_token))->assertDontSee($session->order->order_number);
});
