<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a table's layout details. Its status is changed separately
 * (DiningTableStatusController) because staff may do that without editing tables.
 */
class DiningTableRequest extends FormRequest
{
    /**
     * Access is enforced by the route's `can:tables.*` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'section' => $this->filled('section') ? trim((string) $this->input('section')) : null,
            'location' => $this->filled('location') ? trim((string) $this->input('location')) : null,
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
            'table_no' => ['required', 'integer', 'min:1', 'max:9999', Rule::unique('tables', 'table_no')->ignore($this->route('table'))],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'section' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'table_no.required' => 'Vui lòng nhập số bàn.',
            'table_no.integer' => 'Số bàn phải là số nguyên.',
            'table_no.min' => 'Số bàn phải từ 1 trở lên.',
            'table_no.unique' => 'Số bàn này đã tồn tại.',
            'capacity.required' => 'Vui lòng nhập số chỗ ngồi.',
            'capacity.min' => 'Bàn phải có ít nhất 1 chỗ.',
            'capacity.max' => 'Tối đa 50 chỗ một bàn.',
        ];
    }
}
