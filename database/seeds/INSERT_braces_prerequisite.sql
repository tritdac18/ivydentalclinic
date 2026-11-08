-- ============================================
-- Insert Prerequisite for Braces Service
-- ============================================
-- This sets up the prerequisite relationship:
-- Braces Attachment requires Consultation first

-- Method 1: Using service names (recommended if service names are consistent)
INSERT IGNORE INTO `tbl_service_prerequisites` (`service_id`, `required_service_id`, `is_mandatory`)
SELECT 
    braces.service_id,
    consultation.service_id,
    1
FROM `tbl_service_catalog` braces
CROSS JOIN `tbl_service_catalog` consultation
WHERE braces.service_name LIKE '%Brace%' 
  AND consultation.service_name = 'Consultation'
LIMIT 1;

-- Method 2: Using service_id directly (if you know the IDs)
-- Uncomment and adjust the IDs if needed:
-- INSERT IGNORE INTO `tbl_service_prerequisites` (`service_id`, `required_service_id`, `is_mandatory`)
-- VALUES 
--     ((SELECT service_id FROM tbl_service_catalog WHERE service_name LIKE '%Brace%' LIMIT 1),
--      (SELECT service_id FROM tbl_service_catalog WHERE service_name = 'Consultation' LIMIT 1),
--      1);

-- ============================================
-- Verify the prerequisite relationship
-- ============================================
SELECT 
    sp.prerequisite_id,
    s1.service_name AS 'Service',
    s2.service_name AS 'Required Prerequisite',
    sp.is_mandatory AS 'Mandatory'
FROM `tbl_service_prerequisites` sp
INNER JOIN `tbl_service_catalog` s1 ON sp.service_id = s1.service_id
INNER JOIN `tbl_service_catalog` s2 ON sp.required_service_id = s2.service_id
WHERE s1.service_name LIKE '%Brace%';

