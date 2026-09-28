<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the shape of cart lines sent by the POS. Prices, availability
 * and variant ownership are checked by BuildOrderItems against the menu.
 */
class OrderLinesRequest extends FormRequest
{
    /**
     * Access is enforced by the route's `can:orders.*` middleware.
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
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.type' => ['required', 'in:product,bundle'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.variant_ids' => ['nullable', 'array', 'max:10'],
            'items.*.variant_ids.*' => ['integer'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Chưa có món nào trong đơn.',
            'items.min' => 'Chưa có món nào trong đơn.',
            'items.max' => 'Một đơn tối đa 50 dòng món.',
            'items.*.quantity.min' => 'Số lượng tối thiểu là 1.',
            'items.*.quantity.max' => 'Số lượng tối đa là 99.',
            'items.*.note.max' => 'Ghi chú món tối đa 255 ký tự.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return array_values($this->validated('items'));
    }
}
