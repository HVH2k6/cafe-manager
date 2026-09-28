<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([RoleSeeder::class, PermissionSeeder::class]);

        User::updateOrCreate(
            ['email' => 'admin@cafeabc.vn'],
            [
                'name' => 'Chủ quán',
                'password' => 'password',
                'role_id' => Role::where('name', Role::OWNER)->value('id'),
                'is_active' => true,
            ],
        );
    }
}
