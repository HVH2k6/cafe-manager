<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Reports\ReportPeriod;
use App\Reports\SalesReport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales reports (step 8): revenue by day / month and payment method, best
 * sellers, CSV export. Figures come from SalesReport.
 */
class ReportController extends Controller
{
    /** Report tables that can be exported => file name part. */
    public const EXPORTS = [
        'revenue' => 'doanh-thu',
        'products' => 'san-pham-ban-chay',
        'orders' => 'don-hang',
    ];

    public function index(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request, '7d');
        $group = $this->group($request, $period);
        $report = new SalesReport($period);

        return view('reports.index', [
            'period' => $period,
            'group' => $group,
            'summary' => $report->summary(),
            'timeline' => $report->timeline($group),
            'chart' => $period->isSingleDay() ? $report->hourly() : $report->daily(),
            'paymentMethods' => $report->byPaymentMethod(),
            'orderTypes' => $report->byOrderType(),
            'topProducts' => $report->topProducts(20),
        ]);
    }

    /**
     * CSV (UTF-8 with BOM, so Excel shows Vietnamese correctly).
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, self::EXPORTS), 404);

        $period = ReportPeriod::fromRequest($request, '7d');
        $report = new SalesReport($period);

        [$header, $rows] = match ($type) {
            'revenue' => $this->revenueRows($report, $this->group($request, $period)),
            'products' => $this->productRows($report),
            'orders' => $this->orderRows($period),
        };

        $fileName = self::EXPORTS[$type].'_'.$period->slug().'.csv';

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, escape: '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::safeCell(...), $row), escape: '');
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A cell starting with = + - @ would run as a formula in Excel (CSV injection):
     * prefix text like that with an apostrophe. Numbers are left as they are.
     */
    public static function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @return 'day'|'month'
     */
    private function group(Request $request, ReportPeriod $period): string
    {
        // Grouping by month only makes sense when the period spans more than one month.
        return $request->query('group') === 'month' && $period->from->format('Y-m') !== $period->to->format('Y-m') ? 'month' : 'day';
    }

    /**
     * @return array{0: list<string>, 1: iterable<list<mixed>>}
     */
    private function revenueRows(SalesReport $report, string $group): array
    {
        $timeline = $report->timeline($group);
        $rows = $timeline->map(fn (array $row): array => [
            $row['label'], $row['orders'], $row['revenue'], $row['discount'], $row['refunds'], $row['net'], $row['cash'], $row['card'], $row['transfer'],
        ]);

        $rows->push([
            'Tổng', $timeline->sum('orders'), $timeline->sum('revenue'), $timeline->sum('discount'), $timeline->sum('refunds'),
            $timeline->sum('net'), $timeline->sum('cash'), $timeline->sum('card'), $timeline->sum('transfer'),
        ]);

        return [
            [$group === 'month' ? 'Tháng' : 'Ngày', 'Số đơn', 'Doanh thu (đ)', 'Giảm giá (đ)', 'Hoàn tiền (đ)', 'Thực thu (đ)', 'Tiền mặt (đ)', 'Thẻ (đ)', 'Chuyển khoản (đ)'],
            $rows,
        ];
    }

    /**
     * @return array{0: list<string>, 1: Collection<int, list<mixed>>}
     */
    private function productRows(SalesReport $report): array
    {
        return [
            ['Hạng', 'Sản phẩm', 'Danh mục', 'Số lượng', 'Số đơn', 'Doanh thu (đ)'],
            $report->topProducts(null)->values()->map(fn (array $row, int $index): array => [
                $index + 1, $row['name'], $row['category'], $row['quantity'], $row['orders'], $row['revenue'],
            ]),
        ];
    }

    /**
     * Every order created in the period, cancelled ones included (read in chunks).
     *
     * @return array{0: list<string>, 1: iterable<list<mixed>>}
     */
    private function orderRows(ReportPeriod $period): array
    {
        $rows = (function () use ($period) {
            $orders = Order::with(['diningTable', 'creator', 'payment'])
                ->whereBetween('created_at', [$period->from, $period->to])
                ->orderBy('id');

            foreach ($orders->lazy(200) as $order) {
                yield [
                    $order->order_number,
                    $order->created_at->format('d/m/Y H:i'),
                    $order->completed_at?->format('d/m/Y H:i') ?? '',
                    $order->placeLabel(),
                    $order->status->label(),
                    $order->creatorLabel(),
                    (float) $order->subtotal,
                    (float) $order->discount_amount,
                    (float) $order->total_amount,
                    $order->payment?->method->label() ?? '',
                    (float) ($order->payment?->refund_amount ?? 0),
                    $order->cancelled_reason ?? '',
                ];
            }
        })();

        return [
            ['Mã đơn', 'Tạo lúc', 'Hoàn thành lúc', 'Bàn', 'Trạng thái', 'Người tạo', 'Tạm tính (đ)', 'Giảm giá (đ)', 'Tổng tiền (đ)', 'Thanh toán', 'Đã hoàn (đ)', 'Lý do hủy'],
            $rows,
        ];
    }
}
