-- ============================================================
-- RESET EXPORTED STATUS FOR SALES
-- ============================================================

-- Option 1: Reset export status for ALL sales records
UPDATE sales SET is_exported = 0;

-- Option 2: Reset export status only for a specific date (uncomment to use)
-- UPDATE sales SET is_exported = 0 WHERE DATE(created_at) = '2026-05-18';

-- Option 3: Reset export status only for a specific store code (uncomment to use)
-- UPDATE sales SET is_exported = 0 WHERE store_code = 'STORE-001';
