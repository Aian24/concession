-- This script will delete all records from the `sales` and `returns` tables
-- from May 1, 2026 up to the current date.

-- IMPORTANT: Please ensure you have a backup of your database before running DELETE operations.

DELETE FROM sales 
WHERE created_at >= '2026-05-01 00:00:00';

DELETE FROM returns 
WHERE created_at >= '2026-05-01 00:00:00';
