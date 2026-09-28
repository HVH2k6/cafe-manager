<?php

namespace App\Http\Requests;

use App\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validates creating and updating a permission.
 */
class PermissionRequest extends FormRequest
{
    private const KEY_FORMAT = '/^[a-z][a-z0-9_]*$/';

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
            'module' => Str::lower(trim((string) $this->input('module'))),
            'action' => Str::lower(trim((string) $this->input('action'))),
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
        /** @var Permission|null $permission */
        $permission = $this->route('permission');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('permission', 'name')->ignore($permission)],
            'module' => ['required', 'string', 'max:50', 'regex:'.self::KEY_FORMAT],
            'action' => [
                'required', 'string', 'max:50', 'regex:'.self::KEY_FORMAT,
                Rule::unique('permission', 'action')->where('module', $this->input('module'))->ignore($permission),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên quyền.',
            'name.max' => 'Tên quyền không được vượt quá 100 ký tự.',
            'name.unique' => 'Tên quyền này đã tồn tại.',
            'module.required' => 'Vui lòng nhập module.',
            'module.max' => 'Module không được vượt quá 50 ký tự.',
            'module.regex' => 'Module chỉ gồm chữ thường không dấu, số và dấu gạch dưới, bắt đầu bằng chữ.',
            'action.required' => 'Vui lòng nhập hành động.',
            'action.max' => 'Hành động không được vượt quá 50 ký tự.',
            'action.regex' => 'Hành động chỉ gồm chữ thường không dấu, số và dấu gạch dưới, bắt đầu bằng chữ.',
            'action.unique' => 'Module này đã có hành động trùng tên.',
            'description.max' => 'Mô tả không được vượt quá 1000 ký tự.',
        ];
    }
}
