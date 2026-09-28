<?php

use App\InventoryTransactionType;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;

describe('access', function () {
    test('staff without inventory.view cannot see stock', function () {
        $product = Product::factory()->stocked()->create();

        $this->actingAs(staffWithPermissions('orders.view'))->get(route('inventory.index'))->assertForbidden();
        $this->actingAs(staffWithPermissions('orders.view'))->get(route('inventory.show', $product))->assertForbidden();
    });

    test('staff who may only view cannot change stock', function () {
        $viewer = staffWithPermissions('inventory.view');
        $product = Product::factory()->stocked(onHand: 10)->create();

        $this->actingAs($viewer)->get(route('inventory.show', $product))->assertOk()->assertDontSee('Ghi nhận thay đổi');
        $this->actingAs($viewer)->post(route('inventory.transactions.store', $product), ['transaction_type' => 'purchase', 'quantity' => 5])->assertForbidden();
        $this->actingAs($viewer)->put(route('inventory.update', $product), ['unit_of_measure' => 'cái', 'min_stock' => 1, 'reorder_level' => 2, 'max_stock' => 3])->assertForbidden();

        expect($product->inventory->fresh()->quantity_on_hand)->toBe(10);
    });
});

describe('list', function () {
    test('the list shows tracked and untracked products with a summary', function () {
        Product::factory()->stocked(onHand: 30)->create(['name' => 'Nước suối']);
        Product::factory()->stocked(onHand: 2, minStock: 5)->create(['name' => 'Bánh su kem']);
        Product::factory()->stocked(onHand: 0)->create(['name' => 'Bánh mì que']);
        Product::factory()->create(['name' => 'Cappuccino']);

        $this->actingAs(owner())
            ->get(route('inventory.index'))
            ->assertOk()
            ->assertSee(['Nước suối', 'Bánh su kem', 'Bánh mì que', 'Cappuccino'])
            ->assertSee('Sắp hết · 2 cái')
            ->assertSee('Hết hàng')
            ->assertSee('Không theo dõi')
            ->assertViewHas('summary', ['tracked' => 3, 'low' => 1, 'out' => 1]);
    });

    test('the stock filter narrows the list', function (string $filter, array $visible, array $hidden) {
        Product::factory()->stocked(onHand: 30)->create(['name' => 'Nước suối']);
        Product::factory()->stocked(onHand: 2, minStock: 5)->create(['name' => 'Bánh su kem']);
        Product::factory()->stocked(onHand: 0)->create(['name' => 'Bánh mì que']);
        Product::factory()->create(['name' => 'Cappuccino']);

        $this->actingAs(owner())
            ->get(route('inventory.index', ['stock' => $filter]))
            ->assertSee($visible)
            ->assertDontSee($hidden);
    })->with([
        'tracked' => ['tracked', ['Nước suối', 'Bánh su kem', 'Bánh mì que'], ['Cappuccino']],
        'low' => ['low', ['Bánh su kem', 'Bánh mì que'], ['Nước suối', 'Cappuccino']],
        'out' => ['out', ['Bánh mì que'], ['Nước suối', 'Bánh su kem', 'Cappuccino']],
        'untracked' => ['untracked', ['Cappuccino'], ['Nước suối', 'Bánh su kem', 'Bánh mì que']],
    ]);

    test('the sidebar shows how many products are running low', function () {
        Product::factory()->stocked(onHand: 1, minStock: 5)->count(2)->create();
        Product::factory()->stocked(onHand: 50)->create();

        $this->actingAs(owner())
            ->get(route('dashboard'))
            ->assertSee('title="2 sản phẩm sắp hết"', false);
    });
});

describe('tracking', function () {
    test('stock tracking can be started for a product', function () {
        $product = Product::factory()->create(['name' => 'Bánh flan']);

        $this->actingAs(owner())
            ->post(route('inventory.store', $product))
            ->assertRedirect(route('inventory.show', $product));

        expect($product->fresh()->inventory)->quantity_on_hand->toBe(0);
    });

    test('an untracked product page offers to start tracking', function () {
        $product = Product::factory()->create();

        $this->actingAs(owner())->get(route('inventory.show', $product))->assertOk()->assertSee('Bắt đầu theo dõi kho');
    });

    test('thresholds and unit can be changed', function () {
        $product = Product::factory()->stocked()->create();

        $this->actingAs(owner())
            ->put(route('inventory.update', $product), ['unit_of_measure' => 'chai', 'min_stock' => 10, 'reorder_level' => 24, 'max_stock' => 120])
            ->assertSessionHas('status', 'Đã lưu ngưỡng tồn kho.');

        expect($product->inventory->fresh())
            ->unit_of_measure->toBe('chai')
            ->min_stock->toBe(10)
            ->reorder_level->toBe(24)
            ->max_stock->toBe(120);
    });

    test('thresholds must be in order', function () {
        $product = Product::factory()->stocked()->create();

        $this->actingAs(owner())
            ->put(route('inventory.update', $product), ['unit_of_measure' => 'cái', 'min_stock' => 10, 'reorder_level' => 5, 'max_stock' => 4])
            ->assertSessionHasErrors([
                'reorder_level' => 'Mức cần đặt hàng phải lớn hơn hoặc bằng mức tồn tối thiểu.',
                'max_stock' => 'Mức tồn tối đa phải lớn hơn hoặc bằng mức cần đặt hàng.',
            ]);
    });
});

describe('movements', function () {
    test('each movement changes stock and is logged', function (InventoryTransactionType $type, int $quantity, int $expectedChange, int $expectedOnHand) {
        $this->freezeSecond();
        $owner = owner();
        $product = Product::factory()->stocked(onHand: 10)->create();

        $this->actingAs($owner)
            ->post(route('inventory.transactions.store', $product), ['transaction_type' => $type->value, 'quantity' => $quantity, 'notes' => 'Ghi chú'])
            ->assertRedirect(route('inventory.show', $product))
            ->assertSessionHasNoErrors();

        $inventory = $product->inventory->fresh();
        expect($inventory->quantity_on_hand)->toBe($expectedOnHand)
            ->and($inventory->last_restock?->equalTo(now()) ?? false)->toBe($type === InventoryTransactionType::Purchase);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'transaction_type' => $type->value,
            'quantity' => $expectedChange,
            'notes' => 'Ghi chú',
            'created_by' => $owner->id,
        ]);
    })->with([
        'purchase adds' => [InventoryTransactionType::Purchase, 24, 24, 34],
        'count sets the counted amount' => [InventoryTransactionType::Adjustment, 7, -3, 7],
        'count can find more than expected' => [InventoryTransactionType::Adjustment, 12, 2, 12],
        'count can find none left' => [InventoryTransactionType::Adjustment, 0, -10, 0],
        'damage removes' => [InventoryTransactionType::Damage, 2, -2, 8],
        'expiry removes' => [InventoryTransactionType::Expiry, 10, -10, 0],
    ]);

    test('the product page shows the history newest first', function () {
        $product = Product::factory()->stocked(onHand: 10)->create();
        $owner = owner();
        $this->actingAs($owner)->post(route('inventory.transactions.store', $product), ['transaction_type' => 'purchase', 'quantity' => 5, 'notes' => 'Lô sáng']);
        $this->actingAs($owner)->post(route('inventory.transactions.store', $product), ['transaction_type' => 'damage', 'quantity' => 1, 'notes' => 'Rơi vỡ']);

        $this->actingAs($owner)
            ->get(route('inventory.show', $product))
            ->assertOk()
            ->assertSeeInOrder(['Hư hỏng', '-1', 'Rơi vỡ', $owner->name, 'Nhập hàng', '+5', 'Lô sáng']);
    });

    test('more than is in stock cannot be removed', function () {
        $product = Product::factory()->stocked(onHand: 3)->create();

        $this->actingAs(owner())
            ->post(route('inventory.transactions.store', $product), ['transaction_type' => 'damage', 'quantity' => 5])
            ->assertSessionHasErrors(['quantity' => 'Chỉ còn 3 cái trong kho.']);

        expect($product->inventory->fresh()->quantity_on_hand)->toBe(3);
        $this->assertDatabaseCount('inventory_transactions', 0);
    });

    test('a count equal to the current stock is rejected', function () {
        $product = Product::factory()->stocked(onHand: 8)->create();

        $this->actingAs(owner())
            ->post(route('inventory.transactions.store', $product), ['transaction_type' => 'adjustment', 'quantity' => 8])
            ->assertSessionHasErrors(['quantity' => 'Số đếm được bằng đúng số tồn hiện tại, không cần điều chỉnh.']);
    });

    test('sales and returns cannot be recorded by hand', function (string $type) {
        $product = Product::factory()->stocked(onHand: 10)->create();

        $this->actingAs(owner())
            ->post(route('inventory.transactions.store', $product), ['transaction_type' => $type, 'quantity' => 1])
            ->assertSessionHasErrors(['transaction_type' => 'Loại giao dịch không hợp lệ.']);
    })->with(['sale', 'return']);

    test('the quantity must be positive except for a count', function () {
        $product = Product::factory()->stocked(onHand: 10)->create();

        $this->actingAs(owner())
            ->post(route('inventory.transactions.store', $product), ['transaction_type' => 'purchase', 'quantity' => 0])
            ->assertSessionHasErrors(['quantity' => 'Số lượng phải từ 1 trở lên.']);
    });

    test('recording a movement for an untracked product starts tracking it', function () {
        $product = Product::factory()->create();

        $this->actingAs(owner())->post(route('inventory.transactions.store', $product), ['transaction_type' => 'purchase', 'quantity' => 12]);

        expect(Inventory::firstWhere('product_id', $product->id)->quantity_on_hand)->toBe(12);
    });

    test('the person who recorded a movement is shown', function () {
        $manager = staffWithPermissions('inventory.view', 'inventory.update');
        $manager->update(['name' => 'Lê Quản Lý']);
        $product = Product::factory()->stocked()->create();

        $this->actingAs($manager)->post(route('inventory.transactions.store', $product), ['transaction_type' => 'purchase', 'quantity' => 1]);

        expect($product->inventoryTransactions()->first()->creator)->toBeInstanceOf(User::class)->name->toBe('Lê Quản Lý');
    });
});
