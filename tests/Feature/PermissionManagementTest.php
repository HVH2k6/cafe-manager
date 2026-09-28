<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

describe('access', function () {
    test('guests are redirected to the login screen', function () {
        $this->get(route('permissions.index'))->assertRedirect(route('login'));
    });

    test('staff other than the owner are forbidden', function () {
        $manager = User::factory()->for(Role::factory()->state(['name' => 'Quản lý']))->create();
        $permission = Permission::factory()->create();

        $this->actingAs($manager)->get(route('permissions.index'))->assertForbidden();
        $this->actingAs($manager)->post(route('permissions.store'), [])->assertForbidden();
        $this->actingAs($manager)->delete(route('permissions.destroy', $permission))->assertForbidden();
        $this->actingAs($manager)->put(route('role-permissions.update'), [])->assertForbidden();

        expect(Permission::find($permission->id))->not->toBeNull();
    });
});

describe('matrix', function () {
    test('the owner sees permissions grouped by module with each role ticked', function () {
        $barista = Role::factory()->create(['name' => 'Pha chế']);
        $kitchenView = Permission::factory()->key('kitchen.view')->create(['name' => 'Xem màn hình bếp']);
        $ordersView = Permission::factory()->key('orders.view')->create(['name' => 'Xem đơn hàng']);
        $barista->permissions()->attach($kitchenView);

        $response = $this->actingAs(owner())
            ->get(route('permissions.index'))
            ->assertOk()
            ->assertSeeInOrder(['Đơn hàng', 'Xem đơn hàng', 'Màn hình bếp', 'Xem màn hình bếp'])
            ->assertSee('Toàn quyền');

        $checkbox = fn (Permission $permission): string => '/name="roles\['.$barista->id.'\]\[\]"\s+value="'.$permission->id.'"\s+checked/';

        expect($response->getContent())
            ->toMatch($checkbox($kitchenView))
            ->not->toMatch($checkbox($ordersView));
    });

    test('saving the matrix syncs every non-owner role', function () {
        $owner = owner();
        $barista = Role::factory()->create(['name' => 'Pha chế']);
        $waiter = Role::factory()->create(['name' => 'Phục vụ']);
        [$ordersView, $kitchenView] = [
            Permission::factory()->key('orders.view')->create(),
            Permission::factory()->key('kitchen.view')->create(),
        ];
        $waiter->permissions()->attach($kitchenView);

        $this->actingAs($owner)
            ->put(route('role-permissions.update'), [
                'roles' => [$barista->id => [$ordersView->id, $kitchenView->id]],
            ])
            ->assertRedirect(route('permissions.index'))
            ->assertSessionHas('status', 'Đã lưu phân quyền.');

        expect($barista->permissions()->pluck('id')->sort()->values()->all())->toBe([$ordersView->id, $kitchenView->id])
            ->and($waiter->permissions()->count())->toBe(0)
            ->and($owner->role->permissions()->count())->toBe(0);
    });

    test('an unknown permission id is rejected', function () {
        $barista = Role::factory()->create();

        $this->actingAs(owner())
            ->put(route('role-permissions.update'), ['roles' => [$barista->id => [999]]])
            ->assertSessionHasErrors(['roles.'.$barista->id.'.0' => 'Có quyền không tồn tại, vui lòng tải lại trang.']);
    });
});

describe('crud', function () {
    test('the create and edit forms render', function () {
        $permission = Permission::factory()->key('orders.view')->create();

        $this->actingAs(owner())->get(route('permissions.create'))->assertOk()->assertSee('Thêm quyền');
        $this->actingAs(owner())->get(route('permissions.edit', $permission))->assertOk()->assertSee('orders.view');
    });

    test('the owner can create a permission', function () {
        $this->actingAs(owner())
            ->post(route('permissions.store'), [
                'name' => 'Xuất báo cáo',
                'module' => ' Reports ',
                'action' => 'export',
                'description' => 'Tải báo cáo doanh thu',
                'is_active' => '1',
            ])
            ->assertRedirect(route('permissions.index'))
            ->assertSessionHas('status', 'Đã thêm quyền “Xuất báo cáo”.');

        $this->assertDatabaseHas('permission', [
            'name' => 'Xuất báo cáo',
            'module' => 'reports',
            'action' => 'export',
            'is_active' => true,
        ]);
    });

    test('an unticked active box creates the permission switched off', function () {
        $this->actingAs(owner())->post(route('permissions.store'), [
            'name' => 'Xuất báo cáo',
            'module' => 'reports',
            'action' => 'export',
        ]);

        $this->assertDatabaseHas('permission', ['module' => 'reports', 'action' => 'export', 'is_active' => false]);
    });

    test('name, module and action are required', function () {
        $this->actingAs(owner())
            ->post(route('permissions.store'), [])
            ->assertSessionHasErrors([
                'name' => 'Vui lòng nhập tên quyền.',
                'module' => 'Vui lòng nhập module.',
                'action' => 'Vui lòng nhập hành động.',
            ]);
    });

    test('module and action must be lowercase keys', function (string $field, string $value) {
        $this->actingAs(owner())
            ->post(route('permissions.store'), [
                'name' => 'Quyền mới',
                'module' => 'orders',
                'action' => 'view',
                $field => $value,
            ])
            ->assertSessionHasErrors($field);

        expect(Permission::count())->toBe(0);
    })->with([
        'module with a dot' => ['module', 'orders.all'],
        'module with accents' => ['module', 'đơn_hàng'],
        'action starting with a digit' => ['action', '1view'],
        'action with a space' => ['action', 'view all'],
    ]);

    test('the same module and action cannot be added twice', function () {
        Permission::factory()->key('orders.view')->create();

        $this->actingAs(owner())
            ->post(route('permissions.store'), ['name' => 'Bản sao', 'module' => 'orders', 'action' => 'view'])
            ->assertSessionHasErrors(['action' => 'Module này đã có hành động trùng tên.']);
    });

    test('the owner can update a permission and keep its own key', function () {
        $permission = Permission::factory()->key('orders.view')->create(['name' => 'Xem đơn']);

        $this->actingAs(owner())
            ->put(route('permissions.update', $permission), [
                'name' => 'Xem danh sách đơn hàng',
                'module' => 'orders',
                'action' => 'view',
                'is_active' => '1',
            ])
            ->assertRedirect(route('permissions.index'));

        expect($permission->fresh()->name)->toBe('Xem danh sách đơn hàng');
    });

    test('the owner can delete a permission and it is removed from roles', function () {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create(['name' => 'Xóa sản phẩm']);
        $role->permissions()->attach($permission);

        $this->actingAs(owner())
            ->delete(route('permissions.destroy', $permission))
            ->assertRedirect(route('permissions.index'))
            ->assertSessionHas('status', 'Đã xóa quyền “Xóa sản phẩm”.');

        $this->assertModelMissing($permission);
        $this->assertDatabaseMissing('role_has_permission', ['permission_id' => $permission->id]);
    });
});
