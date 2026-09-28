<?php

use App\Models\Category;
use App\Models\Product;

test('staff without products.view cannot see categories', function () {
    $this->actingAs(staffWithPermissions('orders.view'))->get(route('categories.index'))->assertForbidden();
});

test('staff who may only view cannot change categories', function () {
    $viewer = staffWithPermissions('products.view');
    $category = Category::factory()->create();

    $this->actingAs($viewer)->get(route('categories.index'))->assertOk();
    $this->actingAs($viewer)->post(route('categories.store'), ['name' => 'Trà'])->assertForbidden();
    $this->actingAs($viewer)->put(route('categories.update', $category), ['name' => 'Trà'])->assertForbidden();
    $this->actingAs($viewer)->delete(route('categories.destroy', $category))->assertForbidden();
});

test('categories are listed with their product counts', function () {
    $coffee = Category::factory()->create(['name' => 'Cà phê']);
    Product::factory()->count(3)->for($coffee)->create();

    $this->actingAs(owner())
        ->get(route('categories.index'))
        ->assertOk()
        ->assertSeeInOrder(['Cà phê', '3']);
});

test('the create and edit forms render', function () {
    $category = Category::factory()->create(['name' => 'Cà phê']);

    $this->actingAs(owner())->get(route('categories.create'))->assertOk();
    $this->actingAs(owner())->get(route('categories.edit', $category))->assertOk()->assertSee('Cà phê');
});

test('a category gets a slug from its Vietnamese name', function () {
    $this->actingAs(owner())
        ->post(route('categories.store'), ['name' => ' Cà phê đặc biệt ', 'icon' => '☕', 'is_active' => '1'])
        ->assertRedirect(route('categories.index'));

    $this->assertDatabaseHas('categories', ['name' => 'Cà phê đặc biệt', 'slug' => 'ca-phe-dac-biet', 'icon' => '☕', 'is_active' => true]);
});

test('category names must be unique', function () {
    Category::factory()->create(['name' => 'Trà']);

    $this->actingAs(owner())
        ->post(route('categories.store'), ['name' => 'Trà'])
        ->assertSessionHasErrors(['name' => 'Danh mục này đã tồn tại.']);
});

test('renaming a category updates its slug and unticking hides it', function () {
    $category = Category::factory()->create(['name' => 'Trà']);

    $this->actingAs(owner())->put(route('categories.update', $category), ['name' => 'Trà sữa']);

    expect($category->fresh())->slug->toBe('tra-sua')->is_active->toBeFalse();
});

test('an empty category can be deleted', function () {
    $category = Category::factory()->create();

    $this->actingAs(owner())->delete(route('categories.destroy', $category))->assertRedirect(route('categories.index'));

    $this->assertModelMissing($category);
});

test('a category with products cannot be deleted', function () {
    $category = Category::factory()->create(['name' => 'Bánh']);
    Product::factory()->for($category)->create();

    $this->actingAs(owner())
        ->delete(route('categories.destroy', $category))
        ->assertSessionHas('error', 'Danh mục “Bánh” còn 1 sản phẩm. Hãy chuyển sản phẩm sang danh mục khác hoặc ẩn danh mục này.');

    $this->assertModelExists($category);
});
