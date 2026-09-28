<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the role × permission matrix: roles[{role_id}][] = permission_id.
 */
class UpdateRolePermissionsRequest extends FormRequest
{
    /**
     * Access is enforced by the route's `can:manage-permissions` middleware.
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
            'roles' => ['nullable', 'array'],
            'roles.*' => ['array'],
            'roles.*.*' => ['integer', 'distinct', 'exists:permission,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'roles.*.*.exists' => 'Có quyền không tồn tại, vui lòng tải lại trang.',
        ];
    }

    /**
     * Permission ids submitted for a role; an absent role means none were ticked.
     *
     * @return list<int>
     */
    public function permissionIdsFor(int $roleId): array
    {
        return array_map('intval', $this->validated("roles.{$roleId}", []));
    }
}
