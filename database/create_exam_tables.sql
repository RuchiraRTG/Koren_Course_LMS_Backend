-- Use the koren_lms database
USE koren_lms;

-- Drop existing exam tables if you want a fresh start (CAUTION: This deletes all data!)
-- Uncomment the lines below if you want to recreate tables
-- DROP TABLE IF EXISTS exam_student_assignments;
-- DROP TABLE IF EXISTS exam_questions;
-- DROP TABLE IF EXISTS exams;

-- ========================================
-- Table 1: exams
-- Main table storing exam information
-- ========================================
CREATE TABLE IF NOT EXISTS exams (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    exam_type ENUM('mcq', 'voice', 'both') NOT NULL DEFAULT 'both',
    duration INT(11) NOT NULL COMMENT 'Duration in minutes',
    number_of_questions INT(11) NOT NULL,
    total_marks INT(11) NOT NULL,
    eligibility_type ENUM('batch', 'individual') NOT NULL DEFAULT 'batch',
    selected_batch VARCHAR(100) NULL COMMENT 'Batch name if eligibility_type is batch',
    mcq_count INT(11) DEFAULT 0,
    voice_count INT(11) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_exam_type (exam_type),
    INDEX idx_eligibility_type (eligibility_type),
    INDEX idx_batch (selected_batch),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table 2: exam_questions
-- Junction table linking exams to questions
-- ========================================
CREATE TABLE IF NOT EXISTS exam_questions (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT(11) UNSIGNED NOT NULL,
    question_id INT(11) UNSIGNED NOT NULL,
    question_order INT(11) DEFAULT 0 COMMENT 'Order of question in exam',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exam (exam_id),
    INDEX idx_question (question_id),
    UNIQUE KEY unique_exam_question (exam_id, question_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table 3: exam_student_assignments
-- Junction table linking exams to individual students
-- Only used when eligibility_type = 'individual'
-- ========================================
CREATE TABLE IF NOT EXISTS exam_student_assignments (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id INT(11) UNSIGNED NOT NULL,
    student_id INT(11) UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exam (exam_id),
    INDEX idx_student (student_id),
    UNIQUE KEY unique_exam_student (exam_id, student_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Add batch column to users table if not exists
-- ========================================
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'batch';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_NAME = @tablename)
      AND (TABLE_SCHEMA = @dbname)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD ', @columnname, ' VARCHAR(100) NULL AFTER role')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ========================================
-- Verify tables were created
-- ========================================
SHOW TABLES LIKE '%exam%';

-- Check table structures
DESCRIBE exams;
DESCRIBE exam_questions;
DESCRIBE exam_student_assignments;
