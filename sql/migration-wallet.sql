-- Digital Wallet Migration
-- MFS Compilemama — Wallet / E-Wallet Feature

-- Wallet table (one per user)
CREATE TABLE IF NOT EXISTS `wallets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL UNIQUE,
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_added` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_withdrawn` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_transferred` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_fees` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `free_limit_used` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('active','frozen','closed') NOT NULL DEFAULT 'active',
    `pin_hash` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wallet transactions table
CREATE TABLE IF NOT EXISTS `wallet_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `wallet_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `type` ENUM('add_money','withdraw','transfer_in','transfer_out','fee') NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `fee` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `mfs_provider` VARCHAR(50) DEFAULT NULL,
    `mfs_account` VARCHAR(20) DEFAULT NULL,
    `recipient_user_id` INT UNSIGNED DEFAULT NULL,
    `reference_id` VARCHAR(50) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `balance_before` DECIMAL(12,2) NOT NULL,
    `balance_after` DECIMAL(12,2) NOT NULL,
    `status` ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'pending',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`wallet_id`) REFERENCES `wallets`(`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    INDEX `idx_user_created` (`user_id`, `created_at`),
    INDEX `idx_reference` (`reference_id`),
    INDEX `idx_type` (`type`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
