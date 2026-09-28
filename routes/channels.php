<?php

use App\Broadcasting\KitchenChannel;
use App\Broadcasting\OrderChannel;
use App\Broadcasting\OrdersChannel;
use Illuminate\Support\Facades\Broadcast;

/*
 * Staff channels are private: Echo asks /broadcasting/auth before listening,
 * so only staff with the matching permission receive order data.
 * Customers' QR channels (qr-session.{token}) come with step 9.
 */

Broadcast::channel('App.Models.User.{id}', fn ($user, $id): bool => (int) $user->id === (int) $id);

Broadcast::channel('kitchen', KitchenChannel::class);
Broadcast::channel('orders', OrdersChannel::class);
Broadcast::channel('order.{orderId}', OrderChannel::class);
