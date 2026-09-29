<?php

namespace App\Http\Controllers;

use App\Actions\Orders\LoadMenu;
use App\Actions\Orders\PlaceOrder;
use App\Events\OrderStatusUpdated;
use App\Http\Requests\StoreOrderRequest;
use App\Models\DiningTable;
use App\Models\Order;
use App\OrderStatus;
use App\Services\PayOSService;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Tabs of the order list => the statuses they show.
     */
    private const TABS = [
        'open' => ['pending', 'confirmed', 'preparing', 'ready', 'served'],
        'pending' => ['pending'],
        'completed' => ['completed'],
        'cancelled' => ['cancelled'],
        'all' => [],
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'open';
        // Carbon::parse(null) would mean "now", so only parse a date that was actually sent.
        $date = $request->filled('date') ? rescue(fn () => Carbon::parse($request->query('date')), null, false) : null;
        $search = trim((string) $request->query('search'));

        $orders = Order::with(['diningTable', 'creator', 'items.product', 'items.bundle'])
            ->when(self::TABS[$tab] !== [], fn (Builder $query) => $query->whereIn('status', self::TABS[$tab]))
            ->when($date, fn (Builder $query, Carbon $date) => $query->whereDate('created_at', $date))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('order_number', "%{$search}%")
                ->orWhereLike('customer_name', "%{$search}%")
                ->orWhereLike('customer_phone', "%{$search}%")))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('orders.index', [
            'orders' => $orders,
            'tab' => $tab,
            'filters' => ['date' => $date?->toDateString(), 'search' => $search],
            'tabCounts' => [
                'open' => Order::open()->count(),
                'pending' => Order::where('status', OrderStatus::Pending)->count(),
            ],
        ]);
    }

    /**
     * POS screen for a new order.
     */
    public function create(LoadMenu $loadMenu): View
    {
        return view('orders.pos', [
            'order' => null,
            'menu' => $loadMenu->handle(),
            'tables' => $this->tables(),
        ]);
    }

    public function store(StoreOrderRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $order = $placeOrder->handle($request->user(), $request->details(), $request->lines());

        return to_route('orders.show', $order)->with('status', "Đã tạo đơn {$order->order_number} — ".number_format((float) $order->total_amount, 0, ',', '.').' đ.');
    }

    public function show(Order $order, PayOSService $payos): View
    {
        $payosLink = $order->payosLinks()->first();
        $showQr = $payosLink?->isAwaitingPayment() && filled($payosLink->bin) && filled($payosLink->account_number);

        return view('orders.show', [
            'order' => $order->load(['diningTable', 'creator', 'canceller', 'items.product', 'items.bundle.items.product', 'items.preparer', 'payment.processor', 'payment.refunder']),
            'payosLink' => $payosLink,
            'payosQrImage' => $showQr
                ? $payos->buildQrImageUrl($payosLink->bin, $payosLink->account_number, $payosLink->amount, $payosLink->description)
                : null,
            'payosConfigured' => $payos->isConfigured(),
        ]);
    }

    /**
     * POS screen adding items to an open order.
     */
    public function createItems(Order $order, LoadMenu $loadMenu): View|RedirectResponse
    {
        if (! $order->status->isOpen()) {
            return to_route('orders.show', $order)->with('error', "Đơn {$order->order_number} đã {$order->status->label()}, không thêm món được.");
        }

        return view('orders.pos', [
            'order' => $order->load('diningTable'),
            'menu' => $loadMenu->handle(),
            'tables' => [],
        ]);
    }

    public function updateDiscount(Request $request, Order $order): RedirectResponse
    {
        if (! $order->status->isOpen()) {
            return back()->with('error', "Đơn {$order->order_number} đã {$order->status->label()}, không đổi giảm giá được.");
        }

        if ($lockReason = $order->amountLockReason()) {
            return back()->with('error', $lockReason);
        }

        $validated = $request->validateWithBag('discount', [
            'discount_amount' => ['required', 'numeric', 'min:0', 'max:'.(float) $order->subtotal],
            'discount_reason' => ['nullable', 'required_unless:discount_amount,0', 'string', 'max:255'],
        ], [
            'discount_amount.required' => 'Vui lòng nhập số tiền giảm.',
            'discount_amount.min' => 'Số tiền giảm không được âm.',
            'discount_amount.max' => 'Không được giảm quá tổng tiền món.',
            'discount_reason.required_unless' => 'Vui lòng ghi lý do giảm giá.',
        ]);

        $order->discount_amount = $validated['discount_amount'];
        $order->discount_reason = (float) $validated['discount_amount'] > 0 ? $validated['discount_reason'] : null;
        $order->recalculateTotals();

        ActivityLogger::changed('order.discounted', "Giảm giá đơn {$order->order_number}: ".number_format((float) $order->discount_amount, 0, ',', '.').' đ'
            .($order->discount_reason ? " ({$order->discount_reason})" : ''), $order, ['discount_amount', 'discount_reason', 'total_amount']);

        // The total changed: other cashiers, the customer's QR page and the reports must see it.
        OrderStatusUpdated::changed($order, OrderStatusUpdated::CHANGE_DISCOUNT);

        return back()->with('status', 'Đã cập nhật giảm giá.');
    }

    /**
     * Tables with their open order, so the POS can send staff to "add items" instead.
     *
     * @return list<array<string, mixed>>
     */
    private function tables(): array
    {
        $openOrders = Order::open()->whereNotNull('table_id')->get()->keyBy('table_id');

        return DiningTable::orderBy('section')->orderBy('table_no')->get()
            ->map(fn (DiningTable $table): array => [
                'id' => $table->id,
                'number' => $table->table_no,
                'section' => $table->section ?? 'Chưa xếp khu vực',
                'capacity' => $table->capacity,
                'status' => $table->status->value,
                'statusLabel' => $table->status->label(),
                'openOrder' => $openOrders->has($table->id) ? [
                    'number' => $openOrders[$table->id]->order_number,
                    'addUrl' => route('orders.items.create', $openOrders[$table->id]),
                ] : null,
            ])->all();
    }
}
