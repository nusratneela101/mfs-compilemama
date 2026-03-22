# MFS Compilemama - বাংলাদেশের সকল MFS পোর্টাল

**MFS Compilemama** হলো একটি সম্পূর্ণ PHP + MySQL ওয়েব পোর্টাল যেখানে বিদেশে থাকা বাংলাদেশিরা যেকোনো দেশ থেকে বাংলাদেশের সকল MFS (Mobile Financial Services) ব্যবহার করতে পারবেন।

---

## 🌐 Supported MFS Providers

| # | Company | বাংলা নাম |
|---|---------|-----------|
| 1 | bKash | বিকাশ |
| 2 | Nagad | নগদ |
| 3 | Rocket (DBBL) | রকেট |
| 4 | Upay | উপায় |
| 5 | Tap | ট্যাপ |
| 6 | SureCash | শিউরক্যাশ |
| 7 | T-Cash | টি-ক্যাশ |
| 8 | CelFin | সেলফিন |
| 9 | OK Wallet | ওকে ওয়ালেট |
| 10 | UCash | ইউক্যাশ |
| 11 | Islamic Bank mCash | আইবিএম ক্যাশ |
| 12 | M Class (M Kash) | এম ক্লাস |
| 13 | MyCash | মাইক্যাশ |

---

## 💰 Subscription

- মাসিক সাবস্ক্রিপশন: **৳150/মাস**
- ৩০ দিনের অ্যাক্সেস
- Payment method: bKash / Nagad / Manual
- Admin দ্বারা activation

---

## 📁 File Structure

```
/
├── index.php           # Homepage
├── login.php           # Login
├── register.php        # Registration
├── verify-otp.php      # OTP verification
├── subscribe.php       # Subscription (৳150/month)
├── dashboard.php       # User dashboard
├── mfs-portal.php      # MFS service portal
├── transaction.php     # Transaction history
├── profile.php         # User profile
├── logout.php          # Logout
├── install.php         # One-time DB installer
├── .htaccess           # Clean URLs
├── config/
│   ├── database.php    # MySQL connection
│   ├── config.php      # Site config
│   └── location.php    # Bangladesh location spoof
├── includes/
│   ├── header.php      # Common header
│   ├── footer.php      # Common footer
│   ├── auth.php        # Authentication
│   ├── subscription.php# Subscription checker
│   └── functions.php   # Helper functions
├── admin/
│   ├── login.php       # Admin login
│   ├── index.php       # Admin dashboard
│   ├── users.php       # User management
│   ├── subscriptions.php # Subscription management
│   └── payments.php    # Payment management
├── api/
│   ├── send-otp.php    # Send OTP
│   ├── verify-otp.php  # Verify OTP
│   └── transaction.php # Transaction API
├── assets/
│   ├── css/style.css   # Stylesheet
│   └── js/app.js       # JavaScript
└── sql/
    └── database.sql    # Complete DB schema
```

---

## 🚀 Setup Instructions (ইনস্টলেশন)

### Requirements
- PHP 7.4+
- MySQL 5.7+
- Apache with mod_rewrite enabled

### Step 1: Database Setup

**Option A: Using install.php (Recommended)**
1. Upload all files to your hosting
2. Edit `config/database.php` with your DB credentials
3. Visit: `http://yourdomain.com/install.php?key=mfs-install-2024`
4. **Delete `install.php`** after successful installation

**Option B: Manual Import**
1. Create a MySQL database named `mfs_compilemama`
2. Import `sql/database.sql` via phpMyAdmin or mysql CLI:
   ```bash
   mysql -u root -p mfs_compilemama < sql/database.sql
   ```

### Step 2: Configuration

Edit `config/config.php`:
```php
define('BKASH_NUMBER', '01XXXXXXXXX');   // Your bKash number
define('NAGAD_NUMBER', '01XXXXXXXXX');   // Your Nagad number
define('SITE_URL', 'https://yourdomain.com');
```

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'mfs_compilemama');
```

### Step 3: Web Server

For Apache, ensure `.htaccess` is enabled:
```apache
# In httpd.conf or virtualhost:
AllowOverride All
```

### Step 4: Admin Panel

- URL: `http://yourdomain.com/admin/`
- Username: `admin`
- Password: `admin123`
- **Change the password immediately after first login!**

---

## 🔧 SMS Gateway Configuration

Edit `config/config.php` to configure SMS:

```php
define('SMS_GATEWAY', 'log');  // Options: log, bulksmsbd, smsq, twilio
```

### BulkSMSBD
```php
define('SMS_GATEWAY', 'bulksmsbd');
define('BULKSMS_API_KEY', 'your_api_key');
define('BULKSMS_SENDER_ID', 'MFSMama');
```

### Twilio
```php
define('SMS_GATEWAY', 'twilio');
define('TWILIO_SID', 'your_account_sid');
define('TWILIO_TOKEN', 'your_auth_token');
define('TWILIO_FROM', '+1XXXXXXXXXX');
```

---

## 🌍 Location Spoofing

`config/location.php` forces Bangladesh location:
- Timezone: `Asia/Dhaka (GMT+6)`
- Accept-Language: `bn-BD`
- Geolocation: Dhaka coordinates (23.8103, 90.4125)

---

## 🔒 Security Features

- bcrypt PIN hashing (cost=12)
- Prepared statements (SQL injection prevention)
- CSRF token protection on all forms
- XSS prevention (htmlspecialchars everywhere)
- Rate limiting on OTP (3 per hour per phone)
- Session security (regenerate_id on login)

---

## 📊 Admin Features

- **Dashboard**: Total users, active subscriptions, revenue, analytics
- **User Management**: Activate/deactivate users
- **Subscription Management**: Manual activate/deactivate/extend
- **Payment Management**: Approve/reject pending payments

---

## 💻 Free Hosting Deployment (InfinityFree / 000webhost)

1. Create free account at [InfinityFree](https://infinityfree.net) or [000webhost](https://www.000webhost.com)
2. Upload all files via File Manager or FTP
3. Create MySQL database from hosting control panel
4. Update `config/database.php` with provided credentials
5. Import `sql/database.sql` via phpMyAdmin
6. Visit `install.php?key=mfs-install-2024` OR import SQL manually
7. Delete `install.php`
8. Your site is live! 🎉

---

## ⚠️ Important Notes

1. **Change admin password** after first login
2. **Update payment numbers** (bKash/Nagad) in `config/config.php`
3. **Delete `install.php`** after database setup
4. For production, set `APP_DEBUG = false` in `config/config.php`
5. OTP is logged to DB by default (set SMS gateway for real SMS)

---

## 📞 MFS Services Available

For each MFS provider, users can:
- 💸 **Send Money** - Transfer to any number
- 🏧 **Cash Out** - Withdraw at agent point
- 📱 **Mobile Recharge** - Top up any operator
- 💳 **Payment** - Pay merchants
- 💰 **Balance Check** - Check account balance

---

*Built with ❤️ for Bangladeshis abroad | PHP + MySQL | No framework required*