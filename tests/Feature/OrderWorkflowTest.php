<?php

use App\InventoryTransactionType;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\OrderItemStatus;
use App\OrderStatus;
use App\TableStatus;

describe('list and detail', function () {
    test('open orders are listed by default and can be searched', function () {
        $open = placeOrder([[Product::factory()->create(['name' => 'Latte'])]]);
        $done = Order::factory()->status(OrderStatus::Completed)->create(['order_number' => 'ORD-2026-0900']);

        $this->actingAs(owner())->get(route('orders.index'))->assertOk()->assertSee($open->order_number)->assertSee('Latte')->assertDontSee('ORD-2026-0900');
        $this->actingAs(owner())->get(route('orders.index', ['tab' => 'completed']))->assertSee('ORD-2026-0900')->assertDontSee($open->order_number);
        $this->actingAs(owner())->get(route('orders.index', ['tab' => 'all', 'search' => '0900']))->assertSee('ORD-2026-0900')->assertDontSee($open->order_number);
    });

    test('open orders from earlier days are still listed and a date filters the list', function () {
        $this->travelTo(now()->subDay());
        $yesterday = placeOrder([[Product::factory()->create()]]);
        $this->travelBack();
        $today = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(owner())->get(route('orders.index'))->assertSee($yesterday->order_number)->assertSee($today->order_number);
        $this->actingAs(owner())
            ->get(route('orders.index', ['date' => now()->toDateString()]))
            ->assertSee($today->order_number)
            ->assertDontSee($yesterday->order_number);
    });

    test('a cancelled order still lists the items it held', function () {
        $order = placeOrder([[Product::factory()->create(['name' => 'Bạc xỉu'])]]);
        $order->items()->update(['status' => OrderItemStatus::Cancelled]);
        $order->update(['status' => OrderStatus::Cancelled]);

        $this->actingAs(owner())->get(route('orders.index', ['tab' => 'cancelled']))->assertSee('Bạc xỉu');
    });

    test('the detail page shows items, options and totals', function () {
        $latte = Product::factory()->create(['name' => 'Latte', 'selling_price' => 35000]);
        $order = placeOrder([[$latte, 2]]);

        $this->actingAs(owner())->get(route('orders.show', $order))->assertOk()->assertSee('Latte')->assertSee('70.000 đ');
    });

    test('the sidebar counts orders waiting for confirmation', function () {
        placeOrder([[Product::factory()->create()]], status: OrderStatus::Pending);
        placeOrder([[Product::factory()->create()]], status: OrderStatus::Pending);

        $this->actingAs(owner())->get(route('dashboard'))->assertSee('title="2 đơn chờ xác nhận"', false);
    });
});

describe('adding and removing items', function () {
    test('items can be added to an open order', function () {
        $bread = Product::factory()->stocked(onHand: 10)->create(['selling_price' => 25000]);
        $order = placeOrder([[$bread, 1]]);

        $this->actingAs(owner())->get(route('orders.items.create', $order))->assertOk()->assertSee('Thêm vào đơn '.$order->order_number);
        $this->actingAs(owner())
            ->post(route('orders.items.store', $order), ['items' => [productLine($bread, 2)]])
            ->assertRedirect(route('orders.show', $order));

        expect($order->fresh())->total_amount->toBe('75000.00')
            ->and($order->items()->count())->toBe(2)
            ->and($bread->inventory->fresh()->quantity_reserved)->toBe(3);
    });

    test('new items send a ready order back to the kitchen', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $order->items()->update(['status' => OrderItemStatus::Ready]);
        $order->syncStatusWithItems();
        expect($order->fresh()->status)->toBe(OrderStatus::Ready);

        $this->actingAs(owner())->post(route('orders.items.store', $order), ['items' => [productLine(Product::factory()->create())]]);

        expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
    });

    test('nothing can be added to a closed order', function () {
        $order = Order::factory()->status(OrderStatus::Completed)->create();

        $this->actingAs(owner())->get(route('orders.items.create', $order))->assertRedirect(route('orders.show', $order));
        $this->actingAs(owner())
            ->post(route('orders.items.store', $order), ['items' => [productLine(Product::factory()->create())]])
            ->assertSessionHasErrors('items');
    });

    test('an item the kitchen has not started can be taken off', function () {
        $bread = Product::factory()->stocked(onHand: 10)->create(['selling_price' => 25000]);
        $coffee = Product::factory()->create(['selling_price' => 30000]);
        $order = placeOrder([[$bread, 2], [$coffee, 1]]);
        $breadLine = $order->items()->where('product_id', $bread->id)->first();

        $this->actingAs(owner())
            ->delete(route('orders.items.destroy', [$order, $breadLine]))
            ->assertSessionHas('status');

        expect($breadLine->fresh()->status)->toBe(OrderItemStatus::Cancelled)
            ->and($order->fresh()->total_amount)->toBe('30000.00')
            ->and($bread->inventory->fresh()->quantity_reserved)->toBe(0);
    });

    test('an item already being made cannot be taken off', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $item = $order->items()->first();
        $item->update(['status' => OrderItemStatus::Preparing]);

        $this->actingAs(owner())->delete(route('orders.items.destroy', [$order, $item]))->assertSessionHas('error');

        expect($item->fresh()->status)->toBe(OrderItemStatus::Preparing);
    });

    test('an item of another order cannot be reached through this order', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $otherItem = placeOrder([[Product::factory()->create()]])->items()->first();

        $this->actingAs(owner())->delete(route('orders.items.destroy', [$order, $otherItem]))->assertNotFound();
    });
});

describe('status', function () {
    test('an order sent by a customer is confirmed by staff', function () {
        $order = placeOrder([[Product::factory()->create()]], status: OrderStatus::Pending);

        $this->actingAs(owner())->put(route('orders.confirm', $order))->assertSessionHas('status');

        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
    });

    test('finished items are served and the order waits for payment', function () {
        $order = placeOrder([[Product::factory()->create()], [Product::factory()->create()]]);
        $order->items()->update(['status' => OrderItemStatus::Ready]);
        $order->syncStatusWithItems();

        $this->actingAs(owner())->put(route('orders.serve', $order))->assertSessionHas('status', "Đã mang ra đủ món cho đơn {$order->order_number}. Chờ khách thanh toán.");

        expect($order->fresh()->status)->toBe(OrderStatus::Served)
            ->and($order->items()->whereNull('served_at')->count())->toBe(0);
    });

    test('serving only some items keeps the order in the kitchen', function () {
        $order = placeOrder([[Product::factory()->create()], [Product::factory()->create()]]);
        $order->items()->first()->update(['status' => OrderItemStatus::Ready]);
        $order->syncStatusWithItems();

        $this->actingAs(owner())->put(route('orders.serve', $order))->assertSessionHas('status', 'Đã mang ra các món làm xong; vẫn còn món đang làm.');

        expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
    });

    test('paying a served order completes it: stock out, sale logged, table to clean', function () {
        $owner = owner();
        $table = DiningTable::factory()->create();
        $bread = Product::factory()->stocked(onHand: 10)->create(['selling_price' => 20000]);
        $order = placeOrder([[$bread, 3]], $table);
        $order->items()->update(['status' => OrderItemStatus::Served]);
        $order->syncStatusWithItems();

        $this->actingAs($owner)->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 60000]);

        expect($order->fresh())->status->toBe(OrderStatus::Completed)->completed_at->not->toBeNull()
            ->and($bread->inventory->fresh())->quantity_on_hand->toBe(7)->quantity_reserved->toBe(0)
            ->and($table->fresh()->status)->toBe(TableStatus::Dirty);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $bread->id,
            'transaction_type' => InventoryTransactionType::Sale->value,
            'quantity' => -3,
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'created_by' => $owner->id,
        ]);
    });

    test('a paid order completes once everything is served', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 20000])]]);

        $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 20000]);
        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);

        $order->items()->update(['status' => OrderItemStatus::Ready]);
        $order->syncStatusWithItems();

        $this->actingAs(owner())
            ->put(route('orders.serve', $order))
            ->assertSessionHas('status', "Đã mang ra đủ món. Đơn {$order->order_number} đã thanh toán nên đã hoàn thành.");

        expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    });

    test('a served order waits for payment', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $order->items()->update(['status' => OrderItemStatus::Ready]);
        $order->syncStatusWithItems();

        $this->actingAs(owner())->put(route('orders.serve', $order))->assertSessionHas('status', "Đã mang ra đủ món cho đơn {$order->order_number}. Chờ khách thanh toán.");

        expect($order->fresh()->status)->toBe(OrderStatus::Served);
    });
});

describe('cancelling', function () {
    test('cancelling releases stock and frees the table', function () {
        $owner = owner();
        $table = DiningTable::factory()->create();
        $bread = Product::factory()->stocked(onHand: 10)->create();
        $order = placeOrder([[$bread, 4]], $table);

        $this->actingAs($owner)
            ->put(route('orders.cancel', $order), ['cancelled_reason' => 'Khách đổi ý'])
            ->assertSessionHas('status', "Đã hủy đơn {$order->order_number}.");

        expect($order->fresh())
            ->status->toBe(OrderStatus::Cancelled)
            ->cancelled_reason->toBe('Khách đổi ý')
            ->cancelled_by->toBe($owner->id)
            ->and($order->items()->first()->status)->toBe(OrderItemStatus::Cancelled)
            ->and($bread->inventory->fresh())->quantity_reserved->toBe(0)->quantity_on_hand->toBe(10)
            ->and($table->fresh()->status)->toBe(TableStatus::Available);
        $this->assertDatabaseCount('inventory_transactions', 0);
    });

    test('a table stays occupied while another order is still open on it', function () {
        $table = DiningTable::factory()->status(TableStatus::Occupied)->create();
        $order = Order::factory()->create(['table_id' => $table->id]);
        Order::factory()->create(['table_id' => $table->id]);

        $this->actingAs(owner())->put(route('orders.cancel', $order), ['cancelled_reason' => 'Nhập nhầm']);

        expect($table->fresh()->status)->toBe(TableStatus::Occupied);
    });

    test('a reason is required', function () {
        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(owner())
            ->put(route('orders.cancel', $order), [])
            ->assertSessionHasErrorsIn('cancel', ['cancelled_reason' => 'Vui lòng ghi lý do hủy đơn.']);
    });

    test('a completed order cannot be cancelled', function () {
        $order = Order::factory()->status(OrderStatus::Completed)->create();

        $this->actingAs(owner())->put(route('orders.cancel', $order), ['cancelled_reason' => 'Muộn'])->assertSessionHasErrors('cancelled_reason');

        expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    });

    test('staff without orders.cancel cannot cancel', function () {
        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(staffWithPermissions('orders.view', 'orders.update'))
            ->put(route('orders.cancel', $order), ['cancelled_reason' => 'Thử'])
            ->assertForbidden();
    });
});

describe('discount', function () {
    test('a discount lowers the total and needs a reason', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 50000])]]);

        $this->actingAs(owner())
            ->put(route('orders.discount.update', $order), ['discount_amount' => '10000'])
            ->assertSessionHasErrorsIn('discount', ['discount_reason' => 'Vui lòng ghi lý do giảm giá.']);

        $this->actingAs(owner())->put(route('orders.discount.update', $order), ['discount_amount' => '10000', 'discount_reason' => 'Khách quen']);

        expect($order->fresh())->discount_amount->toBe('10000.00')->discount_reason->toBe('Khách quen')->total_amount->toBe('40000.00');
    });

    test('the discount cannot exceed the subtotal', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 50000])]]);

        $this->actingAs(owner())
            ->put(route('orders.discount.update', $order), ['discount_amount' => '60000', 'discount_reason' => 'Lỗi'])
            ->assertSessionHasErrorsIn('discount', ['discount_amount' => 'Không được giảm quá tổng tiền món.']);
    });

    test('staff without orders.discount cannot give discounts', function () {
        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(staffWithPermissions('orders.view', 'orders.update'))
            ->put(route('orders.discount.update', $order), ['discount_amount' => '1000', 'discount_reason' => 'Bạn bè'])
            ->assertForbidden();
    });
});
