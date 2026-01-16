-- =====================================================
-- Student Progress Data Verification Script
-- =====================================================
-- Run this script to verify your database has the correct
-- data structure and sample data for the Student Progress API
-- =====================================================

-- 1. Check if tables exist
SELECT 'Checking if required tables exist...' as Status;

SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'koren_lms' 
AND TABLE_NAME IN ('users', 'exam_results');

-- 2. Check users table structure
SELECT 'Checking users table structure...' as Status;

DESCRIBE users;

-- 3. Check exam_results table structure
SELECT 'Checking exam_results table structure...' as Status;

DESCRIBE exam_results;

-- 4. Count active users
SELECT 'Counting active users...' as Status;

SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
FROM users;

-- 5. Count exam results by status
SELECT 'Counting exam results by status...' as Status;

SELECT 
    status,
    COUNT(*) as count
FROM exam_results
GROUP BY status;

-- 6. Preview the exact data that the API will return
SELECT 'Preview of API data (first 10 records)...' as Status;

SELECT 
    u.id,
    CONCAT(u.first_name, ' ', u.last_name) as name,
    u.email,
    er.percentage as marks,
    er.submitted_at as examFaceDate,
    er.score,
    er.total_marks as totalMarks,
    er.status
FROM exam_results er
INNER JOIN users u ON er.student_id = u.id
WHERE u.is_active = 1 
AND (er.status = 'completed' OR er.status = 'submitted')
ORDER BY er.submitted_at DESC
LIMIT 10;

-- 7. Check for students with multiple exam results
SELECT 'Students with multiple completed exams...' as Status;

SELECT 
    u.id,
    CONCAT(u.first_name, ' ', u.last_name) as student_name,
    COUNT(er.id) as exam_count,
    AVG(er.percentage) as avg_percentage,
    MAX(er.submitted_at) as latest_exam
FROM exam_results er
INNER JOIN users u ON er.student_id = u.id
WHERE u.is_active = 1 
AND (er.status = 'completed' OR er.status = 'submitted')
GROUP BY u.id, student_name
HAVING exam_count > 1
ORDER BY exam_count DESC;

-- 8. Check for orphaned exam results (no matching user)
SELECT 'Checking for orphaned exam results...' as Status;

SELECT 
    er.id as exam_result_id,
    er.student_id,
    er.percentage,
    er.submitted_at,
    'No matching user found' as issue
FROM exam_results er
LEFT JOIN users u ON er.student_id = u.id
WHERE u.id IS NULL;

-- 9. Statistics overview
SELECT 'Overall statistics...' as Status;

SELECT 
    'Total Students' as metric,
    COUNT(DISTINCT er.student_id) as value
FROM exam_results er
INNER JOIN users u ON er.student_id = u.id
WHERE u.is_active = 1 
AND (er.status = 'completed' OR er.status = 'submitted')

UNION ALL

SELECT 
    'Average Marks' as metric,
    ROUND(AVG(er.percentage), 2) as value
FROM exam_results er
INNER JOIN users u ON er.student_id = u.id
WHERE u.is_active = 1 
AND (er.status = 'completed' OR er.status = 'submitted')

UNION ALL

SELECT 
    'Total Completed Exams' as metric,
    COUNT(*) as value
FROM exam_results er
INNER JOIN users u ON er.student_id = u.id
WHERE u.is_active = 1 
AND (er.status = 'completed' OR er.status = 'submitted')

UNION ALL

SELECT 
    'Students Active Today' as metric,
    COUNT(DISTINCT er.student_id) as value
FROM exam_results er
INNER JOIN users u ON er.student_id = u.id
WHERE u.is_active = 1 
AND (er.status = 'completed' OR er.status = 'submitted')
AND DATE(er.submitted_at) = CURDATE()

UNION ALL

SELECT 
    'Advanced Students (>=75%)' as metric,
    COUNT(DISTINCT er.student_id) as value
FROM exam_results er
INNER JOIN users u ON er.student_id = u.id
WHERE u.is_active = 1 
AND (er.status = 'completed' OR er.status = 'submitted')
AND er.percentage >= 75;

-- 10. Sample data check
SELECT 'Checking if you need sample data...' as Status;

SELECT 
    CASE 
        WHEN COUNT(*) = 0 THEN 'NO DATA FOUND - You need to insert sample data!'
        WHEN COUNT(*) < 5 THEN 'LIMITED DATA - Consider adding more sample records'
        ELSE 'DATA EXISTS - You are good to go!'
    END as data_status,
    COUNT(*) as record_count
FROM exam_results er
INNER JOIN users u ON er.student_id = u.id
WHERE u.is_active = 1 
AND (er.status = 'completed' OR er.status = 'submitted');

-- =====================================================
-- SAMPLE DATA INSERT (Run only if needed)
-- =====================================================
-- Uncomment and modify the following if you need sample data

/*
-- Insert sample users (students)
INSERT INTO users (first_name, last_name, email, nic_number, phone_number, password, is_active, role) 
VALUES 
('Janitha', 'Lakshan', 'janitha@example.com', '199512345678', '0771234567', '$2y$10$YourHashedPasswordHere', 1, 'student'),
('Sarah', 'Johnson', 'sarah@example.com', '199623456789', '0772345678', '$2y$10$YourHashedPasswordHere', 1, 'student'),
('Michael', 'Chen', 'michael@example.com', '199734567890', '0773456789', '$2y$10$YourHashedPasswordHere', 1, 'student'),
('Emma', 'Wilson', 'emma@example.com', '199845678901', '0774567890', '$2y$10$YourHashedPasswordHere', 1, 'student'),
('David', 'Kumar', 'david@example.com', '199956789012', '0775678901', '$2y$10$YourHashedPasswordHere', 1, 'student');

-- Get the student IDs (adjust based on your actual IDs)
-- Insert sample exam results
INSERT INTO exam_results (student_id, exam_id, score, total_marks, percentage, time_taken, status, submitted_at)
VALUES 
-- Janitha (assuming user_id = 1)
(1, 1, 14.4, 20, 72.00, 1800, 'completed', '2026-02-15 14:30:00'),
-- Sarah (assuming user_id = 2)
(2, 1, 17.0, 20, 85.00, 1500, 'completed', '2026-02-10 10:15:00'),
-- Michael (assuming user_id = 3)
(3, 1, 18.4, 20, 92.00, 1200, 'completed', '2026-02-20 16:45:00'),
-- Emma (assuming user_id = 4)
(4, 1, 9.0, 20, 45.00, 2100, 'completed', '2026-03-01 09:30:00'),
-- David (assuming user_id = 5)
(5, 1, 15.6, 20, 78.00, 1650, 'completed', '2026-02-25 13:20:00');
*/

-- =====================================================
-- VERIFICATION COMPLETE
-- =====================================================
SELECT '✓ Verification complete! Check the results above.' as Status;
