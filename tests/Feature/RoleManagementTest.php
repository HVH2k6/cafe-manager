<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

test('staff other than the owner cannot manage roles', function () {
    $manager = staffWithPermissions('employees.view', 'employees.update');
    $role = Role::factory()->create();

    $this->actingAs($manager)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($manager)->post(route('roles.store'), ['name' => 'Bảo vệ'])->assertForbidden();
    $this->actingAs($manager)->delete(route('roles.destroy', $role))->assertForbidden();

    $this->assertModelExists($role);
});

test('the owner sees every role with its staff and permission counts', function () {
    $barista = Role::factory()->create(['name' => 'Pha chế']);
    User::factory()->count(2)->for($barista)->create();
    $barista->permissions()->attach(Permission::factory()->count(3)->create());

    $this->actingAs(owner())
        ->get(route('roles.index'))
        ->assertOk()
        ->assertSeeInOrder(['Pha chế', '2', '3']);
});

test('the create and edit forms render', function () {
    $role = Role::factory()->create(['name' => 'Pha chế']);

    $this->actingAs(owner())->get(route('roles.create'))->assertOk()->assertSee('Thêm vai trò');
    $this->actingAs(owner())->get(route('roles.edit', $role))->assertOk()->assertSee('Pha chế');
});

test('the owner can add a role', function () {
    $this->actingAs(owner())
        ->post(route('roles.store'), ['name' => '  Thu ngân ', 'description' => 'Tính tiền', 'display_order' => '3', 'is_active' => '1'])
        ->assertRedirect(route('roles.index'));

    $this->assertDatabaseHas('role', ['name' => 'Thu ngân', 'description' => 'Tính tiền', 'display_order' => 3, 'is_active' => true]);
});

test('role names must be unique', function () {
    Role::factory()->create(['name' => 'Thu ngân']);

    $this->actingAs(owner())
        ->post(route('roles.store'), ['name' => 'Thu ngân'])
        ->assertSessionHasErrors(['name' => 'Tên vai trò này đã tồn tại.']);
});

test('the owner can rename and lock a role', function () {
    $role = Role::factory()->create(['name' => 'Thu ngân']);

    $this->actingAs(owner())
        ->put(route('roles.update', $role), ['name' => 'Thu ngân ca tối'])
        ->assertRedirect(route('roles.index'));

    expect($role->fresh())->name->toBe('Thu ngân ca tối')->is_active->toBeFalse();
});

test('the owner role cannot be renamed or locked', function () {
    $owner = owner();

    $this->actingAs($owner)
        ->put(route('roles.update', $owner->role), ['name' => 'Ông chủ'])
        ->assertSessionHasErrors([
            'name' => 'Không thể đổi tên vai trò Chủ quán.',
            'is_active' => 'Không thể khóa vai trò Chủ quán.',
        ]);

    $this->actingAs($owner)
        ->put(route('roles.update', $owner->role), ['name' => Role::OWNER, 'description' => 'Người sở hữu quán', 'is_active' => '1'])
        ->assertSessionHasNoErrors();

    expect($owner->role->fresh())->name->toBe(Role::OWNER)->description->toBe('Người sở hữu quán');
});

test('an unused role can be deleted along with its permissions', function () {
    $role = Role::factory()->create(['name' => 'Tạp vụ']);
    $role->permissions()->attach(Permission::factory()->create());

    $this->actingAs(owner())
        ->delete(route('roles.destroy', $role))
        ->assertSessionHas('status', 'Đã xóa vai trò “Tạp vụ”.');

    $this->assertModelMissing($role);
    $this->assertDatabaseMissing('role_has_permission', ['role_id' => $role->id]);
});

test('a role still held by staff cannot be deleted', function () {
    $role = Role::factory()->create(['name' => 'Phục vụ']);
    User::factory()->for($role)->create();

    $this->actingAs(owner())
        ->delete(route('roles.destroy', $role))
        ->assertSessionHas('error', 'Vai trò “Phục vụ” còn 1 nhân viên. Hãy chuyển họ sang vai trò khác hoặc khóa vai trò này.');

    $this->assertModelExists($role);
});

test('the owner role cannot be deleted', function () {
    $owner = owner();

    $this->actingAs($owner)
        ->delete(route('roles.destroy', $owner->role))
        ->assertSessionHas('error', 'Không thể xóa vai trò Chủ quán.');

    $this->assertModelExists($owner->role);
});
