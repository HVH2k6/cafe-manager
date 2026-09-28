-- ==================== CAFE MANAGEMENT SYSTEM - FINAL SCHEMA ====================
-- Database: cafe_management
-- Version: 2.0
-- Fixes: QR operating hours, product bundles, critical triggers, inventory management

-- ==================== 1. AUTH & PERMISSION SYSTEM ====================

CREATE TABLE role (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_is_active (is_active),
    KEY idx_display_order (display_order)
);

CREATE TABLE permission (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_module_action (module, action),
    KEY idx_module (module),
    KEY idx_is_active (is_active)
);

CREATE TABLE role_has_permission (
    role_id BIGINT NOT NULL,
    permission_id BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES role(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permission(id) ON DELETE CASCADE,
    KEY idx_permission_id (permission_id)
);

CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role_id BIGINT NOT NULL,
    avatar_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES role(id),
    KEY idx_email (email),
    KEY idx_role_id (role_id),
    KEY idx_is_active (is_active),
    KEY idx_created_at (created_at)
);

-- ==================== 2. EMPLOYEES ====================

CREATE TABLE employees (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNIQUE NOT NULL,
    position VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    salary DECIMAL(10, 2) NOT NULL DEFAULT 0,
    hire_date DATE NOT NULL,
    contract_type ENUM('full_time', 'part_time', 'contract', 'intern') DEFAULT 'full_time',
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_position (position),
    KEY idx_department (department),
    KEY idx_is_active (is_active)
);

-- ==================== 3. PRODUCT & INVENTORY ====================

CREATE TABLE categories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(100),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_slug (slug),
    KEY idx_is_active (is_active),
    KEY idx_display_order (display_order)
);

CREATE TABLE products (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    category_id BIGINT NOT NULL,
    description TEXT,
    image_url VARCHAR(500),
    base_price DECIMAL(10, 2) NOT NULL,
    selling_price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    sku VARCHAR(50) UNIQUE,
    barcode VARCHAR(100),
    is_available BOOLEAN DEFAULT TRUE,
    is_featured BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    prep_time_minutes INT DEFAULT 5,
    calories INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id),
    KEY idx_category_id (category_id),
    KEY idx_is_available (is_available),
    KEY idx_slug (slug),
    KEY idx_sku (sku),
    KEY idx_created_at (created_at),
    KEY idx_category_available (category_id, is_available)
);

-- Product variants (Size, Sugar level, etc)
CREATE TABLE product_variants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL COMMENT 'Size: Small, Medium, Large or Sugar: None, Less, Normal',
    value VARCHAR(100),
    price_modifier DECIMAL(10, 2) DEFAULT 0,
    is_default BOOLEAN DEFAULT FALSE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    KEY idx_product_id (product_id),
    KEY idx_display_order (display_order)
);

-- ==================== PRODUCT BUNDLES (COMBOS) ====================

CREATE TABLE product_bundles (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL COMMENT 'Coffee + Bread Combo, Student Combo',
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    bundle_price DECIMAL(10, 2) NOT NULL COMMENT 'Giá combo',
    individual_price DECIMAL(10, 2) COMMENT 'Tổng giá nếu mua riêng',
    discount_amount DECIMAL(10, 2) COMMENT 'Tiết kiệm khi mua combo',
    image_url VARCHAR(500),
    is_active BOOLEAN DEFAULT TRUE,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_is_active (is_active),
    KEY idx_display_order (display_order)
);

CREATE TABLE product_bundle_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    bundle_id BIGINT NOT NULL,
    product_id BIGINT NOT NULL,
    quantity INT DEFAULT 1 COMMENT 'Số lượng sản phẩm trong combo',
    display_order INT DEFAULT 0,
    FOREIGN KEY (bundle_id) REFERENCES product_bundles(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY uk_bundle_product (bundle_id, product_id),
    KEY idx_bundle_id (bundle_id),
    KEY idx_product_id (product_id)
);

-- ==================== INVENTORY ====================

CREATE TABLE inventory (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT UNIQUE NOT NULL,
    quantity_on_hand INT DEFAULT 0,
    quantity_reserved INT DEFAULT 0 COMMENT 'Reserved for pending orders',
    min_stock INT DEFAULT 5,
    max_stock INT DEFAULT 100,
    reorder_level INT DEFAULT 20,
    unit_of_measure VARCHAR(20) DEFAULT 'piece',
    last_restock DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    KEY idx_low_stock (quantity_on_hand),
    KEY idx_last_restock (last_restock)
);

-- Inventory transaction log (audit trail)
CREATE TABLE inventory_transactions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    transaction_type ENUM('purchase', 'sale', 'adjustment', 'return', 'damage', 'expiry') NOT NULL,
    quantity INT NOT NULL,
    reference_id BIGINT COMMENT 'order_id, purchase_id, etc',
    reference_type VARCHAR(50) COMMENT 'order, purchase, adjustment',
    notes TEXT,
    created_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    KEY idx_product_id (product_id),
    KEY idx_created_at (created_at),
    KEY idx_reference (reference_type, reference_id),
    KEY idx_transaction_type (transaction_type)
);

-- ==================== 4. TABLES (Physical cafe tables) ====================

CREATE TABLE tables (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    table_no INT UNIQUE NOT NULL,
    capacity INT NOT NULL DEFAULT 2,
    location VARCHAR(100),
    section VARCHAR(50),
    status ENUM('available', 'occupied', 'reserved', 'maintenance', 'dirty') DEFAULT 'available',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_location (location),
    KEY idx_section (section),
    KEY idx_table_no (table_no)
);

-- ==================== 5. ORDERS ====================

CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'ORD-2025-0001',
    user_id BIGINT NOT NULL,
    table_id BIGINT COMMENT 'NULL if takeaway',
    qr_session_id BIGINT COMMENT 'If ordered via QR',
    order_type ENUM('dine_in', 'takeaway', 'delivery') DEFAULT 'dine_in',
    status ENUM('pending', 'confirmed', 'preparing', 'ready', 'served', 'completed', 'cancelled') DEFAULT 'pending',
    subtotal DECIMAL(12, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    discount_reason VARCHAR(255),
    total_amount DECIMAL(12, 2) DEFAULT 0,
    notes TEXT,
    customer_phone VARCHAR(20),
    customer_name VARCHAR(255),
    estimated_ready_at DATETIME,
    started_at DATETIME,
    completed_at DATETIME,
    cancelled_reason VARCHAR(255),
    cancelled_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE SET NULL,
    FOREIGN KEY (qr_session_id) REFERENCES qr_sessions(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uk_order_number (order_number),
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    KEY idx_table_id (table_id),
    KEY idx_user_id (user_id),
    KEY idx_customer_phone (customer_phone),
    KEY idx_order_type (order_type),
    KEY idx_date_range (created_at, status)
);

CREATE TABLE order_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    product_id BIGINT,
    bundle_id BIGINT COMMENT 'If this is a bundle item',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(12, 2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    variant_ids JSON COMMENT 'JSON array of variant IDs',
    variant_values JSON COMMENT 'JSON array of variant values',
    special_instructions TEXT,
    status ENUM('pending', 'preparing', 'ready', 'served', 'cancelled') DEFAULT 'pending',
    prepared_by BIGINT,
    prepared_at DATETIME,
    served_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (bundle_id) REFERENCES product_bundles(id),
    FOREIGN KEY (prepared_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_order_id (order_id),
    KEY idx_product_id (product_id),
    KEY idx_bundle_id (bundle_id),
    KEY idx_status (status),
    KEY idx_prepared_by (prepared_by)
);

-- ==================== 6. PAYMENTS ====================

CREATE TABLE payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNIQUE NOT NULL,
    amount DECIMAL(12, 2) NOT NULL,
    method ENUM('cash', 'card', 'e_wallet', 'bank_transfer', 'qr_pay') DEFAULT 'cash',
    status ENUM('pending', 'completed', 'failed', 'refunded', 'partial') DEFAULT 'pending',
    transaction_id VARCHAR(255) UNIQUE,
    payment_gateway VARCHAR(100),
    gateway_response JSON,
    paid_amount DECIMAL(12, 2),
    change_amount DECIMAL(10, 2) DEFAULT 0,
    card_last4 VARCHAR(4),
    reference_number VARCHAR(100),
    notes TEXT,
    processed_by BIGINT,
    paid_at DATETIME,
    refunded_at DATETIME,
    refund_reason VARCHAR(255),
    refund_amount DECIMAL(12, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_method (method),
    KEY idx_status (status),
    KEY idx_paid_at (paid_at),
    KEY idx_transaction_id (transaction_id),
    KEY idx_created_at (created_at)
);

-- ==================== 7. QR CODE ORDERING SYSTEM ====================

-- ==================== QR OPERATING HOURS ====================
-- Định nghĩa giờ hoạt động của QR (khi nào mở, khi nào đóng)
CREATE TABLE qr_operating_hours (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL COMMENT '0-6 or day name',
    opening_time TIME NOT NULL COMMENT 'e.g., 07:00:00',
    closing_time TIME NOT NULL COMMENT 'e.g., 22:00:00',
    is_open BOOLEAN DEFAULT TRUE COMMENT 'FALSE = đóng cửa ngày này',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_day (day_of_week)
);

CREATE TABLE qr_codes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    table_id BIGINT UNIQUE NOT NULL,
    qr_token VARCHAR(255) UNIQUE NOT NULL,
    qr_data LONGTEXT,
    qr_url VARCHAR(500) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    disabled_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE,
    KEY idx_table_id (table_id),
    KEY idx_qr_token (qr_token),
    KEY idx_is_active (is_active)
);

CREATE TABLE qr_sessions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    table_id BIGINT NOT NULL,
    qr_token VARCHAR(255) NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    order_id BIGINT,
    status ENUM('active', 'completed', 'abandoned', 'expired') DEFAULT 'active',
    cart_data JSON COMMENT 'Temporary cart before order submission',
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity DATETIME,
    expires_at DATETIME COMMENT 'Session expires after 30 minutes',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    FOREIGN KEY (table_id) REFERENCES tables(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    KEY idx_table_id (table_id),
    KEY idx_session_token (session_token),
    KEY idx_order_id (order_id),
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    KEY idx_expires_at (expires_at)
);

CREATE TABLE qr_notifications (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    qr_session_id BIGINT NOT NULL,
    order_id BIGINT,
    type ENUM('order_received', 'order_preparing', 'order_ready', 'call_staff', 'payment_link') DEFAULT 'order_received',
    title VARCHAR(255),
    message TEXT,
    action_url VARCHAR(500),
    is_read BOOLEAN DEFAULT FALSE,
    is_sent BOOLEAN DEFAULT TRUE,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (qr_session_id) REFERENCES qr_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    KEY idx_qr_session_id (qr_session_id),
    KEY idx_is_read (is_read),
    KEY idx_created_at (created_at)
);

-- ==================== 8. ACTIVITY LOGS ====================

CREATE TABLE activity_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    action VARCHAR(100),
    entity_type VARCHAR(50),
    entity_id BIGINT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_user_id (user_id),
    KEY idx_created_at (created_at),
    KEY idx_entity (entity_type, entity_id)
);

-- ==================== 9. DAILY SALES SUMMARY ====================

CREATE TABLE daily_sales_summary (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    date DATE UNIQUE NOT NULL,
    total_orders INT DEFAULT 0,
    total_revenue DECIMAL(12, 2) DEFAULT 0,
    cash_revenue DECIMAL(12, 2) DEFAULT 0,
    card_revenue DECIMAL(12, 2) DEFAULT 0,
    discount_total DECIMAL(10, 2) DEFAULT 0,
    average_order_value DECIMAL(10, 2) DEFAULT 0,
    tables_used INT DEFAULT 0,
    takeaway_orders INT DEFAULT 0,
    dine_in_orders INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_date (date)
);

-- ==================== 10. VIEWS FOR REPORTING ====================

CREATE VIEW order_summary AS
SELECT 
    o.id,
    o.order_number,
    o.created_at,
    t.table_no,
    t.location,
    u.name AS created_by,
    o.order_type,
    o.status,
    o.subtotal,
    o.discount_amount,
    o.total_amount,
    COUNT(oi.id) AS item_count,
    COALESCE(p.status, 'pending') AS payment_status,
    TIMEDIFF(o.completed_at, o.created_at) AS duration
FROM orders o
LEFT JOIN tables t ON o.table_id = t.id
LEFT JOIN users u ON o.user_id = u.id
LEFT JOIN order_items oi ON o.id = oi.order_id
LEFT JOIN payments p ON o.id = p.order_id
GROUP BY o.id
ORDER BY o.created_at DESC;

CREATE VIEW low_stock_products AS
SELECT 
    p.id,
    p.name,
    c.name AS category,
    i.quantity_on_hand,
    i.quantity_reserved,
    i.min_stock,
    (i.min_stock - i.quantity_on_hand) AS shortage,
    p.cost_price,
    (i.min_stock - i.quantity_on_hand) * p.cost_price AS cost_to_restock
FROM inventory i
JOIN products p ON i.product_id = p.id
JOIN categories c ON p.category_id = c.id
WHERE i.quantity_on_hand < i.min_stock
ORDER BY shortage DESC;

CREATE VIEW product_popularity AS
SELECT 
    p.id,
    p.name,
    c.name AS category,
    COUNT(oi.id) AS times_ordered,
    SUM(oi.quantity) AS total_quantity,
    SUM(oi.subtotal) AS total_revenue,
    AVG(oi.quantity) AS avg_quantity_per_order
FROM order_items oi
JOIN products p ON oi.product_id = p.id
JOIN categories c ON p.category_id = c.id
WHERE oi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY p.id, c.id
ORDER BY total_revenue DESC;

-- ==================== 11. CRITICAL TRIGGERS ====================

-- ===== AUTO-UPDATE TABLE STATUS =====
DELIMITER //
CREATE TRIGGER update_table_status_on_order_create
AFTER INSERT ON orders
FOR EACH ROW
BEGIN
    IF NEW.table_id IS NOT NULL THEN
        UPDATE tables 
        SET status = 'occupied', updated_at = NOW() 
        WHERE id = NEW.table_id;
    END IF;
END //
DELIMITER ;

DELIMITER //
CREATE TRIGGER update_table_status_on_order_complete
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.table_id IS NOT NULL AND NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE tables 
        SET status = 'dirty', updated_at = NOW() 
        WHERE id = NEW.table_id;
    END IF;
END //
DELIMITER ;

-- ===== RELEASE RESERVED INVENTORY WHEN ORDER CANCELLED =====
-- FIX: Critical - phải release inventory khi order cancelled
DELIMITER //
CREATE TRIGGER release_reserved_inventory_on_order_cancel
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status != 'cancelled' THEN
        UPDATE inventory i
        JOIN order_items oi ON oi.product_id = i.product_id
        SET i.quantity_reserved = GREATEST(0, i.quantity_reserved - oi.quantity)
        WHERE oi.order_id = NEW.id;
        
        -- Log the transaction
        INSERT INTO inventory_transactions (product_id, transaction_type, quantity, reference_id, reference_type, notes)
        SELECT oi.product_id, 'return', -oi.quantity, NEW.id, 'order', CONCAT('Order cancelled: ', NEW.order_number)
        FROM order_items oi
        WHERE oi.order_id = NEW.id;
    END IF;
END //
DELIMITER ;

-- ===== RESERVE INVENTORY WHEN ORDER CREATED =====
-- FIX: Critical - phải reserve inventory khi order tạo
DELIMITER //
CREATE TRIGGER reserve_inventory_on_order_item_create
AFTER INSERT ON order_items
FOR EACH ROW
BEGIN
    IF NEW.product_id IS NOT NULL THEN
        UPDATE inventory 
        SET quantity_reserved = quantity_reserved + NEW.quantity
        WHERE product_id = NEW.product_id;
        
        -- Check if we have enough stock
        IF (SELECT (quantity_on_hand - quantity_reserved) FROM inventory WHERE product_id = NEW.product_id) < 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Insufficient inventory for product';
        END IF;
    END IF;
END //
DELIMITER ;

-- ===== UPDATE ORDER TOTAL =====
DELIMITER //
CREATE TRIGGER calculate_order_total_on_insert
BEFORE INSERT ON orders
FOR EACH ROW
BEGIN
    SET NEW.total_amount = COALESCE(NEW.subtotal, 0) - COALESCE(NEW.discount_amount, 0);
END //
DELIMITER ;

DELIMITER //
CREATE TRIGGER calculate_order_total_on_update
BEFORE UPDATE ON orders
FOR EACH ROW
BEGIN
    SET NEW.total_amount = COALESCE(NEW.subtotal, 0) - COALESCE(NEW.discount_amount, 0);
END //
DELIMITER ;

-- ===== UPDATE DAILY SALES SUMMARY =====
DELIMITER //
CREATE TRIGGER update_daily_sales_on_order_complete
AFTER UPDATE ON orders
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        INSERT INTO daily_sales_summary (date, total_orders, total_revenue, discount_total, average_order_value)
        VALUES (DATE(NEW.completed_at), 1, NEW.total_amount, NEW.discount_amount, NEW.total_amount)
        ON DUPLICATE KEY UPDATE
            total_orders = total_orders + 1,
            total_revenue = total_revenue + NEW.total_amount,
            discount_total = discount_total + NEW.discount_amount,
            average_order_value = total_revenue / total_orders,
            updated_at = NOW();
    END IF;
END //
DELIMITER ;

-- ==================== 12. HELPER FUNCTIONS ====================

-- Check if QR is currently operating (between opening_time and closing_time)
DELIMITER //
CREATE FUNCTION is_qr_operating() 
RETURNS BOOLEAN
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE current_day VARCHAR(20);
    DECLARE opening_time `TIME`;
    DECLARE closing_time `TIME`;
    DECLARE is_open BOOLEAN;
    DECLARE current_time `TIME`;
    
    SET current_day = DAYNAME(NOW());
    SET current_time = TIME(NOW());
    
    SELECT qro.is_open, qro.opening_time, qro.closing_time 
    INTO is_open, opening_time, closing_time
    FROM qr_operating_hours qro
    WHERE qro.day_of_week = current_day
    LIMIT 1;
    
    IF is_open = FALSE THEN
        RETURN FALSE;
    END IF;
    
    IF current_time >= opening_time AND current_time < closing_time THEN
        RETURN TRUE;
    ELSE
        RETURN FALSE;
    END IF;
END //
DELIMITER ;



-- ==================== ADDITIONAL PERFORMANCE INDEXES ====================

CREATE INDEX idx_orders_user_date ON orders(user_id, created_at);
CREATE INDEX idx_orders_table_date ON orders(table_id, created_at);
CREATE INDEX idx_order_items_order ON order_items(order_id);
CREATE INDEX idx_inventory_product ON inventory(product_id);
CREATE INDEX idx_products_category_available ON products(category_id, is_available);
CREATE INDEX idx_payments_order_status ON payments(order_id, status);
CREATE INDEX idx_qr_sessions_active ON qr_sessions(status, created_at);

-- ==================== END OF SCHEMA ====================
