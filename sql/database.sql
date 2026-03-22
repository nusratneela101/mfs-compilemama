-- MFS Compilemama Database Schema
-- Created for Bangladesh MFS Portal

CREATE DATABASE IF NOT EXISTS mfs_compilemama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE mfs_compilemama;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(15) NOT NULL UNIQUE,
    `pin_hash` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `status` ENUM('active','inactive','blocked') NOT NULL DEFAULT 'inactive',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_phone` (`phone`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscriptions table
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL DEFAULT 150.00,
    `status` ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'bkash',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `transaction_id` VARCHAR(100) NOT NULL,
    `status` ENUM('pending','completed','failed') NOT NULL DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `approved_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_transaction_id` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OTP codes table
CREATE TABLE IF NOT EXISTS `otp_codes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(15) NOT NULL,
    `code` VARCHAR(6) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `verified` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_phone` (`phone`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions table
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `mfs_provider` VARCHAR(50) NOT NULL,
    `type` ENUM('send','cashout','recharge','payment','balance') NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `recipient` VARCHAR(100) DEFAULT NULL,
    `status` ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    `reference` VARCHAR(100) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_mfs_provider` (`mfs_provider`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MFS Providers table
CREATE TABLE IF NOT EXISTS `mfs_providers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_bn` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `color` VARCHAR(7) NOT NULL DEFAULT '#E2136E',
    `icon` VARCHAR(10) NOT NULL DEFAULT '💳',
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin users table
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate limits table
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(100) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
    `reset_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_identifier_action` (`identifier`, `action`),
    INDEX `idx_reset_at` (`reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- MFS Providers seed data
INSERT INTO `mfs_providers` (`name`, `name_bn`, `slug`, `color`, `icon`, `status`, `sort_order`) VALUES
('bKash',              'বিকাশ',           'bkash',        '#E2136E', '💰', 'active', 1),
('Nagad',              'নগদ',             'nagad',        '#F16528', '🟠', 'active', 2),
('Rocket',             'রকেট',            'rocket',       '#8B1A7C', '🚀', 'active', 3),
('Upay',               'উপায়',            'upay',         '#00A651', '💚', 'active', 4),
('Tap',                'ট্যাপ',            'tap',          '#0066CC', '🔵', 'active', 5),
('SureCash',           'শিউরক্যাশ',        'surecash',     '#FF6600', '🔶', 'active', 6),
('T-Cash',             'টি-ক্যাশ',         'tcash',        '#FFD700', '🌟', 'active', 7),
('CelFin',             'সেলফিন',           'celfin',       '#CC0000', '🔴', 'active', 8),
('OK Wallet',          'ওকে ওয়ালেট',       'okwallet',     '#006633', '✅', 'active', 9),
('UCash',              'ইউক্যাশ',          'ucash',        '#FF9900', '🟡', 'active', 10),
('Islamic Bank mCash', 'আইবিএম ক্যাশ',    'ibmcash',      '#006400', '🕌', 'active', 11),
('M Class',            'এম ক্লাস',         'mclass',       '#6600CC', '💜', 'active', 12),
('MyCash',             'মাইক্যাশ',         'mycash',       '#CC3300', '💸', 'active', 13);

-- Admin user (password: admin123)
INSERT INTO `admin_users` (`username`, `password_hash`, `email`) VALUES
('admin', '$2y$12$c2nYXXsX/pIqrCrArvDZ2.xSaCK76hnyMyVIShCpQG6ArmUvDxrW2', 'admin@mfscompilemama.com');

-- ============================================================
-- NOTE: The admin password hash above is for 'password' (Laravel default).
-- Run the following PHP to regenerate for 'admin123':
-- echo password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
-- The install script will update this automatically.
-- ============================================================
