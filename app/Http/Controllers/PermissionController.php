<?php

namespace App\Http\Controllers;

use App\Http\Requests\PermissionRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PermissionController extends Controller
{
    /**
     * The role × permission matrix, grouped by module.
     */
    public function index(): View
    {
        $roles = Role::with('permissions:id')->orderBy('display_order')->orderBy('id')->get();

        $moduleOrder = array_flip(array_keys(Permission::MODULE_LABELS));

        $permissionsByModule = Permission::orderBy('id')
            ->get()
            ->groupBy('module')
            ->sortBy(fn (Collection $permissions, string $module): array => [$moduleOrder[$module] ?? PHP_INT_MAX, $module]);

        return view('permissions.index', [
            'roles' => $roles,
            'permissionsByModule' => $permissionsByModule,
        ]);
    }

    public function create(): View
    {
        return view('permissions.create', [
            'permission' => new Permission(['is_active' => true]),
            'modules' => $this->knownModules(),
        ]);
    }

    public function store(PermissionRequest $request): RedirectResponse
    {
        $permission = Permission::create($request->validated());

        return to_route('permissions.index')->with('status', "Đã thêm quyền “{$permission->name}”.");
    }

    public function edit(Permission $permission): View
    {
        return view('permissions.edit', [
            'permission' => $permission,
            'modules' => $this->knownModules(),
        ]);
    }

    public function update(PermissionRequest $request, Permission $permission): RedirectResponse
    {
        $permission->update($request->validated());

        return to_route('permissions.index')->with('status', "Đã cập nhật quyền “{$permission->name}”.");
    }

    public function destroy(Permission $permission): RedirectResponse
    {
        DB::transaction(function () use ($permission): void {
            $permission->roles()->detach();
            $permission->delete();
        });

        return to_route('permissions.index')->with('status', "Đã xóa quyền “{$permission->name}”.");
    }

    /**
     * Modules suggested in the form: the built-in ones plus any already in use.
     *
     * @return array<string, string> module => label
     */
    private function knownModules(): array
    {
        return collect(Permission::MODULE_LABELS)
            ->union(Permission::distinct()->orderBy('module')->pluck('module')->mapWithKeys(
                fn (string $module): array => [$module => Permission::moduleLabel($module)],
            ))
            ->all();
    }
}
