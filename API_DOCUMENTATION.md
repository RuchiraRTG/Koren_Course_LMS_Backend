# Koren LMS Backend API Documentation

This backend provides RESTful JSON APIs for user authentication.

## Base URL
```
http://localhost/koren_lms/
```

---

## Authentication APIs

### 1. User Registration (Signup)

**Endpoint:** `POST /signup.php`

**Description:** Register a new user account

**Request Headers:**
```
Content-Type: application/json
```

**Request Body (JSON):**
```json
{
  "first_name": "John",
  "last_name": "Doe",
  "nic_number": "123456789V",
  "phone_number": "0771234567",
  "email": "john.doe@example.com",
  "password": "MyPass123",
  "confirm_password": "MyPass123"
}
```

**Request Body (Form Data):**
You can also send data as `application/x-www-form-urlencoded` with the same field names.

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Account created successfully!",
  "errors": [],
  "data": {
    "user_id": 1,
    "email": "john.doe@example.com",
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

**Error Response (200 OK):**
```json
{
  "success": false,
  "message": "Please fix the validation errors",
  "errors": [
    "Email address already registered",
    "Password must be at least 8 characters and contain both letters and numbers"
  ],
  "data": null
}
```

**Validation Rules:**
- `first_name`: Required
- `last_name`: Required
- `nic_number`: Required, must be Sri Lankan format (9 digits + V or 12 digits)
- `phone_number`: Required, 10-15 digits
- `email`: Required, valid email format, must be unique
- `password`: Required, minimum 8 characters with letters and numbers
- `confirm_password`: Must match password

---

### 2. User Login (Signin)

**Endpoint:** `POST /signin.php`

**Description:** Authenticate user and create session

**Request Headers:**
```
Content-Type: application/json
```

**Request Body (JSON):**
```json
{
  "email": "john.doe@example.com",
  "password": "MyPass123"
}
```

**Request Body (Form Data):**
You can also send data as `application/x-www-form-urlencoded` with the same field names.

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful!",
  "errors": [],
  "data": {
    "user_id": 1,
    "email": "john.doe@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "full_name": "John Doe",
    "phone_number": "0771234567",
    "nic_number": "123456789V",
    "session_token": "abc123...",
    "redirect_url": "home.php"
  }
}
```

**Error Response (200 OK):**
```json
{
  "success": false,
  "message": "Invalid email or password",
  "errors": [
    "Invalid credentials"
  ],
  "data": null
}
```

**Account Deactivated Response (200 OK):**
```json
{
  "success": false,
  "message": "Your account has been deactivated. Please contact support.",
  "errors": [
    "Account inactive"
  ],
  "data": null
}
```

**Validation Rules:**
- `email`: Required, valid email format
- `password`: Required

**Session Management:**
Upon successful login, a PHP session is created with:
- `user_id`: User's database ID
- `user_email`: User's email
- `user_name`: User's full name
- `session_token`: Unique session token

---

### 3. User Logout

**Endpoint:** `GET /logout.php`

**Description:** Destroy user session and logout

**Response:**
Redirects to `signin.php`

---

### 4. Check Session (Get User Info)

**Endpoint:** `GET /check_session.php`

**Description:** Check if user is logged in and get user information

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "User is logged in",
  "data": {
    "user_id": 1,
    "email": "john.doe@example.com",
    "full_name": "John Doe",
    "session_token": "abc123..."
  }
}
```

**Not Logged In Response (200 OK):**
```json
{
  "success": false,
  "message": "User is not logged in",
  "data": null
}
```

---

## Frontend Integration Examples

### Using Fetch API (JavaScript)

**Signup:**
```javascript
async function signup(userData) {
  try {
    const response = await fetch('http://localhost/koren_lms/signup.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(userData)
    });
    
    const result = await response.json();
    
    if (result.success) {
      console.log('Signup successful:', result.data);
      // Redirect to signin page
      window.location.href = 'signin.html';
    } else {
      console.error('Signup failed:', result.errors);
      alert(result.message);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}

// Usage
signup({
  first_name: 'John',
  last_name: 'Doe',
  nic_number: '123456789V',
  phone_number: '0771234567',
  email: 'john.doe@example.com',
  password: 'MyPass123',
  confirm_password: 'MyPass123'
});
```

**Signin:**
```javascript
async function signin(email, password) {
  try {
    const response = await fetch('http://localhost/koren_lms/signin.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      credentials: 'include', // Important for session cookies
      body: JSON.stringify({ email, password })
    });
    
    const result = await response.json();
    
    if (result.success) {
      console.log('Login successful:', result.data);
      // Store user info in localStorage if needed
      localStorage.setItem('user', JSON.stringify(result.data));
      // Redirect to home page
      window.location.href = result.data.redirect_url;
    } else {
      console.error('Login failed:', result.errors);
      alert(result.message);
    }
  } catch (error) {
    console.error('Error:', error);
  }
}

// Usage
signin('john.doe@example.com', 'MyPass123');
```

---

### Using Axios (JavaScript)

**Signup:**
```javascript
import axios from 'axios';

const signup = async (userData) => {
  try {
    const response = await axios.post('http://localhost/koren_lms/signup.php', userData);
    
    if (response.data.success) {
      console.log('Signup successful:', response.data);
      // Redirect to signin
      window.location.href = 'signin.html';
    } else {
      alert(response.data.message);
    }
  } catch (error) {
    console.error('Error:', error);
  }
};
```

**Signin:**
```javascript
const signin = async (email, password) => {
  try {
    const response = await axios.post('http://localhost/koren_lms/signin.php', 
      { email, password },
      { withCredentials: true } // Important for session cookies
    );
    
    if (response.data.success) {
      console.log('Login successful:', response.data);
      localStorage.setItem('user', JSON.stringify(response.data.data));
      window.location.href = response.data.data.redirect_url;
    } else {
      alert(response.data.message);
    }
  } catch (error) {
    console.error('Error:', error);
  }
};
```

---

### Using jQuery

**Signup:**
```javascript
$.ajax({
  url: 'http://localhost/koren_lms/signup.php',
  type: 'POST',
  contentType: 'application/json',
  data: JSON.stringify({
    first_name: 'John',
    last_name: 'Doe',
    nic_number: '123456789V',
    phone_number: '0771234567',
    email: 'john.doe@example.com',
    password: 'MyPass123',
    confirm_password: 'MyPass123'
  }),
  success: function(result) {
    if (result.success) {
      alert('Signup successful!');
      window.location.href = 'signin.html';
    } else {
      alert(result.message);
    }
  },
  error: function(xhr, status, error) {
    console.error('Error:', error);
  }
});
```

**Signin:**
```javascript
$.ajax({
  url: 'http://localhost/koren_lms/signin.php',
  type: 'POST',
  contentType: 'application/json',
  xhrFields: {
    withCredentials: true // Important for session cookies
  },
  data: JSON.stringify({
    email: 'john.doe@example.com',
    password: 'MyPass123'
  }),
  success: function(result) {
    if (result.success) {
      localStorage.setItem('user', JSON.stringify(result.data));
      window.location.href = result.data.redirect_url;
    } else {
      alert(result.message);
    }
  },
  error: function(xhr, status, error) {
    console.error('Error:', error);
  }
});
```

---

## CORS Configuration

If your frontend is on a different domain/port, you may need to add CORS headers. Add this to the beginning of your PHP files:

```php
// Allow cross-origin requests (if needed)
header('Access-Control-Allow-Origin: http://localhost:3000'); // Your frontend URL
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}
```

---

## Error Codes

All responses return HTTP 200 OK. Check the `success` field in the JSON response:
- `success: true` - Operation successful
- `success: false` - Operation failed, check `errors` array for details

---

## Security Notes

1. **HTTPS**: Use HTTPS in production
2. **Password Security**: Passwords are hashed using bcrypt
3. **SQL Injection**: Prevented using prepared statements
4. **XSS**: Output is sanitized using `htmlspecialchars()`
5. **Session Security**: Secure session configuration enabled
6. **Input Validation**: All inputs are validated server-side

---

## Database Schema

**Table: users**
```sql
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
