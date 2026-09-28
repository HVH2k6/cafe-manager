<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'phone', 'role_id', 'avatar_url', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function isOwner(): bool
    {
        return $this->role?->name === Role::OWNER;
    }

    /**
     * Whether this is the only active owner account, which must never be
     * locked or demoted or nobody could manage the cafe any more.
     */
    public function isLastActiveOwner(): bool
    {
        if (! $this->isOwner() || ! $this->is_active) {
            return false;
        }

        return ! static::query()
            ->whereKeyNot($this->getKey())
            ->where('is_active', true)
            ->whereRelation('role', 'name', Role::OWNER)
            ->exists();
    }

    /**
     * End every session of this user, including "remember me" cookies.
     */
    public function logoutEverywhere(): void
    {
        $this->setRememberToken(Str::random(60));
        $this->save();

        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $this->getKey())
                ->delete();
        }
    }

    /**
     * Whether the user's role grants the active permission "{module}.{action}".
     * The owner is handled separately by the Gate and always passes.
     */
    public function hasPermission(string $key): bool
    {
        if (! $this->role?->is_active) {
            return false;
        }

        return $this->role->permissions->contains(
            fn (Permission $permission): bool => $permission->is_active && $permission->key === $key,
        );
    }

    /**
     * Up to two initials for the avatar, e.g. "Nguyễn Linh" => "NL".
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $word): string => Str::upper(Str::substr($word, 0, 1)))
            ->take(-2)
            ->implode('');
    }
}
