# Student Progress API Documentation

This API provides endpoints to retrieve student progress data including exam results, marks, and activity information.

## Base URL
```
http://localhost/Koren_Course_LMS_Backend/studentProgress.php
```

## Endpoints

### 1. Get All Student Progress
Retrieves all students with their exam results and progress data.

**Endpoint:** `GET /studentProgress.php?action=list`

**Query Parameters:**
- `action` (optional): `list` - Get all student progress data
- `search` (optional): Search term to filter by student name, email, or batch number
- `level` (optional): Filter by student level (Beginner, Intermediate, Advanced)

**Example Request:**
```javascript
fetch('http://localhost/Koren_Course_LMS_Backend/studentProgress.php?action=list')
  .then(response => response.json())
  .then(data => console.log(data));
```

**Example Request with Search:**
```javascript
fetch('http://localhost/Koren_Course_LMS_Backend/studentProgress.php?action=list&search=janitha')
  .then(response => response.json())
  .then(data => console.log(data));
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Janitha Lakshan",
      "firstName": "Janitha",
      "lastName": "Lakshan",
      "email": "janitha@example.com",
      "phone": "0771234567",
      "batchNumber": "BATCH-15",
      "level": "Intermediate",
      "marks": 72,
      "examFaceDate": "2026-01-10 14:30:00",
      "lastActive": "2 days ago",
      "examResults": [
        {
          "examResultId": 1,
          "examId": 5,
          "score": 18,
          "totalMarks": 25,
          "percentage": 72.0,
          "marks": 72,
          "timeTaken": 1200,
          "status": "completed",
          "submittedAt": "2026-01-10 14:30:00",
          "examDate": "2026-01-10 13:00:00"
        }
      ],
      "totalExams": 1,
      "completedExams": 1,
      "averageMarks": 72,
      "coursesEnrolled": 3,
      "coursesCompleted": 1,
      "progressPercentage": 72,
      "totalLessons": 10,
      "lessonsCompleted": 10
    }
  ],
  "total": 1
}
```

### 2. Get Single Student Progress
Retrieves detailed progress information for a specific student.

**Endpoint:** `GET /studentProgress.php?action=student&id={studentId}`

**Query Parameters:**
- `action`: `student` - Get single student progress
- `id`: Student ID (required)

**Example Request:**
```javascript
fetch('http://localhost/Koren_Course_LMS_Backend/studentProgress.php?action=student&id=1')
  .then(response => response.json())
  .then(data => console.log(data));
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Janitha Lakshan",
    "firstName": "Janitha",
    "lastName": "Lakshan",
    "email": "janitha@example.com",
    "phone": "0771234567",
    "batchNumber": "BATCH-15",
    "level": "Intermediate",
    "marks": 72,
    "examFaceDate": "2026-01-10 14:30:00",
    "lastActive": "2 days ago",
    "examResults": [
      {
        "examResultId": 1,
        "examId": 5,
        "score": 18,
        "totalMarks": 25,
        "percentage": 72.0,
        "marks": 72,
        "timeTaken": 1200,
        "status": "completed",
        "submittedAt": "2026-01-10 14:30:00",
        "examDate": "2026-01-10 13:00:00"
      }
    ],
    "totalExams": 1,
    "completedExams": 1,
    "averageMarks": 72
  }
}
```

### 3. Get Progress Statistics
Retrieves overall statistics about student progress across all students.

**Endpoint:** `GET /studentProgress.php?action=stats`

**Query Parameters:**
- `action`: `stats` - Get progress statistics

**Example Request:**
```javascript
fetch('http://localhost/Koren_Course_LMS_Backend/studentProgress.php?action=stats')
  .then(response => response.json())
  .then(data => console.log(data));
```

**Response:**
```json
{
  "success": true,
  "data": {
    "totalStudents": 5,
    "avgCompletion": 68,
    "activeToday": 2,
    "advancedLevel": 1,
    "totalCompletedExams": 8
  }
}
```

## Data Models

### Student Progress Object
| Field | Type | Description |
|-------|------|-------------|
| id | integer | Student ID |
| name | string | Full name (first + last) |
| firstName | string | Student's first name |
| lastName | string | Student's last name |
| email | string | Student's email address |
| phone | string | Student's phone number |
| batchNumber | string | Student's batch number |
| level | string | Student level (Beginner, Intermediate, Advanced) |
| marks | integer | Most recent exam marks (percentage) |
| examFaceDate | string | Date of most recent exam (ISO 8601 format) |
| lastActive | string | Human-readable last activity time |
| examResults | array | Array of all exam results for this student |
| totalExams | integer | Total number of exams taken |
| completedExams | integer | Number of completed exams |
| averageMarks | integer | Average marks across all completed exams |
| coursesEnrolled | integer | Number of courses enrolled (mock) |
| coursesCompleted | integer | Number of courses completed (mock) |
| progressPercentage | integer | Overall progress percentage |
| totalLessons | integer | Total lessons (mock) |
| lessonsCompleted | integer | Completed lessons (mock) |

### Exam Result Object
| Field | Type | Description |
|-------|------|-------------|
| examResultId | integer | Exam result ID |
| examId | integer | Exam ID |
| score | float | Raw score (correct answers) |
| totalMarks | integer | Total possible marks/questions |
| percentage | float | Percentage score |
| marks | integer | Marks out of 100 (rounded percentage) |
| timeTaken | integer | Time taken in seconds |
| status | string | Status (pending, in_progress, completed, submitted) |
| submittedAt | string | Submission timestamp |
| examDate | string | Exam start date |

## React Integration Example

Here's how to integrate the API with your React component:

```jsx
import React, { useState, useEffect } from 'react';

const StudentProgress = () => {
  const [students, setStudents] = useState([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchStudentProgress();
  }, []);

  const fetchStudentProgress = async () => {
    try {
      setLoading(true);
      const response = await fetch('http://localhost/Koren_Course_LMS_Backend/studentProgress.php?action=list');
      const result = await response.json();
      
      if (result.success) {
        setStudents(result.data);
      } else {
        console.error('Failed to fetch student progress:', result.message);
      }
    } catch (error) {
      console.error('Error fetching student progress:', error);
    } finally {
      setLoading(false);
    }
  };

  const filteredStudents = students.filter((student) => {
    const matchesSearch = 
      student.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      student.email.toLowerCase().includes(searchTerm.toLowerCase());
    return matchesSearch;
  });

  // Rest of your component...
};
```

### With Search Functionality

```jsx
const handleSearch = async (searchValue) => {
  try {
    setLoading(true);
    const response = await fetch(
      `http://localhost/Koren_Course_LMS_Backend/studentProgress.php?action=list&search=${encodeURIComponent(searchValue)}`
    );
    const result = await response.json();
    
    if (result.success) {
      setStudents(result.data);
    }
  } catch (error) {
    console.error('Error searching students:', error);
  } finally {
    setLoading(false);
  }
};

// Use debouncing for better performance
useEffect(() => {
  const timer = setTimeout(() => {
    if (searchTerm) {
      handleSearch(searchTerm);
    } else {
      fetchStudentProgress();
    }
  }, 500);

  return () => clearTimeout(timer);
}, [searchTerm]);
```

## Error Responses

### 400 Bad Request
```json
{
  "success": false,
  "message": "Invalid student ID"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Student not found"
}
```

### 405 Method Not Allowed
```json
{
  "success": false,
  "message": "Method not allowed"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Failed to retrieve student progress",
  "error": "Database error message"
}
```

## Notes

1. **CORS**: The API has CORS enabled for all origins (`Access-Control-Allow-Origin: *`). In production, restrict this to your frontend domain.

2. **Data Source**: The API fetches data from two tables:
   - `students`: Contains student information
   - `exam_results`: Contains exam results with marks, percentage, and submission dates

3. **Level Determination**: Student level is automatically determined based on batch number:
   - Batch 1-10: Advanced
   - Batch 11-20: Intermediate
   - Batch 21+: Beginner

4. **Last Active Calculation**: Automatically calculated based on the most recent exam submission date.

5. **Marks Display**: The API returns percentage-based marks (0-100) from the exam_results table.

6. **Multiple Exam Results**: If a student has taken multiple exams, all results are returned in the `examResults` array, with the most recent used for the main `marks` and `examFaceDate` fields.

## Database Requirements

Ensure your database has the following tables:

1. **students** table with columns:
   - id, first_name, last_name, email, phone, batch_number, is_active

2. **exam_results** table with columns:
   - id, exam_id, student_id, score, total_marks, percentage, time_taken, status, submitted_at, created_at

The API will automatically join these tables to provide comprehensive student progress data.
