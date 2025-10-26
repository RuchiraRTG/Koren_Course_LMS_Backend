# Student API Documentation

Base URL: `http://localhost/student.php`

---

## 📋 API Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/student.php` | Get all students |
| GET | `/student.php?action=list` | Get all students (explicit) |
| GET | `/student.php?action=view&id={id}` | Get student by ID |
| GET | `/student.php?action=stats` | Get student statistics |
| POST | `/student.php` | Create new student |
| PUT | `/student.php` | Update existing student |
| DELETE | `/student.php?id={id}` | Delete student (soft delete) |
| POST | `/student.php?action=delete&id={id}` | Delete student via POST |

---

## 1️⃣ Get All Students

### Request
```
GET http://localhost/student.php
GET http://localhost/student.php?action=list
```

### Query Parameters (Optional)
- `search` - Search in firstName, lastName, email, batchNumber, nicNumber
- `batch` - Filter by batch number

### Examples
```
GET http://localhost/student.php
GET http://localhost/student.php?search=john
GET http://localhost/student.php?batch=BATCH-2024-01
GET http://localhost/student.php?search=doe&batch=BATCH-2024-01
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "firstName": "John",
      "lastName": "Doe",
      "batchNumber": "BATCH-2024-01",
      "email": "john.doe@email.com",
      "phone": "0771234567",
      "nicNumber": "199512345678",
      "createdAt": "2024-10-16 10:30:00",
      "updatedAt": "2024-10-16 10:30:00"
    },
    {
      "id": 2,
      "firstName": "Jane",
      "lastName": "Smith",
      "batchNumber": "BATCH-2024-01",
      "email": "jane.smith@email.com",
      "phone": "0779876543",
      "nicNumber": "199612345678",
      "createdAt": "2024-10-16 11:00:00",
      "updatedAt": "2024-10-16 11:00:00"
    }
  ],
  "total": 2
}
```

---

## 2️⃣ Get Student by ID

### Request
```
GET http://localhost/student.php?action=view&id=1
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "id": 1,
    "firstName": "John",
    "lastName": "Doe",
    "batchNumber": "BATCH-2024-01",
    "email": "john.doe@email.com",
    "phone": "0771234567",
    "nicNumber": "199512345678",
    "createdAt": "2024-10-16 10:30:00",
    "updatedAt": "2024-10-16 10:30:00"
  }
}
```

### Response (Not Found - 404)
```json
{
  "success": false,
  "message": "Student not found"
}
```

---

## 3️⃣ Get Student Statistics

### Request
```
GET http://localhost/student.php?action=stats
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "total": 5,
    "by_batch": {
      "BATCH-2024-02": 2,
      "BATCH-2024-01": 3
    },
    "recent_students": 2
  }
}
```

---

## 4️⃣ Create Student

### Request
```
POST http://localhost/student.php
Content-Type: application/json
```

### Request Body
```json
{
  "firstName": "John",
  "lastName": "Doe",
  "batchNumber": "BATCH-2024-01",
  "email": "john.doe@email.com",
  "phone": "0771234567",
  "nicNumber": "199512345678"
}
```

### Response (Success - 200)
```json
{
  "success": true,
  "message": "Student created successfully",
  "student_id": 1
}
```

### Response (Validation Error - 400)
```json
{
  "success": false,
  "message": "Email is required"
}
```

### Response (Duplicate Email - 400)
```json
{
  "success": false,
  "message": "A student with this email already exists"
}
```

### Response (Duplicate NIC - 400)
```json
{
  "success": false,
  "message": "A student with this NIC number already exists"
}
```

---

## 5️⃣ Update Student

### Request
```
PUT http://localhost/student.php
Content-Type: application/json
```

### Request Body
```json
{
  "id": 1,
  "firstName": "John",
  "lastName": "Doe Updated",
  "batchNumber": "BATCH-2024-02",
  "email": "john.doe.updated@email.com",
  "phone": "0771234567",
  "nicNumber": "199512345678"
}
```

### Response (Success - 200)
```json
{
  "success": true,
  "message": "Student updated successfully"
}
```

### Response (No Changes - 200)
```json
{
  "success": true,
  "message": "No changes made to student"
}
```

### Response (Missing ID - 400)
```json
{
  "success": false,
  "message": "Student ID is required"
}
```

---

## 6️⃣ Delete Student (Soft Delete)

### Request Method 1 (DELETE)
```
DELETE http://localhost/student.php?id=1
```

### Request Method 2 (POST)
```
POST http://localhost/student.php?action=delete&id=1
```

### Request Method 3 (JSON Body)
```
DELETE http://localhost/student.php
Content-Type: application/json

{
  "id": 1
}
```

### Response (Success - 200)
```json
{
  "success": true,
  "message": "Student deleted successfully",
  "affected": 1,
  "id": 1
}
```

### Response (Already Deleted - 200)
```json
{
  "success": true,
  "message": "No change (already deleted or not found)",
  "affected": 0,
  "id": 1
}
```

### Response (Invalid ID - 400)
```json
{
  "success": false,
  "message": "Invalid or missing student ID"
}
```

---

## ✅ Validation Rules

### First Name
- **Required**: Yes
- **Type**: String
- **Max Length**: 100 characters

### Last Name
- **Required**: Yes
- **Type**: String
- **Max Length**: 100 characters

### Batch Number
- **Required**: Yes
- **Type**: String
- **Format**: e.g., "BATCH-2024-01"
- **Max Length**: 50 characters

### Email
- **Required**: Yes
- **Type**: String (valid email)
- **Format**: Must be valid email format
- **Unique**: Yes
- **Max Length**: 150 characters

### Phone
- **Required**: Yes
- **Type**: String
- **Format**: 10 digits starting with 0 (Sri Lankan format)
- **Pattern**: `^0\d{9}$`
- **Example**: "0771234567"

### NIC Number
- **Required**: Yes
- **Type**: String
- **Format**: 
  - Old format: 9 digits + V/X (e.g., "956789012V")
  - New format: 12 digits (e.g., "199512345678")
- **Unique**: Yes
- **Pattern**: `^(\d{9}[VvXx]|\d{12})$`

---

## 🔄 Common Response Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request (validation error) |
| 404 | Not Found |
| 405 | Method Not Allowed |
| 500 | Internal Server Error |

---

## 🚨 Error Messages

### Validation Errors
- "First name is required"
- "Last name is required"
- "Batch number is required"
- "Email is required"
- "Invalid email format"
- "Phone number is required"
- "Invalid phone number format (should be 10 digits starting with 0)"
- "NIC number is required"
- "Invalid NIC number format"

### Duplicate Errors
- "A student with this email already exists"
- "A student with this NIC number already exists"

### General Errors
- "Student ID is required"
- "Invalid student ID"
- "Student not found"
- "Failed to retrieve students"
- "Failed to create student"
- "Failed to update student"
- "Failed to delete student"
