<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @return array<string, mixed>
 */
function validEmployeeData(array $overrides = []): array
{
    return [
        'name' => 'Trần Thị Bình',
        'email' => 'binh@cafeabc.vn',
        'phone' => '0901 234 567',
        'role_id' => Role::factory()->create()->id,
        'password' => 'mat-khau-an-toan',
        'password_confirmation' => 'mat-khau-an-toan',
        'position' => 'Phục vụ',
        'department' => 'Sảnh',
        'contract_type' => 'part_time',
        'hire_date' => '2026-09-01',
        'salary' => '4500000',
        'notes' => 'Làm ca tối',
        ...$overrides,
    ];
}

describe('access', function () {
    test('guests are redirected to the login screen', function () {
        $this->get(route('employees.index'))->assertRedirect(route('login'));
    });

    test('staff without employees.view cannot see the list', function () {
        $this->actingAs(staffWithPermissions('orders.view'))
            ->get(route('employees.index'))
            ->assertForbidden();
    });

    test('staff who may only view cannot add, edit or lock staff', function () {
        $viewer = staffWithPermissions('employees.view');
        $colleague = User::factory()->create();

        $this->actingAs($viewer)->get(route('employees.create'))->assertForbidden();
        $this->actingAs($viewer)->post(route('employees.store'), validEmployeeData())->assertForbidden();
        $this->actingAs($viewer)->get(route('employees.edit', $colleague))->assertForbidden();
        $this->actingAs($viewer)->put(route('employees.status.update', $colleague))->assertForbidden();
        $this->actingAs($viewer)->put(route('employees.password.update', $colleague), [])->assertForbidden();

        expect($colleague->fresh()->is_active)->toBeTrue();
    });

    test('staff with employee permissions cannot touch an owner account', function () {
        $manager = staffWithPermissions('employees.view', 'employees.update', 'employees.delete');
        $owner = owner();

        $this->actingAs($manager)->get(route('employees.edit', $owner))->assertForbidden();
        $this->actingAs($manager)->put(route('employees.update', $owner), validEmployeeData(['password' => null]))->assertForbidden();
        $this->actingAs($manager)->put(route('employees.status.update', $owner))->assertForbidden();
        $this->actingAs($manager)->put(route('employees.password.update', $owner), [
            'password' => 'mat-khau-moi-123',
            'password_confirmation' => 'mat-khau-moi-123',
        ])->assertForbidden();

        expect(Hash::check('password', $owner->fresh()->password))->toBeTrue();
    });
});

describe('list', function () {
    test('working staff are listed by default and staff who left are hidden', function () {
        User::factory()->has(Employee::factory()->state(['position' => 'Pha chế']))->create(['name' => 'Lê Văn Cường']);
        User::factory()->inactive()->create(['name' => 'Phạm Thị Dung']);

        $this->actingAs(owner())
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('Lê Văn Cường')
            ->assertSee('Pha chế')
            ->assertDontSee('Phạm Thị Dung');

        $this->actingAs(owner())
            ->get(route('employees.index', ['status' => 'inactive']))
            ->assertSee('Phạm Thị Dung')
            ->assertDontSee('Lê Văn Cường');
    });

    test('staff can be searched by name, email or phone', function (string $search) {
        User::factory()->create(['name' => 'Lê Văn Cường', 'email' => 'cuong@cafeabc.vn', 'phone' => '0911222333']);
        User::factory()->create(['name' => 'Hoàng Minh Em', 'email' => 'em@cafeabc.vn', 'phone' => '0988777666']);

        $this->actingAs(owner())
            ->get(route('employees.index', ['search' => $search]))
            ->assertSee('Lê Văn Cường')
            ->assertDontSee('Hoàng Minh Em');
    })->with([
        'name' => 'Cường',
        'email' => 'cuong@',
        'phone' => '0911',
    ]);

    test('staff can be filtered by role', function () {
        $barista = Role::factory()->create(['name' => 'Pha chế']);
        User::factory()->for($barista)->create(['name' => 'Lê Văn Cường']);
        User::factory()->create(['name' => 'Hoàng Minh Em']);

        $this->actingAs(owner())
            ->get(route('employees.index', ['role' => $barista->id]))
            ->assertSee('Lê Văn Cường')
            ->assertDontSee('Hoàng Minh Em');
    });
});

describe('create', function () {
    test('the create form renders', function () {
        $this->actingAs(owner())->get(route('employees.create'))->assertOk()->assertSee('Hồ sơ nhân viên');
    });

    test('a new staff member gets an account and an employee profile', function () {
        $data = validEmployeeData();

        $this->actingAs(owner())
            ->post(route('employees.store'), $data)
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('status', 'Đã thêm nhân viên Trần Thị Bình.');

        $user = User::firstWhere('email', 'binh@cafeabc.vn');

        expect($user)
            ->name->toBe('Trần Thị Bình')
            ->role_id->toBe($data['role_id'])
            ->is_active->toBeTrue()
            ->and(Hash::check('mat-khau-an-toan', $user->password))->toBeTrue()
            ->and($user->employee)
            ->position->toBe('Phục vụ')
            ->contract_type->value->toBe('part_time')
            ->salary->toBe('4500000.00')
            ->hire_date->toDateString()->toBe('2026-09-01');
    });

    test('required fields are reported in Vietnamese', function () {
        $this->actingAs(owner())
            ->post(route('employees.store'), [])
            ->assertSessionHasErrors([
                'name' => 'Vui lòng nhập họ tên.',
                'email' => 'Vui lòng nhập email.',
                'role_id' => 'Vui lòng chọn vai trò.',
                'password' => 'Vui lòng nhập mật khẩu.',
                'position' => 'Vui lòng nhập vị trí công việc.',
                'hire_date' => 'Vui lòng nhập ngày vào làm.',
                'salary' => 'Vui lòng nhập lương.',
            ]);

        expect(User::count())->toBe(1);
    });

    test('an email already in use is rejected', function () {
        User::factory()->create(['email' => 'binh@cafeabc.vn']);

        $this->actingAs(owner())
            ->post(route('employees.store'), validEmployeeData())
            ->assertSessionHasErrors(['email' => 'Email này đã được dùng cho tài khoản khác.']);
    });

    test('the password must be confirmed and long enough', function (array $password, string $message) {
        $this->actingAs(owner())
            ->post(route('employees.store'), validEmployeeData($password))
            ->assertSessionHasErrors(['password' => $message]);
    })->with([
        'mismatch' => [['password_confirmation' => 'khac-han'], 'Mật khẩu nhập lại không khớp.'],
        'too short' => [['password' => 'ngan', 'password_confirmation' => 'ngan'], 'Mật khẩu phải có ít nhất 8 ký tự.'],
    ]);

    test('a locked role cannot be assigned', function () {
        $lockedRole = Role::factory()->inactive()->create();

        $this->actingAs(owner())
            ->post(route('employees.store'), validEmployeeData(['role_id' => $lockedRole->id]))
            ->assertSessionHasErrors(['role_id' => 'Vai trò không hợp lệ hoặc đang bị khóa.']);
    });

    test('only the owner can create another owner', function () {
        $ownerRole = Role::firstOrCreate(['name' => Role::OWNER]);

        $this->actingAs(staffWithPermissions('employees.view', 'employees.create'))
            ->post(route('employees.store'), validEmployeeData(['role_id' => $ownerRole->id]))
            ->assertSessionHasErrors(['role_id' => 'Vai trò không hợp lệ hoặc đang bị khóa.']);

        $this->actingAs(owner())
            ->post(route('employees.store'), validEmployeeData(['role_id' => $ownerRole->id]))
            ->assertSessionHasNoErrors();

        expect(User::firstWhere('email', 'binh@cafeabc.vn')->isOwner())->toBeTrue();
    });
});

describe('update', function () {
    test('the edit form renders', function () {
        $employee = User::factory()->has(Employee::factory())->create(['name' => 'Lê Văn Cường']);

        $this->actingAs(owner())->get(route('employees.edit', $employee))->assertOk()->assertSee('Lê Văn Cường');
    });

    test('account and profile are updated together', function () {
        $employee = User::factory()->has(Employee::factory()->state(['position' => 'Phục vụ']))->create();

        $this->actingAs(owner())
            ->put(route('employees.update', $employee), validEmployeeData([
                'name' => 'Tên mới',
                'position' => 'Pha chế',
                'password' => null,
                'password_confirmation' => null,
            ]))
            ->assertRedirect(route('employees.index'));

        expect($employee->fresh())
            ->name->toBe('Tên mới')
            ->employee->position->toBe('Pha chế');
    });

    test('editing never touches the password, even when one is sent', function () {
        $employee = User::factory()->create();

        $this->actingAs(owner())
            ->put(route('employees.update', $employee), validEmployeeData([
                'password' => 'mat-khau-lot-vao',
                'password_confirmation' => 'mat-khau-lot-vao',
            ]))
            ->assertSessionHasNoErrors();

        expect(Hash::check('password', $employee->fresh()->password))->toBeTrue();
    });

    test('a profile is created for an account that had none', function () {
        $account = User::factory()->create();

        $this->actingAs(owner())->put(route('employees.update', $account), validEmployeeData(['password' => null, 'password_confirmation' => null]));

        expect($account->fresh()->employee)->not->toBeNull()->position->toBe('Phục vụ');
    });

    test('the only owner cannot be moved to another role', function () {
        $owner = owner();

        $this->actingAs($owner)
            ->put(route('employees.update', $owner), validEmployeeData(['password' => null, 'password_confirmation' => null]))
            ->assertSessionHasErrors(['role_id' => 'Đây là tài khoản Chủ quán duy nhất, không thể đổi sang vai trò khác.']);

        expect($owner->fresh()->isOwner())->toBeTrue();
    });

    test('an owner can step down when another owner remains', function () {
        $owner = owner();
        owner();

        $this->actingAs($owner)
            ->put(route('employees.update', $owner), validEmployeeData(['password' => null, 'password_confirmation' => null]))
            ->assertSessionHasNoErrors();

        expect($owner->fresh()->isOwner())->toBeFalse();
    });
});

describe('status', function () {
    test('letting someone go locks the account and ends their sessions', function () {
        config(['session.driver' => 'database']);
        $employee = User::factory()->has(Employee::factory())->create(['remember_token' => 'old-token']);
        DB::table('sessions')->insert(['id' => 'their-session', 'user_id' => $employee->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $this->actingAs(owner())
            ->from(route('employees.index'))
            ->put(route('employees.status.update', $employee))
            ->assertRedirect(route('employees.index'))
            ->assertSessionHas('status', "Đã cho {$employee->name} nghỉ việc và khóa tài khoản.");

        $employee->refresh();

        expect($employee->is_active)->toBeFalse()
            ->and($employee->employee->is_active)->toBeFalse()
            ->and($employee->remember_token)->not->toBe('old-token');
        $this->assertDatabaseMissing('sessions', ['id' => 'their-session']);
    });

    test('a former employee can be taken back', function () {
        $employee = User::factory()->inactive()->has(Employee::factory()->state(['is_active' => false]))->create();

        $this->actingAs(owner())->put(route('employees.status.update', $employee));

        expect($employee->fresh()->is_active)->toBeTrue()
            ->and($employee->fresh()->employee->is_active)->toBeTrue();
    });

    test('nobody can lock their own account', function () {
        $owner = owner();

        $this->actingAs($owner)
            ->put(route('employees.status.update', $owner))
            ->assertSessionHas('error', 'Bạn không thể khóa tài khoản của chính mình.');

        expect($owner->fresh()->is_active)->toBeTrue();
    });

    test('a locked user is signed out on their next request', function () {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get(route('dashboard'))->assertOk();

        $employee->update(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản lý.']);

        $this->assertGuest();
    });
});

describe('password', function () {
    test('a new password can be set and old sessions end', function () {
        $employee = User::factory()->create(['remember_token' => 'old-token']);

        $this->actingAs(owner())
            ->put(route('employees.password.update', $employee), [
                'password' => 'mat-khau-moi-123',
                'password_confirmation' => 'mat-khau-moi-123',
            ])
            ->assertSessionHas('status', "Đã đặt lại mật khẩu cho {$employee->name}.");

        $employee->refresh();

        expect(Hash::check('mat-khau-moi-123', $employee->password))->toBeTrue()
            ->and($employee->remember_token)->not->toBe('old-token');
    });

    test('the new password must be confirmed', function () {
        $employee = User::factory()->create();

        $this->actingAs(owner())
            ->put(route('employees.password.update', $employee), [
                'password' => 'mat-khau-moi-123',
                'password_confirmation' => 'khac-han',
            ])
            ->assertSessionHasErrorsIn('password', ['password' => 'Mật khẩu nhập lại không khớp.']);

        expect(Hash::check('password', $employee->fresh()->password))->toBeTrue();
    });
});
