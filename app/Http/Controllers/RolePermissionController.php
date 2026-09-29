<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRolePermissionsRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Support\ActivityLogger;
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

        $keys = Permission::all()->mapWithKeys(fn (Permission $permission): array => [$permission->id => $permission->key]);

        DB::transaction(function () use ($roles, $request, $keys): void {
            foreach ($roles as $role) {
                $changes = $role->permissions()->sync($request->permissionIdsFor($role->id));
                $granted = collect($changes['attached'])->map(fn (int $id): string => $keys[$id] ?? "#{$id}")->values()->all();
                $revoked = collect($changes['detached'])->map(fn (int $id): string => $keys[$id] ?? "#{$id}")->values()->all();

                if ($granted || $revoked) {
                    ActivityLogger::log('permissions.updated', "Đổi quyền vai trò {$role->name}: "
                        .collect([$granted ? '+'.implode(', +', $granted) : null, $revoked ? '−'.implode(', −', $revoked) : null])->filter()->implode('; '),
                        $role, ['revoked' => $revoked], ['granted' => $granted]);
                }
            }
        });

        return to_route('permissions.index')->with('status', 'Đã lưu phân quyền.');
    }
}
