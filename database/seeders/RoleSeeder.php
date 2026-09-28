<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Seed the default staff roles of the cafe.
     */
    public function run(): void
    {
        $roles = [
            ['name' => Role::OWNER, 'description' => 'Toàn quyền, bao gồm phân quyền'],
            ['name' => 'Quản lý', 'description' => 'Quản lý vận hành quán'],
            ['name' => 'Pha chế', 'description' => 'Xử lý đơn tại quầy bếp'],
            ['name' => 'Phục vụ', 'description' => 'Phục vụ bàn và nhận order'],
        ];

        foreach ($roles as $displayOrder => $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                [...$role, 'display_order' => $displayOrder, 'is_active' => true],
            );
        }
    }
}
