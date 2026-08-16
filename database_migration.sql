-- ============================================
-- DMA-RBAPS Database Migration Script
-- Fixes identified database issues
-- ============================================

-- ============================================
-- ISSUE 1: Create missing topic mapping tables
-- ============================================

-- Create mapping_geography table (modern format)
CREATE TABLE IF NOT EXISTS `mapping_geography` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `exam` varchar(10) DEFAULT NULL,
  `best_topic_id` int(11) DEFAULT NULL,
  `best_topic_name` varchar(100) DEFAULT NULL,
  `best_percentage` decimal(5,2) DEFAULT NULL,
  `second_topic_id` int(11) DEFAULT NULL,
  `second_topic_name` varchar(100) DEFAULT NULL,
  `second_percentage` decimal(5,2) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `evaluation_correct` varchar(10) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create mapping_commerce table (modern format)
CREATE TABLE IF NOT EXISTS `mapping_commerce` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `exam` varchar(10) DEFAULT NULL,
  `best_topic_id` int(11) DEFAULT NULL,
  `best_topic_name` varchar(100) DEFAULT NULL,
  `best_percentage` decimal(5,2) DEFAULT NULL,
  `second_topic_id` int(11) DEFAULT NULL,
  `second_topic_name` varchar(100) DEFAULT NULL,
  `second_percentage` decimal(5,2) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `evaluation_correct` varchar(10) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================
-- ISSUE 2: Convert legacy mapping tables to modern format
-- ============================================

-- Create modern mapping_government table
CREATE TABLE IF NOT EXISTS `mapping_government` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `exam` varchar(10) DEFAULT NULL,
  `best_topic_id` int(11) DEFAULT NULL,
  `best_topic_name` varchar(100) DEFAULT NULL,
  `best_percentage` decimal(5,2) DEFAULT NULL,
  `second_topic_id` int(11) DEFAULT NULL,
  `second_topic_name` varchar(100) DEFAULT NULL,
  `second_percentage` decimal(5,2) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `evaluation_correct` varchar(10) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrate data from government_topic_mapping to mapping_government
INSERT INTO `mapping_government` 
(`question_id`, `best_topic_name`, `best_percentage`, `second_topic_name`, `second_percentage`, `keywords`, `created_at`)
SELECT 
    `question_id`,
    `best_subtopic` as `best_topic_name`,
    `percentage` as `best_percentage`,
    NULL as `second_topic_name`,
    NULL as `second_percentage`,
    `matched_keywords` as `keywords`,
    `created_at`
FROM `government_topic_mapping`
WHERE NOT EXISTS (
    SELECT 1 FROM `mapping_government` WHERE `mapping_government`.`question_id` = `government_topic_mapping`.`question_id`
);

-- Create modern mapping_history table
CREATE TABLE IF NOT EXISTS `mapping_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `exam` varchar(10) DEFAULT NULL,
  `best_topic_id` int(11) DEFAULT NULL,
  `best_topic_name` varchar(100) DEFAULT NULL,
  `best_percentage` decimal(5,2) DEFAULT NULL,
  `second_topic_id` int(11) DEFAULT NULL,
  `second_topic_name` varchar(100) DEFAULT NULL,
  `second_percentage` decimal(5,2) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `evaluation_correct` varchar(10) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrate data from history_topic_mapping to mapping_history
INSERT INTO `mapping_history` 
(`question_id`, `best_topic_name`, `best_percentage`, `second_topic_name`, `second_percentage`, `keywords`, `created_at`)
SELECT 
    `question_id`,
    `best_subtopic` as `best_topic_name`,
    `percentage` as `best_percentage`,
    NULL as `second_topic_name`,
    NULL as `second_percentage`,
    `matched_keywords` as `keywords`,
    `created_at`
FROM `history_topic_mapping`
WHERE NOT EXISTS (
    SELECT 1 FROM `mapping_history` WHERE `mapping_history`.`question_id` = `history_topic_mapping`.`question_id`
);

-- Create modern mapping_ict table
CREATE TABLE IF NOT EXISTS `mapping_ict` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) DEFAULT NULL,
  `year` int(11) DEFAULT NULL,
  `exam` varchar(10) DEFAULT NULL,
  `best_topic_id` int(11) DEFAULT NULL,
  `best_topic_name` varchar(100) DEFAULT NULL,
  `best_percentage` decimal(5,2) DEFAULT NULL,
  `second_topic_id` int(11) DEFAULT NULL,
  `second_topic_name` varchar(100) DEFAULT NULL,
  `second_percentage` decimal(5,2) DEFAULT NULL,
  `keywords` text DEFAULT NULL,
  `evaluation_correct` varchar(10) DEFAULT NULL,
  `evaluation_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_question_id` (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrate data from ict_topic_mapping to mapping_ict
INSERT INTO `mapping_ict` 
(`question_id`, `best_topic_name`, `best_percentage`, `second_topic_name`, `second_percentage`, `keywords`, `created_at`)
SELECT 
    `question_id`,
    `best_subtopic` as `best_topic_name`,
    `percentage` as `best_percentage`,
    NULL as `second_topic_name`,
    NULL as `second_percentage`,
    `matched_keywords` as `keywords`,
    `created_at`
FROM `ict_topic_mapping`
WHERE NOT EXISTS (
    SELECT 1 FROM `mapping_ict` WHERE `mapping_ict`.`question_id` = `ict_topic_mapping`.`question_id`
);

-- ============================================
-- ISSUE 3: Standardize Civic Education naming
-- ============================================

-- Update subjectname table to use consistent naming
UPDATE `subjectname` SET `name` = 'Civic Education' WHERE `name` = 'civic';

-- Update any references in subjectyear (this should cascade if FKs are set up properly)
-- For safety, we'll check if there are any inconsistencies
SELECT 'Checking Civic Education naming consistency...' as status;

-- ============================================
-- ISSUE 4: Handle empty subjects (Technical Drawing, Financial Accounting)
-- ============================================

-- For now, we'll add subjectyear entries so they appear in the system
-- Even without questions, this makes the system consistent

-- Check if Technical Drawing has any subjectyear entries
SELECT COUNT(*) as td_count FROM `subjectyear` 
WHERE `subjectnamrid` = (SELECT `id` FROM `subjectname` WHERE `name` = 'Technical Drawing');

-- If none, add placeholder entries (commented out - requires user decision)
-- INSERT INTO `subjectyear` (`subjectnamrid`, `year`, `category`) VALUES
-- ((SELECT `id` FROM `subjectname` WHERE `name` = 'Technical Drawing'), '2024', 'utme'),
-- ((SELECT `id` FROM `subjectname` WHERE `name` = 'Technical Drawing'), '2024', 'ssce');

-- Same for Financial Accounting
-- INSERT INTO `subjectyear` (`subjectnamrid`, `year`, `category`) VALUES
-- ((SELECT `id` FROM `subjectname` WHERE `name` = 'Financial accounting'), '2024', 'utme'),
-- ((SELECT `id` FROM `subjectname` WHERE `name` = 'Financial accounting'), '2024', 'ssce');

-- ============================================
-- ISSUE 5: Add performance indexes
-- ============================================

-- Add indexes to questions table
ALTER TABLE `questions` ADD INDEX IF NOT EXISTS `idx_subjectyear_id` (`subjectyear_id`);
ALTER TABLE `questions` ADD INDEX IF NOT EXISTS `idx_correct_option` (`correct_option`);

-- Add indexes to subjectyear table
ALTER TABLE `subjectyear` ADD INDEX IF NOT EXISTS `idx_subjectnamrid` (`subjectnamrid`);
ALTER TABLE `subjectyear` ADD INDEX IF NOT EXISTS `idx_year_category` (`year`, `category`);

-- Add indexes to user_answers table
ALTER TABLE `user_answers` ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`);
ALTER TABLE `user_answers` ADD INDEX IF NOT EXISTS `idx_question_id` (`question_id`);
ALTER TABLE `user_answers` ADD INDEX IF NOT EXISTS `idx_session_id` (`session_id`);

-- Add indexes to user_topic_performance table
ALTER TABLE `user_topic_performance` ADD INDEX IF NOT EXISTS `idx_user_subject` (`user_id`, `subject_name`);
ALTER TABLE `user_topic_performance` ADD INDEX IF NOT EXISTS `idx_topic_lookup` (`user_id`, `subject_name`, `topic`);

-- Add indexes to user_performance table
ALTER TABLE `user_performance` ADD INDEX IF NOT EXISTS `idx_user_subject_lookup` (`user_id`, `subject_name`);

-- Add indexes to user_sessions table
ALTER TABLE `user_sessions` ADD INDEX IF NOT EXISTS `idx_user_session_lookup` (`user_id`);
ALTER TABLE `user_sessions` ADD INDEX IF NOT EXISTS `idx_subject_session` (`subject_name`, `category`);

-- ============================================
-- ISSUE 6: Create initial topic mappings for Geography and Commerce
-- ============================================

-- For Geography - Create basic topic structure based on common Geography curriculum
-- This will need to be populated with actual question mappings later

-- For Commerce - Create basic topic structure based on common Commerce curriculum
-- This will need to be populated with actual question mappings later

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Check that new tables exist
SELECT 
    'mapping_geography' as table_name, 
    TABLE_ROWS as row_count 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mapping_geography'
UNION ALL
SELECT 
    'mapping_commerce' as table_name, 
    TABLE_ROWS as row_count 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mapping_commerce'
UNION ALL
SELECT 
    'mapping_government' as table_name, 
    TABLE_ROWS as row_count 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mapping_government'
UNION ALL
SELECT 
    'mapping_history' as table_name, 
    TABLE_ROWS as row_count 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mapping_history'
UNION ALL
SELECT 
    'mapping_ict' as table_name, 
    TABLE_ROWS as row_count 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mapping_ict';

-- Check Civic Education naming
SELECT 'Civic Education naming check:' as check_type, `name` FROM `subjectname` WHERE `name` LIKE '%civic%' OR `name` LIKE '%Civic%';

-- Check indexes created
SELECT 
    TABLE_NAME, 
    INDEX_NAME, 
    COLUMN_NAME 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME IN ('questions', 'subjectyear', 'user_answers', 'user_topic_performance', 'user_performance', 'user_sessions')
ORDER BY TABLE_NAME, INDEX_NAME;

-- ============================================
-- COMPLETION MESSAGE
-- ============================================

SELECT 'Database migration completed successfully!' as status;
SELECT 'Please review the results above and verify all changes.' as next_step;
SELECT 'For Geography and Commerce, you will need to manually map questions to topics.' as action_required;