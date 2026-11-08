-- Add PWD ID and Senior Citizen ID columns to tbl_patients
-- These are just ID numbers, not document files
ALTER TABLE `tbl_patients`
ADD COLUMN `pwd_id_number` VARCHAR(50) DEFAULT NULL COMMENT 'PWD ID number for discount eligibility' AFTER `gender`,
ADD COLUMN `senior_citizen_id_number` VARCHAR(50) DEFAULT NULL COMMENT 'Senior Citizen ID number for discount eligibility' AFTER `pwd_id_number`;

