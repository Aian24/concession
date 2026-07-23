-- ============================================================
-- Page Navigation Logs Table Creation
-- Run this to manually create the page_navigation_logs table
-- ============================================================

USE concession_db;

-- ─────────────────────────────────────────────
-- PAGE NAVIGATION LOGS TABLE
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Table successfully created
-- This table will track user page navigation with automatic
-- cleanup of records older than 7 days
-- ============================================================
