<?php

use App\Models\Order;
use App\Models\Product;
use App\OrderItemStatus;
use App\OrderStatus;

test('staff without kitchen.view cannot open the board', function () {
    $this->actingAs(staffWithPermissions('orders.view'))->get(route('kitchen.index'))->assertForbidden();
});

test('staff who may only view the board cannot update items', function () {
    $item = placeOrder([[Product::factory()->create()]])->items()->first();

    $this->actingAs(staffWithPermissions('kitchen.view'))
        ->put(route('kitchen.items.update', $item), ['status' => 'preparing'])
        ->assertForbidden();

    expect($item->fresh()->status)->toBe(OrderItemStatus::Pending);
});

test('the board shows confirmed orders with items to make, oldest first', function () {
    $first = placeOrder([[Product::factory()->create(['name' => 'Latte'])]]);
    $second = placeOrder([[Product::factory()->create(['name' => 'Bạc xỉu'])]]);
    placeOrder([[Product::factory()->create(['name' => 'Đơn chưa xác nhận'])]], status: OrderStatus::Pending);
    $cancelled = placeOrder([[Product::factory()->create(['name' => 'Đơn đã hủy'])]]);
    $cancelled->update(['status' => OrderStatus::Cancelled]);

    $this->actingAs(owner())
        ->get(route('kitchen.index'))
        ->assertOk()
        ->assertSeeInOrder([$first->order_number, 'Latte', $second->order_number, 'Bạc xỉu'])
        ->assertDontSee('Đơn chưa xác nhận')
        ->assertDontSee('Đơn đã hủy');
});

test('an empty board says so', function () {
    $this->actingAs(owner())->get(route('kitchen.index'))->assertOk()->assertSee('Không còn món nào cần làm');
});

test('starting an item moves the order into preparation', function () {
    $this->freezeSecond();
    $order = placeOrder([[Product::factory()->create()], [Product::factory()->create()]]);
    $item = $order->items()->first();

    $this->actingAs(owner())->put(route('kitchen.items.update', $item), ['status' => 'preparing'])->assertRedirect();

    expect($item->fresh()->status)->toBe(OrderItemStatus::Preparing)
        ->and($order->fresh())->status->toBe(OrderStatus::Preparing)->started_at->equalTo(now())->toBeTrue();
});

test('finishing every item makes the order ready', function () {
    $cook = staffWithPermissions('kitchen.view', 'kitchen.update');
    $order = placeOrder([[Product::factory()->create()], [Product::factory()->create()]]);
    [$first, $second] = $order->items;

    $this->actingAs($cook)->put(route('kitchen.items.update', $first), ['status' => 'ready']);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $this->actingAs($cook)->put(route('kitchen.items.update', $second), ['status' => 'ready']);

    expect($order->fresh()->status)->toBe(OrderStatus::Ready)
        ->and($second->fresh())->prepared_by->toBe($cook->id)->prepared_at->not->toBeNull();
});

test('items of unconfirmed or cancelled orders cannot be worked on', function (OrderStatus $status) {
    $order = placeOrder([[Product::factory()->create()]], status: $status === OrderStatus::Pending ? OrderStatus::Pending : OrderStatus::Confirmed);
    $order->update(['status' => $status]);
    $item = $order->items()->first();

    $this->actingAs(owner())->put(route('kitchen.items.update', $item), ['status' => 'ready'])->assertSessionHas('error');

    expect($item->fresh()->status)->toBe(OrderItemStatus::Pending);
})->with([OrderStatus::Pending, OrderStatus::Cancelled]);

test('a finished item cannot be started again', function () {
    $item = placeOrder([[Product::factory()->create()]])->items()->first();
    $item->update(['status' => OrderItemStatus::Ready]);

    $this->actingAs(owner())->put(route('kitchen.items.update', $item), ['status' => 'preparing'])->assertSessionHas('error');

    expect($item->fresh()->status)->toBe(OrderItemStatus::Ready);
});

test('the kitchen cannot serve or cancel items', function (string $status) {
    $item = placeOrder([[Product::factory()->create()]])->items()->first();

    $this->actingAs(owner())->put(route('kitchen.items.update', $item), ['status' => $status])->assertSessionHasErrors('status');
})->with(['served', 'cancelled', 'pending']);

test('orders keep their open status list in one place', function () {
    expect(Order::factory()->status(OrderStatus::Ready)->create()->status->isOpen())->toBeTrue()
        ->and(OrderStatus::Completed->isOpen())->toBeFalse();
});
