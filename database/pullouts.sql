-- ============================================================
-- Pullout Table
-- Import this file via phpMyAdmin or run in your MySQL console
-- ============================================================

CREATE TABLE IF NOT EXISTS `pullouts` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `store_code`   VARCHAR(50)  NOT NULL,
  `username`     VARCHAR(100) NOT NULL,
  `item_no`      VARCHAR(100) NOT NULL,
  `quantity`     INT(11)      NOT NULL DEFAULT 1,
  `image_path`   VARCHAR(255)          DEFAULT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_store_code` (`store_code`),
  KEY `idx_username`   (`username`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
