# ⚡ Quick Start Guide (5 Minutes)

## 🎯 The Fastest Way to Get It Running

### Step 1: Install XAMPP
- Download from: https://www.apachefriends.org
- Install it (just click Next, Next, Next)

### Step 2: Start XAMPP
- Open XAMPP Control Panel
- Click "Start" for **Apache**
- Click "Start" for **MySQL**

### Step 3: Copy Files
- Copy your `dental_clinic` folder to: `C:\xampp\htdocs\`

### Step 4: Create Database
1. Open browser: `http://localhost/phpmyadmin`
2. Click "New" → Type: `dental_clinic_db` → Click "Create"
3. Click "Import" → Choose `database/schema.sql` → Click "Go"

### Step 5: Test It!
- Registration: `http://localhost/dental_clinic/stitch_dental_clinic_homepage/registration_page/register.php`
- Login: `http://localhost/dental_clinic/stitch_dental_clinic_homepage/login_page/login.php`

**That's it!** 🎉

---

## 📝 Test Registration

Fill out the form:
- Name: John Doe
- DOB: 01/15/1990
- Email: test@example.com
- Password: password123

Click "Create Account" → Should redirect to login page ✅

---

## 🔑 Test Login

- Email: test@example.com
- Password: password123

Click "Sign in" → Should see welcome page ✅

---

## ❌ Common Issues

**"Can't connect to database"**
→ Make sure MySQL is running in XAMPP

**"404 Not Found"**
→ Make sure Apache is running in XAMPP
→ Check file path is correct

**"Email already exists"**
→ Use a different email address

---

For detailed instructions, see: **SETUP_GUIDE_FOR_BEGINNERS.md**

