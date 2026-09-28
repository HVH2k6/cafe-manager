<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('a permission granted to the role allows the ability', function () {
    $user = staffWith([Permission::factory()->key('kitchen.view')->create()]);

    expect(Gate::forUser($user)->allows('kitchen.view'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('kitchen.update'))->toBeFalse();
});

test('a switched off permission no longer allows the ability', function () {
    $user = staffWith([Permission::factory()->key('kitchen.view')->inactive()->create()]);

    expect(Gate::forUser($user)->allows('kitchen.view'))->toBeFalse();
});

test('a locked role grants nothing', function () {
    $user = staffWith([Permission::factory()->key('kitchen.view')->create()], roleIsActive: false);

    expect(Gate::forUser($user)->allows('kitchen.view'))->toBeFalse();
});

test('the owner is allowed every ability, even ones never created', function () {
    $owner = User::factory()->for(Role::factory()->owner())->create();

    expect(Gate::forUser($owner)->allows('kitchen.view'))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('anything.else'))->toBeTrue();
});

test('a permission cannot unlock permission management', function () {
    $user = staffWith([Permission::factory()->key('permissions.manage')->create()]);

    expect(Gate::forUser($user)->allows('manage-permissions'))->toBeFalse();
});

test('the sidebar only lists the modules the role may view', function () {
    $user = staffWith([
        Permission::factory()->key('orders.view')->create(),
        Permission::factory()->key('kitchen.view')->create(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('>Màn hình bếp</span>', false)
        ->assertDontSee('>Sản phẩm</span>', false)
        ->assertDontSee('>Tồn kho', false)
        ->assertDontSee('>Phân quyền</span>', false)
        ->assertDontSee('>Cài đặt</span>', false);
});
