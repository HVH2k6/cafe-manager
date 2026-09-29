<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The activity log (step 10): who changed what and when, newest first.
 * Read-only; rows are never edited or deleted from the app.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'group' => array_key_exists((string) $request->query('group'), ActivityLog::GROUPS) ? (string) $request->query('group') : null,
            'user' => $request->integer('user') ?: null,
            'date' => $request->filled('date') ? rescue(fn () => Carbon::parse($request->query('date'))->toDateString(), null, false) : null,
            'search' => trim((string) $request->query('search')),
        ];

        $logs = ActivityLog::with('user')
            ->when($filters['group'], fn (Builder $query, string $group) => $query->inGroup($group))
            ->when($filters['user'], fn (Builder $query, int $userId) => $query->where('user_id', $userId))
            ->when($filters['date'], fn (Builder $query, string $date) => $query->whereDate('created_at', $date))
            ->when($filters['search'] !== '', fn (Builder $query) => $query->whereLike('description', "%{$filters['search']}%"))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('settings.activity', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::whereIn('id', ActivityLog::whereNotNull('user_id')->distinct()->select('user_id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
