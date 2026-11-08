-- ============================================
-- Insert Braces Service into Service Catalog
-- ============================================

-- Insert Braces/Orthodontic service
INSERT IGNORE INTO `tbl_service_catalog` (`service_name`, `base_price`, `default_duration_minutes`, `is_active`) 
VALUES ('Braces Attachment', 15000.00, 120, 1);

-- Note: If you want to use a different service_id, you can specify it:
-- INSERT IGNORE INTO `tbl_service_catalog` (`service_id`, `service_name`, `base_price`, `default_duration_minutes`, `is_active`) 
-- VALUES (7, 'Braces Attachment', 15000.00, 120, 1);

-- ============================================
-- Verify the insertion
-- ============================================
SELECT * FROM `tbl_service_catalog` WHERE `service_name` LIKE '%Brace%';

