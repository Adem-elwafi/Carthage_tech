# 🛡️ Carthage Tech Authentication System

Complete authentication system for the Carthage Tech e-commerce platform with user registration, login, logout, and session management.

## 📁 Project Structure

```
ChTechbackend/
├── backend/
│   ├── config/
│   │   ├── config.php          # App configuration
│   │   └── database.php        # Database connection
│   ├── utils/
│   │   └── Response.php        # JSON response helper
│   ├── middleware/
│   │   └── auth.php            # Authentication middleware
│   └── api/
│       └── auth/
│           ├── register.php    # User registration endpoint
│           ├── login.php       # User login endpoint
│           ├── logout.php      # User logout endpoint
│           └── me.php          # Get current user endpoint
├── database/
│   └── setup_users.sql         # Database setup script
└── test-auth.html              # API testing interface
```

## 🚀 Setup Instructions

### 1. Database Setup

1. Open **phpMyAdmin** (usually at `http://localhost/phpmyadmin`)
2. Click on **SQL** tab
3. Copy and paste the contents of `database/setup_users.sql`
4. Click **Go** to execute

This will:
- Create the `carthage_tech_bd` database
- Create the `users` table
- Insert 2 test users (admin and customer)

### 2. Configuration

Check `backend/config/config.php` and update if needed:
```php
define('BASE_URL', 'http://localhost/ChTechbackend');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'carthage_tech_bd');
define('DB_USER', 'root');
define('DB_PASS', ''); // Add password if needed
```

### 3. Start WAMP

1. Start WAMP server
2. Ensure Apache and MySQL are running (green icon)

## 🧪 Testing the API

### Option 1: Use the HTML Tester (Easiest)

1. Open your browser
2. Navigate to: `http://localhost/ChTechbackend/test-auth.html`
3. Test each endpoint using the forms provided

### Option 2: Use Postman/Thunder Client

#### 1. Register New User
```
POST http://localhost/ChTechbackend/backend/api/auth/register.php
Content-Type: application/json

{
    "email": "newuser@test.com",
    "password": "password123",
    "first_name": "John",
    "last_name": "Doe",
    "phone": "+216 12 345 678"
}
```

#### 2. Login
```
POST http://localhost/ChTechbackend/backend/api/auth/login.php
Content-Type: application/json

{
    "email": "newuser@test.com",
    "password": "password123"
}
```

#### 3. Get Current User (requires login)
```
GET http://localhost/ChTechbackend/backend/api/auth/me.php
```

#### 4. Logout
```
POST http://localhost/ChTechbackend/backend/api/auth/logout.php
```

### Option 3: Use cURL (Command Line)

```bash
# Register
curl -X POST http://localhost/ChTechbackend/backend/api/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password123","first_name":"Test","last_name":"User","phone":"123456789"}'

# Login
curl -X POST http://localhost/ChTechbackend/backend/api/auth/login.php \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"email":"test@example.com","password":"password123"}'

# Get current user (using cookies from login)
curl -X GET http://localhost/ChTechbackend/backend/api/auth/me.php \
  -b cookies.txt

# Logout
curl -X POST http://localhost/ChTechbackend/backend/api/auth/logout.php \
  -b cookies.txt
```

## 📋 Test Users

The SQL script creates these test users:

### Admin User
- **Email:** admin@carthagetech.com
- **Password:** admin123
- **Role:** admin

### Customer User
- **Email:** customer@test.com
- **Password:** password123
- **Role:** customer

## 🔐 Security Features

✅ **Password Hashing:** Uses bcrypt algorithm with `password_hash()`  
✅ **SQL Injection Prevention:** Prepared statements with PDO  
✅ **Session Security:** HTTP-only cookies, session regeneration  
✅ **Input Validation:** Email format, password length, required fields  
✅ **Error Handling:** Comprehensive try-catch blocks  
✅ **CORS Support:** Configurable cross-origin requests  

## 📖 API Documentation

### Register Endpoint
- **URL:** `/backend/api/auth/register.php`
- **Method:** POST
- **Body:** `{ email, password, first_name, last_name, phone }`
- **Success:** 200 with user data
- **Errors:** 422 (validation), 500 (server error)

### Login Endpoint
- **URL:** `/backend/api/auth/login.php`
- **Method:** POST
- **Body:** `{ email, password }`
- **Success:** 200 with user data and session created
- **Errors:** 401 (invalid credentials), 400 (validation)

### Get Current User Endpoint
- **URL:** `/backend/api/auth/me.php`
- **Method:** GET
- **Auth Required:** Yes (session)
- **Success:** 200 with current user data
- **Errors:** 401 (not authenticated)

### Logout Endpoint
- **URL:** `/backend/api/auth/logout.php`
- **Method:** POST/GET
- **Success:** 200 with logout confirmation

## 🛠️ How to Use the Middleware

To protect any endpoint and require authentication:

```php
<?php
// At the top of your protected endpoint
require_once __DIR__ . '/../../middleware/auth.php';

// This will check authentication and return user data
$currentUser = requireAuth();

// Now you can use $currentUser
echo "User ID: " . $currentUser['id'];
echo "Email: " . $currentUser['email'];
```

For admin-only endpoints:

```php
<?php
require_once __DIR__ . '/../../middleware/auth.php';

// This will check if user is admin
$adminUser = requireRole('admin');

// Only admins can reach this point
```

## 🐛 Troubleshooting

### Problem: "Database connection failed"
- **Solution:** Check if MySQL is running in WAMP
- **Solution:** Verify database credentials in `config/config.php`
- **Solution:** Make sure `carthage_tech_bd` database exists

### Problem: "CORS error" in browser
- **Solution:** Make sure WAMP is configured to allow cross-origin requests
- **Solution:** Check that `Access-Control-Allow-Origin` header is set in API files

### Problem: Session not working
- **Solution:** Check if cookies are enabled in your browser
- **Solution:** Ensure you're using the same domain/port for all requests
- **Solution:** Check PHP session configuration in `php.ini`

### Problem: "Invalid credentials" after registration
- **Solution:** Make sure you're using the same email/password you registered with
- **Solution:** Check if user was actually inserted in database (phpMyAdmin)

## 📝 Next Steps

1. ✅ Authentication system is complete
2. Add password reset functionality
3. Add email verification
4. Add "remember me" feature
5. Implement rate limiting for login attempts
6. Add two-factor authentication (2FA)

## 💡 Tips

- Always use HTTPS in production (never HTTP)
- Change `APP_DEBUG` to `false` in production
- Use strong passwords in production
- Implement CSRF protection for production
- Add rate limiting to prevent brute force attacks
- Log failed login attempts

## 📞 Support

If you encounter any issues, check:
1. WAMP logs (Apache error log)
2. PHP error log
3. Browser console for JavaScript errors
4. Network tab in browser DevTools

---

**Created for:** Carthage Tech E-Commerce Platform  
**Version:** 1.0.0  
**Last Updated:** November 21, 2025
