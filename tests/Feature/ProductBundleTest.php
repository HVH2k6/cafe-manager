<?php

use App\Models\Product;
use App\Models\ProductBundle;

test('staff who may only view cannot change combos', function () {
    $viewer = staffWithPermissions('products.view');
    $bundle = ProductBundle::factory()->create();

    $this->actingAs($viewer)->get(route('bundles.index'))->assertOk();
    $this->actingAs($viewer)->post(route('bundles.store'), [])->assertForbidden();
    $this->actingAs($viewer)->delete(route('bundles.destroy', $bundle))->assertForbidden();
});

test('combos are listed with their items and saving', function () {
    $coffee = Product::factory()->create(['name' => 'Cà phê đen']);
    $bundle = ProductBundle::factory()->create(['name' => 'Combo sáng', 'bundle_price' => 40000, 'individual_price' => 50000, 'discount_amount' => 10000]);
    $bundle->items()->create(['product_id' => $coffee->id, 'quantity' => 2]);

    $this->actingAs(owner())
        ->get(route('bundles.index'))
        ->assertOk()
        ->assertSee('Combo sáng')
        ->assertSee('Cà phê đen')
        ->assertSee('×2')
        ->assertSee('10.000 đ');
});

test('the create and edit forms render', function () {
    $bundle = ProductBundle::factory()->create(['name' => 'Combo sáng']);

    $this->actingAs(owner())->get(route('bundles.create'))->assertOk()->assertSee('Món trong combo');
    $this->actingAs(owner())->get(route('bundles.edit', $bundle))->assertOk()->assertSee('Combo sáng');
});

test('a combo stores its items and the price compared with buying separately', function () {
    $coffee = Product::factory()->create(['selling_price' => 29000]);
    $bread = Product::factory()->create(['selling_price' => 25000]);

    $this->actingAs(owner())
        ->post(route('bundles.store'), [
            'name' => 'Combo Cà phê + Bánh mì',
            'bundle_price' => '45000',
            'is_active' => '1',
            'items' => [
                ['product_id' => $coffee->id, 'quantity' => '1'],
                ['product_id' => $bread->id, 'quantity' => '1'],
                ['product_id' => '', 'quantity' => '1'],
            ],
        ])
        ->assertRedirect(route('bundles.index'));

    $bundle = ProductBundle::first();

    expect($bundle)
        ->slug->toBe('combo-ca-phe-banh-mi')
        ->bundle_price->toBe('45000.00')
        ->individual_price->toBe('54000.00')
        ->discount_amount->toBe('9000.00')
        ->and($bundle->items->pluck('product_id')->all())->toBe([$coffee->id, $bread->id]);
});

test('invalid combos are rejected', function (Closure $items, string $field, string $message) {
    $coffee = Product::factory()->create(['selling_price' => 20000]);
    $bread = Product::factory()->create(['selling_price' => 20000]);

    $this->actingAs(owner())
        ->post(route('bundles.store'), ['name' => 'Combo', 'bundle_price' => '30000', 'items' => $items($coffee, $bread)])
        ->assertSessionHasErrors([$field => $message]);

    expect(ProductBundle::count())->toBe(0);
})->with([
    'no items' => [fn () => [], 'items', 'Vui lòng chọn món trong combo.'],
    'a single item' => [fn ($coffee) => [['product_id' => $coffee->id, 'quantity' => 1]], 'items', 'Combo cần ít nhất 2 món.'],
    'the same product twice' => [fn ($coffee) => [['product_id' => $coffee->id, 'quantity' => 1], ['product_id' => $coffee->id, 'quantity' => 1]], 'items.0.product_id', 'Mỗi món chỉ chọn một lần, hãy tăng số lượng thay vì thêm dòng trùng.'],
]);

test('a combo cannot cost more than its items bought separately', function () {
    $coffee = Product::factory()->create(['selling_price' => 20000]);
    $bread = Product::factory()->create(['selling_price' => 15000]);

    $this->actingAs(owner())
        ->post(route('bundles.store'), [
            'name' => 'Combo đắt',
            'bundle_price' => '40000',
            'items' => [['product_id' => $coffee->id, 'quantity' => 1], ['product_id' => $bread->id, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors(['bundle_price' => 'Giá combo không được cao hơn tổng giá mua lẻ (35.000 đ).']);
});

test('updating a combo replaces its items', function () {
    $coffee = Product::factory()->create(['selling_price' => 20000]);
    $bread = Product::factory()->create(['selling_price' => 20000]);
    $cake = Product::factory()->create(['selling_price' => 30000]);
    $bundle = ProductBundle::factory()->create();
    $bundle->items()->createMany([['product_id' => $coffee->id, 'quantity' => 1], ['product_id' => $bread->id, 'quantity' => 1]]);

    $this->actingAs(owner())->put(route('bundles.update', $bundle), [
        'name' => $bundle->name,
        'bundle_price' => '60000',
        'items' => [['product_id' => $coffee->id, 'quantity' => 2], ['product_id' => $cake->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    expect($bundle->fresh()->items->map->only('product_id', 'quantity')->all())->toBe([
        ['product_id' => $coffee->id, 'quantity' => 2],
        ['product_id' => $cake->id, 'quantity' => 1],
    ])->and($bundle->fresh()->individual_price)->toBe('70000.00');
});

test('a combo can be deleted with its items', function () {
    $bundle = ProductBundle::factory()->create(['name' => 'Combo sáng']);
    $bundle->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 2]);

    $this->actingAs(owner())
        ->delete(route('bundles.destroy', $bundle))
        ->assertSessionHas('status', 'Đã xóa combo “Combo sáng”.');

    $this->assertModelMissing($bundle);
    $this->assertDatabaseMissing('product_bundle_items', ['bundle_id' => $bundle->id]);
});
