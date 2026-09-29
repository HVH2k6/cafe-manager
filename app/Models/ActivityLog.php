<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Who changed what and when (step 10). Rows are only ever added, never edited.
 * Written through App\Support\ActivityLogger.
 */
#[Fillable(['user_id', 'action', 'entity_type', 'entity_id', 'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'created_at'])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** Action key => label shown in the log. */
    public const ACTIONS = [
        'order.cancelled' => 'Hủy đơn',
        'order.discounted' => 'Giảm giá đơn',
        'order.item_removed' => 'Bỏ món khỏi đơn',
        'payment.refunded' => 'Hoàn tiền',
        'product.created' => 'Thêm sản phẩm',
        'product.updated' => 'Sửa sản phẩm',
        'product.deleted' => 'Xóa sản phẩm',
        // Stock movements are not repeated here: inventory_transactions already records who and when.
        'employee.created' => 'Thêm nhân viên',
        'employee.updated' => 'Sửa nhân viên',
        'employee.locked' => 'Khóa tài khoản',
        'employee.unlocked' => 'Mở khóa tài khoản',
        'employee.password_reset' => 'Đặt lại mật khẩu',
        'permissions.updated' => 'Đổi phân quyền',
        'settings.updated' => 'Sửa cài đặt',
    ];

    /** Groups for the filter: prefix => label. */
    public const GROUPS = [
        'order' => 'Đơn hàng',
        'payment' => 'Thanh toán',
        'product' => 'Sản phẩm',
        'employee' => 'Nhân viên',
        'permissions' => 'Phân quyền',
        'settings' => 'Cài đặt',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? Str::headline((string) $this->action);
    }

    /**
     * Fields that changed: [field, old, new] (a created or deleted record has one side only).
     *
     * @return list<array{0: string, 1: mixed, 2: mixed}>
     */
    public function changes(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return collect(array_keys([...$old, ...$new]))
            ->map(fn (string $field): array => [$field, $old[$field] ?? null, $new[$field] ?? null])
            ->all();
    }

    #[Scope]
    protected function inGroup(Builder $query, string $group): void
    {
        $query->where('action', 'like', "{$group}.%");
    }
}
