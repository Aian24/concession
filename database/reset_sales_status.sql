-- ============================================================
-- RESET EXPORTED STATUS FOR SALES DATA
-- ============================================================

-- This script resets the `is_exported` status of sales records 
-- back to 0 (Unexported/Pending). 

-- Option 1: Reset export status for ALL sales records
UPDATE sales SET is_exported = 0;

-- Option 2: Reset export status only for a specific date (uncomment to use)
-- UPDATE sales SET is_exported = 0 WHERE DATE(created_at) = '2026-05-18';

-- Option 3: Reset export status only for a specific store code (uncomment to use)
-- UPDATE sales SET is_exported = 0 WHERE store_code = 'STORE-001';

-- Option 4: Reset export status for a specific date range (uncomment to use)
-- UPDATE sales SET is_exported = 0 WHERE DATE(created_at) BETWEEN '2026-05-01' AND '2026-05-31';
