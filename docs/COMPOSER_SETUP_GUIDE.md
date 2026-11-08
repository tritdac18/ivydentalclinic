# Composer Setup Guide for Gmail API

## Prerequisites
- ✅ Composer installed on your local device
- ✅ Gmail API dependencies downloaded
- ✅ Access to your hosting website (via FTP/File Manager)

---

## Step 1: Local Setup (On Your Device)

### 1.1 Navigate to Your Project Folder
```bash
cd C:\Users\Acer\Downloads\ivydentalclinic
```

### 1.2 Initialize Composer (if not already done)
```bash
composer init
```
- Press Enter to accept defaults for most questions
- When asked for dependencies, type: `google/apiclient`
- When asked for dev dependencies, just press Enter

### 1.3 Install Google API Client via Composer
```bash
composer require google/apiclient
```

This will:
- Create/update `composer.json`
- Create/update `composer.lock`
- Download all dependencies to `vendor/` folder
- Create `vendor/autoload.php`

### 1.4 Verify Installation
Check that these files/folders exist:
- ✅ `composer.json`
- ✅ `composer.lock`
- ✅ `vendor/autoload.php`
- ✅ `vendor/google/apiclient/`

---

## Step 2: Prepare Files for Upload

### 2.1 Files to Upload to Hosting
You need to upload these to your hosting website:

**Required Files:**
1. `composer.json` - Dependency configuration
2. `composer.lock` - Locked dependency versions
3. **Entire `vendor/` folder** - All dependencies

**Optional (if you want to use Composer on hosting):**
- Upload `composer.phar` (Composer executable) if your hosting supports it

### 2.2 Files Structure on Hosting Should Be:
```
htdocs/
├── composer.json
├── composer.lock
├── vendor/
│   ├── autoload.php
│   ├── google/
│   │   └── apiclient/
│   └── [other dependencies]
├── config/
│   ├── credentials.json
│   ├── gmail_token.json
│   └── gmail_config.php
├── services/
│   └── email_service.php
└── [other project files]
```

---

## Step 3: Upload to Hosting Website

### Option A: Using FTP Client (FileZilla, WinSCP, etc.)

1. **Connect to your hosting via FTP**
   - Host: `ftp.infinityfree.com` (or your hosting's FTP server)
   - Username: Your FTP username
   - Password: Your FTP password
   - Port: 21

2. **Upload Files:**
   - Upload `composer.json` to root directory
   - Upload `composer.lock` to root directory
   - Upload entire `vendor/` folder (this may take a while, it's large)

### Option B: Using Hosting File Manager

1. **Log into your hosting control panel**
2. **Open File Manager**
3. **Upload `composer.json` and `composer.lock`** to root directory
4. **Upload `vendor/` folder:**
   - Create a zip file of the `vendor/` folder on your local device
   - Upload the zip file to hosting
   - Extract it in the root directory

### Option C: Using SSH (if available)

If your hosting supports SSH:

```bash
# On your local device, create a zip of vendor folder
# Then upload via SSH:

# Extract vendor folder
unzip vendor.zip

# Or if you have Composer on hosting:
composer install --no-dev
```

---

## Step 4: Verify on Hosting

### 4.1 Check Files Exist
Visit your hosting file manager and verify:
- ✅ `composer.json` exists
- ✅ `composer.lock` exists
- ✅ `vendor/autoload.php` exists
- ✅ `vendor/google/apiclient/` folder exists

### 4.2 Test the Setup
Visit: `https://ivydentalbckup.gt.tc/test_email.php`

If you see errors, check:
- File paths are correct
- `vendor/autoload.php` is accessible
- Permissions are correct (folders: 755, files: 644)

---

## Step 5: Clean Up (Optional)

### Remove Old Manual Installation
If you had a manual installation before, you can remove:
- `vendor/google/apiclient/` (old manual installation)
- Keep only the Composer-installed version

---

## Troubleshooting

### Issue: "vendor/autoload.php not found"
**Solution:** Make sure you uploaded the entire `vendor/` folder, not just parts of it.

### Issue: "Class not found" errors
**Solution:** 
1. Verify `vendor/autoload.php` exists
2. Check file permissions (should be readable: 644)
3. Try running `composer dump-autoload` on hosting (if Composer is available)

### Issue: Upload is too slow/large
**Solution:**
- The `vendor/` folder is large (~50-100MB)
- Use FTP in binary mode
- Or zip it, upload, then extract on hosting
- Or use SSH if available

### Issue: Memory limit errors
**Solution:**
- Some hosting providers have limits
- Contact support to increase PHP memory limit
- Or use Composer's `--no-dev` flag to reduce size

---

## Quick Checklist

- [ ] Composer installed locally
- [ ] Ran `composer require google/apiclient` locally
- [ ] Verified `vendor/autoload.php` exists locally
- [ ] Uploaded `composer.json` to hosting
- [ ] Uploaded `composer.lock` to hosting
- [ ] Uploaded entire `vendor/` folder to hosting
- [ ] Verified `vendor/autoload.php` exists on hosting
- [ ] Tested `test_email.php` on hosting
- [ ] Gmail API is authorized (credentials.json and gmail_token.json exist)

---

## Next Steps

Once everything is uploaded:
1. Test email sending: Visit `test_email.php`
2. If it works, the registration system will automatically use it
3. No code changes needed - `email_service.php` will detect Composer automatically

