<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\OrderItemStatus;
use App\OrderStatus;
use App\Reports\ReportPeriod;
use App\Reports\SalesReport;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Overview of a period (today by default). Every staff member may open it;
     * money figures need `reports.view`, the recent orders need `orders.view`.
     */
    public function __invoke(Request $request): View
    {
        $period = ReportPeriod::fromRequest($request);
        $user = $request->user();
        $range = [$period->from, $period->to];

        $report = new SalesReport($period);
        $summary = $report->summary();

        $placed = Order::whereBetween('created_at', $range)->where('status', '!=', OrderStatus::Cancelled);

        return view('dashboard', [
            'period' => $period,
            'openingHours' => $this->openingHours(now()),
            'canSeeMoney' => $user->can('reports.view'),
            'stats' => [
                'orders' => (clone $placed)->count(),
                'revenue' => $summary['revenue'],
                'net' => $summary['net'],
                'average' => $summary['average'],
                'itemsSold' => (int) OrderItem::whereIn('order_id', (clone $placed)->select('id'))
                    ->where('status', '!=', OrderItemStatus::Cancelled)
                    ->sum('quantity'),
                'completedOrders' => $summary['orders'],
                'openOrders' => (clone $placed)->whereIn('status', OrderStatus::open())->count(),
                'cancelledOrders' => $summary['cancelled'],
            ],
            // Hours for a single day, days for a longer period.
            'chart' => $period->isSingleDay() ? $report->hourly() : $report->daily(),
            'chartTitle' => $period->isSingleDay() ? 'Doanh thu theo giờ' : 'Doanh thu theo ngày',
            'topProducts' => $report->topProducts(5),
            'paymentMethods' => $report->byPaymentMethod(),
            'recentOrders' => $user->can('orders.view')
                ? Order::with(['diningTable', 'items.product', 'items.bundle'])->whereBetween('created_at', $range)->latest('id')->limit(8)->get()
                : collect(),
        ]);
    }

    /**
     * @return array{opens: string, closes: string, isOpen: bool}
     */
    private function openingHours(CarbonInterface $now): array
    {
        [$openHour, $openMinute] = array_map('intval', explode(':', (string) Setting::get('opening_time')));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', (string) Setting::get('closing_time')));

        $opens = $now->copy()->setTime($openHour, $openMinute);
        $closes = $now->copy()->setTime($closeHour, $closeMinute);

        // Closing before opening: open overnight (18:00 → 02:00).
        $isOpen = $closes->lessThanOrEqualTo($opens)
            ? $now->greaterThanOrEqualTo($opens) || $now->lessThan($closes)
            : $now->between($opens, $closes->copy()->subSecond());

        return [
            'opens' => $opens->format('G:i'),
            'closes' => $closes->format('G:i'),
            'isOpen' => $isOpen,
        ];
    }
}
