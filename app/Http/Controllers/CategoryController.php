<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('categories.index', [
            'categories' => Category::withCount('products')->orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('categories.create', [
            'category' => new Category(['is_active' => true, 'display_order' => (int) Category::max('display_order') + 1]),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return to_route('categories.index')->with('status', "Đã thêm danh mục “{$category->name}”.");
    }

    public function edit(Category $category): View
    {
        return view('categories.edit', ['category' => $category]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return to_route('categories.index')->with('status', "Đã cập nhật danh mục “{$category->name}”.");
    }

    /**
     * Only an empty category can be deleted; hide it instead while it has products.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $productCount = $category->products()->count();

        if ($productCount > 0) {
            return back()->with('error', "Danh mục “{$category->name}” còn {$productCount} sản phẩm. Hãy chuyển sản phẩm sang danh mục khác hoặc ẩn danh mục này.");
        }

        $category->delete();

        return to_route('categories.index')->with('status', "Đã xóa danh mục “{$category->name}”.");
    }
}
