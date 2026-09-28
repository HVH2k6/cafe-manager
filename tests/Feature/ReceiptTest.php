<?php

use App\Actions\Orders\PlaceOrder;
use App\Http\Controllers\ReceiptController;
use App\Models\DiningTable;
use App\Models\Product;
use App\OrderItemStatus;

test('staff without orders.view cannot open receipts', function () {
    $order = placeOrder([[Product::factory()->create()]]);

    $this->actingAs(staffWithPermissions('kitchen.view'))->get(route('orders.receipt', $order))->assertForbidden();
});

test('an unpaid order prints as a provisional bill', function () {
    config(['app.cafe_name' => 'Cafe ABC', 'app.cafe_address' => '12 Lê Lợi, Q.1', 'app.cafe_phone' => '0901 234 567']);
    $table = DiningTable::factory()->create(['table_no' => 7]);
    $latte = Product::factory()->create(['name' => 'Latte', 'selling_price' => 35000]);
    $large = $latte->variants()->create(['name' => 'Size', 'value' => 'Lớn', 'price_modifier' => 10000]);
    $order = app(PlaceOrder::class)->handle(owner(), ['order_type' => 'dine_in', 'table_id' => $table->id], [
        productLine($latte, 2, [$large->id], 'ít đá'),
    ]);

    $this->actingAs(owner())
        ->get(route('orders.receipt', $order))
        ->assertOk()
        ->assertSeeInOrder(['Cafe ABC', '12 Lê Lợi, Q.1', '0901 234 567', 'PHIẾU TẠM TÍNH', $order->order_number, 'Bàn:', '7'])
        ->assertSeeInOrder(['Latte', 'Size: Lớn', 'ít đá', '2 × 45.000', '90.000', 'TỔNG CỘNG', '90.000 đ', 'Chưa thanh toán'])
        ->assertDontSee("addEventListener('load', () => window.print())", false);
});

test('a paid order prints as a receipt with the payment and change', function () {
    $order = placeOrder([[Product::factory()->create(['name' => 'Bạc xỉu', 'selling_price' => 29000])]]);
    $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 50000]);

    $this->actingAs(owner())
        ->get(route('orders.receipt', ['order' => $order, 'print' => 1]))
        ->assertOk()
        ->assertSeeInOrder(['HÓA ĐƠN THANH TOÁN', 'Bạc xỉu', 'TỔNG CỘNG', '29.000 đ', 'Tiền mặt', 'Khách đưa', '50.000', 'Tiền thừa', '21.000', 'Cảm ơn quý khách!'])
        ->assertSee("addEventListener('load', () => window.print())", false);
});

test('cancelled items and discounts are reflected', function () {
    $order = placeOrder([[Product::factory()->create(['name' => 'Latte', 'selling_price' => 40000])], [Product::factory()->create(['name' => 'Món bỏ', 'selling_price' => 20000])]]);
    $order->items()->where('unit_price', 20000)->update(['status' => OrderItemStatus::Cancelled]);
    $order->update(['discount_amount' => 5000, 'discount_reason' => 'Khách quen']);
    $order->recalculateTotals();

    $this->actingAs(owner())
        ->get(route('orders.receipt', $order))
        ->assertDontSee('Món bỏ')
        ->assertSeeInOrder(['Tạm tính', '40.000', 'Giảm giá (Khách quen)', '-5.000', 'TỔNG CỘNG', '35.000 đ']);
});

describe('e-receipt', function () {
    test('the receipt page offers a signed link and QR for the customer', function () {
        $order = placeOrder([[Product::factory()->create()]]);

        $response = $this->actingAs(owner())->get(route('orders.receipt', ['order' => $order, 'e' => 1]))->assertOk();

        $url = ReceiptController::publicUrl($order);
        expect($url)->toContain("/receipts/{$order->id}?signature=");
        $response->assertSee('data-receipt-qr="'.e($url).'"', false)->assertSee('Sao chép link');
    });

    test('the customer opens the e-receipt without logging in', function () {
        $order = placeOrder([[Product::factory()->create(['name' => 'Bạc xỉu'])]]);
        $path = parse_url(ReceiptController::publicUrl($order));

        $this->get($path['path'].'?'.$path['query'])
            ->assertOk()
            ->assertSee('Bạc xỉu')
            ->assertSee('Lưu / In hóa đơn')
            ->assertDontSee('Quay lại đơn')
            ->assertDontSee('data-receipt-qr', false);
    });

    test('an unsigned or altered link is refused', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $other = placeOrder([[Product::factory()->create()]]);
        $query = parse_url(ReceiptController::publicUrl($order), PHP_URL_QUERY);

        $this->get("/receipts/{$order->id}")->assertForbidden();
        $this->get("/receipts/{$other->id}?{$query}")->assertForbidden();
    });

    test('the link uses the public address when one is configured', function () {
        config(['app.public_url' => 'https://java-afford-naming.ngrok-free.dev/']);
        $order = placeOrder([[Product::factory()->create()]]);

        expect(ReceiptController::publicUrl($order))->toStartWith("https://java-afford-naming.ngrok-free.dev/receipts/{$order->id}?signature=");
    });
});

test('the order page links to the receipt', function () {
    $order = placeOrder([[Product::factory()->create()]]);

    $this->actingAs(owner())->get(route('orders.show', $order))->assertSee(route('orders.receipt', ['order' => $order, 'print' => 1]), false);
});
