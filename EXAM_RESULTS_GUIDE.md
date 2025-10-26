# Exam Results Saving Feature - Implementation Guide

## Overview
This document explains how exam results are automatically saved when students submit their exams. The system uses the `exam_results` table to store all student exam submissions.

---

## Database Schema

### `exam_results` Table Structure

| Column | Type | Null | Description |
|--------|------|------|-------------|
| `id` | INT UNSIGNED | NO | Primary key, auto-increment |
| `exam_id` | INT UNSIGNED | YES | Foreign key to exams table (NULL for mock exams) |
| `student_id` | INT UNSIGNED | NO | Foreign key to users table (student's ID) |
| `score` | DECIMAL(5,2) | YES | Number of correct answers (e.g., 8.00) |
| `total_marks` | INT | NO | Total possible marks/questions |
| `percentage` | DECIMAL(5,2) | YES | Percentage score (e.g., 80.00 for 80%) |
| `time_taken` | INT | YES | Time taken in seconds |
| `status` | ENUM | YES | 'pending', 'in_progress', 'completed', 'submitted' |
| `started_at` | TIMESTAMP | YES | When the exam was started |
| `submitted_at` | TIMESTAMP | YES | When the exam was submitted |
| `created_at` | TIMESTAMP | NO | Record creation timestamp |
| `updated_at` | TIMESTAMP | YES | Record update timestamp |

**Important Notes:**
- `exam_id` can be NULL for mock exams (practice tests)
- `score` and `percentage` are stored as DECIMAL(5,2) for precision
- `status` defaults to 'pending' but is set to 'submitted' when student submits

---

## How It Works

### 1. Student Takes Exam

When a student logs in and takes an exam via `takeExam.php`:

```
1. Frontend calls: POST /takeExam.php?action=startExam
   - Parameters: examType (mcq/voice/both), numberOfQuestions, category (optional)
   
2. Backend generates attempt token and returns questions

3. Student answers questions

4. Frontend calls: POST /takeExam.php?action=submitAnswers
   - Parameters: attemptToken, answers (array of {question_id, selected_index})
```

### 2. Backend Processing (submitAnswers)

The `submitAnswers` action in `takeExam.php` does the following:

```php
1. Validates the attempt token and answers
2. Checks each answer against the correct answer
3. Calculates:
   - Total questions
   - Correct answers
   - Incorrect answers
   - Percentage score
   - Time taken
   
4. If user is a student (userType === 'student'):
   - Inserts result into exam_results table
   - Returns examResultId in the response
```

### 3. Data Saved to Database

Example of what gets saved:

```php
exam_id: NULL (for mock exams) or specific exam ID
student_id: 123 (from $_SESSION['user_id'])
score: 8.00 (8 correct answers)
total_marks: 10 (10 total questions)
percentage: 80.00 (80%)
time_taken: 300 (5 minutes in seconds)
status: 'submitted'
started_at: '2025-10-25 14:00:00'
submitted_at: '2025-10-25 14:05:00'
```

---

## API Response Example

When a student submits an exam, the API returns:

```json
{
  "success": true,
  "message": "Results calculated",
  "data": {
    "attemptToken": "abc123...",
    "examResultId": 15,
    "summary": {
      "total": 10,
      "correct": 8,
      "incorrect": 2,
      "percentage": 80.00
    },
    "details": [
      {
        "question_id": 1,
        "question_type": "mcq",
        "selected_index": 0,
        "is_correct": true,
        "correct_indices": [0]
      },
      // ... more questions
    ]
  }
}
```

**Key Field:**
- `examResultId`: The ID of the saved exam result record (NULL if not saved)

---

## Session Requirements

For exam results to be saved, the following session variables must be set:

```php
$_SESSION['user_id']    // Student's user ID (required)
$_SESSION['user_type']  // Must be 'student' (required)
```

These are typically set during login via `signin.php`.

---

## Migration Notes

### Database Update Required

If you're upgrading from an older version, run this migration:

```bash
php c:\xampp\htdocs\fix_exam_id_null.php
```

Or manually execute:

```sql
ALTER TABLE exam_results 
MODIFY COLUMN exam_id INT(11) UNSIGNED NULL 
COMMENT 'NULL for mock exams';
```

This allows `exam_id` to be NULL for mock exams.

---

## Testing

### Test Files Included

1. **check_exam_results_table.php** - Verifies table structure
   ```bash
   php c:\xampp\htdocs\check_exam_results_table.php
   ```

2. **test_direct_exam_result.php** - Direct insertion test
   ```bash
   php c:\xampp\htdocs\test_direct_exam_result.php
   ```

3. **check_students.php** - Lists students in database
   ```bash
   php c:\xampp\htdocs\check_students.php
   ```

### Manual Testing via API

1. **Start Exam:**
   ```bash
   curl -X POST "http://localhost/takeExam.php?action=startExam" \
     -H "Content-Type: application/json" \
     -d '{"examType":"mcq","numberOfQuestions":5}' \
     --cookie "PHPSESSID=your_session_id"
   ```

2. **Submit Answers:**
   ```bash
   curl -X POST "http://localhost/takeExam.php?action=submitAnswers" \
     -H "Content-Type: application/json" \
     -d '{
       "attemptToken":"TOKEN_FROM_START_EXAM",
       "answers":[
         {"question_id":1,"selected_index":0},
         {"question_id":2,"selected_index":1}
       ]
     }' \
     --cookie "PHPSESSID=your_session_id"
   ```

---

## Viewing Results

### Method 1: Direct Database Query

```sql
SELECT 
  er.id,
  er.exam_id,
  er.score,
  er.total_marks,
  er.percentage,
  er.time_taken,
  er.status,
  er.submitted_at,
  u.first_name,
  u.last_name,
  u.email
FROM exam_results er
LEFT JOIN users u ON er.student_id = u.id
ORDER BY er.submitted_at DESC;
```

### Method 2: Using viewResults.php API

```bash
# Get all results for a specific student
curl "http://localhost/viewResults.php?action=list&student_id=123"

# Get a specific result by ID
curl "http://localhost/viewResults.php?action=detail&result_id=15"
```

---

## Troubleshooting

### Results Not Saving

**Check 1: Session Variables**
```php
// Add this to takeExam.php temporarily for debugging
error_log("Session check: user_id=" . ($_SESSION['user_id'] ?? 'NOT SET') . 
          ", user_type=" . ($_SESSION['user_type'] ?? 'NOT SET'));
```

**Check 2: Database Errors**
Look at PHP error logs:
- Windows: `c:\xampp\apache\logs\error.log`
- Check for SQL errors or connection issues

**Check 3: User Role**
Ensure the user has role='student' in the database:
```sql
SELECT id, email, role FROM users WHERE id = YOUR_USER_ID;
```

### Exam ID Issues

- For **mock exams** (practice): `exam_id` will be NULL
- For **real exams**: Pass `exam_id` parameter when calling `submitAnswers`

Example:
```json
{
  "attemptToken": "abc123...",
  "exam_id": 5,
  "answers": [...]
}
```

---

## Code Location

**Main File:** `c:\xampp\htdocs\takeExam.php`

**Relevant Section:** Lines 456-505 (submitAnswers action)

**Key Code:**
```php
if ($userType === 'student' && $userId !== null) {
    $resultSql = "INSERT INTO exam_results 
                  (exam_id, student_id, score, total_marks, percentage, 
                   time_taken, status, started_at, submitted_at) 
                  VALUES (?, ?, ?, ?, ?, ?, 'submitted', FROM_UNIXTIME(?), NOW())";
    // ... binding and execution
}
```

---

## Future Enhancements

Potential improvements you could add:

1. **Detailed Answer Storage**
   - Store each answer in a separate `exam_result_details` table
   - Link to `exam_results` via `result_id`

2. **Grading System**
   - Add `graded_by` (admin/teacher ID)
   - Add `feedback` (TEXT column for comments)
   - Update status to 'graded' when reviewed

3. **Attempt Tracking**
   - Add `attempt_number` to track multiple attempts
   - Add `is_best_attempt` flag

4. **Time Limits**
   - Compare `time_taken` against exam duration
   - Mark as 'timeout' if exceeded

---

## Summary

✅ Exam results are **automatically saved** when students submit exams  
✅ Works for both **mock exams** (exam_id = NULL) and **real exams**  
✅ Stores score, percentage, time taken, and timestamps  
✅ Returns `examResultId` in API response  
✅ Fully tested and working  

For questions or issues, check the troubleshooting section or review the test files.
