-- ============================================================
-- Update Admin Password
-- New Password: R4styL0p3z
-- ============================================================

UPDATE users 
SET password = '$2y$10$YnKhaqwL0LxJEig5lDBMaOFvRtbIyKLW4KDEgydvQHp78INFE7BPK' 
WHERE username = 'admin';
