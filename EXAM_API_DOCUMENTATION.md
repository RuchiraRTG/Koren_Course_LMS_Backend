# Exam API Documentation

## Base URL
```
http://localhost/exam.php
```

## Overview
The Exam API provides endpoints to manage exams, including creating, reading, updating, and deleting exams. It also supports student and question assignments to exams with batch or individual eligibility.

---

## Database Tables

### 1. `exams` Table
Stores exam information.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key (auto-increment) |
| exam_name | VARCHAR(255) | Name of the exam |
| description | TEXT | Optional exam description |
| exam_type | ENUM | Type: 'mcq', 'voice', 'both' |
| duration | INT | Duration in minutes |
| number_of_questions | INT | Total number of questions |
| total_marks | INT | Total marks for the exam |
| eligibility_type | ENUM | 'batch' or 'individual' |
| selected_batch | VARCHAR(100) | Batch name (if eligibility_type is 'batch') |
| mcq_count | INT | Number of MCQ questions |
| voice_count | INT | Number of voice questions |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |
| is_active | TINYINT(1) | Soft delete flag (1=active, 0=deleted) |

### 2. `exam_questions` Table
Junction table linking exams to questions.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| exam_id | INT UNSIGNED | Foreign key to exams.id |
| question_id | INT UNSIGNED | Foreign key to questions.id |
| question_order | INT | Order of question in exam |
| created_at | TIMESTAMP | Creation timestamp |

### 3. `exam_student_assignments` Table
Junction table for individual student assignments.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| exam_id | INT UNSIGNED | Foreign key to exams.id |
| student_id | INT UNSIGNED | Foreign key to users.id |
| assigned_at | TIMESTAMP | Assignment timestamp |

---

## Endpoints

### 1. Get All Exams
Retrieve all active exams with their details.

**Request:**
```http
GET /exam.php?action=all
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "exam_name": "Korean Language Test - Level 1",
      "description": "Beginner level Korean language assessment",
      "exam_type": "both",
      "duration": 60,
      "number_of_questions": 40,
      "total_marks": 100,
      "eligibility_type": "batch",
      "selected_batch": "Batch A",
      "mcq_count": 25,
      "voice_count": 15,
      "created_at": "2025-10-16 10:30:00",
      "updated_at": "2025-10-16 10:30:00",
      "is_active": 1,
      "total_questions_assigned": 40,
      "total_students_assigned": 0,
      "assigned_questions": [1, 2, 3, 4, 5, ...]
    }
  ],
  "count": 1
}
```

---

### 2. Get Single Exam
Retrieve a specific exam by ID.

**Request:**
```http
GET /exam.php?action=single&id=1
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "exam_name": "Korean Language Test - Level 1",
    "description": "Beginner level Korean language assessment",
    "exam_type": "both",
    "duration": 60,
    "number_of_questions": 40,
    "total_marks": 100,
    "eligibility_type": "individual",
    "selected_batch": null,
    "mcq_count": 25,
    "voice_count": 15,
    "created_at": "2025-10-16 10:30:00",
    "updated_at": "2025-10-16 10:30:00",
    "is_active": 1,
    "assigned_students": [1, 2, 3],
    "assigned_questions": [1, 2, 3, 4, 5, ...]
  }
}
```

---

### 3. Get All Students
Retrieve all active students for assignment.

**Request:**
```http
GET /exam.php?action=students
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "batch": "Batch A",
      "role": "user"
    },
    {
      "id": 2,
      "name": "Jane Smith",
      "first_name": "Jane",
      "last_name": "Smith",
      "email": "jane@example.com",
      "batch": "Batch A",
      "role": "user"
    }
  ],
  "count": 2
}
```

---

### 4. Get All Batches
Retrieve all unique batch names.

**Request:**
```http
GET /exam.php?action=batches
```

**Response:**
```json
{
  "success": true,
  "data": ["Batch A", "Batch B", "Batch C"],
  "count": 3
}
```

---

### 5. Get All Questions
Retrieve all active questions from the questions table for selection.

**Request:**
```http
GET /exam.php?action=questions
```

**Optional Parameter - Filter by exam type:**
```http
GET /exam.php?action=questions&exam_type=mcq      # MCQ questions only
GET /exam.php?action=questions&exam_type=voice    # Voice questions only
GET /exam.php?action=questions&exam_type=both     # All questions
GET /exam.php?action=questions                    # All questions (default)
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "question_text": "What is '안녕하세요'?",
      "question_type": "mcq",
      "question_format": "normal",
      "question_image": null,
      "answer_type": "single",
      "audio_link": null,
      "difficulty": "Beginner",
      "category": "Greetings",
      "time_limit": null,
      "created_at": "2025-10-15 09:00:00"
    },
    {
      "id": 2,
      "question_text": "Listen to the audio and select the correct translation",
      "question_type": "voice",
      "question_format": "normal",
      "question_image": null,
      "answer_type": "single",
      "audio_link": "https://example.com/audio/greeting.mp3",
      "difficulty": "Intermediate",
      "category": "Listening",
      "time_limit": 30,
      "created_at": "2025-10-15 09:10:00"
    }
  ],
  "count": 2
}
```

---

### 6. Get Exam Students
Retrieve students assigned to a specific exam.

**Request:**
```http
GET /exam.php?action=exam-students&exam_id=1
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "batch": "Batch A",
      "assigned_at": "2025-10-16 10:30:00"
    }
  ],
  "count": 1
}
```

---

### 7. Get Exam Questions
Retrieve questions assigned to a specific exam.

**Request:**
```http
GET /exam.php?action=exam-questions&exam_id=1
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "question_text": "What is '안녕하세요'?",
      "question_type": "mcq",
      "question_format": "normal",
      "question_image": null,
      "answer_type": "single",
      "audio_link": null,
      "difficulty": "Beginner",
      "category": "Greetings",
      "time_limit": null,
      "question_order": 0,
      "created_at": "2025-10-15 09:00:00",
      "updated_at": "2025-10-15 09:00:00",
      "is_active": 1
    }
  ],
  "count": 1
}
```

---

### 8. Create Exam
Create a new exam with questions and student assignments.

> **⚠️ Important:** The `selectedQuestions` array must contain valid question IDs that exist in your `questions` table. Use the "Get All Questions" endpoint (`?action=questions`) to fetch available question IDs before creating an exam.

**Request:**
```http
POST /exam.php
Content-Type: application/json
```

**Request Body (Batch Eligibility):**
```json
{
  "examName": "Korean Language Test - Level 1",
  "description": "Beginner level Korean language assessment",
  "examType": "both",
  "duration": "60",
  "numberOfQuestions": "20",
  "totalMarks": "100",
  "eligibilityType": "batch",
  "selectedBatch": "Batch A",
  "selectedQuestions": [11, 12, 13, 14, 15],
  "mcqCount": 3,
  "voiceCount": 2
}
```

**Request Body (Individual Eligibility):**
```json
{
  "examName": "Korean Language Test - Advanced",
  "description": "Advanced level assessment",
  "examType": "both",
  "duration": "120",
  "numberOfQuestions": "20",
  "totalMarks": "150",
  "eligibilityType": "individual",
  "selectedBatch": "",
  "selectedStudents": [1, 2, 3, 5],
  "selectedQuestions": [11, 12, 13, 14],
  "mcqCount": 2,
  "voiceCount": 2
}
```

**Response:**
```json
{
  "success": true,
  "message": "Exam created successfully",
  "data": {
    "id": 2,
    "exam_name": "Korean Language Test - Level 1",
    "description": "Beginner level Korean language assessment",
    "exam_type": "both",
    "duration": 60,
    "number_of_questions": 20,
    "total_marks": 100,
    "eligibility_type": "batch",
    "selected_batch": "Batch A",
    "mcq_count": 3,
    "voice_count": 2,
    "created_at": "2025-10-16 10:45:00",
    "updated_at": "2025-10-16 10:45:00",
    "is_active": 1,
    "assigned_questions": [11, 12, 13, 14, 15]
  }
}
```

---

### 9. Update Exam
Update an existing exam.

**Request:**
```http
PUT /exam.php
Content-Type: application/json
```

**Request Body:**
```json
{
  "id": 1,
  "examName": "Korean Language Test - Level 1 (Updated)",
  "description": "Updated description",
  "examType": "both",
  "duration": "90",
  "numberOfQuestions": "20",
  "totalMarks": "120",
  "eligibilityType": "batch",
  "selectedBatch": "Batch B",
  "selectedQuestions": [11, 12, 13, 14, 15],
  "mcqCount": 3,
  "voiceCount": 2
}
```

**Response:**
```json
{
  "success": true,
  "message": "Exam updated successfully",
  "data": {
    "id": 1,
    "exam_name": "Korean Language Test - Level 1 (Updated)",
    "description": "Updated description",
    "exam_type": "both",
    "duration": 90,
    "number_of_questions": 20,
    "total_marks": 120,
    "eligibility_type": "batch",
    "selected_batch": "Batch B",
    "mcq_count": 3,
    "voice_count": 2,
    "updated_at": "2025-10-16 11:00:00",
    "assigned_questions": [11, 12, 13, 14, 15]
  }
}
```

---

### 10. Delete Exam
Soft delete an exam (sets `is_active` to 0).

**Request (Query Parameter):**
```http
DELETE /exam.php?id=1
```

**Request (JSON Body):**
```http
DELETE /exam.php
Content-Type: application/json

{
  "id": 1
}
```

**Alternative (POST with action):**
```http
POST /exam.php?action=delete
Content-Type: application/json

{
  "id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Exam deleted successfully"
}
```

---

## Error Responses

### 400 Bad Request
```json
{
  "success": false,
  "message": "Field 'examName' is required"
}
```

### 404 Not Found
```json
{
  "success": false,
  "message": "Exam not found"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Error fetching exams: [error details]"
}
```

---

## Validation Rules

### Create/Update Exam
- **examName**: Required, non-empty string
- **examType**: Required, must be 'mcq', 'voice', or 'both'
- **duration**: Required, integer (minutes)
- **numberOfQuestions**: Required, integer
- **totalMarks**: Required, integer
- **eligibilityType**: Required, must be 'batch' or 'individual'
- **selectedBatch**: Required if eligibilityType is 'batch'
- **selectedStudents**: Required (non-empty array) if eligibilityType is 'individual'
- **selectedQuestions**: Required, non-empty array, count must not exceed numberOfQuestions

---

## Testing with Postman

### Setup
1. Import the collection from `POSTMAN_SAMPLE_DATA.json`
2. Set base URL: `http://localhost/exam.php`

### Sample Test Flow

1. **Get all students and batches** (for dropdown data)
   ```
   GET http://localhost/exam.php?action=students
   GET http://localhost/exam.php?action=batches
   ```

2. **Create an exam**
   ```
   POST http://localhost/exam.php
   Body: [See "Create Exam" section above]
   ```

3. **Get all exams**
   ```
   GET http://localhost/exam.php?action=all
   ```

4. **Get single exam**
   ```
   GET http://localhost/exam.php?action=single&id=1
   ```

5. **Update exam**
   ```
   PUT http://localhost/exam.php
   Body: [See "Update Exam" section above]
   ```

6. **Delete exam**
   ```
   DELETE http://localhost/exam.php?id=1
   ```

---

## Notes

1. **CORS**: All endpoints support CORS with `Access-Control-Allow-Origin: *`
2. **Soft Delete**: Deleted exams are marked as `is_active = 0`, not removed from database
3. **Transactions**: Create and Update operations use database transactions for data integrity
4. **Foreign Keys**: The tables use foreign key constraints with CASCADE delete
5. **Batch Column**: The API automatically adds a `batch` column to the `users` table if it doesn't exist
6. **Question Types**: The system counts MCQ and Voice questions automatically based on the selected questions

---

## Database Setup

Run the SQL file to create the required tables:

```bash
mysql -u root -p koren_lms < create_exam_tables.sql
```

Or execute the SQL directly in phpMyAdmin or MySQL Workbench.

The API will also auto-create tables on first run via the `initializeExamTables()` function.
