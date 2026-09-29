<?php

namespace App\Reports;

use App\Models\Payment;
use App\OrderItemStatus;
use App\OrderStatus;
use App\OrderType;
use App\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales figures for a period, computed straight from orders / order_items /
 * payments (rule 6 of the outline: no `daily_sales_summary` table to keep in sync).
 *
 * What counts:
 *  - Revenue: orders *completed* in the period (by `completed_at`), their `total_amount`
 *    (after discount). Open orders are not revenue yet; cancelled orders never are.
 *  - Refunds: payments refunded in the period (by `refunded_at`); net = revenue − refunds.
 *  - Products: items of those completed orders that were not cancelled; their amount is
 *    before the order-level discount.
 *
 * Only SQL that runs on both MySQL and SQLite (tests) is used; grouping by
 * day / hour goes through dateKey() / hourKey().
 */
class SalesReport
{
    public function __construct(public readonly ReportPeriod $period) {}

    /**
     * @return array{orders: int, revenue: float, subtotal: float, discount: float, refunds: float, refund_count: int, net: float, average: float, cancelled: int}
     */
    public function summary(): array
    {
        $totals = $this->completedOrders()
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue, COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(discount_amount), 0) as discount')
            ->first();

        $refunds = $this->refunds()->selectRaw('COUNT(*) as refund_count, COALESCE(SUM(refund_amount), 0) as refunds')->first();

        $orders = (int) $totals->orders;
        $revenue = (float) $totals->revenue;

        return [
            'orders' => $orders,
            'revenue' => $revenue,
            'subtotal' => (float) $totals->subtotal,
            'discount' => (float) $totals->discount,
            'refunds' => (float) $refunds->refunds,
            'refund_count' => (int) $refunds->refund_count,
            'net' => $revenue - (float) $refunds->refunds,
            'average' => $orders > 0 ? round($revenue / $orders) : 0,
            'cancelled' => DB::table('orders')
                ->where('status', OrderStatus::Cancelled->value)
                ->whereBetween('created_at', $this->range())
                ->count(),
        ];
    }

    /**
     * Revenue of the completed orders by how they were paid, largest first.
     *
     * @return Collection<int, array{method: PaymentMethod, label: string, orders: int, amount: float, share: float}>
     */
    public function byPaymentMethod(): Collection
    {
        $rows = $this->completedOrders('o')
            ->join('payments as p', 'p.order_id', '=', 'o.id')
            ->groupBy('p.method')
            ->selectRaw('p.method as method, COUNT(*) as orders, COALESCE(SUM(p.amount), 0) as amount')
            ->get();

        $total = (float) $rows->sum('amount');

        return $rows
            ->map(fn (object $row): array => [
                'method' => $method = PaymentMethod::from($row->method),
                'label' => $method->label(),
                'orders' => (int) $row->orders,
                'amount' => (float) $row->amount,
                'share' => $total > 0 ? round((float) $row->amount / $total * 100, 1) : 0,
            ])
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * @return Collection<int, array{type: OrderType, label: string, orders: int, amount: float}>
     */
    public function byOrderType(): Collection
    {
        return $this->completedOrders()
            ->groupBy('order_type')
            ->selectRaw('order_type, COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as amount')
            ->get()
            ->map(fn (object $row): array => [
                'type' => $type = OrderType::from($row->order_type),
                'label' => $type->label(),
                'orders' => (int) $row->orders,
                'amount' => (float) $row->amount,
            ])
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * One row per day of the period (days without sales included), or per month.
     *
     * @param  'day'|'month'  $group
     * @return Collection<int, array{key: string, label: string, orders: int, revenue: float, discount: float, refunds: float, net: float, cash: float, card: float, transfer: float}>
     */
    public function timeline(string $group = 'day'): Collection
    {
        $dateKey = $this->dateKey('o.completed_at');
        $transfer = implode("', '", [PaymentMethod::BankTransfer->value, PaymentMethod::QrPay->value, PaymentMethod::EWallet->value]);

        $sales = $this->completedOrders('o')
            ->leftJoin('payments as p', 'p.order_id', '=', 'o.id')
            ->groupByRaw($dateKey)
            ->selectRaw("{$dateKey} as day, COUNT(*) as orders, COALESCE(SUM(o.total_amount), 0) as revenue, COALESCE(SUM(o.discount_amount), 0) as discount,
                COALESCE(SUM(CASE WHEN p.method = 'cash' THEN p.amount ELSE 0 END), 0) as cash,
                COALESCE(SUM(CASE WHEN p.method = 'card' THEN p.amount ELSE 0 END), 0) as card,
                COALESCE(SUM(CASE WHEN p.method IN ('{$transfer}') THEN p.amount ELSE 0 END), 0) as transfer")
            ->get()
            ->keyBy('day');

        $refundKey = $this->dateKey('refunded_at');
        $refunds = $this->refunds()
            ->groupByRaw($refundKey)
            ->selectRaw("{$refundKey} as day, COALESCE(SUM(refund_amount), 0) as refunds")
            ->pluck('refunds', 'day');

        $days = collect();

        for ($day = $this->period->from; $day->lessThanOrEqualTo($this->period->to); $day = $day->addDay()) {
            $key = $day->toDateString();
            $row = $sales->get($key);
            $revenue = (float) ($row->revenue ?? 0);
            $refunded = (float) ($refunds[$key] ?? 0);

            $days->push([
                'key' => $key,
                'label' => $day->format('d/m/Y'),
                'orders' => (int) ($row->orders ?? 0),
                'revenue' => $revenue,
                'discount' => (float) ($row->discount ?? 0),
                'refunds' => $refunded,
                'net' => $revenue - $refunded,
                'cash' => (float) ($row->cash ?? 0),
                'card' => (float) ($row->card ?? 0),
                'transfer' => (float) ($row->transfer ?? 0),
            ]);
        }

        if ($group !== 'month') {
            return $days;
        }

        return $days
            ->groupBy(fn (array $day): string => substr($day['key'], 0, 7))
            ->map(fn (Collection $month, string $key): array => [
                'key' => $key,
                'label' => 'Tháng '.CarbonImmutable::createFromFormat('!Y-m', $key)->format('m/Y'),
                ...collect(['orders', 'revenue', 'discount', 'refunds', 'net', 'cash', 'card', 'transfer'])
                    ->mapWithKeys(fn (string $field): array => [$field => $month->sum($field)])
                    ->all(),
            ])
            ->values();
    }

    /**
     * Revenue per hour of the day (for a single-day period), from 6h to 22h
     * and widened when there were sales earlier or later.
     *
     * @return array<string, float> "9:00" => revenue
     */
    public function hourly(): array
    {
        $hourKey = $this->hourKey('completed_at');

        $sales = $this->completedOrders()
            ->groupByRaw($hourKey)
            ->selectRaw("{$hourKey} as hour, COALESCE(SUM(total_amount), 0) as revenue")
            ->pluck('revenue', 'hour')
            ->mapWithKeys(fn ($revenue, $hour): array => [(int) $hour => (float) $revenue]);

        $first = min(6, $sales->keys()->min() ?? 6);
        $last = max(22, $sales->keys()->max() ?? 22);

        return collect(range($first, $last))
            ->mapWithKeys(fn (int $hour): array => ["{$hour}:00" => $sales->get($hour, 0.0)])
            ->all();
    }

    /**
     * Revenue per day of the period, for the chart.
     *
     * @return array<string, float> "23/09" => revenue
     */
    public function daily(): array
    {
        return $this->timeline()
            ->mapWithKeys(fn (array $day): array => [substr($day['label'], 0, 5) => $day['revenue']])
            ->all();
    }

    /**
     * Best sellers: products and combos of the completed orders, most sold first.
     *
     * @return Collection<int, array{name: string, category: string, quantity: int, orders: int, revenue: float}>
     */
    public function topProducts(?int $limit = 10): Collection
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'oi.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'pr.category_id')
            ->leftJoin('product_bundles as b', 'b.id', '=', 'oi.bundle_id')
            ->where('o.status', OrderStatus::Completed->value)
            ->whereBetween('o.completed_at', $this->range())
            ->where('oi.status', '!=', OrderItemStatus::Cancelled->value)
            ->groupBy('oi.product_id', 'oi.bundle_id', 'pr.name', 'b.name', 'c.name')
            ->selectRaw('pr.name as product_name, b.name as bundle_name, c.name as category_name, oi.bundle_id,
                SUM(oi.quantity) as quantity, COUNT(DISTINCT o.id) as orders, SUM(oi.quantity * oi.unit_price) as revenue')
            ->orderByDesc('quantity')
            ->orderByDesc('revenue')
            ->when($limit, fn (Builder $query, int $limit) => $query->limit($limit))
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->product_name ?? $row->bundle_name ?? 'Món đã xóa',
                'category' => $row->bundle_id ? 'Combo' : ($row->category_name ?? '—'),
                'quantity' => (int) $row->quantity,
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(): array
    {
        return [$this->period->from->toDateTimeString(), $this->period->to->toDateTimeString()];
    }

    private function completedOrders(?string $alias = null): Builder
    {
        $prefix = $alias ? "{$alias}." : '';

        return DB::table($alias ? "orders as {$alias}" : 'orders')
            ->where("{$prefix}status", OrderStatus::Completed->value)
            ->whereBetween("{$prefix}completed_at", $this->range());
    }

    private function refunds(): Builder
    {
        return DB::table('payments')
            ->whereIn('status', [Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED])
            ->whereBetween('refunded_at', $this->range());
    }

    /**
     * SQL giving "2026-09-29" for a datetime column.
     */
    private function dateKey(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m-%d', {$column})" : "DATE({$column})";
    }

    /**
     * SQL giving the hour (0–23) of a datetime column.
     */
    private function hourKey(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', {$column}) AS INTEGER)" : "HOUR({$column})";
    }
}
