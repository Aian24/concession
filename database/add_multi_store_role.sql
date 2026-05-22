-- ============================================================
-- Add Multi-Store Admin Role & Assignment Table
-- Run this once to add the new role and create the mapping table
-- ============================================================

-- 1. Update role ENUM to include 'multi_store_admin'
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user', 'admin_view', 'store_admin', 'multi_store_admin') DEFAULT 'user';

-- 2. Create user_store_assignments table for many-to-many mapping
CREATE TABLE IF NOT EXISTS user_store_assignments (
    id          INT             AUTO_INCREMENT PRIMARY KEY,
    user_id     INT             NOT NULL,
    store_code  VARCHAR(50)     NOT NULL,
    created_at  TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_assignment (user_id, store_code),
    INDEX idx_user_id (user_id),
    INDEX idx_store_code (store_code),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
