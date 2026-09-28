<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Default permissions: key => [name, roles granted when first created].
     * The owner role is not listed because it always has every permission.
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    private const PERMISSIONS = [
        'orders.view' => ['Xem đơn hàng', ['Quản lý', 'Pha chế', 'Phục vụ']],
        'orders.create' => ['Tạo đơn hàng', ['Quản lý', 'Phục vụ']],
        'orders.update' => ['Cập nhật đơn hàng', ['Quản lý', 'Phục vụ']],
        'orders.cancel' => ['Hủy đơn hàng', ['Quản lý']],
        'orders.discount' => ['Giảm giá đơn hàng', ['Quản lý']],
        'payments.create' => ['Thu tiền (tiền mặt, thẻ, QR chuyển khoản)', ['Quản lý', 'Phục vụ']],
        'payments.refund' => ['Hoàn tiền cho khách (ghi nhận hoàn thủ công)', ['Quản lý']],
        'kitchen.view' => ['Xem màn hình bếp', ['Quản lý', 'Pha chế']],
        'kitchen.update' => ['Cập nhật trạng thái món', ['Quản lý', 'Pha chế']],
        'tables.view' => ['Xem sơ đồ bàn', ['Quản lý', 'Pha chế', 'Phục vụ']],
        'tables.status' => ['Đổi trạng thái bàn (dọn bàn, đặt trước)', ['Quản lý', 'Phục vụ']],
        'tables.create' => ['Thêm bàn', ['Quản lý']],
        'tables.update' => ['Sửa thông tin bàn', ['Quản lý']],
        'tables.delete' => ['Xóa bàn', ['Quản lý']],
        'products.view' => ['Xem sản phẩm, danh mục, combo', ['Quản lý']],
        'products.create' => ['Thêm sản phẩm, danh mục, combo', ['Quản lý']],
        'products.update' => ['Sửa sản phẩm, danh mục, combo', ['Quản lý']],
        'products.delete' => ['Xóa sản phẩm, danh mục, combo', ['Quản lý']],
        'inventory.view' => ['Xem tồn kho', ['Quản lý', 'Pha chế']],
        'inventory.update' => ['Nhập hàng, kiểm kê, ghi hư hỏng, chỉnh ngưỡng kho', ['Quản lý']],
        'reports.view' => ['Xem báo cáo', ['Quản lý']],
        'employees.view' => ['Xem nhân viên', ['Quản lý']],
        'employees.create' => ['Thêm nhân viên', []],
        'employees.update' => ['Sửa nhân viên, đặt lại mật khẩu', []],
        'employees.delete' => ['Cho nghỉ việc / khóa tài khoản', []],
        'settings.view' => ['Xem cài đặt', []],
        'settings.update' => ['Sửa cài đặt', []],
    ];

    /**
     * Seed the default permissions. Existing permissions and the owner's
     * later changes to the matrix are left untouched.
     */
    public function run(): void
    {
        $roleIds = Role::pluck('id', 'name');

        foreach (self::PERMISSIONS as $key => [$name, $roleNames]) {
            [$module, $action] = explode('.', $key);

            $permission = Permission::firstOrCreate(
                ['module' => $module, 'action' => $action],
                ['name' => $name, 'is_active' => true],
            );

            if ($permission->wasRecentlyCreated) {
                $permission->roles()->attach($roleIds->only($roleNames)->values());
            }
        }
    }
}
