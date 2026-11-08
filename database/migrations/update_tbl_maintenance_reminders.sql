-- Update tbl_maintenance_reminders table to support new reminder types
-- This script updates the ENUM to include the new reminder schedule

-- First, check if table exists and update the ENUM
ALTER TABLE `tbl_maintenance_reminders` 
MODIFY COLUMN `reminder_type` ENUM(
    'two_weeks_72h', 
    'two_weeks_48h', 
    'two_weeks_24h', 
    'one_week', 
    'overdue'
) NOT NULL COMMENT 'Type of reminder: two_weeks_72h (17 days before), two_weeks_48h (16 days before), two_weeks_24h (15 days before), one_week (7 days before), overdue (after due date)';

