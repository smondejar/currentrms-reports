-- CurrentRMS Report Builder Database Schema
-- Run this SQL to create the necessary tables

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Users table
CREATE TABLE IF NOT EXISTS `crms_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'manager', 'user', 'viewer') NOT NULL DEFAULT 'user',
    `permissions` JSON DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'deleted') NOT NULL DEFAULT 'active',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `preferences` JSON DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `reset_token` VARCHAR(100) DEFAULT NULL,
    `reset_expires` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reports table
CREATE TABLE IF NOT EXISTS `crms_reports` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `module` VARCHAR(50) NOT NULL,
    `config` JSON NOT NULL,
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `run_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `last_run` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_module` (`module`),
    INDEX `idx_is_public` (`is_public`),
    FOREIGN KEY (`user_id`) REFERENCES `crms_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dashboards table
CREATE TABLE IF NOT EXISTS `crms_dashboards` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL DEFAULT 'My Dashboard',
    `config` JSON DEFAULT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `crms_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Widgets table
CREATE TABLE IF NOT EXISTS `crms_widgets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `dashboard_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 0,
    `grid_cols` TINYINT UNSIGNED NOT NULL DEFAULT 6,
    `grid_rows` TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `config` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_dashboard_id` (`dashboard_id`),
    INDEX `idx_position` (`position`),
    FOREIGN KEY (`dashboard_id`) REFERENCES `crms_dashboards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled reports table
CREATE TABLE IF NOT EXISTS `crms_scheduled_reports` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `schedule` VARCHAR(50) NOT NULL COMMENT 'daily, weekly, monthly',
    `day_of_week` TINYINT DEFAULT NULL COMMENT '0=Sunday, 6=Saturday',
    `day_of_month` TINYINT DEFAULT NULL COMMENT '1-31',
    `time` TIME NOT NULL DEFAULT '08:00:00',
    `email_to` TEXT DEFAULT NULL,
    `format` VARCHAR(20) NOT NULL DEFAULT 'csv',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_sent` DATETIME DEFAULT NULL,
    `next_send` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    INDEX `idx_report_id` (`report_id`),
    INDEX `idx_next_send` (`next_send`),
    INDEX `idx_is_active` (`is_active`),
    FOREIGN KEY (`report_id`) REFERENCES `crms_reports`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `crms_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log table
CREATE TABLE IF NOT EXISTS `crms_activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) DEFAULT NULL,
    `entity_id` INT UNSIGNED DEFAULT NULL,
    `details` JSON DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `crms_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE IF NOT EXISTS `crms_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT DEFAULT NULL,
    `type` VARCHAR(20) NOT NULL DEFAULT 'string',
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cache table (for API response caching)
CREATE TABLE IF NOT EXISTS `crms_cache` (
    `key` VARCHAR(255) PRIMARY KEY,
    `value` LONGTEXT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123)
INSERT INTO `crms_users` (`name`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`)
VALUES ('Administrator', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Insert default settings
INSERT INTO `crms_settings` (`key`, `value`, `type`, `description`, `updated_at`) VALUES
('company_name', 'CurrentRMS Reports', 'string', 'Company name displayed in reports', NOW()),
('timezone', 'UTC', 'string', 'Default timezone', NOW()),
('date_format', 'M j, Y', 'string', 'Date display format', NOW()),
('currency_symbol', '$', 'string', 'Currency symbol', NOW()),
('items_per_page', '25', 'number', 'Default items per page', NOW()),
('cache_duration', '300', 'number', 'API cache duration in seconds', NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

SET FOREIGN_KEY_CHECKS = 1;
