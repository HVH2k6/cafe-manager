<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    /**
     * Save the whole matrix. The owner role is skipped because it always has
     * every permission.
     */
    public function update(UpdateRolePermissionsRequest $request): RedirectResponse
    {
        $roles = Role::where('name', '!=', Role::OWNER)->get();

        DB::transaction(function () use ($roles, $request): void {
            foreach ($roles as $role) {
                $role->permissions()->sync($request->permissionIdsFor($role->id));
            }
        });

        return to_route('permissions.index')->with('status', 'Đã lưu phân quyền.');
    }
}
