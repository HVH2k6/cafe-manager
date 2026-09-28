<?php

namespace App\Http\Controllers;

use App\Http\Requests\DiningTableRequest;
use App\Models\DiningTable;
use App\TableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiningTableController extends Controller
{
    /**
     * Floor plan: tables grouped by section, coloured by status.
     */
    public function index(): View
    {
        $tables = DiningTable::with('qrCode')->orderBy('table_no')->get();

        return view('tables.index', [
            'tablesBySection' => $tables->groupBy(fn (DiningTable $table): string => $table->section ?? 'Chưa xếp khu vực')->sortKeys(),
            'statusCounts' => $tables->countBy(fn (DiningTable $table): string => $table->status->value),
            'totalSeats' => $tables->sum('capacity'),
            'tableCount' => $tables->count(),
        ]);
    }

    public function create(): View
    {
        return view('tables.create', [
            'table' => new DiningTable(['table_no' => (int) DiningTable::max('table_no') + 1, 'capacity' => 2]),
            'sections' => $this->sections(),
        ]);
    }

    public function store(DiningTableRequest $request): RedirectResponse
    {
        $table = DiningTable::create([...$request->validated(), 'status' => TableStatus::Available]);

        return to_route('tables.index')->with('status', "Đã thêm bàn {$table->table_no}.");
    }

    public function edit(DiningTable $table): View
    {
        return view('tables.edit', [
            'table' => $table,
            'sections' => $this->sections(),
        ]);
    }

    public function update(DiningTableRequest $request, DiningTable $table): RedirectResponse
    {
        $table->update($request->validated());

        return to_route('tables.index')->with('status', "Đã cập nhật bàn {$table->table_no}.");
    }

    public function destroy(DiningTable $table): RedirectResponse
    {
        if ($table->status === TableStatus::Occupied) {
            return back()->with('error', "Bàn {$table->table_no} đang có khách, không xóa được.");
        }

        $table->delete();

        return to_route('tables.index')->with('status', "Đã xóa bàn {$table->table_no}.");
    }

    /**
     * @return list<string>
     */
    private function sections(): array
    {
        return DiningTable::whereNotNull('section')->distinct()->orderBy('section')->pluck('section')->all();
    }
}
