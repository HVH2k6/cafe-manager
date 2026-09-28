<?php

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductBundle;
use App\OrderStatus;
use App\TableStatus;

describe('access', function () {
    test('guests are sent to the login screen', function () {
        $this->get(route('orders.index'))->assertRedirect(route('login'));
    });

    test('staff who may only view orders cannot create them', function () {
        $viewer = staffWithPermissions('orders.view');
        $product = Product::factory()->create();

        $this->actingAs($viewer)->get(route('orders.index'))->assertOk();
        $this->actingAs($viewer)->get(route('orders.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('orders.store'), ['order_type' => 'takeaway', 'items' => [productLine($product)]])->assertForbidden();

        expect(Order::count())->toBe(0);
    });
});

describe('POS', function () {
    test('the POS lists what is on sale and the tables', function () {
        Product::factory()->create(['name' => 'Cappuccino']);
        Product::factory()->create(['name' => 'Món ngừng bán', 'is_available' => false]);
        DiningTable::factory()->create(['table_no' => 7]);

        $this->actingAs(owner())
            ->get(route('orders.create'))
            ->assertOk()
            ->assertSee('Cappuccino')
            ->assertDontSee('Món ngừng bán')
            ->assertSee('Bàn 7');
    });

    test('a table with an open order points to that order', function () {
        $table = DiningTable::factory()->create(['table_no' => 3]);
        $order = Order::factory()->create(['table_id' => $table->id, 'order_number' => 'ORD-2026-0042']);

        $this->actingAs(owner())
            ->get(route('orders.create'))
            ->assertSee('đang có đơn ORD-2026-0042')
            ->assertSee(route('orders.items.create', $order), false);
    });
});

describe('placing an order', function () {
    test('a dine-in order is priced from the menu, numbered and seats the table', function () {
        $this->travelTo(now()->setDate(2026, 9, 26)->setTime(9, 0));
        $owner = owner();
        $table = DiningTable::factory()->create(['table_no' => 3]);
        $latte = Product::factory()->create(['name' => 'Latte', 'selling_price' => 35000, 'prep_time_minutes' => 7]);
        $large = $latte->variants()->create(['name' => 'Size', 'value' => 'Lớn', 'price_modifier' => 10000]);
        $lessSugar = $latte->variants()->create(['name' => 'Đường', 'value' => 'Ít', 'price_modifier' => 0]);
        $bread = Product::factory()->create(['selling_price' => 25000]);

        $response = $this->actingAs($owner)->post(route('orders.store'), [
            'order_type' => 'dine_in',
            'table_id' => $table->id,
            'customer_name' => 'Chị Mai',
            'items' => [
                productLine($latte, 2, [$large->id, $lessSugar->id], ' ít đá '),
                productLine($bread, 1) + ['unit_price' => 1],
            ],
        ]);

        $order = Order::with('items')->sole();
        $response->assertRedirect(route('orders.show', $order))->assertSessionHas('status', 'Đã tạo đơn ORD-2026-0001 — 115.000 đ.');

        expect($order)
            ->order_number->toBe('ORD-2026-0001')
            ->status->toBe(OrderStatus::Confirmed)
            ->user_id->toBe($owner->id)
            ->customer_name->toBe('Chị Mai')
            ->subtotal->toBe('115000.00')
            ->total_amount->toBe('115000.00')
            ->estimated_ready_at->toDateTimeString()->toBe('2026-09-26 09:07:00')
            ->and($order->items[0])
            ->unit_price->toBe('45000.00')
            ->quantity->toBe(2)
            ->special_instructions->toBe('ít đá')
            ->variant_ids->toBe([$large->id, $lessSugar->id])
            ->and($order->items[0]->variantSummary())->toBe('Size: Lớn · Đường: Ít')
            ->and($order->items[1]->unit_price)->toBe('25000.00')
            ->and($table->fresh()->status)->toBe(TableStatus::Occupied);
    });

    test('order numbers follow on within the year and past 9999', function () {
        $this->travelTo(now()->setDate(2026, 3, 1));
        Order::factory()->create(['order_number' => 'ORD-2025-0500']);
        Order::factory()->create(['order_number' => 'ORD-2026-9999']);
        $product = Product::factory()->create();

        $this->actingAs(owner())->post(route('orders.store'), ['order_type' => 'takeaway', 'items' => [productLine($product)]]);

        expect(Order::latest('id')->first()->order_number)->toBe('ORD-2026-10000');
    });

    test('a takeaway order needs no table', function () {
        $this->actingAs(owner())
            ->post(route('orders.store'), ['order_type' => 'takeaway', 'table_id' => '', 'items' => [productLine(Product::factory()->create())]])
            ->assertSessionHasNoErrors();

        expect(Order::sole())->table_id->toBeNull()->placeLabel()->toBe('Mang về');
    });

    test('a combo is sold at its price', function () {
        $bundle = ProductBundle::factory()->create(['bundle_price' => 45000]);
        $bundle->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1]);

        $this->actingAs(owner())->post(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['type' => 'bundle', 'id' => $bundle->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        expect(Order::sole())->total_amount->toBe('90000.00');
    });

    test('invalid orders are rejected and nothing is saved', function (Closure $payload, string $field, string $message) {
        $table = DiningTable::factory()->create(['table_no' => 3]);
        $payload = $payload($table);
        $statusBefore = $table->fresh()->status;

        $this->actingAs(owner())
            ->post(route('orders.store'), $payload)
            ->assertSessionHasErrors([$field => $message]);

        expect(Order::count())->toBe(0)
            ->and($table->fresh()->status)->toBe($statusBefore);
    })->with([
        'no items' => [fn () => ['order_type' => 'takeaway', 'items' => []], 'items', 'Chưa có món nào trong đơn.'],
        'dine-in without a table' => [fn () => ['order_type' => 'dine_in', 'items' => [productLine(Product::factory()->create())]], 'table_id', 'Vui lòng chọn bàn.'],
        'a product off sale' => [fn ($table) => ['order_type' => 'dine_in', 'table_id' => $table->id, 'items' => [productLine(Product::factory()->create(['name' => 'Bạc xỉu', 'is_available' => false]))]], 'items.0', '“Bạc xỉu” đang ngừng bán.'],
        'another product\'s option' => [fn () => ['order_type' => 'takeaway', 'items' => [productLine(Product::factory()->create(['name' => 'Latte']), 1, [Product::factory()->create()->variants()->create(['name' => 'Size', 'value' => 'L'])->id])]], 'items.0', 'Lựa chọn không hợp lệ cho “Latte”.'],
        'two sizes at once' => [function () {
            $latte = Product::factory()->create(['name' => 'Latte']);

            return ['order_type' => 'takeaway', 'items' => [productLine($latte, 1, [
                $latte->variants()->create(['name' => 'Size', 'value' => 'M'])->id,
                $latte->variants()->create(['name' => 'Size', 'value' => 'L'])->id,
            ])]];
        }, 'items.0', '“Latte”: chỉ chọn một Size.'],
        'a paused combo' => [fn () => ['order_type' => 'takeaway', 'items' => [['type' => 'bundle', 'id' => ProductBundle::factory()->create(['name' => 'Combo sáng', 'is_active' => false])->id, 'quantity' => 1]]], 'items.0', 'Combo “Combo sáng” đang tạm ngừng.'],
        'a table under maintenance' => [function ($table) {
            $table->update(['status' => TableStatus::Maintenance]);

            return ['order_type' => 'dine_in', 'table_id' => $table->id, 'items' => [productLine(Product::factory()->create())]];
        }, 'table_id', 'Bàn 3 đang bảo trì.'],
    ]);

    test('a table that already has an open order is refused', function () {
        $table = DiningTable::factory()->status(TableStatus::Occupied)->create(['table_no' => 3]);
        Order::factory()->create(['table_id' => $table->id, 'order_number' => 'ORD-2026-0007']);

        $this->actingAs(owner())
            ->post(route('orders.store'), ['order_type' => 'dine_in', 'table_id' => $table->id, 'items' => [productLine(Product::factory()->create())]])
            ->assertSessionHasErrors(['table_id' => 'Bàn 3 đang có đơn ORD-2026-0007. Hãy thêm món vào đơn đó.']);

        expect(Order::count())->toBe(1);
    });
});

describe('stock', function () {
    test('tracked stock is held for the order, untracked products are ignored', function () {
        $bread = Product::factory()->stocked(onHand: 10)->create();
        $coffee = Product::factory()->create();

        $this->actingAs(owner())->post(route('orders.store'), ['order_type' => 'takeaway', 'items' => [productLine($bread, 3), productLine($coffee, 2)]]);

        expect($bread->inventory->fresh())
            ->quantity_on_hand->toBe(10)
            ->quantity_reserved->toBe(3)
            ->and($coffee->fresh()->inventory)->toBeNull();
    });

    test('a combo holds stock for each product in it', function () {
        $bread = Product::factory()->stocked(onHand: 10)->create();
        $bundle = ProductBundle::factory()->create();
        $bundle->items()->createMany([
            ['product_id' => $bread->id, 'quantity' => 2],
            ['product_id' => Product::factory()->create()->id, 'quantity' => 1],
        ]);

        $this->actingAs(owner())->post(route('orders.store'), [
            'order_type' => 'takeaway',
            'items' => [['type' => 'bundle', 'id' => $bundle->id, 'quantity' => 2], productLine($bread, 1)],
        ])->assertSessionHasNoErrors();

        expect($bread->inventory->fresh()->quantity_reserved)->toBe(5);
    });

    test('more than is available cannot be ordered', function () {
        $bread = Product::factory()->stocked(onHand: 5)->create(['name' => 'Bánh mì']);
        $bread->inventory->forceFill(['quantity_reserved' => 3])->save();

        $this->actingAs(owner())
            ->post(route('orders.store'), ['order_type' => 'takeaway', 'items' => [productLine($bread, 3)]])
            ->assertSessionHasErrors(['items' => '“Bánh mì” chỉ còn 2 cái.']);

        expect(Order::count())->toBe(0)
            ->and($bread->inventory->fresh()->quantity_reserved)->toBe(3);
    });

    test('a sold-out product cannot be ordered', function () {
        $bread = Product::factory()->stocked(onHand: 0)->create(['name' => 'Bánh mì']);

        $this->actingAs(owner())
            ->post(route('orders.store'), ['order_type' => 'takeaway', 'items' => [productLine($bread)]])
            ->assertSessionHasErrors(['items' => '“Bánh mì” đã hết hàng.']);
    });
});
