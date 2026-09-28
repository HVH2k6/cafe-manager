<?php

namespace App\Http\Requests;

use App\OrderType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * A new order from the POS: where it is served plus its cart lines.
 */
class StoreOrderRequest extends OrderLinesRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'order_type' => ['required', Rule::enum(OrderType::class)->only([OrderType::DineIn, OrderType::Takeaway])],
            'table_id' => ['nullable', 'required_if:order_type,'.OrderType::DineIn->value, 'integer', Rule::exists('tables', 'id')],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s().-]+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'order_type.required' => 'Chọn tại bàn hoặc mang về.',
            'table_id.required_if' => 'Vui lòng chọn bàn.',
            'table_id.exists' => 'Bàn không tồn tại.',
            'customer_phone.regex' => 'Số điện thoại không hợp lệ.',
        ];
    }

    /**
     * @return array{order_type: string, table_id: ?int, customer_name: ?string, customer_phone: ?string, notes: ?string}
     */
    public function details(): array
    {
        $details = Arr::only($this->validated(), ['order_type', 'table_id', 'customer_name', 'customer_phone', 'notes']);

        return [
            'order_type' => $details['order_type'],
            'table_id' => $details['order_type'] === OrderType::DineIn->value ? (int) $details['table_id'] : null,
            'customer_name' => $details['customer_name'] ?? null,
            'customer_phone' => $details['customer_phone'] ?? null,
            'notes' => $details['notes'] ?? null,
        ];
    }
}
