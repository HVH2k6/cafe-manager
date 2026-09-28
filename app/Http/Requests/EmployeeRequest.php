<?php

namespace App\Http\Requests;

use App\ContractType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Validates creating and updating a staff member: the login account (users)
 * together with the HR profile (employees).
 */
class EmployeeRequest extends FormRequest
{
    /**
     * The route's `can:` middleware checks the permission; this checks the
     * target account, so staff cannot edit an owner.
     */
    public function authorize(): bool
    {
        $employee = $this->employee();

        return $employee === null || Gate::allows('manage-employee', $employee);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->employee();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\s().-]+$/'],
            'role_id' => [
                'required', 'integer',
                Rule::exists('role', 'id')->where(function (Builder $query): void {
                    $query->where('is_active', true);

                    // Only the owner may hand out the owner role.
                    if (! $this->user()->isOwner()) {
                        $query->where('name', '!=', Role::OWNER);
                    }
                }),
            ],
            // Passwords are changed through EmployeePasswordController, never by this form.
            'password' => $employee
                ? ['exclude']
                : ['required', 'string', 'confirmed', Password::defaults()],
            'position' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'hire_date' => ['required', 'date'],
            'salary' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $employee = $this->employee();

                if ($employee?->isLastActiveOwner() && (int) $this->input('role_id') !== $employee->role_id) {
                    $validator->errors()->add('role_id', 'Đây là tài khoản Chủ quán duy nhất, không thể đổi sang vai trò khác.');
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
            'name.required' => 'Vui lòng nhập họ tên.',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được dùng cho tài khoản khác.',
            'phone.regex' => 'Số điện thoại chỉ gồm số và các ký tự + ( ) - .',
            'phone.max' => 'Số điện thoại không được vượt quá 20 ký tự.',
            'role_id.required' => 'Vui lòng chọn vai trò.',
            'role_id.exists' => 'Vai trò không hợp lệ hoặc đang bị khóa.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất :min ký tự.',
            'position.required' => 'Vui lòng nhập vị trí công việc.',
            'contract_type.required' => 'Vui lòng chọn loại hợp đồng.',
            'hire_date.required' => 'Vui lòng nhập ngày vào làm.',
            'hire_date.date' => 'Ngày vào làm không hợp lệ.',
            'salary.required' => 'Vui lòng nhập lương.',
            'salary.numeric' => 'Lương phải là số.',
            'salary.min' => 'Lương không được âm.',
            'salary.max' => 'Lương quá lớn.',
        ];
    }

    /**
     * @return array{name: string, email: string, phone: ?string, role_id: int, password?: string}
     */
    public function accountData(): array
    {
        return Arr::only($this->validated(), ['name', 'email', 'phone', 'role_id', 'password']);
    }

    /**
     * @return array{position: string, department: ?string, contract_type: string, hire_date: string, salary: string, notes: ?string}
     */
    public function profileData(): array
    {
        return Arr::only($this->validated(), ['position', 'department', 'contract_type', 'hire_date', 'salary', 'notes']);
    }

    /**
     * The staff member being edited, or null when creating one.
     */
    private function employee(): ?User
    {
        return $this->route('user');
    }
}
