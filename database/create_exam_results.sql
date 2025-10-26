-- =====================================================
-- Exam Results Table
-- =====================================================
-- This table stores exam results for students
-- Includes: marks/score, percentage, time taken, timestamps
-- Foreign key: student_id references users(id)
-- =====================================================

CREATE TABLE IF NOT EXISTS exam_results (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Exam and Student References
    exam_id INT(11) UNSIGNED NULL COMMENT 'NULL for mock/practice exams, otherwise references specific exam',
    student_id INT(11) UNSIGNED NOT NULL COMMENT 'References users.id for the student',
    
    -- Scoring Information
    score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Number of correct answers (can be decimal for weighted scoring)',
    total_marks INT(11) NOT NULL COMMENT 'Total possible marks/questions in the exam',
    percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Calculated percentage score',
    
    -- Time Tracking
    time_taken INT(11) NULL COMMENT 'Time taken to complete exam in seconds',
    
    -- Status Tracking
    status ENUM('pending', 'in_progress', 'completed', 'submitted') DEFAULT 'pending' COMMENT 'Current status of exam',
    
    -- Timestamps
    started_at TIMESTAMP NULL COMMENT 'When the student started the exam',
    submitted_at TIMESTAMP NULL COMMENT 'When the student submitted the exam',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update time',
    
    -- Indexes for Performance
    INDEX idx_exam (exam_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_submitted (submitted_at),
    
    -- Foreign Key Constraints
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores exam results including marks, percentage, and time taken';

-- =====================================================
-- Sample Queries
-- =====================================================

-- Get all exam results for a specific student
-- SELECT * FROM exam_results WHERE student_id = ? ORDER BY submitted_at DESC;

-- Get student's average percentage across all exams
-- SELECT student_id, AVG(percentage) as avg_percentage, COUNT(*) as total_exams
-- FROM exam_results WHERE student_id = ? GROUP BY student_id;

-- Get recent exam results (last 10)
-- SELECT er.*, u.username, u.email 
-- FROM exam_results er
-- JOIN users u ON er.student_id = u.id
-- ORDER BY er.submitted_at DESC LIMIT 10;

-- Get exam statistics
-- SELECT 
--     COUNT(*) as total_attempts,
--     AVG(percentage) as avg_percentage,
--     MAX(percentage) as highest_score,
--     MIN(percentage) as lowest_score,
--     AVG(time_taken) as avg_time_seconds
-- FROM exam_results WHERE student_id = ?;
