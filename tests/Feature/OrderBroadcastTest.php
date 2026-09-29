<?php

use App\Broadcasting\KitchenChannel;
use App\Broadcasting\OrderChannel;
use App\Broadcasting\OrdersChannel;
use App\Broadcasting\TablesChannel;
use App\Events\OrderCompleted;
use App\Events\OrderCreated;
use App\Events\OrderItemReady;
use App\Events\OrderReady;
use App\Events\OrderStatusUpdated;
use App\Events\TableUpdated;
use App\Models\DiningTable;
use App\Models\Product;
use App\OrderItemStatus;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;

const ORDER_EVENTS = [OrderCreated::class, OrderStatusUpdated::class, OrderReady::class, OrderCompleted::class];

/**
 * Channel names an event is broadcast on, e.g. ['private-kitchen', 'private-orders'].
 *
 * @return list<string>
 */
function channelNames(object $event): array
{
    return array_map(fn ($channel): string => $channel->name, $event->broadcastOn());
}

describe('events', function () {
    test('placing an order tells the kitchen and the order list', function () {
        Event::fake(ORDER_EVENTS);
        $table = DiningTable::factory()->create(['table_no' => 3]);

        $order = placeOrder([[Product::factory()->create(['selling_price' => 30000]), 2]], $table);

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($order): bool {
            return $event->order->is($order)
                && channelNames($event) === ['private-kitchen', 'private-orders']
                && $event->broadcastAs() === 'order.created'
                && $event->broadcastWith() === [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'place' => 'Bàn 3',
                    'status' => 'confirmed',
                    'status_label' => 'Đã xác nhận',
                    'item_count' => 2,
                    'total_amount' => 60000,
                ];
        });
    });

    test('nothing is broadcast when placing the order fails', function () {
        Event::fake(ORDER_EVENTS);
        $bread = Product::factory()->stocked(onHand: 1)->create();

        expect(fn () => placeOrder([[$bread, 5]]))->toThrow(ValidationException::class);

        Event::assertNotDispatched(OrderCreated::class);
    });

    test('the kitchen starting and finishing items is broadcast, with a ready alert at the end', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        $item = $order->items()->first();
        Event::fake(ORDER_EVENTS);

        $this->actingAs(owner())->put(route('kitchen.items.update', $item), ['status' => 'preparing']);

        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->oldStatus === 'confirmed'
            && $event->newStatus === 'preparing'
            && $event->change === OrderStatusUpdated::CHANGE_STATUS
            && channelNames($event) === ['private-kitchen', 'private-orders', "private-order.{$order->id}"]);
        Event::assertNotDispatched(OrderReady::class);

        $this->actingAs(owner())->put(route('kitchen.items.update', $item), ['status' => 'ready']);

        Event::assertDispatched(OrderReady::class, fn (OrderReady $event): bool => $event->order->is($order)
            && str_contains($event->broadcastWith()['message'], 'món đã xong')
            && $event->broadcastWith()['url'] === route('orders.show', $order));
    });

    test('adding and removing items is broadcast even when the status stays the same', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        Event::fake(ORDER_EVENTS);

        $this->actingAs(owner())->post(route('orders.items.store', $order), ['items' => [productLine(Product::factory()->create())]]);
        $this->actingAs(owner())->delete(route('orders.items.destroy', [$order, $order->items()->latest('id')->first()]));

        Event::assertDispatchedTimes(OrderStatusUpdated::class, 2);
        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->change === OrderStatusUpdated::CHANGE_ITEMS
            && $event->oldStatus === 'confirmed' && $event->newStatus === 'confirmed');
    });

    test('each item the kitchen finishes tells the waiters, and the last one sends the order-ready alert', function () {
        $table = DiningTable::factory()->create(['table_no' => 5]);
        $latte = Product::factory()->create(['name' => 'Latte']);
        $order = placeOrder([[$latte, 2], [Product::factory()->create()]], $table);
        [$first, $second] = $order->items()->get()->all();
        Event::fake([...ORDER_EVENTS, OrderItemReady::class]);

        $this->actingAs(owner())->put(route('kitchen.items.update', $first), ['status' => 'preparing']);
        $this->actingAs(owner())->put(route('kitchen.items.update', $second), ['status' => 'preparing']);

        // The order was already preparing: the item change is still broadcast so order pages refresh.
        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->change === OrderStatusUpdated::CHANGE_ITEMS
            && $event->newStatus === 'preparing');

        $this->actingAs(owner())->put(route('kitchen.items.update', $first), ['status' => 'ready']);

        Event::assertDispatched(OrderItemReady::class, fn (OrderItemReady $event): bool => $event->item->is($first)
            && channelNames($event) === ['private-orders', "private-order.{$order->id}"]
            && $event->broadcastAs() === 'order.item.ready'
            && $event->broadcastWith()['message'] === "Bàn 5 — Latte ×2 đã xong, mang ra được ({$order->order_number}).");
        Event::assertNotDispatched(OrderReady::class);

        $this->actingAs(owner())->put(route('kitchen.items.update', $second), ['status' => 'ready']);

        Event::assertDispatchedTimes(OrderItemReady::class, 1);
        Event::assertDispatched(OrderReady::class);
    });

    test('without the WebSocket, waiters poll the items ready to take out', function () {
        $table = DiningTable::factory()->create(['table_no' => 6]);
        $order = placeOrder([[Product::factory()->create(['name' => 'Bạc xỉu'])], [Product::factory()->create()]], $table);
        $this->actingAs(owner())->put(route('kitchen.items.update', $order->items()->first()), ['status' => 'ready']);

        $this->actingAs(staffWithPermissions('orders.view'))->getJson(route('staff-alerts'))
            ->assertOk()
            ->assertJsonPath('ready.0.item_ids', [$order->items()->first()->id])
            ->assertJsonPath('ready.0.message', "Bàn 6 — Bạc xỉu ×1 đã xong, mang ra cho khách ({$order->order_number}).");
    });

    test('paying a served order broadcasts the payment and the completion', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 20000])]]);
        $order->items()->update(['status' => OrderItemStatus::Served]);
        $order->syncStatusWithItems();
        Event::fake(ORDER_EVENTS);

        $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 20000]);

        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->change === OrderStatusUpdated::CHANGE_PAYMENT);
        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->oldStatus === 'served' && $event->newStatus === 'completed');
        Event::assertDispatched(OrderCompleted::class, fn (OrderCompleted $event): bool => channelNames($event) === ['private-kitchen', 'private-orders', "private-order.{$order->id}"]);
    });

    test('cancelling an order is broadcast', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        Event::fake(ORDER_EVENTS);

        $this->actingAs(owner())->put(route('orders.cancel', $order), ['cancelled_reason' => 'Khách đổi ý']);

        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->newStatus === 'cancelled');
    });

    test('saving an order without changing its status broadcasts nothing', function () {
        $order = placeOrder([[Product::factory()->create()]]);
        Event::fake(ORDER_EVENTS);

        $order->update(['notes' => 'Ít đá']);

        Event::assertNotDispatched(OrderStatusUpdated::class);
    });
});

describe('changes that keep the status', function () {
    test('a discount is broadcast to the order pages and the customer', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 50000])]]);
        Event::fake(ORDER_EVENTS);

        $this->actingAs(owner())->put(route('orders.discount.update', $order), ['discount_amount' => 10000, 'discount_reason' => 'Khách quen']);

        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->change === OrderStatusUpdated::CHANGE_DISCOUNT
            && $event->oldStatus === 'confirmed' && $event->newStatus === 'confirmed'
            && in_array("private-order.{$order->id}", channelNames($event), true));
    });

    test('a rejected discount broadcasts nothing', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 50000])]]);
        Event::fake(ORDER_EVENTS);

        $this->actingAs(owner())->put(route('orders.discount.update', $order), ['discount_amount' => 90000, 'discount_reason' => 'x']);

        Event::assertNotDispatched(OrderStatusUpdated::class);
    });

    test('refunding a completed order is broadcast as a payment change', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 30000])]]);
        $order->items()->update(['status' => OrderItemStatus::Served]);
        $order->syncStatusWithItems();
        $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 30000]);
        Event::fake(ORDER_EVENTS);

        $this->actingAs(owner())->post(route('payments.refund', $order->fresh()->payment), [
            'refund_amount' => 10000, 'refund_reason' => 'Đổ ly', 'refund_method' => 'cash',
        ])->assertSessionHasNoErrors();

        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->change === OrderStatusUpdated::CHANGE_PAYMENT
            && $event->newStatus === 'completed');
    });

    test('refunding an open order is broadcast as its cancellation, once', function () {
        $order = placeOrder([[Product::factory()->create(['selling_price' => 30000])]]);
        $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 30000]);
        Event::fake(ORDER_EVENTS);

        $this->actingAs(owner())->post(route('payments.refund', $order->fresh()->payment), [
            'refund_amount' => 30000, 'refund_reason' => 'Hết món', 'refund_method' => 'cash',
        ]);

        Event::assertDispatchedTimes(OrderStatusUpdated::class, 1);
        Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event): bool => $event->newStatus === 'cancelled');
    });
});

describe('tables', function () {
    test('seating and freeing a table is broadcast to the floor plan', function () {
        $table = DiningTable::factory()->create(['table_no' => 7]);
        Event::fake([TableUpdated::class]);

        $order = placeOrder([[Product::factory()->create()]], $table);

        Event::assertDispatched(TableUpdated::class, fn (TableUpdated $event): bool => $event->tableId === $table->id
            && $event->broadcastWith() === ['id' => $table->id, 'table_no' => 7, 'status' => 'occupied', 'old_status' => 'available']
            && channelNames($event) === ['private-tables']
            && $event->broadcastAs() === 'table.updated');

        $this->actingAs(owner())->put(route('orders.cancel', $order), ['cancelled_reason' => 'Khách đi']);

        Event::assertDispatched(TableUpdated::class, fn (TableUpdated $event): bool => $event->status === 'available' && $event->oldStatus === 'occupied');
    });

    test('a completed order marks the table for cleaning, and cleaning it is broadcast', function () {
        $table = DiningTable::factory()->create();
        $order = placeOrder([[Product::factory()->create(['selling_price' => 20000])]], $table);
        $order->items()->update(['status' => OrderItemStatus::Served]);
        $order->syncStatusWithItems();
        Event::fake([TableUpdated::class]);

        $this->actingAs(owner())->post(route('orders.payments.store', $order), ['method' => 'cash', 'paid_amount' => 20000]);
        Event::assertDispatched(TableUpdated::class, fn (TableUpdated $event): bool => $event->status === 'dirty');

        $this->actingAs(owner())->put(route('tables.status.update', $table), ['status' => 'available']);
        Event::assertDispatched(TableUpdated::class, fn (TableUpdated $event): bool => $event->oldStatus === 'dirty' && $event->status === 'available');
    });

    test('adding, editing and removing a table is broadcast', function () {
        Event::fake([TableUpdated::class]);

        $table = DiningTable::factory()->create(['capacity' => 2]);
        $table->update(['capacity' => 6]);
        $table->delete();

        Event::assertDispatchedTimes(TableUpdated::class, 3);
        Event::assertDispatched(TableUpdated::class, fn (TableUpdated $event): bool => $event->status === null); // deleted
    });

    test('nothing is broadcast when placing the order rolls back', function () {
        $table = DiningTable::factory()->create();
        Event::fake([TableUpdated::class]);

        expect(fn () => placeOrder([[Product::factory()->stocked(onHand: 0)->create()]], $table))->toThrow(ValidationException::class);

        Event::assertNotDispatched(TableUpdated::class);
    });

    test('the floor plan refreshes live', function () {
        $this->actingAs(owner())->get(route('tables.index'))
            ->assertOk()
            ->assertSee("channel: 'tables'", false)
            ->assertSee('data-live-refresh="plan"', false);
    });
});

describe('dashboard and reports', function () {
    test('the dashboard listens to order events when the period includes today', function () {
        $this->actingAs(owner())->get(route('dashboard'))
            ->assertSee("channel: 'orders'", false)
            ->assertSee('data-live-refresh="stats"', false);

        $past = ['period' => 'custom', 'from' => now()->subDays(10)->toDateString(), 'to' => now()->subDays(5)->toDateString()];

        $this->actingAs(owner())->get(route('dashboard', $past))
            ->assertSee('channel: null', false)
            ->assertSee('poll: false', false);
    });

    test('staff who may not listen to orders poll the dashboard instead', function () {
        $this->actingAs(staffWithPermissions('kitchen.view'))->get(route('dashboard'))
            ->assertSee('channel: null', false)
            ->assertSee('poll: 30', false);
    });
});

describe('fallback', function () {
    test('an order is still placed when the WebSocket server is down', function () {
        Exceptions::fake();
        Broadcast::extend('down', fn () => new class extends Broadcaster
        {
            public function auth($request): mixed
            {
                return null;
            }

            public function validAuthenticationResponse($request, $result): mixed
            {
                return null;
            }

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new BroadcastException('Reverb is not running');
            }
        });
        config(['broadcasting.connections.down' => ['driver' => 'down'], 'broadcasting.default' => 'down']);

        $order = placeOrder([[Product::factory()->create()]]);

        expect($order->exists)->toBeTrue();
        Exceptions::assertReported(fn (BroadcastException $exception): bool => $exception->getMessage() === 'Reverb is not running');
    });
});

describe('channel authorization', function () {
    test('only kitchen staff may listen to the kitchen channel', function () {
        expect((new KitchenChannel)->join(staffWithPermissions('kitchen.view')))->toBeTrue()
            ->and((new KitchenChannel)->join(staffWithPermissions('orders.view')))->toBeFalse()
            ->and((new KitchenChannel)->join(owner()))->toBeTrue();
    });

    test('only staff who may view tables may listen to the tables channel', function () {
        expect((new TablesChannel)->join(staffWithPermissions('tables.view')))->toBeTrue()
            ->and((new TablesChannel)->join(staffWithPermissions('orders.view')))->toBeFalse();
    });

    test('private channels need a login and the right permission over HTTP', function () {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb' => [
            'driver' => 'reverb', 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => '1',
            'options' => ['host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false],
        ]]);
        // The channels were registered on the default (null) broadcaster at boot: register them on reverb too.
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');
        $auth = fn (string $channel): array => ['socket_id' => '1234.5678', 'channel_name' => $channel];

        $this->post('/broadcasting/auth', $auth('private-kitchen'))->assertForbidden();

        $this->actingAs(staffWithPermissions('orders.view'))->post('/broadcasting/auth', $auth('private-orders'))
            ->assertOk()->assertJsonStructure(['auth']);
        $this->actingAs(staffWithPermissions('orders.view'))->post('/broadcasting/auth', $auth('private-kitchen'))->assertForbidden();
        $this->actingAs(staffWithPermissions('tables.view'))->post('/broadcasting/auth', $auth('private-tables'))->assertOk();
    });

    test('only staff who may view orders may listen to order channels', function () {
        $waiter = staffWithPermissions('orders.view');
        $cook = staffWithPermissions('kitchen.view');

        expect((new OrdersChannel)->join($waiter))->toBeTrue()
            ->and((new OrdersChannel)->join($cook))->toBeFalse()
            ->and((new OrderChannel)->join($waiter, 1))->toBeTrue()
            ->and((new OrderChannel)->join($cook, 1))->toBeFalse();
    });
});
