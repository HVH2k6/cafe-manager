<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a product with its variants (size, sugar level…) and, when
 * creating, whether its stock should be tracked.
 */
class ProductRequest extends FormRequest
{
    private const MAX_PRICE = 99_999_999;

    /**
     * Access is enforced by the route's `can:products.*` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $variants = collect($this->input('variants', []))
            ->filter(fn (mixed $variant): bool => is_array($variant) && (filled($variant['name'] ?? null) || filled($variant['value'] ?? null)))
            ->map(fn (array $variant): array => [
                ...$variant,
                'name' => trim((string) ($variant['name'] ?? '')),
                'value' => trim((string) ($variant['value'] ?? '')),
                'price_modifier' => ($variant['price_modifier'] ?? null) ?: 0,
                'is_default' => filter_var($variant['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ])
            ->values()
            ->all();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'sku' => $this->filled('sku') ? trim((string) $this->input('sku')) : null,
            'variants' => $variants,
            'is_available' => $this->boolean('is_available'),
            'is_featured' => $this->boolean('is_featured'),
            'track_stock' => $this->boolean('track_stock'),
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
        /** @var Product|null $product */
        $product = $this->route('product');
        $money = ['numeric', 'min:0', 'max:'.self::MAX_PRICE];

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['boolean'],
            'selling_price' => ['required', ...$money],
            'base_price' => ['nullable', ...$money],
            'cost_price' => ['nullable', ...$money],
            'sku' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('products', 'sku')->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'is_available' => ['boolean'],
            'is_featured' => ['boolean'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'prep_time_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'calories' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'track_stock' => ['boolean'],
            'unit_of_measure' => ['nullable', 'string', 'max:20'],
            'variants' => ['array', 'max:30'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required', 'string', 'max:100'],
            'variants.*.value' => ['required', 'string', 'max:100'],
            'variants.*.price_modifier' => ['numeric', 'min:-'.self::MAX_PRICE, 'max:'.self::MAX_PRICE],
            'variants.*.is_default' => ['boolean'],
        ];
    }

    /**
     * At most one default option per variant group ("Size", "Đường"…).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                collect($this->input('variants', []))
                    ->filter(fn (array $variant): bool => $variant['is_default'])
                    ->groupBy(fn (array $variant): string => mb_strtolower($variant['name']))
                    ->filter(fn ($defaults): bool => $defaults->count() > 1)
                    ->each(fn ($defaults) => $validator->errors()->add('variants', "Nhóm “{$defaults->first()['name']}” chỉ được có một lựa chọn mặc định."));
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên sản phẩm.',
            'name.max' => 'Tên sản phẩm không được vượt quá 255 ký tự.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không tồn tại.',
            'image.image' => 'Ảnh không hợp lệ.',
            'image.mimes' => 'Ảnh phải là JPG, PNG hoặc WEBP.',
            'image.max' => 'Ảnh không được lớn hơn 2 MB.',
            'selling_price.required' => 'Vui lòng nhập giá bán.',
            '*.numeric' => 'Giá phải là số.',
            '*.min' => 'Giá trị không được âm.',
            'sku.unique' => 'Mã SKU này đã được dùng cho sản phẩm khác.',
            'sku.alpha_dash' => 'Mã SKU chỉ gồm chữ không dấu, số, "-" và "_".',
            'prep_time_minutes.max' => 'Thời gian pha chế tối đa 240 phút.',
            'variants.*.name.required' => 'Vui lòng nhập tên nhóm biến thể (ví dụ: Size).',
            'variants.*.value.required' => 'Vui lòng nhập lựa chọn (ví dụ: Lớn).',
            'variants.*.price_modifier.numeric' => 'Giá cộng thêm phải là số.',
            'variants.max' => 'Tối đa 30 biến thể.',
        ];
    }

    /**
     * Product attributes; the list price falls back to the selling price.
     *
     * @return array<string, mixed>
     */
    public function productData(): array
    {
        $data = Arr::only($this->validated(), [
            'name', 'category_id', 'description', 'selling_price', 'base_price', 'cost_price', 'sku', 'barcode',
            'is_available', 'is_featured', 'display_order', 'prep_time_minutes', 'calories',
        ]);

        return [
            ...$data,
            'base_price' => $data['base_price'] ?? $data['selling_price'],
            'cost_price' => $data['cost_price'] ?? 0,
            'display_order' => $data['display_order'] ?? 0,
            'prep_time_minutes' => $data['prep_time_minutes'] ?? 5,
        ];
    }

    /**
     * @return list<array{id: ?int, name: string, value: string, price_modifier: string|int, is_default: bool}>
     */
    public function variants(): array
    {
        return array_map(fn (array $variant): array => [
            'id' => isset($variant['id']) ? (int) $variant['id'] : null,
            'name' => $variant['name'],
            'value' => $variant['value'],
            'price_modifier' => $variant['price_modifier'] ?? 0,
            'is_default' => (bool) ($variant['is_default'] ?? false),
        ], $this->validated('variants', []));
    }
}
