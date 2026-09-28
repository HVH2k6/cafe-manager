<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a combo and the products in it.
 */
class ProductBundleRequest extends FormRequest
{
    /**
     * Access is enforced by the route's `can:products.*` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'items' => collect($this->input('items', []))
                ->filter(fn (mixed $item): bool => is_array($item) && filled($item['product_id'] ?? null))
                ->values()
                ->all(),
            'is_active' => $this->boolean('is_active'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('product_bundles', 'name')->ignore($this->route('bundle'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'bundle_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['boolean'],
            'is_active' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items' => ['required', 'array', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (array_sum(array_column($this->input('items'), 'quantity')) < 2) {
                    $validator->errors()->add('items', 'Combo cần ít nhất 2 món.');

                    return;
                }

                if ((float) $this->input('bundle_price') > $this->individualPrice()) {
                    $validator->errors()->add('bundle_price', 'Giá combo không được cao hơn tổng giá mua lẻ ('.number_format($this->individualPrice(), 0, ',', '.').' đ).');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên combo.',
            'name.unique' => 'Tên combo này đã tồn tại.',
            'bundle_price.required' => 'Vui lòng nhập giá combo.',
            'bundle_price.numeric' => 'Giá combo phải là số.',
            'bundle_price.min' => 'Giá combo không được âm.',
            'image.image' => 'Ảnh không hợp lệ.',
            'image.mimes' => 'Ảnh phải là JPG, PNG hoặc WEBP.',
            'image.max' => 'Ảnh không được lớn hơn 2 MB.',
            'items.required' => 'Vui lòng chọn món trong combo.',
            'items.*.product_id.distinct' => 'Mỗi món chỉ chọn một lần, hãy tăng số lượng thay vì thêm dòng trùng.',
            'items.*.product_id.exists' => 'Sản phẩm không tồn tại.',
            'items.*.quantity.min' => 'Số lượng tối thiểu là 1.',
            'items.*.quantity.max' => 'Số lượng tối đa là 20.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bundleData(): array
    {
        $data = Arr::only($this->validated(), ['name', 'description', 'bundle_price', 'is_active', 'display_order']);
        $individualPrice = $this->individualPrice();

        return [
            ...$data,
            'display_order' => $data['display_order'] ?? 0,
            'individual_price' => $individualPrice,
            'discount_amount' => $individualPrice - (float) $data['bundle_price'],
        ];
    }

    /**
     * @return list<array{product_id: int, quantity: int, display_order: int}>
     */
    public function items(): array
    {
        return array_map(fn (array $item, int $index): array => [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
            'display_order' => $index,
        ], $this->validated('items'), array_keys($this->validated('items')));
    }

    /**
     * What the items would cost bought separately, at current selling prices.
     */
    private function individualPrice(): float
    {
        $prices = Product::whereKey(array_column($this->input('items'), 'product_id'))->pluck('selling_price', 'id');

        return collect($this->input('items'))->sum(fn (array $item): float => (float) ($prices[$item['product_id']] ?? 0) * (int) $item['quantity']);
    }
}
