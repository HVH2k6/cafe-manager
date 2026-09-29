<?php

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\OrderItemStatus;
use App\OrderStatus;
use App\OrderType;
use App\PaymentMethod;
use App\Reports\ReportPeriod;
use App\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A completed, paid order at a given time.
 *
 * @param  list<array{0: Product, 1: int, 2?: OrderItemStatus}>  $lines
 */
function soldOrder(string $completedAt, array $lines, PaymentMethod $method = PaymentMethod::Cash, float $discount = 0, OrderType $type = OrderType::Takeaway): Order
{
    $order = Order::factory()->status(OrderStatus::Completed)->create([
        'order_type' => $type,
        'created_at' => $completedAt,
        'completed_at' => $completedAt,
    ]);

    foreach ($lines as $line) {
        OrderItem::factory()->for($order)->create([
            'product_id' => $line[0]->id,
            'quantity' => $line[1],
            'unit_price' => $line[0]->selling_price,
            'status' => $line[2] ?? OrderItemStatus::Served,
        ]);
    }

    $order->discount_amount = $discount;
    $order->recalculateTotals();
    Payment::factory()->for($order)->create(['amount' => $order->total_amount, 'method' => $method, 'paid_at' => $completedAt]);

    return $order;
}

function periodOf(string $from, string $to): ReportPeriod
{
    return ReportPeriod::between(CarbonImmutable::parse($from), CarbonImmutable::parse($to));
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-29 15:00:00'));
});

describe('period', function () {
    test('presets cover whole days up to today', function (string $key, string $from) {
        $period = ReportPeriod::preset($key);

        expect($period->from->toDateTimeString())->toBe("{$from} 00:00:00")
            ->and($period->to->toDateTimeString())->toBe('2026-09-29 23:59:59');
    })->with([
        'today' => ['today', '2026-09-29'],
        '7 days' => ['7d', '2026-09-23'],
        '30 days' => ['30d', '2026-08-31'],
    ]);

    test('a custom range is read from the query string, swapped when reversed', function () {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['period' => 'custom', 'from' => '2026-09-20', 'to' => '2026-09-10']));

        expect($period->key)->toBe('custom')
            ->and($period->from->toDateString())->toBe('2026-09-10')
            ->and($period->to->toDateTimeString())->toBe('2026-09-20 23:59:59')
            ->and($period->days())->toBe(11)
            ->and($period->label())->toBe('10/09/2026 – 20/09/2026');
    });

    test('invalid input falls back to the default period', function (array $query) {
        expect(ReportPeriod::fromRequest(Request::create('/', 'GET', $query))->key)->toBe('today');
    })->with([
        'unknown preset' => [['period' => 'forever']],
        'custom without dates' => [['period' => 'custom']],
        'bad dates' => [['period' => 'custom', 'from' => 'yesterday', 'to' => '2026-13-45']],
    ]);

    test('a custom range is cut to one year', function () {
        $period = periodOf('2000-01-01', '2026-09-29');

        expect($period->days())->toBe(ReportPeriod::MAX_DAYS)
            ->and($period->to->toDateString())->toBe('2026-09-29');
    });
});

describe('figures', function () {
    test('revenue only counts orders completed in the period, after discount', function () {
        $latte = Product::factory()->create(['selling_price' => 40000]);
        soldOrder('2026-09-29 09:10:00', [[$latte, 2]], discount: 10000); // 70 000
        soldOrder('2026-09-29 14:00:00', [[$latte, 1]], PaymentMethod::QrPay); // 40 000
        soldOrder('2026-09-28 20:00:00', [[$latte, 5]]); // yesterday
        Order::factory()->status(OrderStatus::Served)->create(['total_amount' => 99000]); // still open
        Order::factory()->status(OrderStatus::Cancelled)->create(['total_amount' => 50000]);

        $summary = (new SalesReport(ReportPeriod::preset('today')))->summary();

        expect($summary)->toMatchArray([
            'orders' => 2,
            'revenue' => 110000.0,
            'discount' => 10000.0,
            'net' => 110000.0,
            'average' => 55000.0,
            'cancelled' => 1,
        ]);
    });

    test('refunds are taken off the day they were made', function () {
        $order = soldOrder('2026-09-28 10:00:00', [[Product::factory()->create(['selling_price' => 50000]), 1]]);
        $order->payment->update(['status' => Payment::STATUS_PARTIALLY_REFUNDED, 'refund_amount' => 20000, 'refunded_at' => '2026-09-29 11:00:00']);

        $timeline = (new SalesReport(periodOf('2026-09-28', '2026-09-29')))->timeline()->keyBy('key');

        expect($timeline['2026-09-28'])->toMatchArray(['revenue' => 50000.0, 'refunds' => 0.0, 'net' => 50000.0])
            ->and($timeline['2026-09-29'])->toMatchArray(['revenue' => 0.0, 'refunds' => 20000.0, 'net' => -20000.0]);
    });

    test('revenue is split by payment method', function () {
        $tea = Product::factory()->create(['selling_price' => 30000]);
        soldOrder('2026-09-29 08:00:00', [[$tea, 1]]);
        soldOrder('2026-09-29 09:00:00', [[$tea, 1]]);
        soldOrder('2026-09-29 10:00:00', [[$tea, 2]], PaymentMethod::QrPay);
        soldOrder('2026-09-29 11:00:00', [[$tea, 3]], PaymentMethod::Card);

        $methods = (new SalesReport(ReportPeriod::preset('today')))->byPaymentMethod();

        expect($methods->map(fn ($row) => [$row['method'], $row['orders'], $row['amount']])->all())->toBe([
            [PaymentMethod::Card, 1, 90000.0],
            [PaymentMethod::Cash, 2, 60000.0],
            [PaymentMethod::QrPay, 1, 60000.0],
        ])->and($methods->pluck('share')->all())->toBe([42.9, 28.6, 28.6]); // rounded to 0.1 %
    });

    test('the timeline has every day of the period and sums by month', function () {
        $cake = Product::factory()->create(['selling_price' => 25000]);
        soldOrder('2026-08-30 12:00:00', [[$cake, 1]]);
        soldOrder('2026-09-02 12:00:00', [[$cake, 2]], PaymentMethod::BankTransfer);
        soldOrder('2026-09-02 18:00:00', [[$cake, 1]], PaymentMethod::Card);

        $report = new SalesReport(periodOf('2026-08-29', '2026-09-02'));

        expect($report->timeline()->pluck('orders', 'key')->all())->toBe([
            '2026-08-29' => 0, '2026-08-30' => 1, '2026-08-31' => 0, '2026-09-01' => 0, '2026-09-02' => 2,
        ]);

        expect($report->timeline('month')->all())->toMatchArray([
            ['key' => '2026-08', 'label' => 'Tháng 08/2026', 'orders' => 1, 'revenue' => 25000.0, 'discount' => 0.0, 'refunds' => 0.0, 'net' => 25000.0, 'cash' => 25000.0, 'card' => 0.0, 'transfer' => 0.0],
            ['key' => '2026-09', 'label' => 'Tháng 09/2026', 'orders' => 2, 'revenue' => 75000.0, 'discount' => 0.0, 'refunds' => 0.0, 'net' => 75000.0, 'cash' => 0.0, 'card' => 25000.0, 'transfer' => 50000.0],
        ]);
    });

    test('hourly revenue of a day covers the opening hours', function () {
        $coffee = Product::factory()->create(['selling_price' => 20000]);
        soldOrder('2026-09-29 09:05:00', [[$coffee, 1]]);
        soldOrder('2026-09-29 09:55:00', [[$coffee, 2]]);
        soldOrder('2026-09-29 23:30:00', [[$coffee, 1]]);

        $hourly = (new SalesReport(ReportPeriod::preset('today')))->hourly();

        expect(array_key_first($hourly))->toBe('6:00')
            ->and(array_key_last($hourly))->toBe('23:00')
            ->and($hourly['9:00'])->toBe(60000.0)
            ->and($hourly['23:00'])->toBe(20000.0)
            ->and($hourly['12:00'])->toBe(0.0);
    });

    test('best sellers leave out cancelled items and unfinished orders', function () {
        $latte = Product::factory()->create(['name' => 'Latte', 'selling_price' => 40000]);
        $tea = Product::factory()->create(['name' => 'Trà đào', 'selling_price' => 35000]);
        soldOrder('2026-09-29 08:00:00', [[$latte, 2], [$tea, 5, OrderItemStatus::Cancelled]]);
        soldOrder('2026-09-29 09:00:00', [[$latte, 1], [$tea, 1]]);
        OrderItem::factory()->for(Order::factory()->status(OrderStatus::Preparing))->create(['product_id' => $tea->id, 'quantity' => 10]);

        $top = (new SalesReport(ReportPeriod::preset('today')))->topProducts();

        expect($top->map(fn ($row) => [$row['name'], $row['quantity'], $row['orders'], $row['revenue']])->all())->toBe([
            ['Latte', 3, 2, 120000.0],
            ['Trà đào', 1, 1, 35000.0],
        ]);
    });
});

describe('dashboard', function () {
    test('it shows real figures for today', function () {
        soldOrder('2026-09-29 10:00:00', [[Product::factory()->create(['name' => 'Bạc xỉu', 'selling_price' => 45000]), 2]]);

        $this->actingAs(owner())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tổng quan hôm nay')
            ->assertSee('90.000')
            ->assertSee('Bạc xỉu')
            ->assertDontSee('Cappuccino'); // the old sample data
    });

    test('the period filter changes the figures', function () {
        soldOrder('2026-09-25 10:00:00', [[Product::factory()->create(['name' => 'Cold brew', 'selling_price' => 55000]), 1]]);

        $this->actingAs(owner())->get(route('dashboard'))->assertDontSee('Cold brew');
        $this->actingAs(owner())->get(route('dashboard', ['period' => '7d']))
            ->assertOk()
            ->assertSee('7 ngày qua (23/09 – 29/09)')
            ->assertSee('Doanh thu theo ngày')
            ->assertSee('Cold brew');
    });

    test('staff without reports.view do not see money', function () {
        soldOrder('2026-09-29 10:00:00', [[Product::factory()->create(['selling_price' => 123000]), 1]]);

        $this->actingAs(staffWithPermissions('kitchen.view'))->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('123.000')
            ->assertDontSee('Doanh thu')
            ->assertDontSee('Đơn hàng gần đây');
    });

    test('an empty day still renders', function () {
        $this->actingAs(owner())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Chưa có doanh thu')
            ->assertSee('Chưa có món nào bán ra');
    });
});

describe('reports page', function () {
    test('it needs reports.view', function () {
        $this->actingAs(staffWithPermissions('orders.view'))->get(route('reports.index'))->assertForbidden();
        $this->actingAs(staffWithPermissions('reports.view'))->get(route('reports.index'))->assertOk()->assertDontSee('Xuất Excel');
    });

    test('it shows revenue, payment methods and best sellers', function () {
        $table = DiningTable::factory()->create();
        soldOrder('2026-09-28 10:00:00', [[Product::factory()->create(['name' => 'Espresso', 'selling_price' => 30000]), 3]], PaymentMethod::Card, type: OrderType::DineIn);

        $this->actingAs(owner())->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Báo cáo doanh thu')
            ->assertSee('90.000')
            ->assertSee('Thẻ')
            ->assertSee('Tại bàn')
            ->assertSee('Espresso')
            ->assertSee('Xuất Excel');
    });
});

describe('export', function () {
    test('it needs reports.export', function () {
        $this->actingAs(staffWithPermissions('reports.view'))->get(route('reports.export', 'revenue'))->assertForbidden();
    });

    test('unknown exports are not found', function () {
        $this->actingAs(owner())->get('/reports/export/users')->assertNotFound();
    });

    test('revenue is exported as a UTF-8 CSV Excel can open', function () {
        soldOrder('2026-09-28 10:00:00', [[Product::factory()->create(['selling_price' => 30000]), 2]]);

        $response = $this->actingAs(staffWithPermissions('reports.view', 'reports.export'))
            ->get(route('reports.export', ['type' => 'revenue', 'period' => '7d']));

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertDownload('doanh-thu_2026-09-23_2026-09-29.csv');

        $csv = $response->streamedContent();
        $lines = array_map(str_getcsv(...), explode("\n", trim(substr($csv, 3))));

        expect(substr($csv, 0, 3))->toBe("\xEF\xBB\xBF")
            ->and($lines[0][0])->toBe('Ngày')
            ->and($lines)->toHaveCount(9) // header + 7 days + total
            ->and($lines[6])->toBe(['28/09/2026', '1', '60000', '0', '0', '60000', '60000', '0', '0'])
            ->and(end($lines)[0])->toBe('Tổng');
    });

    test('orders are exported with text that cannot run as a spreadsheet formula', function () {
        $order = soldOrder('2026-09-29 10:00:00', [[Product::factory()->create(), 1]]);
        Order::factory()->status(OrderStatus::Cancelled)->create(['cancelled_reason' => '=HYPERLINK("http://evil")', 'created_at' => '2026-09-29 11:00:00']);

        $csv = $this->actingAs(owner())->get(route('reports.export', ['type' => 'orders']))->streamedContent();

        expect($csv)->toContain($order->order_number)
            ->toContain('Đã hủy')
            ->toContain("'=HYPERLINK")
            ->not->toContain(',=HYPERLINK');
    });

    test('best sellers are exported ranked', function () {
        soldOrder('2026-09-29 10:00:00', [[Product::factory()->create(['name' => 'Mocha', 'selling_price' => 50000]), 4]]);

        $csv = $this->actingAs(owner())->get(route('reports.export', ['type' => 'products', 'period' => 'today']))->streamedContent();

        expect($csv)->toContain('1,Mocha,');
    });
});
