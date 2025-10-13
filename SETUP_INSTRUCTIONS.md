# Koren Course LMS Backend - Setup Instructions

This is a PHP-based Learning Management System (LMS) backend with user authentication (signup/signin).

## Features

- ✅ User Registration (Signup) with validation
- ✅ User Login (Signin) with secure password verification
- ✅ Session management
- ✅ Automatic database and table creation
- ✅ Secure password hashing
- ✅ Form validation (NIC, Phone, Email, Password)
- ✅ Flash messages for user feedback
- ✅ Responsive UI design
- ✅ Home page dashboard after login
- ✅ Logout functionality

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher (or MariaDB)
- Apache/Nginx web server (or XAMPP/WAMP/MAMP)
- Web browser

## Installation Steps

### 1. Install XAMPP (if not already installed)

Download and install XAMPP from: https://www.apachefriends.org/

### 2. Setup Project Files

1. Copy all project files to your web server directory:
   - For XAMPP on Windows: `C:\xampp\htdocs\koren_lms\`
   - For XAMPP on Mac: `/Applications/XAMPP/htdocs/koren_lms/`
   - For XAMPP on Linux: `/opt/lampp/htdocs/koren_lms/`

### 3. Configure Database

1. Open `config/database.php`
2. Update the database credentials if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', ''); // Default for XAMPP
   define('DB_NAME', 'koren_lms');
   ```

### 4. Start Web Server

1. Start XAMPP Control Panel
2. Start Apache and MySQL services

### 5. Initialize Database

The database and tables will be created automatically when you first access the signup page.

Alternatively, you can manually create the database:

```sql
CREATE DATABASE koren_lms;
USE koren_lms;

CREATE TABLE users (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    nic_number VARCHAR(20) NOT NULL UNIQUE,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_email (email),
    INDEX idx_nic (nic_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. Access the Application

Open your web browser and navigate to:
- Signup Page: `http://localhost/koren_lms/signup.php`
- Signin Page: `http://localhost/koren_lms/signin.php`

## File Structure

```
koren_lms/
├── config/
│   └── database.php          # Database configuration
├── includes/
│   └── functions.php         # Helper functions
├── signup.php                # User registration page
├── signin.php                # User login page
├── home.php                  # Dashboard/home page (after login)
├── logout.php                # Logout handler
├── README.md
└── SETUP_INSTRUCTIONS.md
```

## Usage

### Creating an Account (Signup)

1. Navigate to `http://localhost/koren_lms/signup.php`
2. Fill in the required fields:
   - First Name
   - Last Name
   - NIC Number (Sri Lankan format: 9 digits + V or 12 digits)
   - Phone Number (10-15 digits)
   - Email Address
   - Password (minimum 8 characters with letters and numbers)
   - Confirm Password
3. Click "Create Account"
4. You'll be redirected to the signin page

### Signing In

1. Navigate to `http://localhost/koren_lms/signin.php`
2. Enter your email and password
3. Click "Sign In"
4. You'll be redirected to the home page

### Logging Out

Click the "Logout" button in the navigation bar

## Validation Rules

- **First Name & Last Name**: Required
- **NIC Number**: Must be in Sri Lankan format (9 digits + V or 12 digits)
- **Phone Number**: 10-15 digits
- **Email**: Valid email format
- **Password**: Minimum 8 characters, must contain letters and numbers

## Security Features

- ✅ Password hashing using PHP's `password_hash()` (bcrypt)
- ✅ Prepared statements to prevent SQL injection
- ✅ Input sanitization
- ✅ Session security configurations
- ✅ CSRF protection ready (can be enhanced)
- ✅ XSS prevention through `htmlspecialchars()`

## Customization

### Change Database Credentials

Edit `config/database.php`:
```php
define('DB_HOST', 'your_host');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');
```

### Modify Validation Rules

Edit the validation functions in `includes/functions.php`

### Change Design/Styling

Modify the `<style>` section in each PHP file

## Troubleshooting

### Database Connection Error

- Ensure MySQL is running in XAMPP
- Verify database credentials in `config/database.php`
- Check if the database exists

### Session Issues

- Ensure PHP session is enabled
- Check file permissions for session storage
- Clear browser cookies and cache

### Page Not Found (404)

- Verify files are in the correct directory
- Check your web server is running
- Ensure correct URL path

## Next Steps

You can extend this application by adding:
- Email verification
- Password reset functionality
- User profile management
- Admin panel
- Course management
- Assignment submission
- File uploads
- Role-based access control

## Support

For issues or questions, please refer to the code comments or contact the development team.

## License

This project is for educational purposes.
