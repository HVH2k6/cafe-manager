<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A permission is checked by its key "{module}.{action}", e.g. @can('orders.view').
 */
#[Fillable(['name', 'module', 'action', 'description', 'is_active'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * Vietnamese labels for the modules of the app. Modules created by the
     * owner that are not listed here fall back to a headline of their key.
     */
    public const MODULE_LABELS = [
        'orders' => 'Đơn hàng',
        'payments' => 'Thanh toán',
        'kitchen' => 'Màn hình bếp',
        'tables' => 'Bàn',
        'products' => 'Sản phẩm',
        'inventory' => 'Tồn kho',
        'reports' => 'Báo cáo',
        'employees' => 'Nhân viên',
        'settings' => 'Cài đặt',
    ];

    protected $table = 'permission';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_has_permission');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function key(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->module}.{$this->action}");
    }

    public static function moduleLabel(string $module): string
    {
        return self::MODULE_LABELS[$module] ?? Str::headline($module);
    }
}
