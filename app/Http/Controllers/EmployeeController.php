<?php

namespace App\Http\Controllers;

use App\ContractType;
use App\Http\Requests\EmployeeRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    /**
     * Staff list with search and filters. Shows working staff by default.
     */
    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->query('search')),
            'role' => $request->integer('role') ?: null,
            'status' => in_array($request->query('status'), ['active', 'inactive', 'all'], true) ? $request->query('status') : 'active',
        ];

        $employees = User::with(['role', 'employee'])
            ->when($filters['search'] !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->whereLike('name', "%{$filters['search']}%")
                    ->orWhereLike('email', "%{$filters['search']}%")
                    ->orWhereLike('phone', "%{$filters['search']}%");
            }))
            ->when($filters['role'], fn (Builder $query, int $roleId) => $query->where('role_id', $roleId))
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', [
            'employees' => $employees,
            'filters' => $filters,
            'roles' => Role::orderBy('display_order')->orderBy('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('employees.create', [
            'employee' => new User,
            'roles' => $this->assignableRoles($request->user()),
            'contractTypes' => ContractType::cases(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = DB::transaction(function () use ($request): User {
            $employee = User::create([...$request->accountData(), 'is_active' => true]);
            $employee->employee()->create([...$request->profileData(), 'is_active' => true]);

            return $employee;
        });

        return to_route('employees.index')->with('status', "Đã thêm nhân viên {$employee->name}.");
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('manage-employee', $user);

        $user->load(['role', 'employee']);

        return view('employees.edit', [
            'employee' => $user,
            'roles' => $this->assignableRoles($request->user(), $user->role),
            'contractTypes' => ContractType::cases(),
        ]);
    }

    public function update(EmployeeRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->update($request->accountData());
            $user->employee()->updateOrCreate([], $request->profileData());
        });

        return to_route('employees.index')->with('status', "Đã cập nhật nhân viên {$user->name}.");
    }

    /**
     * Roles the current user may hand out. The employee's current role is kept
     * in the list so the form never silently changes it.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(User $actor, ?Role $currentRole = null): Collection
    {
        return Role::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->when($currentRole, fn (Builder $query) => $query->orWhereKey($currentRole->id)))
            ->when(! $actor->isOwner(), fn (Builder $query) => $query->where('name', '!=', Role::OWNER))
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }
}
