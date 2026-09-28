<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('guests are redirected to the login screen', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the owner sees the dashboard with the permissions menu', function () {
    $owner = User::factory()->for(Role::factory()->owner())->create(['name' => 'Nguyễn Linh']);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Tổng quan', 'Đơn hàng', 'Màn hình bếp', 'Sản phẩm', 'Tồn kho', 'Báo cáo', 'Nhân viên', 'Phân quyền', 'Cài đặt'])
        ->assertSee('Tổng quan hôm nay')
        ->assertSee('NL');
});

test('staff other than the owner do not see the permissions menu', function () {
    $manager = User::factory()->for(Role::factory()->state(['name' => 'Quản lý']))->create();

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Phân quyền');
});

test('only the owner role may manage permissions', function (string $roleName, bool $allowed) {
    $user = User::factory()->for(Role::factory()->state(['name' => $roleName]))->create();

    expect(Gate::forUser($user)->allows('manage-permissions'))->toBe($allowed);
})->with([
    'Chủ quán' => ['Chủ quán', true],
    'Quản lý' => ['Quản lý', false],
    'Pha chế' => ['Pha chế', false],
    'Phục vụ' => ['Phục vụ', false],
]);
