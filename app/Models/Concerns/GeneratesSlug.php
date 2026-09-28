<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Fills `slug` from `name` ("Cà phê sữa đá" => "ca-phe-sua-da") whenever the
 * name changes, adding "-2", "-3"… when another row already uses the slug.
 */
trait GeneratesSlug
{
    public static function bootGeneratesSlug(): void
    {
        static::saving(function (self $model): void {
            if ($model->isDirty('name') || blank($model->slug)) {
                $model->slug = $model->uniqueSlugFor($model->name);
            }
        });
    }

    private function uniqueSlugFor(string $name): string
    {
        $base = Str::limit(Str::slug($name), 90, '') ?: 'item';
        $slug = $base;

        for ($suffix = 2; static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
