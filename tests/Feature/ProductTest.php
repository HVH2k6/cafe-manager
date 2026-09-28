<?php

use App\InventoryTransactionType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBundle;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, mixed>
 */
function validProductData(array $overrides = []): array
{
    return [
        'name' => 'Cà phê sữa đá',
        'category_id' => Category::factory()->create()->id,
        'selling_price' => '29000',
        'cost_price' => '11000',
        'is_available' => '1',
        ...$overrides,
    ];
}

describe('access', function () {
    test('staff without products.view cannot see products', function () {
        $this->actingAs(staffWithPermissions('orders.view'))->get(route('products.index'))->assertForbidden();
    });

    test('staff who may only view cannot change products', function () {
        $viewer = staffWithPermissions('products.view');
        $product = Product::factory()->create();

        $this->actingAs($viewer)->get(route('products.index'))->assertOk();
        $this->actingAs($viewer)->get(route('products.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('products.store'), validProductData())->assertForbidden();
        $this->actingAs($viewer)->put(route('products.update', $product), validProductData())->assertForbidden();
        $this->actingAs($viewer)->delete(route('products.destroy', $product))->assertForbidden();
    });
});

describe('list', function () {
    test('products show price, margin and stock', function () {
        Product::factory()->stocked(onHand: 3, minStock: 5)->create([
            'name' => 'Bánh mì bơ tỏi', 'selling_price' => 25000, 'cost_price' => 10000,
        ]);

        $this->actingAs(owner())
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Bánh mì bơ tỏi')
            ->assertSee('25.000 đ')
            ->assertSee('15.000 đ')
            ->assertSee('Sắp hết · 3 cái');
    });

    test('products can be filtered by name, category and sale status', function () {
        $tea = Category::factory()->create();
        Product::factory()->for($tea)->create(['name' => 'Trà đào cam sả']);
        Product::factory()->create(['name' => 'Cà phê đen', 'is_available' => false]);

        $this->actingAs(owner())->get(route('products.index', ['search' => 'đào']))->assertSee('Trà đào cam sả')->assertDontSee('Cà phê đen');
        $this->actingAs(owner())->get(route('products.index', ['category' => $tea->id]))->assertSee('Trà đào cam sả')->assertDontSee('Cà phê đen');
        $this->actingAs(owner())->get(route('products.index', ['status' => 'unavailable']))->assertSee('Cà phê đen')->assertDontSee('Trà đào cam sả');
    });
});

describe('create', function () {
    test('the create form renders', function () {
        $this->actingAs(owner())->get(route('products.create'))->assertOk()->assertSee('Biến thể');
    });

    test('a product is created with its variants', function () {
        $this->actingAs(owner())
            ->post(route('products.store'), validProductData([
                'sku' => 'CF-SUA-DA',
                'variants' => [
                    ['name' => 'Size', 'value' => 'Nhỏ', 'price_modifier' => '0', 'is_default' => '1'],
                    ['name' => 'Size', 'value' => 'Lớn', 'price_modifier' => '10000'],
                    ['name' => '', 'value' => ''],
                ],
            ]))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('status', 'Đã thêm sản phẩm “Cà phê sữa đá”.');

        $product = Product::firstWhere('sku', 'CF-SUA-DA');

        expect($product)
            ->slug->toBe('ca-phe-sua-da')
            ->selling_price->toBe('29000.00')
            ->base_price->toBe('29000.00')
            ->cost_price->toBe('11000.00')
            ->is_available->toBeTrue()
            ->and($product->inventory)->toBeNull()
            ->and($product->variants->map->only('value', 'price_modifier', 'is_default', 'display_order')->all())->toBe([
                ['value' => 'Nhỏ', 'price_modifier' => '0.00', 'is_default' => true, 'display_order' => 0],
                ['value' => 'Lớn', 'price_modifier' => '10000.00', 'is_default' => false, 'display_order' => 1],
            ]);
    });

    test('ticking stock tracking creates an empty stock record', function () {
        $this->actingAs(owner())->post(route('products.store'), validProductData(['track_stock' => '1', 'unit_of_measure' => 'chai']));

        expect(Product::first()->inventory)
            ->quantity_on_hand->toBe(0)
            ->unit_of_measure->toBe('chai');
    });

    test('an uploaded image is stored on the public disk', function () {
        Storage::fake('public');

        $this->actingAs(owner())->post(route('products.store'), validProductData(['image' => UploadedFile::fake()->image('ly.jpg', 400, 400)]));

        $product = Product::first();
        Storage::disk('public')->assertExists($product->image_url);
        expect($product->imageUrl())->toEndWith($product->image_url);
    });

    test('name, category and price are required', function () {
        $this->actingAs(owner())
            ->post(route('products.store'), [])
            ->assertSessionHasErrors([
                'name' => 'Vui lòng nhập tên sản phẩm.',
                'category_id' => 'Vui lòng chọn danh mục.',
                'selling_price' => 'Vui lòng nhập giá bán.',
            ]);
    });

    test('invalid input is rejected', function (array $overrides, string $field, string $message) {
        Product::factory()->create(['sku' => 'TRUNG']);

        $this->actingAs(owner())
            ->post(route('products.store'), validProductData($overrides))
            ->assertSessionHasErrors([$field => $message]);

        expect(Product::count())->toBe(1);
    })->with([
        'negative price' => [['selling_price' => '-1000'], 'selling_price', 'Giá trị không được âm.'],
        'duplicate sku' => [['sku' => 'TRUNG'], 'sku', 'Mã SKU này đã được dùng cho sản phẩm khác.'],
        'variant without option' => [['variants' => [['name' => 'Size', 'value' => '']]], 'variants.0.value', 'Vui lòng nhập lựa chọn (ví dụ: Lớn).'],
        'two defaults in one group' => [['variants' => [
            ['name' => 'Size', 'value' => 'Nhỏ', 'is_default' => '1'],
            ['name' => 'size', 'value' => 'Lớn', 'is_default' => '1'],
        ]], 'variants', 'Nhóm “Size” chỉ được có một lựa chọn mặc định.'],
        'not an image' => [['image' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')], 'image', 'Ảnh không hợp lệ.'],
    ]);
});

describe('update', function () {
    test('the edit form renders with the variants', function () {
        $product = Product::factory()->create(['name' => 'Latte']);
        $product->variants()->create(['name' => 'Size', 'value' => 'Lớn', 'price_modifier' => 10000]);

        $this->actingAs(owner())->get(route('products.edit', $product))->assertOk()->assertSee('Latte')->assertSee('value="Lớn"', false);
    });

    test('variants are updated, added and removed', function () {
        $product = Product::factory()->create();
        $kept = $product->variants()->create(['name' => 'Size', 'value' => 'Nhỏ']);
        $removed = $product->variants()->create(['name' => 'Size', 'value' => 'Vừa']);
        $otherProductsVariant = Product::factory()->create()->variants()->create(['name' => 'Size', 'value' => 'Khác']);

        $this->actingAs(owner())
            ->put(route('products.update', $product), validProductData([
                'name' => 'Latte',
                'variants' => [
                    ['id' => $kept->id, 'name' => 'Size', 'value' => 'Nhỏ (M)', 'price_modifier' => '0'],
                    ['id' => $otherProductsVariant->id, 'name' => 'Size', 'value' => 'Lớn', 'price_modifier' => '10000'],
                ],
            ]))
            ->assertRedirect(route('products.index'));

        expect($product->fresh()->variants->pluck('value')->all())->toBe(['Nhỏ (M)', 'Lớn'])
            ->and($kept->fresh()->value)->toBe('Nhỏ (M)')
            ->and($otherProductsVariant->fresh()->value)->toBe('Khác');
        $this->assertModelMissing($removed);
    });

    test('a new image replaces the old file and the image can be removed', function () {
        Storage::fake('public');
        $product = Product::factory()->create(['image_url' => UploadedFile::fake()->image('cu.jpg')->store('products', 'public')]);
        $oldPath = $product->image_url;

        $this->actingAs(owner())->put(route('products.update', $product), validProductData(['image' => UploadedFile::fake()->image('moi.jpg')]));

        $newPath = $product->fresh()->image_url;
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);

        $this->actingAs(owner())->put(route('products.update', $product), validProductData(['remove_image' => '1']));

        expect($product->fresh()->image_url)->toBeNull();
        Storage::disk('public')->assertMissing($newPath);
    });

    test('unticking "Đang bán" takes the product off sale', function () {
        $product = Product::factory()->create();

        $this->actingAs(owner())->put(route('products.update', $product), validProductData(['is_available' => null]));

        expect($product->fresh()->is_available)->toBeFalse();
    });
});

describe('delete', function () {
    test('a product without history is deleted with its variants and image', function () {
        Storage::fake('public');
        $product = Product::factory()->stocked()->create(['image_url' => UploadedFile::fake()->image('ly.jpg')->store('products', 'public')]);
        $product->variants()->create(['name' => 'Size', 'value' => 'Lớn']);

        $this->actingAs(owner())->delete(route('products.destroy', $product))->assertRedirect(route('products.index'));

        $this->assertModelMissing($product);
        $this->assertDatabaseMissing('product_variants', ['product_id' => $product->id]);
        $this->assertDatabaseMissing('inventory', ['product_id' => $product->id]);
        Storage::disk('public')->assertMissing($product->image_url);
    });

    test('a product in a combo cannot be deleted', function () {
        $product = Product::factory()->create(['name' => 'Bánh mì']);
        ProductBundle::factory()->create()->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs(owner())
            ->delete(route('products.destroy', $product))
            ->assertSessionHas('error', '“Bánh mì” đang nằm trong combo. Hãy bỏ khỏi combo trước, hoặc chuyển sang “Ngừng bán”.');

        $this->assertModelExists($product);
    });

    test('a product with stock history cannot be deleted', function () {
        $product = Product::factory()->stocked()->create(['name' => 'Bánh mì']);
        $product->inventoryTransactions()->create(['transaction_type' => InventoryTransactionType::Purchase, 'quantity' => 10]);

        $this->actingAs(owner())
            ->delete(route('products.destroy', $product))
            ->assertSessionHas('error', '“Bánh mì” đã có lịch sử kho nên không xóa được. Hãy chuyển sang “Ngừng bán”.');

        $this->assertModelExists($product);
    });
});
