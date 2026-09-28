<?php

namespace App\Http\Controllers;

use App\OrderStatus;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show today's overview.
     *
     * TODO: the figures below are sample data with the shape the view expects;
     * replace them with queries on orders / order_items / payments.
     */
    public function __invoke(): View
    {
        /** @var array<int, int> $hourlySales hour => revenue (thousand VND) */
        $hourlySales = [
            7 => 60, 8 => 170, 9 => 320, 10 => 240, 11 => 150, 12 => 110, 13 => 210,
            14 => 250, 15 => 190, 16 => 140, 17 => 160, 18 => 200, 19 => 170, 20 => 90, 21 => 40,
        ];

        $recentOrders = [
            ['number' => 'ORD-2025-0012', 'table' => 'Bàn 2', 'items' => 'Cappuccino ×2, Bánh mì bơ tỏi', 'total' => 72000, 'status' => OrderStatus::Pending, 'time' => '13:10:04'],
            ['number' => 'ORD-2025-0011', 'table' => 'Bàn 5', 'items' => 'Latte, Croissant', 'total' => 57000, 'status' => OrderStatus::Preparing, 'time' => '12:58:41'],
            ['number' => 'ORD-2025-0010', 'table' => 'Bàn 5', 'items' => 'Combo Cà phê + Bánh mì', 'total' => 50000, 'status' => OrderStatus::Completed, 'time' => '12:45:30'],
            ['number' => 'ORD-2025-0009', 'table' => 'Mang về', 'items' => 'Trà đào cam sả ×2', 'total' => 70000, 'status' => OrderStatus::Served, 'time' => '12:40:15'],
        ];

        return view('dashboard', [
            'openingHours' => $this->openingHours(now()),
            'stats' => [
                'orders' => 12,
                'revenue' => array_sum($hourlySales) * 1000,
                'customers' => 45,
                'completedOrders' => 10,
                'pendingOrders' => 2,
            ],
            'hourlySales' => $hourlySales,
            'topProducts' => [
                ['name' => 'Cappuccino', 'quantity' => 45, 'unit' => 'ly'],
                ['name' => 'Bánh mì', 'quantity' => 32, 'unit' => 'cái'],
                ['name' => 'Iced Coffee', 'quantity' => 28, 'unit' => 'ly'],
            ],
            'recentOrders' => $recentOrders,
        ]);
    }

    /**
     * @return array{opens: string, closes: string, isOpen: bool}
     */
    private function openingHours(Carbon $now): array
    {
        $opens = $now->copy()->setTime(7, 0);
        $closes = $now->copy()->setTime(22, 0);

        return [
            'opens' => $opens->format('G:i'),
            'closes' => $closes->format('G:i'),
            'isOpen' => $now->between($opens, $closes->copy()->subSecond()),
        ];
    }
}
