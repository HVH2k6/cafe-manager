# Cafe Manager — Lộ trình phát triển

Thứ tự dưới đây đi theo phụ thuộc dữ liệu: bước sau cần dữ liệu của bước trước
(có nhân viên → có sản phẩm, bàn → mới tạo được đơn → mới thanh toán, báo cáo).

## Đã xong

- [x] Đăng nhập / đăng xuất, ghi nhớ đăng nhập, khóa tạm sau 5 lần sai
- [x] Dashboard tổng quan (giao diện, đang dùng **dữ liệu mẫu**)
- [x] Phân quyền role + permission: ma trận gán quyền, CRUD quyền, sidebar ẩn/hiện theo quyền
- [x] 19 quyền mặc định cho 7 module (`orders`, `kitchen`, `products`, `inventory`, `reports`, `employees`, `settings`)

---

## Bước 0 — Dọn nền ✅

- [x] `timezone` = `Asia/Ho_Chi_Minh`; kết nối MySQL cố định `+07:00` (`DB_TIMEZONE`) để `NOW()` / `CURRENT_TIMESTAMP` khớp giờ Laravel
- [x] **Chốt: logic nghiệp vụ nằm trong Laravel, không dùng trigger MySQL.**
      Migration `drop_business_logic_triggers` đã gỡ 7 trigger (rollback sẽ tạo lại nguyên bản).
      Các view báo cáo (`order_summary`, `low_stock_products`, `product_popularity`) vẫn giữ — chỉ đọc, không có tác dụng phụ.
      → Mỗi luật trong "Luật nghiệp vụ cần tự viết" bên dưới phải có Action class + test khi làm tới bước tương ứng.
- [x] Test `RouteAuthorizationTest`: route nào cần đăng nhập mà thiếu `->middleware('can:module.action')` sẽ làm test đỏ
      (ngoại lệ có chủ đích: `dashboard`, `logout`)

## Bước 1 — Nhân viên (`users`, `employees`) ✅

- [x] Danh sách nhân viên: tìm theo tên / email / SĐT, lọc theo vai trò và đang làm / đã nghỉ, phân trang
- [x] Thêm / sửa nhân viên: `users` + `employees` trong 1 transaction; tài khoản chưa có hồ sơ sẽ được tạo hồ sơ khi lưu
- [x] Cho nghỉ việc / nhận lại thay vì xóa: khóa tài khoản + đăng xuất khỏi mọi thiết bị ngay lập tức
      (middleware `EnsureUserIsActive` đá người bị khóa ra ở request kế tiếp)
- [x] Đặt lại mật khẩu cho nhân viên (đăng xuất họ khỏi mọi thiết bị)
- [x] Chống leo quyền: nhân viên có quyền `employees.*` không sửa / khóa / đổi mật khẩu được tài khoản Chủ quán,
      không gán được vai trò Chủ quán; không ai tự khóa chính mình; không hạ quyền được Chủ quán cuối cùng
- [x] CRUD vai trò (tab "Vai trò" trong Phân quyền, chỉ Chủ quán): vai trò Chủ quán không đổi tên / khóa / xóa được;
      vai trò còn nhân viên thì không xóa được (khóa thay thế)
- [x] Quyền: `employees.view|create|update`, `employees.delete` = cho nghỉ việc / khóa tài khoản

Còn để sau: nhân viên tự đổi mật khẩu / cập nhật hồ sơ của mình (trang "Tài khoản của tôi"), upload ảnh đại diện (`avatar_url`).

## Bước 2 — Danh mục & sản phẩm (`categories`, `products`, `product_variants`, `product_bundles`) ✅

Trang "Thực đơn" có 3 tab: Sản phẩm · Danh mục · Combo. Tất cả dùng chung quyền `products.*`.

- [x] CRUD danh mục (biểu tượng, thứ tự, ẩn/hiện); slug tự sinh từ tên tiếng Việt; không xóa được danh mục còn sản phẩm
- [x] CRUD sản phẩm: ảnh (lưu ở `storage/app/public/products`, đã chạy `storage:link`), giá bán / niêm yết / vốn + lãi mỗi món,
      SKU, mã vạch, thời gian pha chế, calo, đang bán / nổi bật; tìm & lọc theo danh mục, trạng thái
- [x] Biến thể theo nhóm (Size, Đường…) với giá cộng thêm, mỗi nhóm tối đa 1 lựa chọn mặc định
- [x] Combo: chọn sản phẩm + số lượng, tự tính `individual_price` / `discount_amount`; giá combo không được cao hơn mua lẻ
- [x] Tồn kho **không** tự tạo cho mọi sản phẩm: có ô "Theo dõi tồn kho" khi tạo — món pha chế tại chỗ không cần đếm
- [x] Không xóa được sản phẩm đã nằm trong combo / có lịch sử kho / có trong đơn → chuyển "Ngừng bán"

## Bước 3 — Bàn (`tables`) ✅

- [x] Sơ đồ bàn theo khu vực, tô màu theo trạng thái, đếm số bàn mỗi trạng thái
- [x] CRUD bàn: số bàn (không trùng), số chỗ, khu vực, vị trí, ghi chú; không xóa được bàn đang có khách
- [x] Nút "Đã dọn xong" + menu đổi trạng thái (trống / đặt trước / cần dọn / bảo trì).
      "Có khách" **không chọn tay được** — Bước 5 sẽ đặt khi tạo đơn tại bàn
- [x] Quyền: `tables.view|create|update|delete` + `tables.status` riêng để phục vụ dọn bàn mà không sửa được cấu hình bàn

## Bước 4 — Tồn kho (`inventory`, `inventory_transactions`) ✅

- [x] Danh sách tồn kho + thẻ tóm tắt (đang theo dõi / sắp hết / hết hàng), lọc theo tình trạng
- [x] Nhập hàng / kiểm kê (nhập số đếm được, tự tính chênh lệch) / hư hỏng / hết hạn → ghi `inventory_transactions`
      trong transaction có khóa dòng; không cho trừ quá số tồn. Bán / trả lại **không ghi tay được** (do đơn hàng ghi)
- [x] Lịch sử kho theo sản phẩm (ai ghi, lúc nào), ngưỡng tối thiểu / cần đặt hàng / tối đa, đơn vị tính
- [x] Badge số sản phẩm sắp hết ở sidebar lấy từ dữ liệu thật
- [x] Quyền: `inventory.view`, `inventory.update`

## Bước 5 — Đơn hàng / POS (`orders`, `order_items`) ✅

Luật nghiệp vụ nằm trong `app/Actions/Orders` (`PlaceOrder`, `AddOrderItems`, `CancelOrder`, `CompleteOrder`, `StockReservation`…).

- [x] Màn hình POS: tại bàn / mang về, chọn món + biến thể (popup), combo, ghi chú, số lượng; lọc danh mục, tìm món;
      giá luôn tính lại ở server từ thực đơn (không tin giá từ trình duyệt), biến thể được lưu bản chụp vào đơn
- [x] `order_number` dạng `ORD-2026-0001`, đánh số lại mỗi năm, khóa dòng + thử lại khi trùng; vẫn đúng sau 9999
- [x] Tạo đơn trong 1 transaction; bàn đang có đơn mở → POS dẫn sang "Thêm món vào đơn đó"; bàn bảo trì bị chặn
- [x] Thêm món vào đơn đang mở; bỏ món bếp chưa làm (nhả kho, tính lại tiền)
- [x] Danh sách đơn: tab Đang xử lý / Chờ xác nhận / Hoàn thành / Đã hủy / Tất cả, tìm theo mã / tên / SĐT, lọc ngày
- [x] Luồng trạng thái: POS tạo = `confirmed` (đơn QR sẽ là `pending` → nút "Xác nhận");
      `preparing` / `ready` tự theo bếp → "Đã mang món ra" → `served` → "Hoàn thành" → `completed`
- [x] Hủy đơn (bắt buộc lý do, lưu `cancelled_by`) — quyền `orders.cancel`
- [x] Giảm giá (bắt buộc lý do, không quá tạm tính) — quyền mới `orders.discount`, mặc định chỉ Quản lý
- [x] Badge số đơn chờ xác nhận ở sidebar lấy từ dữ liệu thật
- [ ] Sau này: in phiếu order cho bếp / tem ly; chuyển bàn / gộp bàn; tách hóa đơn

## Bước 6 — Màn hình bếp ✅

- [x] Phiếu theo đơn (bàn, mã, số phút chờ — đỏ khi ≥ 15 phút), món cũ nhất trước; hiện biến thể, ghi chú, thành phần combo
- [x] Nút "Làm" / "Xong" cho từng món (lưu `prepared_by`, `prepared_at`); đơn tự chuyển `preparing` → `ready`
- [x] Đơn chưa xác nhận / đã hủy không hiện ở bếp
- [x] Tự cập nhật bằng polling 15 giây (bỏ qua khi tab ẩn hoặc đang bấm) → đã nâng lên Laravel Reverb ở Bước 7A
- [x] Quyền: `kitchen.view`, `kitchen.update`

## Bước 7 — Thanh toán (`payments`, `payos_payment_links`) ✅

- [x] Tiền mặt (nhập số khách đưa, nút mệnh giá nhanh, tính tiền thừa), thẻ (máy POS ngân hàng, 4 số cuối)
- [x] Chuyển khoản QR qua **payOS** (`App\Services\PayOSService`): tạo link có chữ ký HMAC, ảnh VietQR,
      trang đơn tự kiểm tra trạng thái 3 giây/lần qua server (không lộ key), hủy mã QR, trang return/cancel luôn hỏi lại payOS,
      webhook xác thực chữ ký (bỏ CSRF), tự thử lại khi trùng `orderCode`, báo lỗi rõ khi timeout / `code != "00"`
- [x] Mỗi lần tạo QR là 1 dòng `payos_payment_links` (orderCode không bao giờ dùng lại); đơn chỉ có 1 dòng `payments`
- [x] Đơn tự **hoàn thành** khi vừa đã thanh toán vừa đã mang đủ món (thứ tự nào cũng được) → trừ kho, bàn "Cần dọn"
- [x] Đã thanh toán hoặc đang có QR chờ chuyển khoản → khóa thêm/bỏ món, giảm giá, hủy đơn
- [x] Tiền chuyển tới đơn đã thu bằng cách khác → giữ trên link + cảnh báo "Cần hoàn tiền" trên trang đơn
- [x] Quyền: `payments.create` (mặc định Quản lý + Phục vụ)
- [x] Webhook: đã test qua ngrok (`php artisan payos:webhook <url-https>`); lên server thật chỉ cần chạy lại lệnh với domain thật
- [x] Hóa đơn khổ 80 mm (in nhiệt): "Phiếu tạm tính" khi chưa trả, "Hóa đơn thanh toán" khi đã trả; tên / địa chỉ / SĐT quán từ `.env`
- [x] **Hóa đơn điện tử** khi không có máy in: QR (vẽ bằng thư viện `qrcode`, link không gửi ra ngoài) → link có chữ ký,
      khách mở trên điện thoại không cần đăng nhập. Đặt `APP_PUBLIC_URL` = địa chỉ điện thoại truy cập được (domain / IP LAN / ngrok)
- [x] Hoàn tiền **thủ công** (payOS không tự trả tiền về): ghi nhận số tiền, lý do, cách hoàn (tiền mặt / chuyển khoản + mã GD), người hoàn
      - Đơn chưa xong: "Hủy đơn & hoàn tiền" toàn bộ → hủy đơn, nhả kho, trả bàn
      - Đơn đã hoàn thành: hoàn toàn bộ hoặc một phần, đơn giữ trạng thái hoàn thành
      - Tiền payOS chuyển nhầm vào đơn đã thu cách khác: nút "Đã hoàn tiền cho khách" để tắt cảnh báo
      - Quyền `payments.refund` (mặc định chỉ Quản lý); mỗi khoản chỉ hoàn 1 lần

## Bước 7A — Realtime WebSocket (Laravel Reverb + Echo + Alpine.js) ✅

Theo flow trong `WEBSOCKET_IMPLEMENTATION.md`, khác ở 2 điểm: dùng `pusher-js` thay `socket.io` (Reverb chỉ nói giao thức Pusher)
và event chỉ là **tín hiệu** — trang tự tải lại khối HTML từ server, nên dữ liệu hiển thị vẫn qua kiểm tra quyền như bình thường.

- [x] Cài `laravel/reverb`, `laravel-echo`, `pusher-js`, `alpinejs`; `BROADCAST_CONNECTION=reverb`, key `REVERB_*` trong `.env`
- [x] Event (`app/Events`, `ShouldBroadcastNow`): `OrderCreated` (`order.created`), `OrderStatusUpdated` (`order.status.updated`,
      kèm `change` = status / items / payment), `OrderReady` (`order.ready`), `OrderCompleted` (`order.completed`)
- [x] Chỉ phát **sau khi transaction commit** (rollback → không phát); Reverb tắt / lỗi → ghi log, **không** làm hỏng thao tác
- [x] `OrderObserver` bắt mọi lần đổi trạng thái đơn (bếp, thanh toán, hủy, xác nhận…); thêm / bỏ món và thanh toán phát thủ công
- [x] Kênh **private theo quyền** (`app/Broadcasting`): `kitchen` ← `kitchen.view`; `orders`, `order.{id}` ← `orders.view`
- [x] Màn hình bếp: đơn mới tự hiện + chớp viền + âm báo (Web Audio, nút "Bật âm báo") + rung; chấm trạng thái kết nối;
      mất kết nối → polling 15 giây, có kết nối vẫn đối soát 60 giây/lần
- [x] Toast "Món đã xong, mang ra cho khách" trên mọi trang cho người có `orders.view` (nghe `order.ready`)
- [x] Bếp bấm "Xong" **từng món** (đơn còn món khác đang làm): `OrderItemReady` (`order.item.ready`) → toast "Bàn 5 — Latte ×2 đã xong";
      mọi thay đổi món ở bếp đều phát `order.status.updated` (`change = items`) để trang đơn / trang khách cập nhật
- [x] Không có Reverb: popup nhân viên (món xong, đơn QR mới, khách gọi) tự hỏi `/staff-alerts` mỗi 15 giây;
      broadcast lỗi ⇒ tạm dừng gửi 30 giây (không làm chậm thao tác)
- [x] Danh sách đơn: số đếm, tab và bảng tự cập nhật (ô tìm kiếm không bị đụng tới)
- [x] Trang chi tiết đơn: tự tải lại khi đơn đổi; đang nhập form (giảm giá, hủy, thu tiền) → hiện thanh "Có cập nhật mới — Tải lại"
- [x] Test: `tests/Feature/OrderBroadcastTest.php` (payload, kênh, rollback, fallback khi Reverb lỗi, quyền vào kênh)
- [x] Kênh cho khách QR: kênh **công khai** `qr-session.{token}` (khách không đăng nhập; tên kênh chứa token ngẫu nhiên 48 ký tự)
      nhận `order.status.updated` của đơn mình theo dõi + `staff.answered`; nhân viên nhận `staff.called` trên kênh `orders`
- [x] Rà soát bổ sung (sau Bước 8) — những thao tác trước đây đổi dữ liệu mà màn hình khác không biết:
      - **Sơ đồ bàn**: event `TableUpdated` (`table.updated`) trên kênh private `tables` ← `tables.view`, phát từ `DiningTableObserver`
        (tạo đơn → Có khách, hủy → Trống, hoàn thành → Cần dọn, "Đã dọn xong", thêm / sửa / xóa bàn).
        `FreeTable` đổi sang cập nhật qua model để observer bắt được. Đang mở menu "Đổi trạng thái" → hiện thanh "Tải lại"
      - **Giảm giá** → `order.status.updated` với `change = discount` (trang đơn, danh sách, trang khách QR, dashboard)
      - **Hoàn tiền đơn đã hoàn thành** (một phần / toàn bộ) và "Đã hoàn tiền cho khách" (payOS chuyển nhầm) → `change = payment`
      - **Tạo / hủy mã QR payOS**, mã hết hạn phía payOS → `change = payment` (màn hình khác khóa / mở khóa sửa đơn ngay)
      - **Dashboard & Báo cáo** tự cập nhật khi khoảng xem có hôm nay (nghe kênh `orders`; không có `orders.view` → polling 30 giây)
      - `OrderStatusUpdated::changed($order, CHANGE_…)` cho mọi thay đổi không đổi trạng thái
- [x] Badge sidebar (`App\Support\SidebarBadges`, store Alpine `badges`, `GET /sidebar-badges` lọc theo quyền):
      Đơn hàng = đơn chờ xác nhận · Màn hình bếp = món chờ / đang làm · Sơ đồ bàn = bàn cần dọn · Tồn kho = sản phẩm sắp hết.
      Đếm lại sau event trên kênh người dùng được nghe (`orders` / `kitchen` / `tables`), polling 30 giây khi mất kết nối,
      120 giây khi có kết nối (nhập hàng / kiểm kê không phát event), và khi quay lại tab. Test: `SidebarBadgeTest.php`
- [x] Test realtime: event + kênh + payload cho từng thao tác trên, rollback không phát, `/broadcasting/auth` từ chối
      người thiếu quyền; đã chạy thử end-to-end với Reverb thật (client pusher-js nhận `table.updated`, `order.status.updated`)
- [x] Lên server thật: `deploy/nginx/cafe-manager.conf` (proxy `/app`, `/apps` → Reverb 127.0.0.1:8080, wss cùng domain),
      `deploy/supervisor/cafe-reverb.conf`, hướng dẫn `.env` production + các bước deploy trong `deploy/README.md`

Chạy khi dev (2 terminal): `php artisan serve` và `php artisan reverb:start` (thêm `--debug` để xem kết nối).
Điện thoại / máy khác trong LAN: đặt `REVERB_HOST` = IP máy chủ, chạy `php artisan reverb:start --host=0.0.0.0`, rồi `npm run build`.

## Bước 8 — Dashboard dữ liệu thật & Báo cáo ✅

Số liệu tính trong `App\Reports\SalesReport` thẳng từ `orders` / `order_items` / `payments` (không dùng `daily_sales_summary`, xem luật 6).
**Doanh thu = đơn đã hoàn thành** (theo `completed_at`, sau giảm giá); hoàn tiền trừ vào ngày hoàn (`refunded_at`) → "Thực thu".

- [x] Dashboard dùng query thật: số đơn (không tính đơn hủy), doanh thu, trung bình/đơn, số phần đã bán, tiến độ đơn,
      biểu đồ theo giờ (1 ngày) hoặc theo ngày, món bán chạy, tỷ lệ phương thức thanh toán, đơn gần đây
      - Ai đăng nhập cũng xem được; **số tiền cần `reports.view`**, bảng đơn gần đây cần `orders.view`
- [x] Bộ lọc "Hôm nay / 7 ngày / 30 ngày / Tùy chọn" (`App\Reports\ReportPeriod`): ngày đảo ngược tự đổi chỗ, tối đa 366 ngày, ngày sai → mặc định
- [x] Trang Báo cáo (`/reports`, `reports.view`): doanh thu theo ngày / tháng (tách tiền mặt / thẻ / chuyển khoản, hoàn tiền),
      theo phương thức thanh toán, theo hình thức (tại bàn / mang về), top 20 sản phẩm bán chạy
- [x] Sản phẩm bán chạy: tính từ `order_items` của đơn hoàn thành, bỏ món đã hủy, gồm cả combo
      (view `product_popularity` không dùng: cố định 30 ngày, tính cả đơn chưa xong / đã hủy)
- [x] Xuất CSV (UTF-8 có BOM, Excel mở đúng tiếng Việt) — quyền mới `reports.export` (mặc định Quản lý):
      doanh thu theo ngày/tháng, sản phẩm bán chạy, danh sách đơn; chặn chèn công thức Excel (`=`, `+`, `-`, `@`)
- [x] Test: `tests/Feature/ReportTest.php` (25 test)

## Bước 9 — Gọi món bằng QR (khách tự đặt, không cần đăng nhập) ✅

- [x] Sinh / tải QR cho từng bàn (`qr_codes`), vô hiệu hóa QR
      - Sơ đồ bàn → icon QR từng bàn (`tables/{id}/qr`): xem, tải PNG, in, **tạm ngưng kèm lời nhắn**, **tạo mã mới** (mã cũ đã in ngừng hoạt động)
      - "Mã QR gọi món" (`/qr-codes`): tạo mã cho các bàn chưa có, in cả loạt khổ A4 (6 mã/trang)
      - Ảnh QR vẽ bằng thư viện `qrcode` trên trình duyệt; link dùng `APP_PUBLIC_URL` (điện thoại khách phải truy cập được)
      - Quyền: `tables.update`
- [x] Cài đặt giờ nhận đơn QR theo ngày trong tuần (`qr_operating_hours`) — sidebar "Cài đặt" (`settings.view` / `settings.update`)
      - Hỗ trợ qua đêm (18:00 → 02:00), đóng = mở ⇒ cả ngày; chưa cài ⇒ nhận đơn cả ngày
      - Ngoài giờ: khách vẫn xem menu, gọi nhân viên; không gửi đơn được
- [x] Trang cho khách (UI theo 6 ảnh thiết kế, Alpine, tối ưu điện thoại):
      - `/qr/{token}`: "Bạn đang ngồi tại Bàn 3", trạng thái mở cửa, tên khách, "Xem Menu", "Gọi nhân viên", giờ nhận đơn hôm nay
      - `/s/{session}`: menu theo danh mục (tab dính + tự đổi khi cuộn), combo tiết kiệm, món yêu thích (lưu trên máy), hết hàng
      - Chi tiết món dạng bottom sheet: size (radio kèm giá), đường/đá (chip), ghi chú, số lượng, tổng tiền
      - Giỏ hàng (`#cart`) + thanh toán (`#checkout`): tiền mặt / thẻ / ví điện tử / chuyển khoản, ghi chú, điều khoản
      - Giỏ lưu trong `qr_sessions.cart_data` (tải lại trang không mất), phiên hết hạn sau 30 phút không dùng
        (`qr:expire-sessions` chạy 5 phút/lần qua scheduler)
- [x] Khách gửi đơn → đơn `pending` (không có nhân viên tạo: `orders.user_id` cho phép null) vào hàng chờ xác nhận
      - Giá luôn tính lại trên server; bàn đang có đơn mở ⇒ món gọi thêm (cùng máy hoặc máy khác ở cùng bàn) **gộp vào đơn đó**
      - Đơn đã thanh toán / đang chờ chuyển khoản ⇒ khách được nhắn gọi nhân viên
      - Nhân viên: popup "Bàn 3 vừa gửi đơn qua QR — cần xác nhận" + âm báo; nhãn "QR" trong danh sách đơn;
        trang đơn hiện "Khách tự đặt qua QR" + phương thức khách chọn (cột mới `orders.payment_preference`)
      - Bếp chỉ báo âm khi đơn QR **đã được xác nhận**
- [x] Khách theo dõi trạng thái đơn realtime (Chờ xác nhận → Đang chuẩn bị → Sẵn sàng → Hoàn thành, "Dự kiến N phút", đơn bị hủy + lý do)
      - Chuyển khoản / ví: sau khi quầy xác nhận, khách bấm "Chuyển khoản ngay" → VietQR payOS ngay trên điện thoại, tự cập nhật khi nhận tiền
      - "Gọi nhân viên" (`qr_notifications`): popup trên mọi màn hình nhân viên tới khi có người bấm "Tôi ra ngay",
        khách thấy "Lan đang tới bàn bạn"; lịch sử đơn (nhận / đang làm / xong / mã chuyển khoản) cũng ghi vào `qr_notifications`
      - Đơn hoàn thành / hủy ⇒ phiên QR đóng (`completed`), quét lại để gọi món mới
- [x] Chống spam (`AppServiceProvider`): mở phiên 10/phút/IP; gửi đơn 5 lần/10 phút/phiên + 20/10 phút/IP;
      gọi nhân viên 3/phút/phiên (bấm lại trong 60 giây không tạo cuộc gọi mới); giỏ ≤ 30 dòng, ≤ 20 phần/món
- [x] Test: `tests/Feature/QrOrderingTest.php` (27 test)

## Bước 10 — Cài đặt & nhật ký ✅

Trang "Cài đặt" có 3 tab: Thông tin quán · Giờ nhận đơn QR · Nhật ký hoạt động.

- [x] Cài đặt quán (bảng `settings` key/value, `App\Models\Setting`, có cache): tên, địa chỉ, SĐT, lời cảm ơn + ghi chú cuối hóa đơn
      (Wi-Fi, MST…), giờ mở cửa (hỗ trợ qua đêm, hiện trên Tổng quan)
      - Chưa lưu thì dùng giá trị `.env` (`CAFE_NAME`, `CAFE_ADDRESS`, `CAFE_PHONE`); đã lưu thì ghi đè `config('app.cafe_*')` lúc khởi động
- [x] **Lịch sử gọi món QR trên điện thoại khách**, giữ `qr_history_minutes` phút (mặc định 20, chọn 5–120) sau khi đơn xong / bị hủy:
      - Quét lại mã trên bàn → khối "Món bạn đã gọi": mọi đơn điện thoại này đã gửi ở bàn này, trạng thái, tổng tiền,
        "xem được đến HH:MM", nút "Xem hóa đơn" (link hóa đơn điện tử có chữ ký) khi đã thanh toán
      - Lưu trong session trình duyệt của khách (tối đa 10 phiên / bàn), **không** lộ cho điện thoại khác ngồi sau ở cùng bàn
      - Hết giờ: khối tự ẩn và link `/s/{token}/order` cũ trả 410 "không còn xem được"
- [x] Nhật ký hoạt động (`activity_logs`, thêm cột `description`; `App\Support\ActivityLogger`):
      hủy đơn, giảm giá, bỏ món, hoàn tiền, thêm / sửa (giá cũ → mới) / xóa sản phẩm, thêm / sửa (đổi vai trò, lương) / khóa / mở khóa nhân viên,
      đặt lại mật khẩu, đổi phân quyền (+ quyền thêm, − quyền bỏ), sửa cài đặt
      - Chỉ ghi khi transaction commit; chỉ ghi các trường thật sự đổi; mật khẩu / token không bao giờ vào log; ghi IP, trình duyệt
      - Nhập / kiểm kê kho không ghi lại ở đây (đã có `inventory_transactions` với người ghi, thời gian)
      - Trang nhật ký: lọc theo loại, người, ngày, tìm theo nội dung; xem chi tiết trước / sau
- [x] Quyền: `settings.view`, `settings.update`, mới `settings.activity` (xem nhật ký) — mặc định chỉ Chủ quán
- [x] Test: `QrHistoryTest.php` (7), `SettingsAndActivityTest.php` (19)

Còn để sau: trang "Tài khoản của tôi", ảnh đại diện; tự dọn nhật ký cũ (hiện giữ mãi).

---

## Luật nghiệp vụ cần tự viết trong Laravel

Trước đây do trigger MySQL đảm nhận (đã gỡ ở Bước 0). Mỗi luật = 1 Action class + test, làm ở bước ghi trong ngoặc.
Viết đúng luôn những chỗ trigger cũ làm sai.

1. ✅ **Tổng tiền đơn** = tạm tính (món chưa hủy) − giảm giá, không âm — `Order::recalculateTotals()`.
2. ✅ **Trạng thái bàn**: tạo đơn → `occupied`; hoàn thành → `dirty`; hủy → `available` (nếu bàn không còn đơn mở khác) — `PlaceOrder`, `FreeTable`.
3. ✅ **Giữ kho khi tạo / thêm món**: kiểm tra số *có thể bán* (tồn − đang giữ) **trước** khi giữ, khóa dòng kho theo thứ tự sản phẩm — `StockReservation::reserve()`.
   Combo được tách thành từng sản phẩm; sản phẩm không theo dõi kho thì bỏ qua.
4. ✅ **Nhả kho khi hủy đơn / bỏ món**: chỉ trả lại phần đang giữ. Tồn kho thực chưa đổi nên **không** ghi giao dịch kho
   (trigger cũ ghi `return` số âm là sai nghĩa).
5. ✅ **Trừ kho khi đơn hoàn thành** — trigger cũ **không có**: trừ tồn, nhả phần giữ, ghi giao dịch `sale` kèm mã đơn — `StockReservation::consume()`.
6. ✅ **Tổng hợp doanh thu ngày**: **không** ghi bảng `daily_sales_summary` — tính trực tiếp từ `orders` + `payments` (`SalesReport`),
   nên không bao giờ lệch khi hoàn tiền / sửa đơn. Nếu sau này dữ liệu lớn làm báo cáo chậm mới cân nhắc bảng tổng hợp.
7. ✅ **Kiểm tra giờ nhận đơn QR** — hàm `is_qr_operating()` trong file SQL **chưa từng được tạo** (lỗi khi import); viết bằng PHP, hỗ trợ ca qua đêm (18:00 → 02:00) — `QrOperatingHour::isOpenAt()`.
8. ✅ **Phát event realtime** chỉ sau khi transaction commit, lỗi broadcast không được làm hỏng thao tác — `OrderBroadcast::send()`, `OrderObserver`.
   Code mới đổi trạng thái đơn không cần tự phát event (observer lo); đổi món / tiền mà không đổi trạng thái thì gọi `OrderStatusUpdated::send(..., CHANGE_ITEMS | CHANGE_PAYMENT)`.

Lưu ý khác về file `cafe_schema_final.sql`: bảng `orders` khai báo khóa ngoại tới `qr_sessions` trước khi bảng này được tạo
→ import vào DB mới sẽ lỗi nếu không tắt `FOREIGN_KEY_CHECKS`.

## Gợi ý thứ tự làm tiếp

~~Bước 0 → 1 → 2 → 3 → 4 → 5 (Đơn hàng) → 6 (Bếp) → 7 (Thanh toán) → 7A (Realtime) → 9 (QR) → 8 (Dashboard & báo cáo) → 10 (Cài đặt & nhật ký)~~ ✅

Lên server sau Bước 8–10: `php artisan migrate --force`, `php artisan db:seed --class=PermissionSeeder --force`
(thêm quyền `reports.export`, `settings.activity`), `npm run build`, `php artisan optimize:clear`, restart Reverb.

Thử gọi món QR trên điện thoại (cùng Wi-Fi): đặt `APP_PUBLIC_URL=http://<IP-máy>:8000`, chạy `php artisan serve --host=0.0.0.0`,
tạo mã ở "Mã QR gọi món" rồi quét. Muốn khách nhận realtime thì làm thêm phần LAN của Reverb ở Bước 7A
(không có thì trang khách tự tải lại 20 giây/lần).
