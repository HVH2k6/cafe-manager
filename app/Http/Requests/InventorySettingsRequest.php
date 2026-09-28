<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the stock thresholds and unit of one product.
 */
class InventorySettingsRequest extends FormRequest
{
    /**
     * Access is enforced by the route's `can:inventory.update` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'unit_of_measure' => ['required', 'string', 'max:20'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['required', 'integer', 'gte:min_stock', 'max:1000000'],
            'max_stock' => ['required', 'integer', 'gte:reorder_level', 'max:1000000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_of_measure.required' => 'Vui lòng nhập đơn vị tính.',
            'min_stock.required' => 'Vui lòng nhập mức tồn tối thiểu.',
            'min_stock.min' => 'Mức tồn tối thiểu không được âm.',
            'reorder_level.required' => 'Vui lòng nhập mức cần đặt hàng.',
            'reorder_level.gte' => 'Mức cần đặt hàng phải lớn hơn hoặc bằng mức tồn tối thiểu.',
            'max_stock.required' => 'Vui lòng nhập mức tồn tối đa.',
            'max_stock.gte' => 'Mức tồn tối đa phải lớn hơn hoặc bằng mức cần đặt hàng.',
            '*.integer' => 'Giá trị phải là số nguyên.',
        ];
    }
}
