-- ============================================================
-- Add 'itemcode' column to prismdata table
-- Position: AFTER item_no (between item_no and stylename)
-- Run this on your LIVE hosting database
-- ============================================================

ALTER TABLE prismdata
ADD COLUMN itemcode VARCHAR(255) NULL AFTER item_no;
