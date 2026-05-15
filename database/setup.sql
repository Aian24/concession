-- ============================================================
-- Concession System Database Setup
-- Run this once in phpMyAdmin or via MySQL CLI
-- ============================================================

CREATE DATABASE IF NOT EXISTS concession_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE concession_db;

-- ─────────────────────────────────────────────
-- USERS TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NOT NULL UNIQUE,
    password    VARCHAR(255)    NOT NULL,          -- bcrypt hashed
    store_code  VARCHAR(50)     NOT NULL,
    role        ENUM('admin','user') DEFAULT 'user',
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- SALES TABLE
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sales (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100)    NOT NULL,
    store_code  VARCHAR(50)     NOT NULL,
    item_no     VARCHAR(150)    NOT NULL,
    amount_sold DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    quantity    INT             NOT NULL DEFAULT 0,
    line_total  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,  -- amount_sold * quantity
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username   (username),
    INDEX idx_store_code (store_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- DEFAULT ADMIN ACCOUNT
-- Password: admin123  (change after first login!)
-- ─────────────────────────────────────────────
INSERT IGNORE INTO users (username, password, store_code, role)
VALUES (
    'admin',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- admin123
    'ADMIN-001',
    'admin'
);
