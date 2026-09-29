<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'category' => $request->integer('category') ?: null,
            'status' => in_array($request->query('status'), ['available', 'unavailable'], true) ? $request->query('status') : 'all',
        ];

        $products = Product::with(['category', 'inventory'])
            ->withCount('variants')
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$filters['search']}%")
                ->orWhereLike('sku', "%{$filters['search']}%")))
            ->when($filters['category'], fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('is_available', $filters['status'] === 'available'))
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('products.create', [
            'product' => new Product(['is_available' => true, 'prep_time_minutes' => 5]),
            'categories' => Category::orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = new Product($request->productData());
            $product->applyImage($request->file('image'), false, 'products');
            $product->save();

            $this->syncVariants($product, $request->variants());

            ActivityLogger::log('product.created', "Thêm sản phẩm “{$product->name}” giá ".number_format((float) $product->selling_price, 0, ',', '.').' đ', $product,
                null, ['name' => $product->name, 'selling_price' => (float) $product->selling_price, 'cost_price' => (float) $product->cost_price]);

            if ($request->boolean('track_stock')) {
                Inventory::create([
                    'product_id' => $product->id,
                    'unit_of_measure' => $request->validated('unit_of_measure') ?: 'piece',
                ]);
            }

            return $product;
        });

        return to_route('products.index')->with('status', "Đã thêm sản phẩm “{$product->name}”.");
    }

    public function edit(Product $product): View
    {
        return view('products.edit', [
            'product' => $product->load(['variants', 'inventory']),
            'categories' => Category::orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product->fill($request->productData());
            $product->applyImage($request->file('image'), $request->boolean('remove_image'), 'products');
            $product->save();

            // Price changes are what owners look for; other edits are logged too, without the image path.
            ActivityLogger::changed('product.updated', $product->wasChanged('selling_price')
                ? "Đổi giá “{$product->name}”: ".number_format((float) $product->getPrevious()['selling_price'], 0, ',', '.').' → '.number_format((float) $product->selling_price, 0, ',', '.').' đ'
                : "Sửa sản phẩm “{$product->name}”", $product, ['name', 'selling_price', 'base_price', 'cost_price', 'category_id', 'is_available', 'is_featured', 'sku']);

            $this->syncVariants($product, $request->variants());
        });

        return to_route('products.index')->with('status', "Đã cập nhật sản phẩm “{$product->name}”.");
    }

    /**
     * Products with history (in a combo, stock movements, orders) cannot be
     * deleted; they are taken off sale instead.
     */
    public function destroy(Product $product): RedirectResponse
    {
        if ($product->bundleItems()->exists()) {
            return back()->with('error', "“{$product->name}” đang nằm trong combo. Hãy bỏ khỏi combo trước, hoặc chuyển sang “Ngừng bán”.");
        }

        if ($product->inventoryTransactions()->exists()) {
            return back()->with('error', "“{$product->name}” đã có lịch sử kho nên không xóa được. Hãy chuyển sang “Ngừng bán”.");
        }

        try {
            $product->delete();
            ActivityLogger::log('product.deleted', "Xóa sản phẩm “{$product->name}”", $product, ['name' => $product->name, 'selling_price' => (float) $product->selling_price]);
        } catch (QueryException) {
            return back()->with('error', "“{$product->name}” đã có trong đơn hàng nên không xóa được. Hãy chuyển sang “Ngừng bán”.");
        }

        return to_route('products.index')->with('status', "Đã xóa sản phẩm “{$product->name}”.");
    }

    /**
     * Update submitted variants that belong to the product, create new ones and
     * delete the ones removed from the form.
     *
     * @param  list<array{id: ?int, name: string, value: string, price_modifier: string|int, is_default: bool}>  $variants
     */
    private function syncVariants(Product $product, array $variants): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $keptIds = [];

        foreach ($variants as $displayOrder => $data) {
            $attributes = [...collect($data)->except('id')->all(), 'display_order' => $displayOrder];
            $variant = $data['id'] !== null ? $existing->get($data['id']) : null;

            if ($variant) {
                $variant->update($attributes);
            } else {
                $variant = $product->variants()->create($attributes);
            }

            $keptIds[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keptIds)->delete();
    }
}
