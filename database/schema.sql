-- Portable PHP File Storage Server - Database Schema
-- This file creates all necessary tables for the application

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `storage_used` BIGINT UNSIGNED DEFAULT 0,
    `storage_limit` BIGINT UNSIGNED DEFAULT 1073741824,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` TIMESTAMP NULL
);

-- Files table
CREATE TABLE IF NOT EXISTS `files` (
    `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) UNIQUE NOT NULL,
    `file_type` ENUM('static','dynamic','one-time','temp','persistent','private','public') DEFAULT 'private',
    `size` BIGINT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_hash` VARCHAR(64) NOT NULL,
    `storage_path` VARCHAR(500) NOT NULL,
    `description` TEXT NULL,
    `download_count` INT UNSIGNED DEFAULT 0,
    `max_downloads` INT UNSIGNED NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    `is_public` BOOLEAN DEFAULT FALSE,
    `is_deleted` BOOLEAN DEFAULT FALSE,
    `deleted_at` TIMESTAMP NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- User sessions table
CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- File shares table
CREATE TABLE IF NOT EXISTS `file_shares` (
    `id` INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `file_id` INT UNSIGNED NOT NULL,
    `share_token` VARCHAR(64) UNIQUE NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `access_count` INT UNSIGNED DEFAULT 0,
    `max_accesses` INT UNSIGNED NULL,
    `password` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- Activity logs table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `file_id` INT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `details` JSON NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE SET NULL
);

-- System settings table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(100) PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `data_type` ENUM('string','integer','boolean','json') DEFAULT 'string',
    `description` TEXT NULL,
    `is_editable` BOOLEAN DEFAULT TRUE,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_username` ON `users` (`username`);
CREATE INDEX IF NOT EXISTS `idx_email` ON `users` (`email`);
CREATE INDEX IF NOT EXISTS `idx_role` ON `users` (`role`);

CREATE INDEX IF NOT EXISTS `idx_user_files` ON `files` (`user_id`, `is_deleted`);
CREATE INDEX IF NOT EXISTS `idx_stored_name` ON `files` (`stored_name`);
CREATE INDEX IF NOT EXISTS `idx_file_type` ON `files` (`file_type`);
CREATE INDEX IF NOT EXISTS `idx_expires_at` ON `files` (`expires_at`);
CREATE INDEX IF NOT EXISTS `idx_file_hash` ON `files` (`file_hash`);

CREATE INDEX IF NOT EXISTS `idx_user_sessions` ON `user_sessions` (`user_id`, `is_active`);
CREATE INDEX IF NOT EXISTS `idx_session_expires` ON `user_sessions` (`expires_at`);

CREATE INDEX IF NOT EXISTS `idx_share_token` ON `file_shares` (`share_token`);
CREATE INDEX IF NOT EXISTS `idx_file_shares` ON `file_shares` (`file_id`, `is_active`);

CREATE INDEX IF NOT EXISTS `idx_user_logs` ON `activity_logs` (`user_id`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_action_logs` ON `activity_logs` (`action`, `created_at`);
CREATE INDEX IF NOT EXISTS `idx_file_logs` ON `activity_logs` (`file_id`, `created_at`);

-- Insert default system settings
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `data_type`, `description`) VALUES
('app_installed', 'true', 'boolean', 'Whether the app has been installed'),
('maintenance_mode', 'false', 'boolean', 'Whether the app is in maintenance mode'),
('max_upload_size', '104857600', 'integer', 'Maximum upload size in bytes (100MB)'),
('allowed_file_types', 'jpg,jpeg,png,gif,pdf,txt,doc,docx,zip,mp4,mp3', 'string', 'Allowed file extensions'),
('default_user_quota', '1073741824', 'integer', 'Default storage quota for new users (1GB)'),
('enable_public_registration', 'true', 'boolean', 'Allow public user registration'),
('session_timeout', '3600', 'integer', 'Session timeout in seconds'),
('cleanup_temp_files_hours', '24', 'integer', 'Hours after which temp files are deleted'),
('enable_file_versioning', 'true', 'boolean', 'Enable file versioning'),
('max_file_versions', '5', 'integer', 'Maximum number of file versions to keep');
