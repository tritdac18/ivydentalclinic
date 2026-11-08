# Upload Instructions

## Critical: You MUST upload the `config` folder!

The diagnostic shows that `config/db.php` is missing on your server. This is why your website won't work.

## Steps to Fix:

### 1. Upload the `config` folder
   - Using FTP/SFTP or your hosting file manager
   - Upload the entire `config` folder to your website root
   - Make sure `config/db.php` is inside the `config` folder

### 2. Verify the structure on your server:
```
your-website-root/
├── config/
│   └── db.php          ← THIS FILE MUST EXIST
├── index.php
├── homepage.php
├── login.php
├── register.php
└── ... (other files)
```

### 3. Set proper permissions (if needed):
   - `config` folder: 755 (readable, executable)
   - `config/db.php`: 644 (readable)

### 4. Test again:
   - Visit `error_check.php` again
   - Should now show: `config/db.php: ✓ EXISTS`

## Quick Check:
After uploading, the file path should be:
- `yourdomain.com/config/db.php` should be accessible
- Or: `yourdomain.com/ivydentalclinic/config/db.php` (if in subfolder)

## If you're using InfinityFree or similar:
1. Log into your hosting control panel
2. Go to File Manager
3. Navigate to your website root (usually `htdocs` or `public_html`)
4. Upload the `config` folder
5. Make sure `db.php` is inside the `config` folder

## After Upload:
Once `config/db.php` is uploaded, your website should work! Test by:
1. Visiting `homepage.php` - should show the homepage
2. Visiting `login.php` - should show login form
3. Visiting `register.php` - should show registration form

