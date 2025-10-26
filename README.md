# Koren Course LMS Backend

A secure PHP-based RESTful API backend for Learning Management System (LMS) with user authentication.

## Features

- ✅ RESTful JSON API endpoints
- ✅ User Registration (Signup) with comprehensive validation
- ✅ User Login (Signin) with secure authentication
- ✅ Session management and user verification
- ✅ Secure password hashing (bcrypt)
- ✅ SQL injection prevention (prepared statements)
- ✅ Input validation and sanitization
- ✅ Automatic database setup

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/signup.php` | POST | Register new user account |
| `/signin.php` | POST | User authentication/login |
| `/check_session.php` | GET | Verify active session and get user info |
| `/logout.php` | GET | Destroy session and logout |

## Quick Start

### 1. Requirements
- PHP 7.4+
- MySQL 5.7+ / MariaDB
- Apache/Nginx (or XAMPP/WAMP/MAMP)

### 2. Installation
```bash
# Clone repository
git clone https://github.com/RuchiraRTG/Koren_Course_LMS_Backend.git

# Copy to web server directory
# For XAMPP on Windows: C:\xampp\htdocs\koren_lms\
```

### 3. Configuration
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'koren_lms');
```

### 4. Start Server
- Start Apache and MySQL in XAMPP
- Database will be created automatically on first request

### 5. Test API
```bash
# Test signup endpoint
curl -X POST http://localhost/koren_lms/signup.php \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe","nic_number":"123456789V","phone_number":"0771234567","email":"john@example.com","password":"Test1234","confirm_password":"Test1234"}'
```

## Project Structure

```
koren_lms/
├── config/
│   └── database.php          # Database configuration
├── includes/
│   └── functions.php         # Helper functions & validation
├── signup.php                # User registration API
├── signin.php                # User login API
├── check_session.php         # Session verification API
├── logout.php                # Logout handler
├── home.php                  # Protected page example
├── API_DOCUMENTATION.md      # Complete API documentation
└── SETUP_INSTRUCTIONS.md     # Detailed setup guide
```

## Usage Example (JavaScript)

```javascript
// Signup
const response = await fetch('http://localhost/koren_lms/signup.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    first_name: 'John',
    last_name: 'Doe',
    nic_number: '123456789V',
    phone_number: '0771234567',
    email: 'john@example.com',
    password: 'Test1234',
    confirm_password: 'Test1234'
  })
});
const data = await response.json();
console.log(data);

// Signin
const loginResponse = await fetch('http://localhost/koren_lms/signin.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  credentials: 'include', // Important for session cookies
  body: JSON.stringify({
    email: 'john@example.com',
    password: 'Test1234'
  })
});
const loginData = await loginResponse.json();
console.log(loginData);
```

## Validation Rules

- **NIC**: Sri Lankan format (9 digits + V or 12 digits)
- **Phone**: 10-15 digits
- **Email**: Valid email format, unique
- **Password**: Min 8 characters with letters and numbers

## Security Features

- Bcrypt password hashing
- Prepared SQL statements
- XSS prevention
- Input sanitization
- Secure session handling
- CSRF protection ready

## Documentation

- 📖 [API Documentation](API_DOCUMENTATION.md) - Complete API reference with examples
- 📝 [Setup Instructions](SETUP_INSTRUCTIONS.md) - Detailed installation guide

## License

Educational purposes