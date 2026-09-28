<?php

namespace App\Http\Requests;

use App\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The customer confirms their cart on the checkout screen.
 */
class SubmitQrOrderRequest extends FormRequest
{
    /**
     * How customers may say they will pay. Cash and card are collected at the
     * table; transfer and e-wallet are paid with the payOS VietQR code (any
     * banking or wallet app scans it) once staff confirmed the order.
     */
    public const PAYMENT_METHODS = [PaymentMethod::Cash, PaymentMethod::Card, PaymentMethod::EWallet, PaymentMethod::BankTransfer];

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
            'items' => ['required', 'array', 'min:1', 'max:'.QrCartRequest::MAX_LINES],
            ...QrCartRequest::lineRules(),
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only(self::PAYMENT_METHODS)],
            'notes' => ['nullable', 'string', 'max:500'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...QrCartRequest::lineMessages(),
            'payment_method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'payment_method.enum' => 'Phương thức thanh toán không hợp lệ.',
            'notes.max' => 'Ghi chú tối đa 500 ký tự.',
            'accept_terms.accepted' => 'Vui lòng đồng ý với điều khoản sử dụng.',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return array_values($this->validated('items'));
    }

    /**
     * @return array{customer_name: ?string, notes: ?string, payment_preference: string}
     */
    public function details(): array
    {
        return [
            'customer_name' => filled($this->validated('customer_name')) ? trim($this->validated('customer_name')) : null,
            'notes' => filled($this->validated('notes')) ? trim($this->validated('notes')) : null,
            'payment_preference' => $this->validated('payment_method'),
        ];
    }
}
