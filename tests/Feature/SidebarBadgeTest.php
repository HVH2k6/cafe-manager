<?php

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\OrderItemStatus;
use App\OrderStatus;
use App\TableStatus;

test('guests cannot read the counts', function () {
    $this->getJson(route('sidebar-badges'))->assertUnauthorized();
});

test('the owner gets every count', function () {
    Order::factory()->status(OrderStatus::Pending)->count(2)->create();
    placeOrder([[Product::factory()->create(), 1], [Product::factory()->create(), 1]]); // 2 items for the kitchen
    DiningTable::factory()->create(['status' => TableStatus::Dirty]);
    Product::factory()->stocked(onHand: 1)->create(); // below the default minimum (5)

    $this->actingAs(owner())->getJson(route('sidebar-badges'))
        ->assertOk()
        ->assertExactJson(['pending' => 2, 'kitchen' => 2, 'dirty' => 1, 'low_stock' => 1]);
});

test('each count needs its permission', function () {
    Order::factory()->status(OrderStatus::Pending)->create();

    $this->actingAs(staffWithPermissions('kitchen.view'))->getJson(route('sidebar-badges'))
        ->assertExactJson(['kitchen' => 0]);

    $this->actingAs(staffWithPermissions('orders.view', 'tables.view'))->getJson(route('sidebar-badges'))
        ->assertExactJson(['pending' => 1, 'dirty' => 0]);
});

test('the kitchen count leaves out unconfirmed orders and finished items', function () {
    placeOrder([[Product::factory()->create()]], status: OrderStatus::Pending);
    $order = placeOrder([[Product::factory()->create()], [Product::factory()->create()]]);
    $order->items()->first()->update(['status' => OrderItemStatus::Ready]);

    $this->actingAs(owner())->getJson(route('sidebar-badges'))->assertJsonPath('kitchen', 1);
});

test('the sidebar renders the counts and the live settings', function () {
    DiningTable::factory()->count(3)->create(['status' => TableStatus::Dirty]);
    $cook = staffWithPermissions('kitchen.view', 'tables.view');

    $this->actingAs($cook)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('title="3 bàn cần dọn"', false)
        ->assertSee('data-badges="{&quot;kitchen&quot;:0,&quot;dirty&quot;:3}"', false)
        ->assertSee('&quot;channels&quot;:[&quot;kitchen&quot;,&quot;tables&quot;]', false)
        ->assertDontSee('đơn chờ xác nhận');
});

test('a user without any count gets an empty object', function () {
    $this->actingAs(staffWithPermissions('products.view'))->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-badges="{}"', false);
});
