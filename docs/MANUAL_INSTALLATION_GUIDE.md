# Manual Installation Guide - Google API PHP Client

## Option 1: Using Composer (Recommended - Easiest)

If you have Composer installed, this is the simplest method:

```bash
cd C:\Users\Acer\Downloads\ivydentalclinic
composer require google/apiclient
```

That's it! Composer will automatically:
- Download the library
- Create the `vendor/` folder
- Set up autoloading
- Install all dependencies

---

## Option 2: Manual Installation (Without Composer)

### Step 1: Download the Library

1. Go to: https://github.com/googleapis/google-api-php-client/releases
2. Download the latest release ZIP file (e.g., `google-api-php-client-v2.x.x.zip`)
3. Extract the ZIP file to a temporary location

### Step 2: Understand the Structure

After extracting, you'll see a folder structure like:
```
google-api-php-client-v2.x.x/
├── src/
│   └── Google/
│       ├── Client.php
│       ├── Service/
│       └── ...
├── vendor/
│   ├── google/
│   │   └── auth/
│   └── ...
└── composer.json
```

### Step 3: Copy Files to Your Project

**IMPORTANT**: You need to copy the ENTIRE extracted folder structure, not just parts of it.

#### Method A: Copy Everything to `vendor/` (Recommended)

1. In your project root (`C:\Users\Acer\Downloads\ivydentalclinic`), create a `vendor` folder if it doesn't exist
2. Copy the ENTIRE extracted folder into `vendor/`:
   ```
   ivydentalclinic/
   └── vendor/
       └── google/
           └── apiclient/          ← Copy the entire extracted folder here
               ├── src/
               ├── vendor/
               └── composer.json
   ```

   So the final structure should be:
   ```
   ivydentalclinic/
   └── vendor/
       └── google/
           └── apiclient/
               ├── src/
               │   └── Google/
               │       ├── Client.php
               │       └── Service/
               ├── vendor/
               │   └── (dependencies)
               └── composer.json
   ```

#### Method B: Flatten Structure (Alternative)

If you want a flatter structure:

1. Copy `src/Google/` to `vendor/Google/`:
   ```
   ivydentalclinic/
   └── vendor/
       └── Google/
           ├── Client.php
           └── Service/
   ```

2. Copy dependencies from `vendor/` in the extracted folder to your `vendor/` folder

### Step 4: Set Up Autoloading

Since you're not using Composer, you need to manually include the files. Update your `services/email_service.php`:

**Replace this section:**
```php
// Load Google API Client (if using Composer)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Manual path if not using Composer
    // You'll need to download and include the Google API Client library
    die('Google API Client library not found. Please install via Composer: composer require google/apiclient');
}
```

**With this:**
```php
// Load Google API Client (Manual Installation)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../vendor/google/apiclient/src/Google/Client.php')) {

    require_once __DIR__ . '/../vendor/google/apiclient/src/Google/Client.php';
    require_once __DIR__ . '/../vendor/google/apiclient/src/Google/Service/Gmail.php';
    require_once __DIR__ . '/../vendor/google/apiclient/src/Google/Service/Gmail/Message.php';
    
} else {
    die('Google API Client library not found. Please install via Composer or manually copy the library to vendor/google/apiclient/');
}
```

### Step 5: Handle Dependencies (The Tricky Part)

The Google API Client has dependencies that also need to be loaded. The extracted ZIP includes these in its `vendor/` folder. You have two options:

#### Option A: Copy All Dependencies
Copy the entire `vendor/` folder from the extracted ZIP to your project's `vendor/` folder, merging with existing files.

#### Option B: Use a Simple Autoloader
Create a simple autoloader that loads classes on demand. This is more complex but cleaner.

---

## ⚠️ Why Composer is Recommended

Manual installation is complex because:
1. The library has multiple dependencies (Guzzle, Monolog, etc.)
2. You need to load all dependencies in the correct order
3. Namespace autoloading needs to be set up manually
4. Updates are harder to manage

---

## ✅ Recommended: Install Composer

If you don't have Composer, install it:

1. **Download Composer**: https://getcomposer.org/download/
2. **Run the installer** (Windows: `Composer-Setup.exe`)
3. **Open Command Prompt** in your project folder
4. **Run**: `composer require google/apiclient`

This takes 2 minutes and handles everything automatically!

---

## Quick Manual Setup (Simplified)

If you MUST do manual installation, here's the simplest approach:

1. **Download ZIP** from GitHub releases
2. **Extract** to a temp folder
3. **Copy the entire extracted folder** to:
   ```
   C:\Users\Acer\Downloads\ivydentalclinic\vendor\google\apiclient\
   ```
4. **Update `services/email_service.php`** to use the manual path (see Step 4 above)
5. **Copy dependencies**: Copy everything from `extracted/vendor/` to `ivydentalclinic/vendor/`

---

## Verification

After installation, test if it works:

1. Create a test file `test_gmail.php`:
```php
<?php
require_once 'vendor/google/apiclient/src/Google/Client.php';

$client = new Google\Client();
echo "✅ Google API Client loaded successfully!";
```

2. Run it: `php test_gmail.php`

If you see the success message, installation worked!

---

## Still Having Issues?

**Best Solution**: Install Composer and use `composer require google/apiclient`

**Alternative**: Use the pre-built PHAR file (if available) or contact me for a custom autoloader setup.

