<?php

use App\Actions\Orders\CancelOrder;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\OrderItemStatus;
use Illuminate\Support\Facades\DB;

function settingsData(array $overrides = []): array
{
    return [
        'cafe_name' => 'Cà phê Mộc',
        'cafe_address' => '12 Lê Lợi, Huế',
        'cafe_phone' => '0901 234 567',
        'receipt_footer' => 'Hẹn gặp lại!',
        'receipt_note' => 'Wi-Fi: mocfree',
        'opening_time' => '06:30',
        'closing_time' => '23:00',
        'qr_history_minutes' => 15,
        ...$overrides,
    ];
}

describe('settings', function () {
    test('without saved values the .env details are used', function () {
        config(['app.cafe_name' => 'Cafe ENV']);

        expect(Setting::get('qr_history_minutes'))->toBe(20)
            ->and(Setting::get('opening_time'))->toBe('07:00');
    });

    test('staff need settings.view to open them and settings.update to save', function () {
        $this->actingAs(staffWithPermissions('orders.view'))->get(route('settings.general.edit'))->assertForbidden();

        $viewer = staffWithPermissions('settings.view');
        $this->actingAs($viewer)->get(route('settings.general.edit'))->assertOk()->assertDontSee('Lưu cài đặt');
        $this->actingAs($viewer)->put(route('settings.general.update'), settingsData())->assertForbidden();
    });

    test('saved details replace the .env values everywhere', function () {
        $this->actingAs(owner())->put(route('settings.general.update'), settingsData())->assertSessionHasNoErrors();

        expect(config('app.cafe_name'))->toBe('Cà phê Mộc')
            ->and(Setting::get('qr_history_minutes'))->toBe('15');

        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(owner())->get(route('orders.receipt', $order))
            ->assertSee('Cà phê Mộc')
            ->assertSee('12 Lê Lợi, Huế')
            ->assertSee('0901 234 567')
            ->assertSee('Hẹn gặp lại!')
            ->assertSee('Wi-Fi: mocfree');

        $this->actingAs(owner())->get(route('dashboard'))->assertSee('6:30 – 23:00');
    });

    test('invalid settings are rejected', function (array $overrides, string $field) {
        $this->actingAs(owner())->put(route('settings.general.update'), settingsData($overrides))->assertSessionHasErrors($field);
    })->with([
        'no name' => [['cafe_name' => ''], 'cafe_name'],
        'bad phone' => [['cafe_phone' => 'call me'], 'cafe_phone'],
        'bad time' => [['opening_time' => '25:00'], 'opening_time'],
        'history too short' => [['qr_history_minutes' => 1], 'qr_history_minutes'],
        'history too long' => [['qr_history_minutes' => 999], 'qr_history_minutes'],
    ]);

    test('an overnight cafe is open after midnight on the dashboard', function () {
        Setting::putMany(['opening_time' => '18:00', 'closing_time' => '02:00']);
        $this->travelTo(now()->setTime(1, 0));

        $this->actingAs(owner())->get(route('dashboard'))->assertSee('Quán đang mở');
    });
});

describe('activity log', function () {
    test('cancelling an order is logged with who, why and the old status', function () {
        $manager = staffWithPermissions('orders.view', 'orders.cancel');
        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs($manager)->put(route('orders.cancel', $order), ['cancelled_reason' => 'Khách đổi ý']);

        $log = ActivityLog::sole();
        expect($log)
            ->action->toBe('order.cancelled')
            ->user_id->toBe($manager->id)
            ->entity_type->toBe('order')
            ->entity_id->toBe($order->id)
            ->description->toContain($order->order_number)->toContain('Khách đổi ý')
            ->old_values->toMatchArray(['status' => 'confirmed'])
            ->new_values->toMatchArray(['status' => 'cancelled'])
            ->ip_address->toBe('127.0.0.1');
    });

    test('a discount records the old and new amounts', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 50000])]]);

        $this->actingAs(owner())->put(route('orders.discount.update', $order), ['discount_amount' => 5000, 'discount_reason' => 'Khách quen']);

        expect(ActivityLog::sole())
            ->action->toBe('order.discounted')
            ->old_values->toMatchArray(['discount_amount' => '0.00', 'total_amount' => '50000.00'])
            ->new_values->toMatchArray(['discount_amount' => '5000.00', 'discount_reason' => 'Khách quen']);
    });

    test('a price change says old → new', function () {
        $product = Product::factory()->create(['name' => 'Latte', 'selling_price' => 40000]);

        $this->actingAs(owner())->put(route('products.update', $product), [
            ...$product->only(['name', 'category_id', 'base_price', 'cost_price', 'prep_time_minutes']),
            'selling_price' => 45000,
            'is_available' => 1,
        ])->assertSessionHasNoErrors();

        expect(ActivityLog::sole())
            ->action->toBe('product.updated')
            ->description->toBe('Đổi giá “Latte”: 40.000 → 45.000 đ')
            ->and((float) ActivityLog::sole()->old_values['selling_price'])->toBe(40000.0)
            ->and((float) ActivityLog::sole()->new_values['selling_price'])->toBe(45000.0)
            ->and(array_keys(ActivityLog::sole()->new_values))->toBe(['selling_price']);
    });

    test('saving a product without changes logs nothing', function () {
        $product = Product::factory()->create();

        $this->actingAs(owner())->put(route('products.update', $product), [
            ...$product->only(['name', 'category_id', 'base_price', 'selling_price', 'cost_price', 'prep_time_minutes']),
            'is_available' => (int) $product->is_available,
            'is_featured' => (int) $product->is_featured,
        ]);

        expect(ActivityLog::count())->toBe(0);
    });

    test('permission changes list what was granted and revoked', function () {
        $role = Role::factory()->create(['name' => 'Thu ngân']);
        $view = Permission::factory()->create(['module' => 'orders', 'action' => 'view']);
        $cancel = Permission::factory()->create(['module' => 'orders', 'action' => 'cancel']);
        $role->permissions()->attach($view);

        $this->actingAs(owner())->put(route('role-permissions.update'), ['roles' => [$role->id => [$cancel->id]]]);

        expect(ActivityLog::sole())
            ->action->toBe('permissions.updated')
            ->description->toBe('Đổi quyền vai trò Thu ngân: +orders.cancel; −orders.view');
    });

    test('passwords never reach the log', function () {
        $waiter = User::factory()->for(Role::factory())->create(['name' => 'An']);

        $this->actingAs(owner())->put(route('employees.password.update', $waiter), ['password' => 'Secret-123!', 'password_confirmation' => 'Secret-123!']);

        $log = ActivityLog::sole();
        expect($log->action)->toBe('employee.password_reset')
            ->and(json_encode($log->toArray()))->not->toContain('Secret-123!');
    });

    test('locking an account is logged', function () {
        $waiter = User::factory()->for(Role::factory())->create(['name' => 'Bình']);

        $this->actingAs(owner())->put(route('employees.status.update', $waiter));

        expect(ActivityLog::sole())->action->toBe('employee.locked')->description->toContain('Bình');
    });

    test('a refund is logged with the amount and method', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 30000])]]);
        $order->items()->update(['status' => OrderItemStatus::Served]);
        $order->syncStatusWithItems();
        $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 30000]);

        $this->actingAs(owner())->post(route('payments.refund', $order->fresh()->payment), ['refund_amount' => 10000, 'refund_reason' => 'Đổ ly', 'refund_method' => 'cash']);

        expect(ActivityLog::where('action', 'payment.refunded')->sole())
            ->description->toContain('10.000 đ')->toContain('Tiền mặt')->toContain('Đổ ly');
    });

    test('nothing is logged when the change rolls back', function () {
        $order = placeOrder([[Product::factory()->create()]]);

        try {
            DB::transaction(function () use ($order): void {
                app(CancelOrder::class)->handle($order, owner(), 'Thử');
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        expect(ActivityLog::count())->toBe(0)
            ->and($order->fresh()->status->value)->toBe('confirmed');
    });

    test('the log page needs settings.activity and can be filtered', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        app(CancelOrder::class)->handle($order, owner(), 'Hết món');
        $this->actingAs(owner())->put(route('settings.general.update'), settingsData());

        $this->actingAs(staffWithPermissions('settings.view'))->get(route('settings.activity'))->assertForbidden();

        $this->actingAs(staffWithPermissions('settings.activity'))->get(route('settings.activity'))
            ->assertOk()
            ->assertSee('Hủy đơn')
            ->assertSee('Hết món')
            ->assertSee('Sửa cài đặt quán');

        $this->actingAs(owner())->get(route('settings.activity', ['group' => 'settings']))
            ->assertSee('Sửa cài đặt quán')
            ->assertDontSee('Hết món');

        $this->actingAs(owner())->get(route('settings.activity', ['search' => $order->order_number]))
            ->assertSee('Hết món')
            ->assertDontSee('Sửa cài đặt quán');
    });
});
