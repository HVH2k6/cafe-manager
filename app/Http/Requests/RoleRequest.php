<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoleRequest extends FormRequest
{
    /**
     * Access is enforced by the route's `can:manage-permissions` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'display_order' => $this->input('display_order') ?: 0,
            'is_active' => $this->boolean('is_active'),
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
            'name' => ['required', 'string', 'max:100', Rule::unique('role', 'name')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'display_order' => ['integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * The owner role is recognised by its name in code, so it cannot be renamed or locked.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Role|null $role */
                $role = $this->route('role');

                if ($role?->name !== Role::OWNER) {
                    return;
                }

                if ($this->input('name') !== Role::OWNER) {
                    $validator->errors()->add('name', 'Không thể đổi tên vai trò '.Role::OWNER.'.');
                }

                if (! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'Không thể khóa vai trò '.Role::OWNER.'.');
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
            'name.required' => 'Vui lòng nhập tên vai trò.',
            'name.max' => 'Tên vai trò không được vượt quá 100 ký tự.',
            'name.unique' => 'Tên vai trò này đã tồn tại.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
            'display_order.integer' => 'Thứ tự hiển thị phải là số nguyên.',
            'display_order.min' => 'Thứ tự hiển thị không được âm.',
            'display_order.max' => 'Thứ tự hiển thị tối đa là 999.',
        ];
    }
}
