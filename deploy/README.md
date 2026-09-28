# Triển khai lên server thật (Ubuntu + Nginx + PHP-FPM + Supervisor)

Realtime dùng Laravel Reverb chạy sau Nginx trên **cùng domain**: trình duyệt kết nối `wss://<domain>/app/...`,
Laravel gửi event tới `https://<domain>/apps/...`, Nginx chuyển cả hai vào Reverb ở `127.0.0.1:8080`.
Không cần mở thêm cổng firewall nào ngoài 80/443.

## 1. `.env` trên server

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cafe.example.com
APP_PUBLIC_URL=https://cafe.example.com   # link QR gọi món + hóa đơn điện tử

BROADCAST_CONNECTION=reverb

# Nơi trình duyệt và Laravel kết nối tới (qua Nginx, có TLS)
REVERB_HOST="cafe.example.com"
REVERB_PORT=443
REVERB_SCHEME=https

# Nơi Reverb thực sự lắng nghe (chỉ trong máy)
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# Giữ REVERB_APP_ID / REVERB_APP_KEY / REVERB_APP_SECRET; nên sinh key mới cho production:
#   php artisan reverb:install   (hoặc tự đặt chuỗi ngẫu nhiên)

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

`VITE_REVERB_*` được đóng gói vào JS lúc build → **đổi `.env` xong phải `npm run build` lại**.

## 2. Nginx

`deploy/nginx/cafe-manager.conf` — sửa domain, đường dẫn, socket PHP-FPM rồi:

```bash
sudo cp deploy/nginx/cafe-manager.conf /etc/nginx/sites-available/
sudo ln -s ../sites-available/cafe-manager.conf /etc/nginx/sites-enabled/
sudo certbot --nginx -d cafe.example.com
sudo nginx -t && sudo systemctl reload nginx
```

## 3. Supervisor (giữ Reverb luôn chạy)

```bash
sudo apt install supervisor
sudo cp deploy/supervisor/cafe-reverb.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status cafe-reverb     # RUNNING
```

Nếu có nhiều người dùng đồng thời, tăng giới hạn file mở của hệ thống (`ulimit -n`) — mỗi kết nối WebSocket là 1 file.

## 4. Lịch chạy (hết hạn phiên QR…)

```bash
sudo crontab -u www-data -e
* * * * * cd /var/www/cafe-manager && php artisan schedule:run >> /dev/null 2>&1
```

## 5. Mỗi lần cập nhật code

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build
php artisan optimize
php artisan reverb:restart     # Supervisor tự chạy lại Reverb với code mới
```

## 6. Kiểm tra

- `curl -i https://cafe.example.com/app/<REVERB_APP_KEY>` → trả về lỗi 4xx của Reverb (không phải 502) là Nginx đã nối được.
- Mở màn hình bếp: chấm trạng thái xanh "Realtime: đã kết nối".
- `tail -f storage/logs/reverb.log`, `tail -f storage/logs/laravel.log`.
- Webhook payOS: `php artisan payos:webhook https://cafe.example.com/webhooks/payos`.
