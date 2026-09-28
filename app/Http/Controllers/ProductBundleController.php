<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductBundleRequest;
use App\Models\Product;
use App\Models\ProductBundle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductBundleController extends Controller
{
    public function index(): View
    {
        return view('bundles.index', [
            'bundles' => ProductBundle::with('items.product')->orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('bundles.create', [
            'bundle' => new ProductBundle(['is_active' => true]),
            'products' => $this->products(),
        ]);
    }

    public function store(ProductBundleRequest $request): RedirectResponse
    {
        $bundle = DB::transaction(function () use ($request): ProductBundle {
            $bundle = new ProductBundle($request->bundleData());
            $bundle->applyImage($request->file('image'), false, 'bundles');
            $bundle->save();
            $bundle->items()->createMany($request->items());

            return $bundle;
        });

        return to_route('bundles.index')->with('status', "Đã thêm combo “{$bundle->name}”.");
    }

    public function edit(ProductBundle $bundle): View
    {
        return view('bundles.edit', [
            'bundle' => $bundle->load('items'),
            'products' => $this->products(),
        ]);
    }

    public function update(ProductBundleRequest $request, ProductBundle $bundle): RedirectResponse
    {
        DB::transaction(function () use ($request, $bundle): void {
            $bundle->fill($request->bundleData());
            $bundle->applyImage($request->file('image'), $request->boolean('remove_image'), 'bundles');
            $bundle->save();

            $bundle->items()->delete();
            $bundle->items()->createMany($request->items());
        });

        return to_route('bundles.index')->with('status', "Đã cập nhật combo “{$bundle->name}”.");
    }

    public function destroy(ProductBundle $bundle): RedirectResponse
    {
        try {
            $bundle->delete();
        } catch (QueryException) {
            return back()->with('error', "Combo “{$bundle->name}” đã có trong đơn hàng nên không xóa được. Hãy tắt combo thay vì xóa.");
        }

        return to_route('bundles.index')->with('status', "Đã xóa combo “{$bundle->name}”.");
    }

    /**
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        return Product::with('category')->orderBy('name')->get();
    }
}
