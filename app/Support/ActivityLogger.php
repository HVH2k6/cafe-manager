<?php

namespace App\Support;

use App\Models\ActivityLog;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes the activity log (step 10).
 *
 *  - Inside a transaction the row is written only once it commits, so a
 *    rolled-back change leaves no trace.
 *  - Secrets (passwords, tokens) are never stored; long values are shortened.
 *  - A logging failure is reported but never breaks the action being logged.
 */
class ActivityLogger
{
    /** Fields never written to the log. */
    private const HIDDEN = ['password', 'remember_token', 'session_token', 'qr_token', 'checksum_key', 'api_key'];

    /** Bookkeeping fields that say nothing to a reader. */
    private const IGNORED = ['created_at', 'updated_at', 'last_login_at'];

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function log(string $action, string $description, ?Model $subject = null, ?array $old = null, ?array $new = null): void
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        $row = [
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $subject ? Str::snake(class_basename($subject)) : null,
            'entity_id' => $subject?->getKey(),
            'description' => Str::limit($description, 490),
            'old_values' => $old === null ? null : self::clean($old),
            'new_values' => $new === null ? null : self::clean($new),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'created_at' => now(),
        ];

        DB::afterCommit(function () use ($row): void {
            try {
                ActivityLog::create($row);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * Log an update with only the fields that actually changed (nothing when none did).
     *
     * @param  list<string>|null  $only  fields worth logging (null = all but the ignored ones)
     */
    public static function changed(string $action, string $description, Model $model, ?array $only = null): void
    {
        $fields = collect($model->getChanges())->keys()
            ->reject(fn (string $field): bool => in_array($field, self::IGNORED, true))
            ->when($only !== null, fn ($fields) => $fields->intersect($only))
            ->values();

        if ($fields->isEmpty()) {
            return;
        }

        $original = $model->getPrevious();

        self::log(
            $action,
            $description,
            $model,
            $fields->mapWithKeys(fn (string $field): array => [$field => $original[$field] ?? null])->all(),
            $fields->mapWithKeys(fn (string $field): array => [$field => $model->getAttribute($field)])->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function clean(array $values): array
    {
        return collect($values)
            ->map(fn (mixed $value, string $field): mixed => match (true) {
                in_array($field, self::HIDDEN, true) => '••••••',
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                is_string($value) => Str::limit($value, 300),
                default => $value,
            })
            ->all();
    }
}
