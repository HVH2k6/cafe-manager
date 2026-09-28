<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmployeeStatusController extends Controller
{
    /**
     * Let a staff member go (lock the account) or take them back. Staff are
     * never deleted because orders and payments keep referencing them.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-employee', $user);

        if ($user->is($request->user())) {
            return back()->with('error', 'Bạn không thể khóa tài khoản của chính mình.');
        }

        if ($user->isLastActiveOwner()) {
            return back()->with('error', 'Không thể khóa tài khoản Chủ quán duy nhất.');
        }

        $isActive = ! $user->is_active;

        DB::transaction(function () use ($user, $isActive): void {
            $user->update(['is_active' => $isActive]);
            $user->employee?->update(['is_active' => $isActive]);
        });

        if (! $isActive) {
            $user->logoutEverywhere();
        }

        return back()->with('status', $isActive
            ? "Đã mở lại tài khoản của {$user->name}."
            : "Đã cho {$user->name} nghỉ việc và khóa tài khoản.");
    }
}
