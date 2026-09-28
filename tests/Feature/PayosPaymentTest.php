<?php

use App\Models\Order;
use App\Models\PayosPaymentLink;
use App\Models\Product;
use App\OrderItemStatus;
use App\OrderStatus;
use App\PaymentMethod;
use App\Services\PayOSService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const PAYOS_URL = 'https://api-merchant.payos.vn/v2/payment-requests';

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

/**
 * @return array<string, mixed>
 */
function createdLinkResponse(array $overrides = []): array
{
    return ['code' => '00', 'desc' => 'success', 'data' => [
        'bin' => '970422',
        'accountNumber' => 'VQRQAMIDG1780',
        'accountName' => 'QUAN CAFE ABC',
        'amount' => 45000,
        'description' => 'DH260001',
        'orderCode' => 1,
        'currency' => 'VND',
        'paymentLinkId' => '41dcfdd1c94f4ffca8f010e5749abb87',
        'status' => 'PENDING',
        'checkoutUrl' => 'https://pay.payos.vn/web/41dcfdd1c94f4ffca8f010e5749abb87',
        'qrCode' => '000201010212',
        ...$overrides,
    ], 'signature' => 'ignored'];
}

/**
 * @return array<string, mixed>
 */
function paymentInfoResponse(string $status, int $amountPaid, int $amount = 45000): array
{
    return ['code' => '00', 'desc' => 'success', 'data' => [
        'orderCode' => 1,
        'amount' => $amount,
        'amountPaid' => $amountPaid,
        'amountRemaining' => $amount - $amountPaid,
        'status' => $status,
        'transactions' => $amountPaid > 0 ? [['amount' => $amountPaid, 'reference' => 'FT26271945740991']] : [],
    ]];
}

function waitingLink(Order $order, int $orderCode = 260927001044123): PayosPaymentLink
{
    return PayosPaymentLink::create([
        'order_id' => $order->id,
        'order_code' => $orderCode,
        'payment_link_id' => 'link-id',
        'amount' => (int) $order->total_amount,
        'description' => 'DH260001',
        'bin' => '970422',
        'account_number' => 'VQRQAMIDG1780',
        'status' => 'PENDING',
        'expires_at' => now()->addMinutes(15),
    ]);
}

function servedOrderOf(int $price): Order
{
    $order = placeOrder([[Product::factory()->create(['selling_price' => $price])]]);
    $order->items()->update(['status' => OrderItemStatus::Served]);
    $order->syncStatusWithItems();

    return $order->fresh();
}

describe('creating a QR code', function () {
    test('a signed payment link is created for the order total and shown as a VietQR image', function () {
        Http::fake([PAYOS_URL => Http::response(createdLinkResponse())]);
        $order = placeOrder([[Product::factory()->create(['selling_price' => 45000])]]);

        $this->actingAs(owner())
            ->post(route('orders.payos.store', $order))
            ->assertRedirect(route('orders.show', $order).'#payment');

        $link = PayosPaymentLink::sole();
        expect($link)
            ->amount->toBe(45000)
            ->description->toBe(PayosPaymentLink::descriptionFor($order))
            ->status->toBe('PENDING')
            ->payment_link_id->toBe('41dcfdd1c94f4ffca8f010e5749abb87')
            ->bin->toBe('970422')
            ->account_number->toBe('VQRQAMIDG1780');

        Http::assertSent(function (Request $request) use ($link): bool {
            $service = app(PayOSService::class);

            return $request->method() === 'POST'
                && $request->url() === PAYOS_URL
                && $request->hasHeader('x-client-id', 'test-client')
                && $request->hasHeader('x-api-key', 'test-api-key')
                && $request['orderCode'] === $link->order_code
                && $request['amount'] === 45000
                && $request['returnUrl'] === route('payos.return', $link)
                && $request['signature'] === $service->createSignature($request->data());
        });

        $this->actingAs(owner())
            ->get(route('orders.show', $order))
            ->assertSee('https://img.vietqr.io/image/970422-VQRQAMIDG1780-payos.jpg?amount=45000&amp;addInfo='.$link->description, false)
            ->assertSee(route('payos.status', $link), false)
            ->assertSee('Hủy mã QR');
    });

    test('the description is short ASCII taken from the order number', function () {
        $order = new Order(['order_number' => 'ORD-2026-0042']);

        expect(PayosPaymentLink::descriptionFor($order))->toBe('DH260042');
    });

    test('an error from payOS is shown and nothing is kept', function () {
        Http::fake([PAYOS_URL => Http::response(['code' => '20', 'desc' => 'Thông tin truyền lên không đúng'])]);
        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(owner())
            ->post(route('orders.payos.store', $order))
            ->assertSessionHas('error', 'payOS: Thông tin truyền lên không đúng');

        expect(PayosPaymentLink::count())->toBe(0);
    });

    test('a timeout is reported instead of crashing', function () {
        Http::fake(fn () => throw new ConnectionException('timed out'));
        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(owner())
            ->post(route('orders.payos.store', $order))
            ->assertSessionHas('error', 'payOS: Không kết nối được payOS (hết thời gian chờ). Vui lòng thử lại.');
    });

    test('a taken orderCode is retried with a new one', function () {
        Http::fake([PAYOS_URL => Http::sequence()
            ->push(['code' => '231', 'desc' => 'Đơn thanh toán đã tồn tại'])
            ->push(createdLinkResponse())]);
        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(owner())->post(route('orders.payos.store', $order))->assertSessionHasNoErrors();

        expect(PayosPaymentLink::count())->toBe(1);
        Http::assertSentCount(2);
    });

    test('a new QR code cancels the one still waiting', function () {
        Http::fake([
            PAYOS_URL.'/*/cancel' => Http::response(['code' => '00', 'data' => ['status' => 'CANCELLED']]),
            PAYOS_URL => Http::response(createdLinkResponse()),
        ]);
        $order = placeOrder([[Product::factory()->create()]]);
        $old = waitingLink($order);

        $this->actingAs(owner())->post(route('orders.payos.store', $order));

        expect($old->fresh()->status)->toBe('CANCELLED')
            ->and($order->payosLinks()->awaitingPayment()->count())->toBe(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === PAYOS_URL."/{$old->order_code}/cancel");
    });

    test('a waiting QR code locks the order amount', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        waitingLink($order);

        $this->actingAs(owner())
            ->post(route('orders.items.store', $order), ['items' => [productLine(Product::factory()->create())]])
            ->assertSessionHasErrors(['items' => "Đơn {$order->order_number} đang chờ khách chuyển khoản. Hãy hủy mã QR trước khi sửa đơn."]);
    });

    test('staff without payments.create cannot create QR codes', function () {
        Http::fake();
        $order = placeOrder([[Product::factory()->create()]]);

        $this->actingAs(staffWithPermissions('orders.view'))->post(route('orders.payos.store', $order))->assertForbidden();

        Http::assertNothingSent();
    });
});

describe('checking the status', function () {
    test('a pending link stays pending', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 45000])]]);
        $link = waitingLink($order);
        Http::fake([PAYOS_URL.'/*' => Http::response(paymentInfoResponse('PENDING', 0))]);

        $this->actingAs(owner())
            ->getJson(route('payos.status', $link))
            ->assertOk()
            ->assertExactJson(['status' => 'PENDING', 'label' => 'Đang chờ khách chuyển khoản', 'final' => false, 'orderPaid' => false, 'error' => null]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === PAYOS_URL."/{$link->order_code}");
    });

    test('a paid link records the payment and completes a served order', function () {
        $order = servedOrderOf(45000);
        $link = waitingLink($order);
        Http::fake([PAYOS_URL.'/*' => Http::response(paymentInfoResponse('PAID', 45000))]);

        $this->actingAs(owner())
            ->getJson(route('payos.status', $link))
            ->assertJson(['status' => 'PAID', 'final' => true, 'orderPaid' => true]);

        expect($link->fresh())->status->toBe('PAID')->amount_paid->toBe(45000)->reference->toBe('FT26271945740991')->paid_at->not->toBeNull()
            ->and($order->payment)
            ->method->toBe(PaymentMethod::QrPay)
            ->amount->toBe('45000.00')
            ->reference_number->toBe('FT26271945740991')
            ->transaction_id->toBe("payos-{$link->order_code}")
            ->payment_gateway->toBe('payos')
            ->and($order->fresh()->status)->toBe(OrderStatus::Completed);
    });

    test('PAID with less money than the order is not accepted', function () {
        $order = servedOrderOf(45000);
        $link = waitingLink($order);
        Http::fake([PAYOS_URL.'/*' => Http::response(paymentInfoResponse('PAID', 40000))]);

        $this->actingAs(owner())->getJson(route('payos.status', $link))->assertJson(['orderPaid' => false]);

        expect($order->payment)->toBeNull()
            ->and($link->fresh()->status)->not->toBe('PAID');
    });

    test('an expired link is final and the order can be paid again', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $link = waitingLink($order);
        Http::fake([PAYOS_URL.'/*' => Http::response(paymentInfoResponse('EXPIRED', 0))]);

        $this->actingAs(owner())->getJson(route('payos.status', $link))->assertJson(['status' => 'EXPIRED', 'final' => true, 'orderPaid' => false]);

        expect($order->amountLockReason())->toBeNull();
    });

    test('a payOS error is reported in the JSON without changing the link', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $link = waitingLink($order);
        Http::fake([PAYOS_URL.'/*' => Http::response(['code' => '101', 'desc' => 'Không tìm thấy đơn'])]);

        $this->actingAs(owner())->getJson(route('payos.status', $link))->assertJson(['status' => 'PENDING', 'error' => 'Không tìm thấy đơn']);
    });

    test('the return page ignores the query string and asks payOS', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 45000])]]);
        $link = waitingLink($order);
        Http::fake([PAYOS_URL.'/*' => Http::response(paymentInfoResponse('PENDING', 0))]);

        $this->actingAs(owner())
            ->get(route('payos.return', $link).'?status=PAID&code=00')
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('error', 'Đang chờ khách chuyển khoản.');

        expect($order->payment)->toBeNull();
    });

    test('a waiting QR code can be cancelled', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $link = waitingLink($order);
        Http::fake([
            PAYOS_URL.'/*/cancel' => Http::response(['code' => '00', 'data' => ['status' => 'CANCELLED']]),
            PAYOS_URL.'/*' => Http::response(paymentInfoResponse('CANCELLED', 0)),
        ]);

        $this->actingAs(owner())->post(route('payos.cancel', $link))->assertSessionHas('status', 'Đã hủy mã QR.');

        expect($link->fresh()->status)->toBe('CANCELLED')
            ->and($order->amountLockReason())->toBeNull();
    });
});

describe('registering the webhook', function () {
    test('the ngrok base URL is completed with the webhook path and sent to payOS', function () {
        Http::fake(['https://api-merchant.payos.vn/confirm-webhook' => Http::response(['code' => '00', 'desc' => 'success', 'data' => []])]);

        $this->artisan('payos:webhook', ['url' => 'https://abcd.ngrok-free.app/'])
            ->expectsOutputToContain('https://abcd.ngrok-free.app/webhooks/payos')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request): bool => $request['webhookUrl'] === 'https://abcd.ngrok-free.app/webhooks/payos'
            && $request->hasHeader('x-api-key', 'test-api-key'));
    });

    test('an http URL is refused before calling payOS', function () {
        Http::fake();

        $this->artisan('payos:webhook', ['url' => 'http://localhost:8000'])->assertFailed();

        Http::assertNothingSent();
    });

    test('a refusal from payOS is explained', function () {
        Http::fake(['*' => Http::response(['code' => '20', 'desc' => 'Webhook url không hợp lệ'])]);

        $this->artisan('payos:webhook', ['url' => 'https://abcd.ngrok-free.app'])
            ->expectsOutputToContain('payOS từ chối: Webhook url không hợp lệ')
            ->assertFailed();
    });
});

describe('webhook', function () {
    /**
     * @return array<string, mixed>
     */
    function signedWebhook(array $data, bool $valid = true): array
    {
        $signature = hash_hmac('sha256', app(PayOSService::class)->webhookSigningString($data), 'test-checksum');

        return ['code' => '00', 'desc' => 'success', 'success' => true, 'data' => $data, 'signature' => $valid ? $signature : str_repeat('0', 64)];
    }

    test('a signed webhook marks the order paid, without login or CSRF token, once', function () {
        $order = servedOrderOf(45000);
        $link = waitingLink($order);
        $data = ['orderCode' => $link->order_code, 'amount' => 45000, 'code' => '00', 'desc' => 'success', 'reference' => 'FT26271945740991', 'counterAccountBankId' => null];

        $this->postJson(route('payos.webhook'), signedWebhook($data))->assertOk()->assertJson(['success' => true]);
        $this->postJson(route('payos.webhook'), signedWebhook($data))->assertOk();

        expect($link->fresh()->status)->toBe('PAID')
            ->and($order->payment)->method->toBe(PaymentMethod::QrPay)->processed_by->toBeNull()
            ->and($order->fresh()->status)->toBe(OrderStatus::Completed)
            ->and($order->payment()->count())->toBe(1);
    });

    test('each webhook is logged with what it did', function () {
        Log::spy();
        $order = servedOrderOf(45000);
        $link = waitingLink($order);
        $data = ['orderCode' => $link->order_code, 'amount' => 45000, 'code' => '00', 'reference' => 'FT1'];

        $this->postJson(route('payos.webhook'), signedWebhook($data));
        $this->postJson(route('payos.webhook'), signedWebhook($data));

        Log::shouldHaveReceived('info')->with('payOS webhook received', Mockery::on(fn (array $context): bool => $context['result'] === 'settled the payment'))->once();
        Log::shouldHaveReceived('info')->with('payOS webhook received', Mockery::on(fn (array $context): bool => $context['result'] === 'already paid (status check was faster)'))->once();
    });

    test('a webhook with a bad signature is rejected', function () {
        $order = servedOrderOf(45000);
        $link = waitingLink($order);

        $this->postJson(route('payos.webhook'), signedWebhook(['orderCode' => $link->order_code, 'amount' => 45000, 'code' => '00'], valid: false))
            ->assertStatus(400);

        expect($order->payment)->toBeNull();
    });

    test('the test webhook payOS sends when saving the URL is acknowledged', function () {
        $this->postJson(route('payos.webhook'), signedWebhook(['orderCode' => 123, 'amount' => 3000, 'code' => '00']))->assertOk();
    });

    test('money arriving for an order already paid in cash is kept on the link for a refund', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 45000])]]);
        $link = waitingLink($order);
        $link->update(['status' => 'CANCELLED']);
        $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 45000]);
        $link->update(['status' => 'PENDING']);

        $this->postJson(route('payos.webhook'), signedWebhook(['orderCode' => $link->order_code, 'amount' => 45000, 'code' => '00', 'reference' => 'FT1']))->assertOk();

        expect($link->fresh()->status)->toBe('PAID')
            ->and($order->payment()->count())->toBe(1)
            ->and($order->payment->method)->toBe(PaymentMethod::Cash);

        $this->actingAs(owner())->get(route('orders.show', $order))->assertSee('Cần hoàn tiền cho khách');
    });
});
