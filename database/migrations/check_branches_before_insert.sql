-- Check available branches before inserting staff
-- Run this first to see what branch_id values you can use

SELECT branch_id, branch_name, address 
FROM tbl_branches 
ORDER BY branch_id;

-- Use the branch_id from the results above in your INSERT statements
-- Example: If you see branch_id = 1, 2, 3, use one of those values for primary_branch_id

