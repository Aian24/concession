-- ============================================================
-- Concession System Database Setup
-- Complete database schema & initial seed data
-- Can be run in phpMyAdmin, MySQL CLI, or auto-initialized by db.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS concession_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE concession_db;

-- ─────────────────────────────────────────────
-- 1. USERS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,          -- bcrypt hashed
    store_code  VARCHAR(50)     NOT NULL,
    role        VARCHAR(100)    NOT NULL DEFAULT 'user',
    avatar      VARCHAR(255)    DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username   (username),
    INDEX idx_store_code (store_code),
    INDEX idx_role       (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 2. ROLES TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS roles (
    id           INT             AUTO_INCREMENT PRIMARY KEY,
    role_name    VARCHAR(50)     NOT NULL UNIQUE,
    display_name VARCHAR(100)    NOT NULL,
    permissions  JSON            NULL,
    can_submit   TINYINT(1)      DEFAULT 0,
    can_edit     TINYINT(1)      DEFAULT 0,
    can_delete   TINYINT(1)      DEFAULT 0,
    is_admin     TINYINT(1)      DEFAULT 0,
    created_at   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 3. STORE CODE TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS storecode (
    scode       VARCHAR(30)     NOT NULL PRIMARY KEY,
    sname       VARCHAR(100)    NOT NULL,
    INDEX idx_sname (sname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 4. SALES TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales (
    id               INT             AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(100)    NOT NULL,
    store_code       VARCHAR(50)     NOT NULL,
    item_no          VARCHAR(150)    NOT NULL,
    base_price       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    discount         DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    amount_sold      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    quantity         INT             NOT NULL DEFAULT 0,
    line_total       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    created_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    system_timestamp TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username   (username),
    INDEX idx_store_code (store_code),
    INDEX idx_created_at (created_at),
    INDEX idx_item_no    (item_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 5. RECEIVING TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS receiving (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NOT NULL,
    store_code  VARCHAR(50)     NOT NULL,
    os_no       VARCHAR(100)    NOT NULL,
    item_no     VARCHAR(150)    NOT NULL DEFAULT '',
    from_store  VARCHAR(50)     NOT NULL,
    to_store    VARCHAR(30)     NOT NULL DEFAULT '',
    quantity    INT             NOT NULL DEFAULT 0,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username   (username),
    INDEX idx_store_code (store_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 6. RETURNS & EXCHANGES TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS returns (
    id                INT             AUTO_INCREMENT PRIMARY KEY,
    username          VARCHAR(50)     NOT NULL,
    store_code        VARCHAR(20)     NOT NULL,
    return_item       VARCHAR(100)    DEFAULT NULL,
    quantity          INT             DEFAULT 1,
    return_amount     DECIMAL(10,2)   DEFAULT NULL,
    reason            TEXT            DEFAULT NULL,
    is_exchange       TINYINT(1)      DEFAULT 0,
    exchange_name     VARCHAR(100)    DEFAULT NULL,
    exchange_item     VARCHAR(100)    DEFAULT NULL,
    exchange_quantity INT             DEFAULT 0,
    exchange_amount   DECIMAL(10,2)   DEFAULT NULL,
    created_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username   (username),
    INDEX idx_store_code (store_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 7. PULLOUTS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pullouts (
    id           INT             AUTO_INCREMENT PRIMARY KEY,
    store_code   VARCHAR(50)     NOT NULL,
    username     VARCHAR(100)    NOT NULL,
    item_no      VARCHAR(100)    NOT NULL,
    quantity     INT             NOT NULL DEFAULT 1,
    image_path   VARCHAR(255)    DEFAULT NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_store_code (store_code),
    INDEX idx_username   (username),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 8. SYSTEM SETTINGS TABLE (PROMO & BRANDING)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    id               INT             AUTO_INCREMENT PRIMARY KEY,
    company_name     VARCHAR(100)    NOT NULL DEFAULT 'Concession System',
    time_format      VARCHAR(10)     NOT NULL DEFAULT '12h',
    logo_path        VARCHAR(255)    NOT NULL DEFAULT 'images/logo.png',
    favicon_path     VARCHAR(255)    NOT NULL DEFAULT 'assets/images/concessiontab.webp',
    logo_radius      INT             NOT NULL DEFAULT 0,
    logo_size        INT             NOT NULL DEFAULT 96,
    promo_enabled    TINYINT(1)      NOT NULL DEFAULT 1,
    promo_name       VARCHAR(150)    NOT NULL DEFAULT 'RL Shoe Bag Promo',
    promo_min_spend  DECIMAL(10,2)   NOT NULL DEFAULT 1999.00,
    promo_item_name  VARCHAR(150)    NOT NULL DEFAULT 'RL Shoe Bag',
    promo_item_no    VARCHAR(50)     NOT NULL DEFAULT '475552',
    promo_style_code VARCHAR(100)    NOT NULL DEFAULT 'RACT95002T26',
    promo_start_date DATE            NULL DEFAULT '2026-10-01',
    promo_end_date   DATE            NULL DEFAULT NULL,
    promo_stores     TEXT            NULL,
    updated_at       TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 9. USER LOGS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_logs (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    username    VARCHAR(100)    NOT NULL,
    ip_address  VARCHAR(50)     NOT NULL,
    login_time  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 10. USER STORE ASSIGNMENTS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_store_assignments (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    store_code  VARCHAR(50)     NOT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (user_id, store_code),
    INDEX idx_user_id (user_id),
    INDEX idx_store_code (store_code),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 11. PAGE NAVIGATION LOGS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS page_navigation_logs (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    username    VARCHAR(100)    NOT NULL,
    page_name   VARCHAR(100)    NOT NULL,
    page_url    VARCHAR(255)    NOT NULL,
    ip_address  VARCHAR(50)     NOT NULL,
    visit_time  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_username (username),
    INDEX idx_page_name (page_name),
    INDEX idx_visit_time (visit_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 12. ACTIVITY LOG TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NOT NULL,
    action_type VARCHAR(50)     NOT NULL,
    module      ENUM('Sale', 'Return', 'Receiving', 'Pullout') DEFAULT NULL,
    store_code  VARCHAR(50)     NOT NULL,
    reference   VARCHAR(150)    DEFAULT NULL,
    quantity    INT             NOT NULL DEFAULT 0,
    details     TEXT            DEFAULT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username   (username),
    INDEX idx_store_code (store_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 13. PRISMDATA TABLE (BARCODE LOOKUP)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS prismdata (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    item_no     VARCHAR(50)     NOT NULL UNIQUE,
    itemcode    VARCHAR(255)    NULL,
    stylename   VARCHAR(255)    NULL,
    color       VARCHAR(100)    NULL,
    size        VARCHAR(100)    NULL,
    srp         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item_no   (item_no),
    INDEX idx_itemcode  (itemcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 14. BOUTIQUE TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS boutique (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    date        DATE            NOT NULL,
    store_code  VARCHAR(50)     NOT NULL,
    store_name  VARCHAR(150)    NOT NULL,
    qty_sold    INT             NOT NULL DEFAULT 0,
    amount      DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date       (date),
    INDEX idx_store_code (store_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 15. USER THEMES TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS user_themes (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    theme_data  JSON            NOT NULL,
    updated_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- SEED DATA: DEFAULT ADMIN ACCOUNT
-- Password: admin123
-- ─────────────────────────────────────────────
INSERT IGNORE INTO users (username, password, store_code, role)
VALUES (
    'admin',
    '$2y$10$s0JAlEVGsvaYZnY0HlpEVeI4mJmC2g4ZmtrvgzBXDvl8WsBm5N/UO',
    'ADMIN-001',
    'admin'
);

-- ─────────────────────────────────────────────
-- SEED DATA: DEFAULT ROLES
-- ─────────────────────────────────────────────
INSERT IGNORE INTO roles (role_name, display_name, permissions, can_submit, can_edit, can_delete, is_admin) VALUES
('admin', 'Full Administrator', '["dashboard", "monitoring", "history", "create_sale", "sale", "create_return", "return", "create_receiving", "receiving", "create_pullout", "pullout", "create_ros_supplies", "ros_supplies", "non_submission", "admin", "roles", "stores", "prism_data", "boutique_data", "recent_activity"]', 0, 1, 1, 1),
('admin_view', 'View Only Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "pullout", "ros_supplies", "non_submission"]', 0, 1, 1, 1),
('store_admin', 'Store Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "pullout", "ros_supplies", "non_submission"]', 0, 1, 1, 1),
('multi_store_admin', 'Multi-Store Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "pullout", "ros_supplies", "non_submission"]', 0, 1, 1, 1),
('user', 'Sales Agent (User)', '["create_sale", "create_return", "create_receiving", "create_pullout", "create_ros_supplies", "history"]', 1, 0, 0, 0);

-- ─────────────────────────────────────────────
-- SEED DATA: CONCESSION STORES
-- ─────────────────────────────────────────────
INSERT IGNORE INTO storecode (scode, sname) VALUES
('14Y', 'CASH & CARRY'),
('1AY', 'CDM ANTIPOLO'),
('17C', 'FISHER MALL'),
('16A', 'GF CANLUBANG'),
('198', 'KCC GEN. SANTOS'),
('18T', 'KCC MALL ZAM'),
('138', 'KCC MARVEL'),
('1AB', 'LANDMARK ALABANG'),
('140', 'LANDMARK MAKATI'),
('1BD', 'LANDMARK MANILA BAY'),
('11X', 'LANDMARK TRINOMA'),
('147', 'RDS BACOLOD'),
('16W', 'RDS CENTRIO'),
('146', 'RDS ERMITA'),
('10D', 'RDS FESTIVAL'),
('19X', 'RDS ILIGAN'),
('152', 'RDS ILOILO'),
('17P', 'RDS MALOLOS'),
('1AN', 'RDS ORMOC'),
('1BC', 'SM ARANETA'),
('1BE', 'SM BACOLOD'),
('1BB', 'SM BACOOR'),
('183', 'SM BAGUIO'),
('168', 'SM CEBU'),
('11K', 'SM CLARK'),
('181', 'SM DASMA'),
('178', 'SM DAVAO'),
('172', 'SM FAIRVIEW'),
('11O', 'SM LIPA'),
('1BA', 'SM MAKATI'),
('11A', 'SM MALL OF ASIA'),
('174', 'SM MANDURRIAO'),
('175', 'SM MANILA'),
('164', 'SM MEGAMALL'),
('13J', 'SM NAGA'),
('166', 'SM NORTH EDSA'),
('14I', 'SM OLONGAPO'),
('176', 'SM PAMPANGA'),
('10P', 'SM SAN LAZARO'),
('1BF', 'SM SEASIDE CEBU'),
('11B', 'SM STA.ROSA'),
('12O', 'SM TAYTAY'),
('161', 'STA. LUCIA'),
('16S', 'TIONGSAN BAGUIO'),
('14W', 'GAI FIESTA MALL'),
('1AQ', 'GAI GRAND BUHANGIN'),
('17D', 'GAI GRAND MACTAN'),
('17E', 'GAI KIDAPAWAN'),
('14X', 'GAI  MALL OF DAVAO'),
('134', 'GAI SOUTH DAVAO'),
('15R', 'LCC IRIGA'),
('139', 'LCC LEGAZPI'),
('14V', 'LCC TABACO'),
('1AX', 'MET GAI AYALA CEBU'),
('131', 'MET GAI COLON'),
('12V', 'MET GAI MARKET MARKET'),
('13B', 'MET LEGAZPI');

-- ─────────────────────────────────────────────
-- SEED DATA: SYSTEM SETTINGS
-- ─────────────────────────────────────────────
INSERT IGNORE INTO system_settings (
    id, company_name, time_format, logo_path, favicon_path, logo_radius, logo_size,
    promo_enabled, promo_name, promo_min_spend, promo_item_name, promo_item_no, promo_style_code, promo_start_date, promo_stores
) VALUES (
    1, 'Concession System', '12h', 'images/logo.png', 'assets/images/concessiontab.webp', 0, 96,
    1, 'RL Shoe Bag Promo', 1999.00, 'RL Shoe Bag', '475552', 'RACT95002T26', '2026-10-01', 'ALL'
);

-- ─────────────────────────────────────────────
-- SEED DATA: PRISMDATA ITEMS
-- ─────────────────────────────────────────────
INSERT IGNORE INTO prismdata (item_no, itemcode, stylename, color, size, srp) VALUES
('100101', 'RACT95001T26', 'Classic Pique Polo', 'Black', 'M', 1250.00),
('100102', 'RACT95002T26', 'Premium Leather Sneakers', 'Brown', '42', 2499.00),
('100103', 'RACT95003T26', 'Executive Tailored Blazer', 'Navy Blue', 'L', 3850.00),
('100104', 'RACT95004T26', 'Slim Fit Stretch Chinos', 'Khaki', '32', 1999.00),
('100105', 'RACT95005T26', 'Graphic Cotton T-Shirt', 'White', 'S', 850.00),
('100106', 'RACT95006T26', 'Signature Denim Jacket', 'Indigo', 'M', 2990.00),
('100107', 'RACT95007T26', 'Breathable Running Shoes', 'Gray', '41', 2199.00),
('100108', 'RACT95008T26', 'Urban Canvas Backpack', 'Olive', 'One Size', 1650.00),
('475552', 'RACT95002T26', 'RL Shoe Bag (Promo Gift)', 'Black/Red', 'Standard', 0.00);

-- ─────────────────────────────────────────────
-- SEED DATA: BOUTIQUE SALES
-- ─────────────────────────────────────────────
INSERT IGNORE INTO boutique (date, store_code, store_name, qty_sold, amount) VALUES
(CURDATE(), 'BTQ-01', 'Boutique Ayala Center', 5, 12450.00),
(DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'BTQ-02', 'Boutique Power Plant Mall', 8, 21300.00),
(DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'BTQ-01', 'Boutique Ayala Center', 6, 15800.00);

-- ─────────────────────────────────────────────
-- SEED DATA: SAMPLE SALES TRANSACTIONS
-- ─────────────────────────────────────────────
INSERT IGNORE INTO sales (username, store_code, item_no, base_price, discount, amount_sold, quantity, line_total, created_at, system_timestamp) VALUES
('admin', 'ADMIN-001', '100101', 1250.00, 0.00, 1250.00, 1, 1250.00, NOW(), NOW()),
('admin', 'ADMIN-001', '100102', 2499.00, 0.00, 2499.00, 1, 2499.00, NOW(), NOW()),
('admin', 'ADMIN-001', '475552', 0.00, 100.00, 0.00, 1, 0.00, NOW(), NOW()),
('admin', 'ADMIN-001', '100103', 3850.00, 10.00, 3465.00, 1, 3465.00, NOW(), NOW()),
('admin', 'ADMIN-001', '475552', 0.00, 100.00, 0.00, 1, 0.00, NOW(), NOW()),
('admin', '11A', '100105', 850.00, 0.00, 850.00, 2, 1700.00, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('admin', '11A', '100106', 2990.00, 0.00, 2990.00, 1, 2990.00, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('admin', '11A', '475552', 0.00, 100.00, 0.00, 1, 0.00, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
('admin', '164', '100104', 1999.00, 0.00, 1999.00, 1, 1999.00, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('admin', '164', '475552', 0.00, 100.00, 0.00, 1, 0.00, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
('admin', '11K', '100108', 1650.00, 0.00, 1650.00, 1, 1650.00, DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY));
