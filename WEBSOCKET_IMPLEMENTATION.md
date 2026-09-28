# 🔌 WEBSOCKET - REAL-TIME ORDER MANAGEMENT (BLADE + ALPINE.JS)

## 📍 VỊ TRỊ TRONG LỘ TRÌNH

**Bước 7A** — Sau Thanh toán (Bước 7), trước Dashboard dữ liệu thật (Bước 8)
- Phụ thuộc: Bước 5 (Đơn hàng) + Bước 6 (Màn hình bếp) + Bước 9 (QR khách) ✅
- **Không bắt buộc**: có thể dùng polling 15 giây thay WebSocket, nhưng WebSocket tốt hơn cho trải nghiệm

## 🎯 OVERVIEW

**Yêu cầu**: Real-time 2 chiều giữa Khách & Quán
- ✅ Khách đặt order QR → Bếp nhận ngay (KDS cập nhật)
- ✅ Quán update status → Khách thấy ngay (timeline, notification)
- ✅ Sound + Vibration khi order ready
- ✅ Gọi nhân viên kích hoạt notification cho staff

**Tech Stack**:
- Backend: Laravel Reverb (built-in WebSocket)
- Frontend: **Blade + Alpine.js** + Laravel Echo + Socket.io
- Database: MySQL (sync with WebSocket)

---

## 📊 FLOW DIAGRAM

```
┌─────────────┐                          ┌──────────────┐
│   KHÁCH     │                          │     QUÁN     │
│ (QR Page)   │                          │  (Dashboard) │
└──────┬──────┘                          └──────┬───────┘
       │                                        │
       │  1. Khách ORDER                        │
       ├─────── POST /api/orders ─────────────>│
       │         + Socket emit:order.created    │
       │                                    ┌───┴────┐
       │                              Notification
       │                              Kitchen Display
       │                                    │
       │  2. Quán update STATUS              │
       │  <─── Socket: order.status.updated ─┤
       │      (Waiting → Preparing)          │
       │  (UI update ngay)                   │
       │                                    │
       │  3. Quán READY                      │
       │  <─── Socket: order.ready ─────────┤
       │      (Sound + Notification)         │
       │      "Bàn 3 sẵn sàng"               │
       │                                    │
       └─────────────────────────────────────┘
```

---

## 🛠️ SETUP LARAVEL REVERB

### **Step 1: Install Reverb**

```bash
composer require laravel/reverb

# Publish config
php artisan reverb:install

# Generate app key
php artisan reverb:key:generate
```

### **Step 2: Configure (.env)**

```env
REVERB_APP_ID=123456789
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

### **Step 3: Start Reverb Server**

```bash
# Terminal 1: Laravel development server
php artisan serve

# Terminal 2: Reverb WebSocket server
php artisan reverb:start

# Or run together
php artisan reverb:start --host=0.0.0.0 --port=8080
```

---

## ✅ LUẬT NGHIỆP VỤ WEBSOCKET

Tất cả logic broadcast nằm trong `app/Actions/Orders` (được viết ở Bước 5):

1. **PlaceOrder (Bước 5)** 
   - Tạo đơn POS → `broadcast(new OrderCreated($order))` to `channel('kitchen')`
   - Tạo đơn QR → `broadcast(new OrderCreated($order))` to `channel('qr-session.' . $session_id)`
   - Kitchen nhận → Sound `new-order.mp3`, Vibrate

2. **UpdateOrderStatus (Bước 6 → Bước 7)**
   - Nhân viên bếp click "Làm" / "Xong" → `broadcast(new OrderStatusUpdated($order, 'pending', 'preparing'))`
   - Broadcast to: `channel('kitchen')` + `channel('qr-session.' . $order->qr_session_id)` + `channel('order.' . $order->id)`
   - Khách thấy timeline cập nhật realtime

3. **MarkOrderReady (Bước 6)**
   - Status change to `ready` → `broadcast(new OrderReady($order))` 
   - Broadcast to: `channel('qr-session.')` + `channel('order.')`
   - Khách nhận: Sound `ready-alert.mp3`, Vibrate `[500, 200, 500]`, Notification popup
   - KDS tô highlight / blink animation

4. **CallStaff (Bước 9 - QR)**
   - Khách click "Gọi nhân viên" → `broadcast(new CallStaffRequest($order))`
   - Broadcast to: `channel('kitchen')` + `channel('staff')`
   - Nhân viên nhận notification

5. **CompleteOrder (Bước 7)**
   - Thanh toán xong + đã mang đủ món → `broadcast(new OrderCompleted($order))`
   - Broadcast to: `channel('kitchen')` + `channel('order.')`
   - Bàn chuyển status `dirty`, KDS gỡ đơn

---

## 📡 BACKEND - LARAVEL EVENTS & CHANNELS

### **1. Create Events**

```bash
php artisan make:event OrderCreated
php artisan make:event OrderStatusUpdated
php artisan make:event OrderReady
php artisan make:event OrderCompleted
```

### **2. OrderCreated Event (app/Events/OrderCreated.php)**

```php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            // Broadcast to kitchen staff
            new Channel('kitchen'),
            
            // Broadcast to specific QR session (customer)
            new Channel('qr-session.' . $this->order->qr_session_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'table_id' => $this->order->table_id,
            'table_name' => $this->order->table?->table_no,
            'items' => $this->order->items->map(fn($item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name ?? $item->bundle?->name,
                'quantity' => $item->quantity,
                'special_instructions' => $item->special_instructions,
                'image' => $item->product?->image_url ?? $item->bundle?->image_url,
            ]),
            'total_amount' => $this->order->total_amount,
            'created_at' => $this->order->created_at->toDateTimeString(),
            'estimated_ready_at' => $this->order->estimated_ready_at?->diffForHumans(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.created';
    }
}
```

### **3. OrderStatusUpdated Event (app/Events/OrderStatusUpdated.php)**

```php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $oldStatus;
    public string $newStatus;

    public function __construct(Order $order, string $oldStatus, string $newStatus)
    {
        $this->order = $order;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('kitchen'),
            new Channel('qr-session.' . $this->order->qr_session_id),
            new Channel('order.' . $this->order->id), // Admin dashboard
        ];
    }

    public function broadcastWith(): array
    {
        $statusLabels = [
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'preparing' => 'Đang chuẩn bị',
            'ready' => 'Sẵn sàng',
            'served' => 'Đã phục vụ',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
        ];

        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'table_name' => $this->order->table?->table_no,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'status_label' => $statusLabels[$this->newStatus] ?? $this->newStatus,
            'status_color' => $this->getStatusColor($this->newStatus),
            'updated_at' => $this->order->updated_at->toDateTimeString(),
        ];
    }

    private function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'blue',
            'confirmed' => 'blue',
            'preparing' => 'yellow',
            'ready' => 'green',
            'served' => 'green',
            'completed' => 'gray',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }
}
```

### **4. OrderReady Event (app/Events/OrderReady.php)**

```php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderReady implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('kitchen'),
            new Channel('qr-session.' . $this->order->qr_session_id),
            new Channel('order.' . $this->order->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'table_name' => $this->order->table?->table_no,
            'message' => "Bàn {$this->order->table?->table_no} sẵn sàng",
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.ready';
    }
}
```

### **5. OrderCompleted Event (app/Events/OrderCompleted.php)**

```php
<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('kitchen'),
            new Channel('order.' . $this->order->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'table_name' => $this->order->table?->table_no,
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.completed';
    }
}
```

---

## 🎨 FRONTEND - ECHO SETUP (JavaScript)

### **1. Install Dependencies**

```bash
npm install laravel-echo socket.io-client
```

### **2. Create Echo Configuration (resources/js/echo.js)**

```javascript
import Echo from 'laravel-echo';
import io from 'socket.io-client';

window.io = io;

window.Echo = new Echo({
    broadcaster: 'socket.io',
    host: window.location.hostname + ':' + import.meta.env.VITE_REVERB_PORT,
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    encrypted: true,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});

export default window.Echo;
```

### **3. Import Echo in app.js (resources/js/app.js)**

```javascript
import Echo from './echo.js';
window.Echo = Echo;
```

---

## 🎭 FRONTEND - BLADE TEMPLATES + ALPINE.JS

### **1. Kitchen Display (KDS) - Blade Template**

**File**: `resources/views/kitchen/display.blade.php`

```html
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Màn hình bếp</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            background: #1a1a1a;
            color: #fff;
            font-family: Inter, sans-serif;
            overflow: hidden;
        }

        .kitchen-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            padding: 20px;
            height: 100vh;
            overflow-y: auto;
            background: #0d0d0d;
        }

        .order-card {
            background: linear-gradient(135deg, #2a2a2a 0%, #1f1f1f 100%);
            border: 2px solid #444;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            transition: all 0.3s ease;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .order-card.ready {
            border-color: #10b981;
            background: linear-gradient(135deg, #064e3b 0%, #034e3b 100%);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); }
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            border-bottom: 1px solid #444;
            padding-bottom: 12px;
        }

        .order-number {
            font-size: 24px;
            font-weight: bold;
            color: #f59e0b;
        }

        .order-time {
            font-size: 12px;
            color: #999;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending { background: #3b82f6; color: #fff; }
        .status-badge.preparing { background: #f59e0b; color: #000; }
        .status-badge.ready { background: #10b981; color: #fff; }

        .order-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .item {
            background: rgba(255,255,255,0.05);
            padding: 8px;
            border-radius: 6px;
            font-size: 13px;
            border-left: 3px solid #f59e0b;
        }

        .item-qty {
            color: #f59e0b;
            font-weight: bold;
        }

        .item-name {
            margin: 0 4px;
        }

        .special-instructions {
            font-size: 11px;
            color: #fca5a5;
            font-style: italic;
            margin-top: 4px;
        }

        .order-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }

        button {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-preparing {
            background: #f59e0b;
            color: #000;
        }

        .btn-preparing:hover {
            background: #d97706;
        }

        .btn-ready {
            background: #10b981;
            color: #fff;
        }

        .btn-ready:hover {
            background: #059669;
        }

        .btn-cancel {
            background: #ef4444;
            color: #fff;
        }

        .btn-cancel:hover {
            background: #dc2626;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="kitchen-container" 
         x-data="kitchenDisplay()" 
         @window:keydown.escape="connectionStatus = 'disconnected'">
        
        <template x-if="orders.length === 0">
            <div class="loading">
                <p>Chưa có đơn hàng. Đang chờ...</p>
                <p style="font-size: 12px; color: #666; margin-top: 10px;">
                    Trạng thái: <span x-text="connectionStatus"></span>
                </p>
            </div>
        </template>

        <template x-for="order in orders" :key="order.id">
            <div class="order-card" 
                 :class="{ ready: order.status === 'ready' }"
                 :data-order-id="order.id">
                
                <div class="order-header">
                    <div>
                        <div class="order-number" x-text="'#' + order.order_number"></div>
                        <div class="order-time" x-text="formatTime(order.created_at)"></div>
                    </div>
                    <span class="status-badge" :class="order.status" x-text="getStatusLabel(order.status)"></span>
                </div>

                <div class="order-items">
                    <template x-for="item in order.items" :key="item.id">
                        <div class="item">
                            <span class="item-qty" x-text="item.quantity + 'x'"></span>
                            <span class="item-name" x-text="item.product_name"></span>
                            <div class="special-instructions" x-show="item.special_instructions" x-text="item.special_instructions"></div>
                        </div>
                    </template>
                </div>

                <div class="order-actions" x-show="order.status !== 'ready'">
                    <button class="btn-preparing" 
                            @click="updateOrderStatus(order.id, 'preparing')"
                            x-show="order.status === 'pending'">
                        Đang làm
                    </button>
                    <button class="btn-ready" 
                            @click="updateOrderStatus(order.id, 'ready')"
                            x-show="order.status === 'preparing'">
                        Xong rồi
                    </button>
                    <button class="btn-cancel" @click="cancelOrder(order.id)">Hủy</button>
                </div>
            </div>
        </template>
    </div>

    <script>
        function kitchenDisplay() {
            return {
                orders: [],
                connectionStatus: 'connecting',

                init() {
                    this.subscribeToKitchen();
                    this.loadInitialOrders();
                },

                loadInitialOrders() {
                    // Fetch initial orders from backend
                    fetch('/api/orders?status=pending,preparing')
                        .then(r => r.json())
                        .then(data => {
                            this.orders = data.data || [];
                            this.connectionStatus = 'connected';
                        })
                        .catch(err => {
                            console.error('Failed to load orders:', err);
                            this.connectionStatus = 'error';
                        });
                },

                subscribeToKitchen() {
                    // Subscribe to kitchen channel
                    window.Echo.channel('kitchen')
                        .listen('order.created', (data) => {
                            console.log('New order:', data);
                            
                            // Add new order to list (avoid duplicates)
                            if (!this.orders.find(o => o.id === data.id)) {
                                this.orders.unshift(data);
                                this.playSound('new-order');
                                this.vibrate([200, 100, 200]);
                            }
                        })
                        .listen('order.status.updated', (data) => {
                            console.log('Order status updated:', data);
                            
                            const order = this.orders.find(o => o.id === data.id);
                            if (order) {
                                order.status = data.new_status;
                                order.status_label = data.status_label;
                            }
                        })
                        .listen('order.ready', (data) => {
                            console.log('Order ready:', data);
                            this.playSound('ready-alert');
                            this.vibrate([500, 200, 500]);
                        });
                },

                updateOrderStatus(orderId, status) {
                    fetch(`/api/orders/${orderId}/status`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ status })
                    })
                    .then(r => r.json())
                    .then(data => {
                        console.log('Status updated:', data);
                        // Data comes back from broadcast, no need to update manually
                    })
                    .catch(err => console.error('Failed to update status:', err));
                },

                cancelOrder(orderId) {
                    if (!confirm('Xác nhận hủy đơn này?')) return;
                    
                    fetch(`/api/orders/${orderId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        }
                    })
                    .then(r => r.json())
                    .then(() => {
                        this.orders = this.orders.filter(o => o.id !== orderId);
                    })
                    .catch(err => console.error('Failed to cancel order:', err));
                },

                getStatusLabel(status) {
                    const labels = {
                        'pending': 'Chờ xác nhận',
                        'confirmed': 'Đã xác nhận',
                        'preparing': 'Đang chuẩn bị',
                        'ready': 'Sẵn sàng',
                        'served': 'Đã phục vụ',
                        'completed': 'Hoàn thành',
                    };
                    return labels[status] || status;
                },

                formatTime(timestamp) {
                    const date = new Date(timestamp);
                    return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
                },

                playSound(soundName) {
                    const audio = new Audio(`/sounds/${soundName}.mp3`);
                    audio.play().catch(err => console.warn('Could not play sound:', err));
                },

                vibrate(pattern) {
                    if (navigator.vibrate) {
                        navigator.vibrate(pattern);
                    }
                }
            };
        }
    </script>
</body>
</html>
```

### **2. Customer Order Tracking (QR Page) - Blade Template**

**File**: `resources/views/orders/tracking.blade.php`

```html
@extends('layouts.app')

@section('content')
<div class="tracking-container" 
     x-data="orderTracking({{ $order->id }}, '{{ $qrSessionId }}')">
    
    <div class="tracking-header">
        <h1>Trạng thái đơn hàng #{{ $order->order_number }}</h1>
        <p class="table-info" x-show="order.table_name" x-text="'Bàn ' + order.table_name"></p>
    </div>

    <!-- Status Timeline -->
    <div class="timeline">
        <div class="timeline-item" :class="{ active: isStatusReached('pending'), completed: isStatusPassed('pending') }">
            <div class="timeline-circle">
                <span x-show="!isStatusPassed('pending')">1</span>
                <span x-show="isStatusPassed('pending')" class="check">✓</span>
            </div>
            <div class="timeline-label">
                <p class="timeline-title">Đã đặt</p>
                <p class="timeline-time" x-text="formatTime(order.created_at)"></p>
            </div>
        </div>

        <div class="timeline-item" :class="{ active: isStatusReached('preparing'), completed: isStatusPassed('preparing') }">
            <div class="timeline-circle">
                <span x-show="!isStatusPassed('preparing')">2</span>
                <span x-show="isStatusPassed('preparing')" class="check">✓</span>
            </div>
            <div class="timeline-label">
                <p class="timeline-title">Đang chuẩn bị</p>
                <p class="timeline-time" x-text="order.preparing_at ? formatTime(order.preparing_at) : ''"></p>
            </div>
        </div>

        <div class="timeline-item" :class="{ active: isStatusReached('ready'), completed: isStatusPassed('ready') }">
            <div class="timeline-circle">
                <span x-show="!isStatusPassed('ready')">3</span>
                <span x-show="isStatusPassed('ready')" class="check">✓</span>
            </div>
            <div class="timeline-label">
                <p class="timeline-title">Sẵn sàng</p>
                <p class="timeline-time" x-text="order.ready_at ? formatTime(order.ready_at) : ''"></p>
            </div>
        </div>
    </div>

    <!-- Ready Notification -->
    <div class="notification-container" x-show="order.status === 'ready'" x-transition>
        <div class="notification-box">
            <div class="notification-icon">🎉</div>
            <h2>Đơn của bạn sẵn sàng!</h2>
            <p>Vui lòng đến quầy nhận hàng</p>
        </div>
    </div>

    <!-- Order Items -->
    <div class="order-items">
        <h3>Chi tiết đơn hàng</h3>
        <template x-for="item in order.items" :key="item.id">
            <div class="item-row">
                <div class="item-info">
                    <span class="item-qty" x-text="item.quantity + 'x'"></span>
                    <span class="item-name" x-text="item.product_name"></span>
                </div>
                <span class="item-price" x-text="'₫' + formatPrice(item.price)"></span>
            </div>
        </template>
    </div>

    <!-- Total -->
    <div class="order-total">
        <span>Tổng cộng:</span>
        <span class="total-amount" x-text="'₫' + formatPrice(order.total_amount)"></span>
    </div>

    <!-- Call Staff Button -->
    <button class="btn-call-staff" @click="callStaff()" x-show="order.status !== 'completed'">
        📞 Gọi nhân viên
    </button>

    <!-- Connection Status -->
    <div class="connection-status" :class="connectionStatus">
        <span x-text="connectionStatus === 'connected' ? '🟢 Kết nối' : '🔴 Mất kết nối'"></span>
    </div>
</div>

<style>
    .tracking-container {
        max-width: 500px;
        margin: 0 auto;
        padding: 20px;
        background: #fafaf8;
        min-height: 100vh;
    }

    .tracking-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .tracking-header h1 {
        font-size: 24px;
        color: #1a1a1a;
        margin: 0 0 8px 0;
    }

    .table-info {
        color: #666;
        margin: 0;
    }

    .timeline {
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-item {
        display: flex;
        gap: 16px;
        margin-bottom: 24px;
        opacity: 0.5;
        transition: opacity 0.3s;
    }

    .timeline-item.active,
    .timeline-item.completed {
        opacity: 1;
    }

    .timeline-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-weight: bold;
        color: #666;
    }

    .timeline-item.active .timeline-circle {
        background: #f59e0b;
        color: #fff;
    }

    .timeline-item.completed .timeline-circle {
        background: #10b981;
        color: #fff;
    }

    .timeline-label {
        flex: 1;
        padding-top: 8px;
    }

    .timeline-title {
        margin: 0;
        font-weight: 600;
        color: #1a1a1a;
    }

    .timeline-time {
        margin: 4px 0 0 0;
        font-size: 12px;
        color: #999;
    }

    .notification-container {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #fff;
        border-radius: 12px;
        padding: 30px 20px;
        text-align: center;
        margin-bottom: 30px;
    }

    .notification-icon {
        font-size: 48px;
        margin-bottom: 12px;
    }

    .notification-box h2 {
        margin: 0 0 8px 0;
        font-size: 20px;
    }

    .notification-box p {
        margin: 0;
        opacity: 0.9;
    }

    .order-items {
        background: #fff;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .order-items h3 {
        margin: 0 0 12px 0;
        font-size: 14px;
        color: #1a1a1a;
    }

    .item-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .item-row:last-child {
        border-bottom: none;
    }

    .item-info {
        display: flex;
        gap: 8px;
    }

    .item-qty {
        color: #8b6f47;
        font-weight: bold;
    }

    .item-name {
        color: #1a1a1a;
    }

    .item-price {
        color: #1a1a1a;
        font-weight: 600;
    }

    .order-total {
        background: #fff;
        border-radius: 8px;
        padding: 12px 16px;
        display: flex;
        justify-content: space-between;
        font-weight: 600;
        margin-bottom: 20px;
    }

    .total-amount {
        color: #8b6f47;
        font-size: 18px;
    }

    .btn-call-staff {
        width: 100%;
        padding: 14px;
        background: #f59e0b;
        color: #000;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.3s;
        margin-bottom: 16px;
    }

    .btn-call-staff:hover {
        background: #d97706;
    }

    .connection-status {
        text-align: center;
        font-size: 12px;
        color: #666;
        margin-top: 16px;
    }

    .connection-status.connected {
        color: #10b981;
    }

    .connection-status.disconnected {
        color: #ef4444;
    }
</style>

<script>
    function orderTracking(orderId, sessionId) {
        return {
            order: @json($order),
            connectionStatus: 'connected',
            sessionId: sessionId,

            init() {
                this.subscribeToOrderUpdates();
            },

            subscribeToOrderUpdates() {
                // Subscribe to order updates
                window.Echo.channel(`qr-session.${this.sessionId}`)
                    .listen('order.status.updated', (data) => {
                        if (data.id === this.order.id) {
                            this.order.status = data.new_status;
                            console.log('Order status updated:', data.status_label);
                        }
                    })
                    .listen('order.ready', (data) => {
                        if (data.id === this.order.id) {
                            this.order.status = 'ready';
                            this.playSound('ready-alert');
                            this.vibrate([500, 200, 500]);
                        }
                    });

                // Also subscribe to private order channel
                window.Echo.channel(`order.${orderId}`)
                    .listen('order.status.updated', (data) => {
                        if (data.id === this.order.id) {
                            this.order.status = data.new_status;
                        }
                    });
            },

            isStatusReached(status) {
                const statusOrder = ['pending', 'preparing', 'ready', 'served', 'completed'];
                const currentIndex = statusOrder.indexOf(this.order.status);
                const checkIndex = statusOrder.indexOf(status);
                return currentIndex >= checkIndex;
            },

            isStatusPassed(status) {
                const statusOrder = ['pending', 'preparing', 'ready', 'served', 'completed'];
                const currentIndex = statusOrder.indexOf(this.order.status);
                const checkIndex = statusOrder.indexOf(status);
                return currentIndex > checkIndex;
            },

            callStaff() {
                fetch(`/api/orders/${this.order.id}/call-staff`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                .then(r => r.json())
                .then(data => {
                    alert('Nhân viên sẽ tới sớm!');
                })
                .catch(err => console.error('Failed to call staff:', err));
            },

            formatTime(timestamp) {
                if (!timestamp) return '';
                const date = new Date(timestamp);
                return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
            },

            formatPrice(price) {
                return new Intl.NumberFormat('vi-VN').format(price);
            },

            playSound(soundName) {
                const audio = new Audio(`/sounds/${soundName}.mp3`);
                audio.play().catch(err => console.warn('Could not play sound:', err));
            },

            vibrate(pattern) {
                if (navigator.vibrate) {
                    navigator.vibrate(pattern);
                }
            }
        };
    }
</script>
@endsection
```

---

## 📊 ROUTES - API

```php
// routes/api.php

Route::middleware(['auth:sanctum'])->group(function () {
    // Orders
    Route::apiResource('orders', OrderController::class);
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::post('orders/{order}/call-staff', [OrderController::class, 'callStaff']);
    
    // QR Sessions
    Route::apiResource('qr-sessions', QrSessionController::class);
});
```

---

## 🔔 DATABASE - ADD TIMESTAMPS

```sql
ALTER TABLE orders ADD COLUMN ready_notified_at DATETIME NULL;
ALTER TABLE orders ADD COLUMN served_notified_at DATETIME NULL;

-- Create notification log table (optional)
CREATE TABLE notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    type ENUM('order_received', 'preparing', 'ready', 'served'),
    sent_to ENUM('kitchen', 'customer', 'both'),
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
```

---

## ✅ DEPLOYMENT CHECKLIST

- [ ] Install Laravel Reverb
- [ ] Configure WebSocket in .env
- [ ] Start Reverb server (`php artisan reverb:start`)
- [ ] Create Events (OrderCreated, OrderStatusUpdated, OrderReady, OrderCompleted)
- [ ] Update OrderController with broadcast()
- [ ] Install Echo + Socket.io on frontend
- [ ] Create echo.js configuration
- [ ] Create Blade templates: kitchen/display.blade.php, orders/tracking.blade.php
- [ ] Setup sound files in `/public/sounds/new-order.mp3`, `/public/sounds/ready-alert.mp3`
- [ ] Test on mobile (WebSocket may need SSL in production)
- [ ] Deploy to production with nginx + supervisor

---

## 🚀 PRODUCTION SETUP

```bash
# Supervisor config for Laravel Reverb
# /etc/supervisor/conf.d/reverb.conf

[program:reverb]
process_name=%(program_name)s
command=php /path/to/cafe/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
numprocs=1
stdout_logfile=/var/log/reverb.log
stderr_logfile=/var/log/reverb-error.log
```

---

## 📋 THỨ TỰ IMPLEMENT (BƯỚC 7A)

### **Prerequisite** ✅
- [ ] Bước 5 hoàn thành: `PlaceOrder`, `OrderController` có method create/update
- [ ] Bước 6 hoàn thành: `KitchenController` có method updateStatus
- [ ] Bước 7 hoàn thành: `PaymentController` có method complete

### **Week 1: Backend Setup** (2-3 ngày)
1. [ ] Install Laravel Reverb + configure `.env`
2. [ ] Start Reverb server locally (`php artisan reverb:start`)
3. [ ] Create 4 Events: `OrderCreated`, `OrderStatusUpdated`, `OrderReady`, `OrderCompleted`
4. [ ] Update `PlaceOrder` Action: add `broadcast(new OrderCreated($order))`
5. [ ] Update `OrderController@updateStatus`: add `broadcast(new OrderStatusUpdated())`
6. [ ] Test Events: sử dụng `php artisan tinker` để fire events
7. [ ] Write tests: `OrderBroadcastTest` verify broadcast được gọi

### **Week 2: Frontend Setup** (3-4 ngày)
1. [ ] Install `laravel-echo` + `socket.io-client` on frontend
2. [ ] Create `resources/js/echo.js` (Echo configuration)
3. [ ] Add Echo to `app.js` global
4. [ ] Create 2 Blade templates with Alpine.js:
   - `resources/views/kitchen/display.blade.php` (Kitchen Display System)
   - `resources/views/orders/tracking.blade.php` (Customer Order Tracking)
5. [ ] Integrate templates into routes
6. [ ] Test on mobile: order → status update → notification

### **Week 3: Testing & Refinement** (2-3 ngày)
1. [ ] Load test: 50 concurrent connections
2. [ ] Network delay test: toggle browser devtools throttling
3. [ ] Mobile test: iOS Safari + Android Chrome
4. [ ] Sound files: place `/public/sounds/new-order.mp3`, `ready-alert.mp3`
5. [ ] Error handling: reconnection, fallback to polling

### **Week 4: Production Deploy** (1 ngày)
1. [ ] Setup Supervisor for Reverb on production server
2. [ ] Configure nginx proxy to WebSocket port (8080)
3. [ ] SSL/TLS for WSS (WebSocket Secure)
4. [ ] Monitor Reverb logs
5. [ ] Rollback plan if WebSocket fails (fall back to polling)

---

## 🔗 INTEGRATION POINTS

### **Bước 5 (Đơn hàng) + WebSocket**
```php
// In PlaceOrder Action
public function execute(PlaceOrderRequest $request): Order {
    $order = Order::create([...]);
    
    // NEW: Broadcast to kitchen
    broadcast(new OrderCreated($order))->toOthers();
    
    return $order;
}
```

### **Bước 6 (Màn hình bếp) + WebSocket**
```php
// In KitchenController@updateStatus
public function updateStatus(Request $request, Order $order) {
    $oldStatus = $order->status;
    $order->status = $request->status;
    $order->save();
    
    // NEW: Real-time broadcast
    broadcast(new OrderStatusUpdated($order, $oldStatus, $request->status))->toOthers();
    
    if ($request->status === 'ready') {
        broadcast(new OrderReady($order))->toOthers();
    }
}
```

### **Bước 7 (Thanh toán) + WebSocket**
```php
// In PaymentController@store
public function store(Request $request, Order $order) {
    $payment = Payment::create([...]);
    $order->update(['status' => 'completed']);
    
    // NEW: Notify kitchen & customer
    broadcast(new OrderCompleted($order))->toOthers();
}
```

### **Bước 9 (QR khách) + WebSocket**
```javascript
// Customer page subscribes to order updates
Echo.channel(`qr-session.${sessionId}`)
    .listen('order.status.updated', (data) => {
        // Update UI
    })
    .listen('order.ready', (data) => {
        // Play sound + vibrate
    });
```

---

## ❌ FALLBACK (KHÔNG CÓ WEBSOCKET)

Nếu WebSocket fail, hệ thống vẫn hoạt động:
- **KDS**: polling mỗi 15 giây (Bước 6 làm rồi)
- **QR**: customer tự bấm "Refresh" hoặc polling nền 30 giây
- **Dashboard**: polling

→ WebSocket là "enhancement", không phải "requirement".

---

## 📊 PERFORMANCE NOTES

- **Reverb memory**: ~50MB base + ~0.5MB per connection
- **Max connections**: 10,000+ on single server (tùy RAM)
- **Message latency**: <100ms local, <500ms over internet
- **Broadcast overhead**: ~10ms per message

Café nhỏ (1-2 quán): 1 Reverb server đủ. Lớn (10+ quán): scale horizontal với Redis adapter.

---

## 🔐 SECURITY

- [ ] Verify user permissions before broadcasting (chỉ kitchen xem kitchen channel)
- [ ] Rate limit: tối đa 10 calls/second per user
- [ ] Validate order ownership: khách chỉ subscribe được channel của đơn của mình
- [ ] Nginx: `proxy_read_timeout 3600s` để WebSocket connection sống lâu
- [ ] CORS: `Access-Control-Allow-Origin` cho frontend domain

---

**Status**: ✅ Complete WebSocket implementation with Blade + Alpine.js ready!

**Next**: Sau khi implement Bước 7A → Bước 8 (Dashboard thật) → Bước 9 (QR hoàn chỉnh)