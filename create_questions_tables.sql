-- Use the koren_lms database
USE koren_lms;

-- Drop existing tables if you want a fresh start (CAUTION: This deletes all data!)
-- Uncomment the lines below if you want to recreate tables
DROP TABLE IF EXISTS voice_question_answers;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;

-- ========================================
-- Table 1: questions
-- Main table storing question information
-- ========================================
CREATE TABLE questions (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_text TEXT NOT NULL,
    question_type ENUM('mcq', 'voice') NOT NULL DEFAULT 'mcq',
    question_format ENUM('normal', 'image') DEFAULT 'normal',
    question_image VARCHAR(500) NULL,
    answer_type ENUM('single', 'multiple') NOT NULL DEFAULT 'single',
    audio_link VARCHAR(500) NULL,
    difficulty ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL DEFAULT 'Beginner',
    category VARCHAR(100) NOT NULL,
    time_limit INT(11) NULL COMMENT 'Time limit in seconds (for voice questions only)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    INDEX idx_type (question_type),
    INDEX idx_category (category),
    INDEX idx_difficulty (difficulty),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table 2: question_options
-- Stores MCQ answer options (4 per question)
-- ========================================
CREATE TABLE question_options (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT(11) UNSIGNED NOT NULL,
    option_text TEXT NOT NULL,
    option_image VARCHAR(500) NULL,
    option_order TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=A, 1=B, 2=C, 3=D',
    is_correct TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Table 3: voice_question_answers
-- Stores voice question answers separately
-- ========================================
CREATE TABLE voice_question_answers (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT(11) UNSIGNED NOT NULL,
    answer_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_question (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- Verify tables were created
-- ========================================
SHOW TABLES;

-- Check table structures
DESCRIBE questions;
DESCRIBE question_options;
DESCRIBE voice_question_answers;
