CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) UNIQUE NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `permissions` JSON NULL,
  `can_submit` TINYINT(1) DEFAULT 0,
  `can_edit` TINYINT(1) DEFAULT 0,
  `can_delete` TINYINT(1) DEFAULT 0,
  `is_admin` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO `roles` (`role_name`, `display_name`, `permissions`, `can_submit`, `can_edit`, `can_delete`, `is_admin`) VALUES
('admin', 'Full Administrator', '["dashboard", "monitoring", "history", "create_sale", "sale", "create_return", "return", "create_receiving", "receiving", "create_pullout", "pullout", "create_ros_supplies", "ros_supplies", "non_submission", "admin", "roles", "stores", "prism_data", "boutique_data", "recent_activity"]', 0, 1, 1, 1),
('admin_view', 'View Only Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "pullout", "ros_supplies", "non_submission"]', 0, 1, 1, 1),
('store_admin', 'Store Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "pullout", "ros_supplies", "non_submission"]', 0, 1, 1, 1),
('multi_store_admin', 'Multi-Store Admin', '["dashboard", "monitoring", "sale", "return", "receiving", "pullout", "ros_supplies", "non_submission"]', 0, 1, 1, 1),
('user', 'Sales Agent (User)', '["create_sale", "create_return", "create_receiving", "create_pullout", "create_ros_supplies", "history"]', 1, 0, 0, 0);
