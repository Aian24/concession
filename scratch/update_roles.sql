ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user', 'admin_view', 'store_admin') DEFAULT 'user';
