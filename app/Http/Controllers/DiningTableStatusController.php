<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\TableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DiningTableStatusController extends Controller
{
    /**
     * Mark a table cleaned, reserved or under maintenance. "Occupied" is set by
     * orders only, so it cannot be chosen here.
     */
    public function update(Request $request, DiningTable $table): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(TableStatus::class)->only(TableStatus::manuallySettable())],
        ], [
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.enum' => 'Không thể chọn trạng thái này bằng tay.',
        ]);

        $table->update(['status' => $validated['status']]);

        return back()->with('status', "Bàn {$table->table_no}: {$table->status->label()}.");
    }
}
