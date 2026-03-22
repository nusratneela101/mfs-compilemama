-- Email Verification tokens table
-- Run this migration to enable email-based account verification.

CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token` (`token`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
