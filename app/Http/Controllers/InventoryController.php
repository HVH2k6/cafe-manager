<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventorySettingsRequest;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    private const STOCK_FILTERS = ['all', 'tracked', 'low', 'out', 'untracked'];

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'category' => $request->integer('category') ?: null,
            'stock' => in_array($request->query('stock'), self::STOCK_FILTERS, true) ? $request->query('stock') : 'all',
        ];

        $products = Product::with(['category', 'inventory'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$filters['search']}%")
                ->orWhereLike('sku', "%{$filters['search']}%")))
            ->when($filters['category'], fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['stock'] === 'tracked', fn (Builder $query) => $query->has('inventory'))
            ->when($filters['stock'] === 'untracked', fn (Builder $query) => $query->doesntHave('inventory'))
            ->when($filters['stock'] === 'low', fn (Builder $query) => $query->whereHas('inventory', fn (Builder $query) => $query->lowStock()))
            ->when($filters['stock'] === 'out', fn (Builder $query) => $query->whereRelation('inventory', 'quantity_on_hand', '<=', 0))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.index', [
            'products' => $products,
            'filters' => $filters,
            'categories' => Category::orderBy('display_order')->orderBy('name')->get(),
            'summary' => [
                'tracked' => Inventory::count(),
                'low' => Inventory::lowStock()->where('quantity_on_hand', '>', 0)->count(),
                'out' => Inventory::where('quantity_on_hand', '<=', 0)->count(),
            ],
        ]);
    }

    /**
     * One product's stock, movement form, thresholds and history.
     */
    public function show(Product $product): View
    {
        return view('inventory.show', [
            'product' => $product->load(['category', 'inventory']),
            'transactions' => $product->inventoryTransactions()->with('creator')->latest('id')->paginate(20),
        ]);
    }

    /**
     * Start tracking stock for a product (quantity starts at 0; record a purchase or count next).
     */
    public function store(Product $product): RedirectResponse
    {
        Inventory::firstOrCreate(['product_id' => $product->id]);

        return to_route('inventory.show', $product)->with('status', "Bắt đầu theo dõi kho cho “{$product->name}”. Hãy nhập hàng hoặc kiểm kê để có số tồn đầu tiên.");
    }

    public function update(InventorySettingsRequest $request, Product $product): RedirectResponse
    {
        Inventory::updateOrCreate(['product_id' => $product->id], $request->validated());

        return back()->with('status', 'Đã lưu ngưỡng tồn kho.');
    }
}
