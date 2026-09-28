<?php

namespace App\Http\Requests;

use App\InventoryTransactionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a stock movement recorded by hand. For a stock count
 * (adjustment) the quantity is the counted amount, otherwise it is the
 * amount added (purchase) or removed (damage, expiry).
 */
class StoreInventoryTransactionRequest extends FormRequest
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
        $isCount = $this->input('transaction_type') === InventoryTransactionType::Adjustment->value;

        return [
            'transaction_type' => ['required', Rule::enum(InventoryTransactionType::class)->only(InventoryTransactionType::manual())],
            'quantity' => ['required', 'integer', 'min:'.($isCount ? 0 : 1), 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_type.required' => 'Vui lòng chọn loại giao dịch.',
            'transaction_type.enum' => 'Loại giao dịch không hợp lệ.',
            'quantity.required' => 'Vui lòng nhập số lượng.',
            'quantity.integer' => 'Số lượng phải là số nguyên.',
            'quantity.min' => 'Số lượng phải từ :min trở lên.',
            'quantity.max' => 'Số lượng quá lớn.',
        ];
    }

    public function type(): InventoryTransactionType
    {
        return InventoryTransactionType::from($this->validated('transaction_type'));
    }
}
