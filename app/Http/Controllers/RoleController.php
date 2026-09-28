<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::withCount(['users', 'permissions'])->orderBy('display_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('roles.create', [
            'role' => new Role(['is_active' => true, 'display_order' => Role::max('display_order') + 1]),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create($request->validated());

        return to_route('roles.index')->with('status', "Đã thêm vai trò “{$role->name}”. Hãy gán quyền cho vai trò ở tab Ma trận quyền.");
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', ['role' => $role]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        return to_route('roles.index')->with('status', "Đã cập nhật vai trò “{$role->name}”.");
    }

    /**
     * Only an unused role can be deleted; lock it instead while staff still hold it.
     */
    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === Role::OWNER) {
            return back()->with('error', 'Không thể xóa vai trò '.Role::OWNER.'.');
        }

        $userCount = $role->users()->count();

        if ($userCount > 0) {
            return back()->with('error', "Vai trò “{$role->name}” còn {$userCount} nhân viên. Hãy chuyển họ sang vai trò khác hoặc khóa vai trò này.");
        }

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->delete();
        });

        return to_route('roles.index')->with('status', "Đã xóa vai trò “{$role->name}”.");
    }
}
