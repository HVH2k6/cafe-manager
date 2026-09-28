<?php

use App\Actions\Orders\CancelOrder;
use App\Events\OrderStatusUpdated;
use App\Events\StaffCallAnswered;
use App\Events\StaffCalled;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\QrNotification;
use App\Models\QrOperatingHour;
use App\Models\QrSession;
use App\Models\User;
use App\OrderStatus;
use App\PaymentMethod;
use App\QrNotificationType;
use App\QrSessionStatus;
use App\TableStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

function qrCodeForTable(int $tableNo = 3): QrCode
{
    return QrCode::issueFor(DiningTable::factory()->create(['table_no' => $tableNo]));
}

/**
 * Scan the code and press "Xem Menu", the way a customer's phone does.
 */
function startQrSession(QrCode $code, ?string $name = null): QrSession
{
    test()->post(route('qr.sessions.store', $code->qr_token), ['intent' => 'menu', 'customer_name' => $name]);

    return QrSession::latest('id')->firstOrFail();
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return array<string, mixed>
 */
function qrCheckout(array $items, string $method = 'cash', array $overrides = []): array
{
    return ['items' => $items, 'payment_method' => $method, 'accept_terms' => true, ...$overrides];
}

function fakePayosCreateLink(): void
{
    Http::fake(['api-merchant.payos.vn/*' => Http::response(['code' => '00', 'desc' => 'success', 'data' => [
        'bin' => '970422',
        'accountNumber' => 'VQRQAMIDG1780',
        'accountName' => 'QUAN CAFE ABC',
        'paymentLinkId' => 'link-1',
        'status' => 'PENDING',
        'checkoutUrl' => 'https://pay.payos.vn/web/link-1',
        'qrCode' => '000201010212',
    ]])]);
}

function openAllWeek(string $opening, string $closing, array $closedDays = []): void
{
    foreach (QrOperatingHour::DAYS as $day) {
        QrOperatingHour::create([
            'day_of_week' => $day,
            'opening_time' => $opening,
            'closing_time' => $closing,
            'is_open' => ! in_array($day, $closedDays, true),
        ]);
    }
}

describe('operating hours', function () {
    test('without any hours set up, QR orders are taken all day', function () {
        expect(QrOperatingHour::isOpenAt(Carbon::parse('2026-09-28 03:00')))->toBeTrue();
    });

    test('same-day hours include the opening minute and exclude the closing one', function () {
        openAllWeek('07:00:00', '22:00:00');

        expect(QrOperatingHour::isOpenAt(Carbon::parse('2026-09-28 06:59')))->toBeFalse()
            ->and(QrOperatingHour::isOpenAt(Carbon::parse('2026-09-28 07:00')))->toBeTrue()
            ->and(QrOperatingHour::isOpenAt(Carbon::parse('2026-09-28 21:59')))->toBeTrue()
            ->and(QrOperatingHour::isOpenAt(Carbon::parse('2026-09-28 22:00')))->toBeFalse();
    });

    test('overnight hours run into the next morning, even when that day is closed', function () {
        openAllWeek('18:00:00', '02:00:00', closedDays: ['Saturday']);

        // 2026-10-02 is a Friday.
        expect(QrOperatingHour::isOpenAt(Carbon::parse('2026-10-02 17:59')))->toBeFalse()
            ->and(QrOperatingHour::isOpenAt(Carbon::parse('2026-10-02 23:30')))->toBeTrue()
            ->and(QrOperatingHour::isOpenAt(Carbon::parse('2026-10-03 01:30')))->toBeTrue()
            ->and(QrOperatingHour::isOpenAt(Carbon::parse('2026-10-03 02:30')))->toBeFalse()
            ->and(QrOperatingHour::isOpenAt(Carbon::parse('2026-10-03 19:00')))->toBeFalse();
    });

    test('a closed day takes no orders', function () {
        openAllWeek('07:00:00', '22:00:00', closedDays: ['Monday']);

        expect(QrOperatingHour::isOpenAt(Carbon::parse('2026-09-28 10:00')))->toBeFalse();
    });

    test('staff with settings.update save the week', function () {
        $days = collect(QrOperatingHour::DAYS)->mapWithKeys(fn (string $day): array => [$day => [
            'is_open' => $day === 'Sunday' ? '0' : '1',
            'opening_time' => '06:30',
            'closing_time' => '23:00',
        ]])->all();

        $this->actingAs(staffWithPermissions('settings.view', 'settings.update'))
            ->put(route('settings.qr-hours.update'), ['days' => $days])
            ->assertSessionHasNoErrors();

        expect(QrOperatingHour::count())->toBe(7)
            ->and(QrOperatingHour::where('day_of_week', 'Sunday')->first()->is_open)->toBeFalse()
            ->and(QrOperatingHour::labelFor(Carbon::parse('2026-09-28')))->toBe('6:30 – 23:00');

        $this->actingAs(staffWithPermissions('settings.view'))
            ->put(route('settings.qr-hours.update'), ['days' => $days])
            ->assertForbidden();
    });
});

describe('landing page and session', function () {
    test('scanning the code shows the table and starts a session for the menu', function () {
        $code = qrCodeForTable(3);

        $this->get(route('qr.show', $code->qr_token))
            ->assertOk()
            ->assertSee('Bàn 3')
            ->assertSee('Đang mở cửa');

        $this->post(route('qr.sessions.store', $code->qr_token), ['intent' => 'menu', 'customer_name' => 'Minh'])
            ->assertRedirect(route('qr.menu', QrSession::first()));

        expect(QrSession::first())
            ->table_id->toBe($code->table_id)
            ->status->toBe(QrSessionStatus::Active)
            ->cart_data->toBe(['customer_name' => 'Minh']);
    });

    test('the same phone keeps its session instead of opening new ones', function () {
        $code = qrCodeForTable();

        startQrSession($code);
        startQrSession($code);

        expect(QrSession::count())->toBe(1);
    });

    test('an unknown, replaced or paused code explains what to do', function () {
        $code = qrCodeForTable(5);
        $oldToken = $code->qr_token;

        QrCode::issueFor($code->diningTable);

        $this->get(route('qr.show', $oldToken))->assertNotFound()->assertSee('Mã QR không còn hiệu lực');

        $code->refresh()->update(['is_active' => false, 'disabled_reason' => 'Bàn đang sửa, vui lòng gọi nhân viên.']);

        $this->get(route('qr.show', $code->qr_token))->assertOk()->assertSee('Bàn đang sửa, vui lòng gọi nhân viên.');
        $this->post(route('qr.sessions.store', $code->qr_token), ['intent' => 'menu'])->assertSessionHas('error');
        expect(QrSession::count())->toBe(0);
    });

    test('an unknown session token shows a friendly page', function () {
        $this->get('/s/not-a-real-token')->assertNotFound()->assertSee('Không tìm thấy phiên gọi món');
    });

    test('the menu lists what is on sale', function () {
        $code = qrCodeForTable();
        Product::factory()->create(['name' => 'Cà phê sữa đá']);
        Product::factory()->create(['name' => 'Món ngừng bán', 'is_available' => false]);

        $this->get(route('qr.menu', startQrSession($code)))
            ->assertOk()
            ->assertSee('Cà phê sữa đá', false)
            ->assertDontSee('Món ngừng bán', false);
    });

    test('the cart is kept on the server', function () {
        $session = startQrSession(qrCodeForTable(), 'Minh');
        $latte = Product::factory()->create();

        $this->putJson(route('qr.cart.update', $session), ['items' => [productLine($latte, 2, [], 'ít đá')]])
            ->assertNoContent();

        expect($session->fresh()->cart_data)->toBe([
            'customer_name' => 'Minh',
            'items' => [['type' => 'product', 'id' => $latte->id, 'quantity' => 2, 'variant_ids' => [], 'note' => 'ít đá']],
        ]);
    });

    test('an expired session sends the customer back to the table page', function () {
        $code = qrCodeForTable();
        $session = startQrSession($code);
        $session->update(['expires_at' => now()->subMinute()]);

        $this->get(route('qr.menu', $session))->assertRedirect(route('qr.show', $code->qr_token));
        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine(Product::factory()->create())]))
            ->assertStatus(410)
            ->assertJsonPath('redirect', route('qr.show', $code->qr_token));
    });
});

describe('ordering', function () {
    test('the order waits for staff, is priced on the server and seats the table', function () {
        $code = qrCodeForTable(3);
        $session = startQrSession($code, 'Minh');
        $latte = Product::factory()->create(['selling_price' => 25000]);
        $large = $latte->variants()->create(['name' => 'Size', 'value' => 'Lớn', 'price_modifier' => 5000]);

        $this->postJson(route('qr.orders.store', $session), qrCheckout(
            [productLine($latte, 2, [$large->id], 'ít đá'), ['type' => 'product', 'id' => $latte->id, 'quantity' => 1, 'price' => 1]],
            'bank_transfer',
            ['notes' => 'Mang ra sau', 'customer_name' => 'Minh'],
        ))->assertCreated()->assertJsonPath('redirect', route('qr.order', $session));

        $order = Order::sole();

        expect($order)
            ->status->toBe(OrderStatus::Pending)
            ->user_id->toBeNull()
            ->qr_session_id->toBe($session->id)
            ->table_id->toBe($code->table_id)
            ->customer_name->toBe('Minh')
            ->notes->toBe('Mang ra sau')
            ->payment_preference->toBe(PaymentMethod::BankTransfer)
            ->and((float) $order->total_amount)->toBe(85000.0) // 2 × 30.000 + 25.000, whatever the phone sent
            ->and($order->creatorLabel())->toBe('Khách (QR)')
            ->and($code->diningTable->fresh()->status)->toBe(TableStatus::Occupied)
            ->and($session->fresh())->order_id->toBe($order->id)->cart_data->toBe(['customer_name' => 'Minh', 'items' => []])
            ->and($session->notifications()->first()->type)->toBe(QrNotificationType::OrderReceived);

        $this->get(route('qr.order', $session))
            ->assertOk()
            ->assertSee('Đặt món thành công')
            ->assertSee($order->order_number)
            ->assertSee('Chờ xác nhận');
    });

    test('ordering again, or from a second phone at the table, adds to the open order', function () {
        $code = qrCodeForTable();
        $product = Product::factory()->create(['selling_price' => 20000]);
        $first = startQrSession($code);

        $this->postJson(route('qr.orders.store', $first), qrCheckout([productLine($product)]))->assertCreated();
        $this->postJson(route('qr.orders.store', $first), qrCheckout([productLine($product)]))->assertCreated();

        // Another phone: a new browser session.
        $this->flushSession();
        $second = startQrSession($code);
        $this->postJson(route('qr.orders.store', $second), qrCheckout([productLine($product, 2)]))->assertCreated();

        $order = Order::sole();

        expect($order->items()->sum('quantity'))->toBe(4)
            ->and((float) $order->total_amount)->toBe(80000.0)
            ->and($second->fresh()->order_id)->toBe($order->id)
            ->and($order->followingQrSessions()->count())->toBe(2);
    });

    test('a paid order at the table sends the customer to staff to order more', function () {
        $code = qrCodeForTable();
        $product = Product::factory()->create();
        $order = placeOrder([[$product]], $code->diningTable);
        $order->payment()->create(['amount' => $order->total_amount, 'method' => 'cash', 'status' => 'completed', 'paid_at' => now()]);

        $this->postJson(route('qr.orders.store', startQrSession($code)), qrCheckout([productLine($product)]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.order.0', "Bàn đang có đơn {$order->order_number} đã thanh toán. Vui lòng gọi nhân viên để gọi thêm món.");
    });

    test('orders are refused outside QR hours or once the code is paused', function () {
        $code = qrCodeForTable();
        $session = startQrSession($code);
        $product = Product::factory()->create();

        openAllWeek('07:00:00', '22:00:00');
        $this->travelTo(Carbon::parse('2026-09-28 23:00'));
        $session->touchActivity();

        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine($product)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order' => 'ngoài giờ nhận đơn']);

        $this->travelTo(Carbon::parse('2026-09-28 10:00'));
        $session->touchActivity();
        $code->update(['is_active' => false]);

        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine($product)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order' => 'tạm ngưng']);

        expect(Order::count())->toBe(0);
    });

    test('customers must accept the terms, pick a known payment method and stay within limits', function () {
        $session = startQrSession(qrCodeForTable());
        $product = Product::factory()->create();

        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine($product, 21)], 'qr_pay', ['accept_terms' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity', 'payment_method', 'accept_terms']);
    });

    test('a customer cannot send order after order to spam the bar', function () {
        $session = startQrSession(qrCodeForTable());
        $product = Product::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine($product)]))->assertCreated();
        }

        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine($product)]))
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Bạn đã gửi nhiều đơn liên tiếp. Vui lòng chờ vài phút hoặc gọi nhân viên.');
    });
});

describe('calling staff', function () {
    test('the call pops up for staff, and the customer sees who answered', function () {
        Event::fake([StaffCalled::class, StaffCallAnswered::class]);
        $code = qrCodeForTable(7);

        $this->post(route('qr.sessions.store', $code->qr_token), ['intent' => 'call'])
            ->assertRedirect(route('qr.show', $code->qr_token))
            ->assertSessionHas('status');

        $call = QrNotification::sole();

        expect($call)->type->toBe(QrNotificationType::CallStaff)->message->toBe('Bàn 7 gọi nhân viên.')->is_read->toBeFalse();
        Event::assertDispatched(StaffCalled::class, fn (StaffCalled $event): bool => $event->broadcastWith()['place'] === 'Bàn 7'
            && $event->broadcastOn()[0]->name === 'private-orders');

        $this->actingAs(User::factory()->create())->postJson(route('qr-calls.answer', $call))->assertForbidden();

        $waiter = staffWithPermissions('orders.view');
        $waiter->update(['name' => 'Lan']);

        $this->actingAs($waiter)->postJson(route('qr-calls.answer', $call))->assertOk();

        expect($call->fresh())->is_read->toBeTrue()->title->toBe('Lan đang tới bàn bạn');
        Event::assertDispatched(StaffCallAnswered::class, fn (StaffCallAnswered $event): bool => $event->broadcastOn()[0]->name === "qr-session.{$call->qrSession->session_token}");

        auth()->logout();
        $this->get(route('qr.show', $code->qr_token))->assertSee('Lan đang tới bàn bạn');
    });

    test('without the WebSocket, staff screens poll the waiting calls and QR orders', function () {
        Event::fake([StaffCalled::class]);
        $code = qrCodeForTable(4);
        $session = startQrSession($code);
        $this->post(route('qr.call', $session));
        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine(Product::factory()->create())]));
        $order = Order::sole();

        $this->actingAs(staffWithPermissions('orders.view'))->getJson(route('staff-alerts'))
            ->assertOk()
            ->assertJsonPath('pending_count', 1)
            ->assertJsonPath('calls.0.place', 'Bàn 4')
            ->assertJsonPath('pending.0.order_number', $order->order_number)
            ->assertJsonPath('pending.0.url', route('orders.show', $order));

        $this->actingAs(staffWithPermissions('kitchen.view'))->getJson(route('staff-alerts'))->assertForbidden();
    });

    test('pressing the button again does not pile up calls, and is rate limited', function () {
        Event::fake([StaffCalled::class]);
        $session = startQrSession(qrCodeForTable());

        foreach (range(1, 3) as $attempt) {
            $this->post(route('qr.call', $session))->assertRedirect();
        }

        $this->post(route('qr.call', $session))->assertSessionHas('error');

        expect(QrNotification::where('type', QrNotificationType::CallStaff)->count())->toBe(1);
        Event::assertDispatchedTimes(StaffCalled::class, 1);
    });
});

describe('following the order', function () {
    test('status changes are broadcast to the customer phones following the order', function () {
        $session = startQrSession(qrCodeForTable());
        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine(Product::factory()->create())]));
        $order = Order::sole();
        Event::fake([OrderStatusUpdated::class]);

        $this->actingAs(owner())->put(route('orders.confirm', $order))->assertSessionHas('status');

        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => collect($event->broadcastOn())
            ->pluck('name')->contains("qr-session.{$session->session_token}"));
        expect($session->notifications()->where('type', QrNotificationType::OrderReceived)->count())->toBe(2);
    });

    test('closing the order closes the customer session', function () {
        $session = startQrSession(qrCodeForTable());
        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine(Product::factory()->create())]));

        app(CancelOrder::class)->handle(Order::sole(), owner(), 'Khách đổi ý');

        expect($session->fresh())->status->toBe(QrSessionStatus::Completed)->completed_at->not->toBeNull();

        $this->get(route('qr.order', $session))->assertOk()->assertSee('Đơn đã bị hủy')->assertSee('Khách đổi ý');
    });

    test('staff see that the order came by QR and how the customer wants to pay', function () {
        $session = startQrSession(qrCodeForTable());
        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine(Product::factory()->create())], 'card'));

        $this->actingAs(owner())->get(route('orders.show', Order::sole()))
            ->assertOk()
            ->assertSee('Khách tự đặt qua QR')
            ->assertSee('Thẻ tín dụng / ghi nợ')
            ->assertSee('Khách (QR)');
    });

    test('idle sessions are closed by the scheduled command', function () {
        $code = qrCodeForTable();
        $expired = QrSession::start($code, request());
        $abandoned = QrSession::start($code, request());
        $abandoned->update(['cart_data' => ['items' => [productLine(Product::factory()->create())]]]);
        $following = QrSession::start($code, request());
        $following->update(['order_id' => placeOrder([[Product::factory()->create()]], $code->diningTable)->id]);
        $fresh = QrSession::start($code, request());

        QrSession::whereKeyNot($fresh->id)->update(['expires_at' => now()->subMinute()]);

        $this->artisan('qr:expire-sessions')->assertSuccessful();

        expect($expired->fresh()->status)->toBe(QrSessionStatus::Expired)
            ->and($abandoned->fresh()->status)->toBe(QrSessionStatus::Abandoned)
            ->and($following->fresh()->status)->toBe(QrSessionStatus::Active) // its order is still open
            ->and($fresh->fresh()->status)->toBe(QrSessionStatus::Active);
    });
});

describe('paying by VietQR', function () {
    beforeEach(function () {
        config(['services.payos' => [
            'client_id' => 'test-client',
            'api_key' => 'test-api-key',
            'checksum_key' => 'test-checksum',
            'base_url' => 'https://api-merchant.payos.vn',
            'timeout' => 5,
            'link_lifetime_minutes' => 15,
        ]]);
    });

    test('a transfer or e-wallet order shows its VietQR code right after it is sent', function () {
        fakePayosCreateLink();
        $session = startQrSession(qrCodeForTable());

        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine(Product::factory()->create(['selling_price' => 45000]))], 'e_wallet'))
            ->assertCreated();

        $order = Order::sole();

        expect($order->status)->toBe(OrderStatus::Pending)
            ->and($order->payosLinks()->sole())->amount->toBe(45000)->created_by->toBeNull()
            ->and($session->notifications()->where('type', QrNotificationType::PaymentLink)->exists())->toBeTrue();
        Http::assertSent(fn ($request): bool => $request['amount'] === 45000
            && $request['returnUrl'] === route('qr.pay.return', $session));

        $this->get(route('qr.order', $session))->assertSee('Quét mã để chuyển khoản')->assertSee('img.vietqr.io', false);
    });

    test('a cash order has no code, but the customer may still choose to transfer', function () {
        fakePayosCreateLink();
        $session = startQrSession(qrCodeForTable());

        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine(Product::factory()->create())]))->assertCreated();

        Http::assertNothingSent();
        $this->get(route('qr.order', $session))->assertSee('Chuyển khoản ngay');

        $this->post(route('qr.pay', $session))->assertRedirect(route('qr.order', $session).'#payment');
        expect(Order::sole()->payosLinks()->count())->toBe(1);
    });

    test('the order is still sent when payOS is down', function () {
        Http::fake(['api-merchant.payos.vn/*' => Http::response('', 500)]);
        $session = startQrSession(qrCodeForTable());

        $this->postJson(route('qr.orders.store', $session), qrCheckout([productLine(Product::factory()->create())], 'bank_transfer'))->assertCreated();

        expect(Order::count())->toBe(1);
        $this->get(route('qr.order', $session))->assertSee('Chuyển khoản ngay');
    });
});

describe('staff QR codes', function () {
    test('managers create, replace and pause table codes', function () {
        $table = DiningTable::factory()->create(['table_no' => 9]);
        $manager = staffWithPermissions('tables.view', 'tables.update');

        $this->actingAs($manager)->post(route('tables.qr.store', $table))->assertRedirect(route('tables.qr.show', $table));
        $token = $table->qrCode->qr_token;

        expect($table->qrCode->url())->toEndWith("/qr/{$token}");

        $this->actingAs($manager)->get(route('tables.qr.show', $table))->assertOk()->assertSee($table->qrCode->url());

        $this->actingAs($manager)->post(route('tables.qr.store', $table));
        expect($table->qrCode()->first()->qr_token)->not->toBe($token);

        $this->actingAs($manager)->put(route('tables.qr.status', $table), ['is_active' => 0, 'disabled_reason' => 'Đang sửa bàn'])->assertSessionHas('status');
        expect($table->qrCode()->first())->is_active->toBeFalse()->disabled_reason->toBe('Đang sửa bàn');

        $this->actingAs(staffWithPermissions('tables.view'))->post(route('tables.qr.store', $table))->assertForbidden();
    });

    test('the print sheet can create the missing codes', function () {
        DiningTable::factory()->count(3)->create();

        $this->actingAs(owner())->post(route('tables.qr.store-missing'))->assertRedirect(route('tables.qr.index'));

        expect(QrCode::count())->toBe(3);
        $this->actingAs(owner())->get(route('tables.qr.index'))->assertOk()->assertSee('Quét để xem menu');
    });
});
