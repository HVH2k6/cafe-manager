<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The cart a customer keeps on their phone, saved in qr_sessions.cart_data so
 * it survives a reload. Only its shape is checked here; prices and what is
 * still on the menu are checked when the order is sent.
 */
class QrCartRequest extends FormRequest
{
    /** Tighter than the staff POS: customers are anonymous. */
    public const MAX_LINES = 30;

    public const MAX_QUANTITY = 20;

    /**
     * The session token in the URL is the customer's proof (checked by the route).
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
            'customer_name' => ['nullable', 'string', 'max:100'],
            'items' => ['present', 'array', 'max:'.self::MAX_LINES],
            ...self::lineRules(),
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function lineRules(): array
    {
        return [
            'items.*.type' => ['required', 'in:product,bundle'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
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
        return self::lineMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function lineMessages(): array
    {
        return [
            'items.required' => 'Giỏ hàng đang trống.',
            'items.min' => 'Giỏ hàng đang trống.',
            'items.max' => 'Một lần gọi tối đa '.self::MAX_LINES.' món.',
            'items.*.quantity.min' => 'Số lượng tối thiểu là 1.',
            'items.*.quantity.max' => 'Mỗi món tối đa '.self::MAX_QUANTITY.' phần. Cần nhiều hơn, vui lòng gọi nhân viên.',
            'items.*.note.max' => 'Ghi chú món tối đa 255 ký tự.',
        ];
    }

    /**
     * Lines as stored in the cart (only the known keys).
     *
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $line): array => [
            'type' => $line['type'],
            'id' => (int) $line['id'],
            'quantity' => (int) $line['quantity'],
            'variant_ids' => array_map('intval', $line['variant_ids'] ?? []),
            'note' => filled($line['note'] ?? null) ? trim($line['note']) : null,
        ], $this->validated('items')));
    }
}
