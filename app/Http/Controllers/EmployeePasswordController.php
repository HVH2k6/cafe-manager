<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class EmployeePasswordController extends Controller
{
    /**
     * Set a new password for a staff member, e.g. when they forgot theirs.
     * Their other sessions are ended so the old password stops working everywhere.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('manage-employee', $user);

        $validated = $request->validateWithBag('password', [
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], [
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.confirmed' => 'Mật khẩu nhập lại không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất :min ký tự.',
        ]);

        $user->update(['password' => $validated['password']]);

        if (! $user->is($request->user())) {
            $user->logoutEverywhere();
        }

        return back()->with('status', "Đã đặt lại mật khẩu cho {$user->name}.");
    }
}
