# Dentist File Upload Module - Setup Instructions

## Overview
This module allows dentists to upload files to patient records. It includes:
- File upload interface
- File viewing interface
- Secure file download handler
- Database schema updates

## Quick Start for InfinityFree Users

**Step 1:** Run the SQL migration (see Step 1 below)  
**Step 2:** Create folders in File Manager: `uploads/patient_documents/`  
**Step 3:** Upload `.htaccess` files to both folders  
**Step 4:** Test by uploading a file as a dentist

**That's it!** The system will work automatically after these steps.

## Setup Steps

### 1. Update Database Schema
Run the SQL migration to expand document types:

```sql
-- Run this SQL file:
update_patient_documents_schema.sql
```

Or execute the SQL directly:
```sql
ALTER TABLE `tbl_patient_documents` 
MODIFY COLUMN `document_type` ENUM(
    'Parent Consent',
    'X-Ray',
    'Medical Record',
    'Consent Form',
    'Treatment Plan',
    'Prescription',
    'Lab Result',
    'Insurance Document',
    'Other'
) NOT NULL;
```

### 2. Create Upload Directory (InfinityFree)

**Option A: Using InfinityFree File Manager (Recommended)**

1. **Log into InfinityFree Control Panel (hPanel)**
   - Go to https://infinityfree.net/
   - Log in to your account
   - Click on your website/domain

2. **Open File Manager**
   - In the hPanel, find and click **"File Manager"** (usually under "Files" section)
   - Navigate to your website's root directory (usually `htdocs` or `public_html`)

3. **Create the directories:**
   - Click **"New Folder"** button (or right-click → New Folder)
   - Name it: `uploads`
   - Click inside the `uploads` folder
   - Click **"New Folder"** again
   - Name it: `patient_documents`
   
   Your structure should be:
   ```
   your-website-root/
   ├── uploads/
   │   └── patient_documents/
   ```

4. **Set Permissions (if needed):**
   - Right-click on `uploads` folder → **"Change Permissions"**
   - Set to: **755** (or check: Owner: Read/Write/Execute, Group: Read/Execute, Public: Read/Execute)
   - Click **"Change"**
   - Repeat for `patient_documents` folder

**Option B: Let PHP Create It Automatically**
- The directory will be created automatically when you upload the first file
- However, you still need to upload the `.htaccess` files manually (see below)

**Option C: Using FTP (Alternative)**
1. Connect to InfinityFree via FTP (FileZilla, WinSCP, etc.)
2. Navigate to your website root
3. Create `uploads` folder
4. Inside `uploads`, create `patient_documents` folder
5. Set permissions to 755 for both folders

### 3. Upload Security Files (.htaccess)

**Important:** You need to upload the `.htaccess` files to protect uploaded files:

1. **In File Manager:**
   - Navigate to `uploads/` folder
   - Upload the `.htaccess` file (from `uploads/.htaccess` in your project)
   - Navigate to `uploads/patient_documents/` folder
   - Upload the `.htaccess` file (from `uploads/patient_documents/.htaccess` in your project)

2. **Or create them manually in File Manager:**
   - In `uploads/` folder, click **"New File"**
   - Name it: `.htaccess` (with the dot at the beginning)
   - Open it and paste:
     ```
     Order Deny,Allow
     Deny from all
     ```
   - Save
   - Repeat for `uploads/patient_documents/.htaccess`

### 4. Security Notes
- Files are stored in `uploads/patient_documents/` directory
- Direct access to files is blocked via `.htaccess`
- Files must be accessed through `download_patient_file.php` for security
- Only dentists assigned to a patient can upload/view files for that patient
- File size limit: 10MB
- Allowed file types: PDF, DOC, DOCX, JPG, JPEG, PNG, GIF, TXT, XLS, XLSX

## Features

### Upload Files (`dentist_upload_files.php`)
- Select patient from dropdown (only shows patients assigned to the dentist)
- Select document type
- Upload file with optional notes
- File validation (type, size)
- Secure file storage

### View Files (`dentist_view_files.php`)
- Select patient to view their files
- Display all uploaded files with metadata
- Download files securely
- View upload date and uploader information

### Download Handler (`download_patient_file.php`)
- Secure file download with access control
- Prevents directory traversal attacks
- Verifies user permissions
- Serves files with proper MIME types

## Navigation
The module is accessible from:
- Dentist sidebar menu: "Upload Files"
- Patient list page: "View Files" button for each patient

## Testing
1. Log in as a dentist
2. Navigate to "Upload Files" from the sidebar
3. Select a patient and upload a test file
4. Go to "View Files" or click "View Files" from patient list
5. Verify the file appears and can be downloaded

## Troubleshooting

### Files not uploading (InfinityFree Specific)
- **Check directory exists:** Verify `uploads/patient_documents/` folder exists in your website root
- **Check permissions:** Folders should be 755 (readable, writable by owner)
- **Check PHP limits:** InfinityFree may have upload size limits (usually 2-10MB)
  - If files are too large, you'll see an error message
  - Consider compressing images before upload
- **Check error logs:** In hPanel → Error Logs, check for PHP errors
- **Test with small file first:** Try uploading a small text file (under 1MB) to verify the system works
- **Check disk space:** InfinityFree has storage limits - ensure you have space available

### Files not downloading
- Verify `.htaccess` files are in place
- Check file paths in database match actual file locations
- Verify user has permission to access the file

### Database errors
- Ensure the schema update was applied successfully
- Check that `tbl_patient_documents` table exists
- Verify foreign key constraints are working

