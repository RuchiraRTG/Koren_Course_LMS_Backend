-- ========================================
-- Add MCQ Question Answers Table
-- ========================================

USE koren_lms;

-- Create new table for MCQ answers
CREATE TABLE IF NOT EXISTS mcq_question_answers (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT(11) UNSIGNED NOT NULL,
    answer_indices TEXT NOT NULL COMMENT 'JSON array of correct option indices',
    answer_texts TEXT NULL COMMENT 'JSON array of correct option texts',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remove is_correct column from question_options
ALTER TABLE question_options DROP COLUMN is_correct;
