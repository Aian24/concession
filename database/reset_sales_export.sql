-- ============================================================
-- RESET EXPORTED STATUS FOR SALES
-- ============================================================

-- Option 1: Reset ALL sales
UPDATE sales SET is_exported = 0;

-- Option 2: Reset only for a specific date (comment out Option 1 if using this)
-- UPDATE sales SET is_exported = 0 WHERE DATE(created_at) = '2026-05-15';

-- Option 3: Reset only for a specific store
-- UPDATE sales SET is_exported = 0 WHERE store_code = 'STORE-001';
