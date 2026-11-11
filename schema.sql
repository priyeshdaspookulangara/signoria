-- =======================================================
-- MLM 3x Matrix Auto-Spillover BFS Engine Schema (USDT)
-- =======================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `usdt_payments`;
DROP TABLE IF EXISTS `packages`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `matrix_nodes`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `system_settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `sponsor_id` INT NULL,
  `email` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `gender` ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL,
  `dob` DATE NULL,
  `address` TEXT NULL,
  `city` VARCHAR(100) NULL,
  `district` VARCHAR(100) NULL,
  `state_province` VARCHAR(100) NULL,
  `country` VARCHAR(100) NULL,
  `password` VARCHAR(255) NOT NULL,
  `usdt_wallet` VARCHAR(100) NULL,
  `usdt_network` VARCHAR(30) DEFAULT 'TRC20',
  `usdt_trc20_address` VARCHAR(128) NULL,
  `usdt_bep20_address` VARCHAR(128) NULL,
  `usdt_qr_path` VARCHAR(255) NULL,
  `qr_code_path` VARCHAR(255) NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_sponsor` (`sponsor_id`),
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Packages Table
CREATE TABLE `packages` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `inr_price` DECIMAL(15, 2) NOT NULL,
  `usdt_price` DECIMAL(15, 2) NOT NULL,
  `matrix_type` ENUM('package_1', 'package_2') NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Matrix Nodes Table (BFS Tree Tracking)
CREATE TABLE `matrix_nodes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `package_type` ENUM('package_1', 'package_2') NOT NULL,
  `level` INT NOT NULL DEFAULT 1,
  `position_index` INT NOT NULL DEFAULT 1,
  `parent_node_id` INT NULL,
  `status` ENUM('active', 'completed', 'rebirthed') NOT NULL DEFAULT 'active',
  `is_rebirth` TINYINT(1) NOT NULL DEFAULT 0,
  `rebirth_source_node_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_nodes_user` (`user_id`),
  INDEX `idx_nodes_package` (`package_type`),
  INDEX `idx_nodes_parent` (`parent_node_id`),
  INDEX `idx_nodes_pkg_pos` (`package_type`, `position_index`),
  INDEX `idx_nodes_status` (`status`),
  CONSTRAINT `fk_nodes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Transactions Table
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('deposit', 'withdrawal', 'matrix_payout', 'rebirth_fee', 'package_upgrade', 'referral_income') NOT NULL,
  `amount_usdt` DECIMAL(18,2) NOT NULL,
  `tx_hash` VARCHAR(128) NULL,
  `status` ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  `details` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_tx_user` (`user_id`),
  INDEX `idx_tx_type` (`type`),
  INDEX `idx_tx_status` (`status`),
  CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. USDT Payments / Activations Table
CREATE TABLE `usdt_payments` (
  `id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `package_id` INT NOT NULL,
  `network` ENUM('TRC20', 'BEP20') NOT NULL,
  `deposit_address` VARCHAR(128) NOT NULL,
  `inr_amount` DECIMAL(15, 2) NOT NULL,
  `usdt_rate` DECIMAL(10, 4) NOT NULL,
  `usdt_amount` DECIMAL(15, 2) NOT NULL,
  `transaction_hash` VARCHAR(128) NOT NULL,
  `screenshot_path` VARCHAR(255) NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` TEXT NULL,
  `reviewed_by` INT NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_usdt_user_id` (`user_id`),
  INDEX `idx_usdt_package_id` (`package_id`),
  INDEX `idx_usdt_tx_hash` (`transaction_hash`),
  INDEX `idx_usdt_status` (`status`),
  CONSTRAINT `fk_usdt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_usdt_pkg` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Activity Logs Table
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_logs_user` (`user_id`),
  INDEX `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. System Settings Table
CREATE TABLE `system_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- Default Seed Data
-- =======================================================

-- Users
INSERT INTO `users` (`id`, `username`, `sponsor_id`, `email`, `password`, `usdt_wallet`, `usdt_network`, `usdt_trc20_address`, `usdt_bep20_address`, `role`, `status`, `created_at`) VALUES
(1, 'admin', NULL, 'admin@matrixmlm.com', '$2y$10$RgRo9x2bqGFj2ekH05QMpOGffs2l2yxz3k8h7hd9R.zURElM8EtQu', '0x572790c26FC062C247D0e1797D646F20a093AE14', 'BEP20', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', '0x572790c26FC062C247D0e1797D646F20a093AE14', 'admin', 'active', NOW()),
(2, 'alex_leader', 1, 'alex@example.com', '$2y$10$RgRo9x2bqGFj2ekH05QMpOGffs2l2yxz3k8h7hd9R.zURElM8EtQu', 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', 'TRC20', 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', '0x71C8395B0F751beF48cf1E8e8A3669a8B8497645', 'member', 'active', NOW()),
(3, 'bob_crypto', 2, 'bob@example.com', '$2y$10$RgRo9x2bqGFj2ekH05QMpOGffs2l2yxz3k8h7hd9R.zURElM8EtQu', 'TPYmHEhy5n8TCEfYGqW2rPxsghSfzghPDn', 'TRC20', 'TPYmHEhy5n8TCEfYGqW2rPxsghSfzghPDn', '0x21C8395B0F751beF48cf1E8e8A3669a8B8497123', 'member', 'active', NOW()),
(4, 'carol_trader', 2, 'carol@example.com', '$2y$10$RgRo9x2bqGFj2ekH05QMpOGffs2l2yxz3k8h7hd9R.zURElM8EtQu', 'TVDGm34jDYz31GkXU1W2rPxsghSfzghPDm', 'TRC20', 'TVDGm34jDYz31GkXU1W2rPxsghSfzghPDm', '0x31C8395B0F751beF48cf1E8e8A3669a8B8497456', 'member', 'active', NOW()),
(5, 'david_defi', 3, 'david@example.com', '$2y$10$RgRo9x2bqGFj2ekH05QMpOGffs2l2yxz3k8h7hd9R.zURElM8EtQu', 'TK9Xn5H6eT31GkXU1W2rPxsghSfzghPDk', 'TRC20', 'TK9Xn5H6eT31GkXU1W2rPxsghSfzghPDk', '0x41C8395B0F751beF48cf1E8e8A3669a8B8497789', 'member', 'active', NOW());

-- Packages Seed
INSERT INTO `packages` (`id`, `name`, `code`, `inr_price`, `usdt_price`, `matrix_type`, `description`, `status`) VALUES
(1, 'Tier 1 Starter (₹4,500 / 50 USDT)', 'tier_1_starter', 4500.00, 50.00, 'package_1', 'Tier 1 Entry: 6 Matrix Levels, 60% commission distribution, automated rebirth booster generation.', 'active'),
(2, 'Tier 2 Pro (₹27,000 / 300 USDT)', 'tier_2_pro', 27000.00, 300.00, 'package_2', 'Tier 2 Entry: 7 Matrix Levels, 60% commission distribution, multi-stage rebirth booster generation.', 'active'),
(3, 'Micro Booster (₹500 / ~5.55 USDT)', 'tier_micro_500', 500.00, 5.56, 'package_1', 'Entry micro-tier package with 6 levels access.', 'active'),
(4, 'Basic Package (₹1,000 / ~11.11 USDT)', 'tier_basic_1000', 1000.00, 11.11, 'package_1', 'Standard entry package.', 'active'),
(5, 'Silver Package (₹2,500 / ~27.78 USDT)', 'tier_silver_2500', 2500.00, 27.78, 'package_1', 'Silver tier package.', 'active'),
(6, 'Gold Package (₹5,000 / ~55.56 USDT)', 'tier_gold_5000', 5000.00, 55.56, 'package_1', 'Gold tier package.', 'active'),
(7, 'Platinum Package (₹10,000 / ~111.11 USDT)', 'tier_plat_10000', 10000.00, 111.11, 'package_1', 'Platinum tier package.', 'active'),
(8, 'Diamond Package (₹25,000 / ~277.78 USDT)', 'tier_diam_25000', 25000.00, 277.78, 'package_2', 'Diamond tier package with 7 levels access.', 'active'),
(9, 'Crown Ambassador (₹50,000 / ~555.56 USDT)', 'tier_crown_50000', 50000.00, 555.56, 'package_2', 'Crown tier with high-depth commissions.', 'active'),
(10, 'Executive VIP (₹1,00,000 / ~1111.11 USDT)', 'tier_vip_100000', 100000.00, 1111.11, 'package_2', 'Top executive tier with max spillover priority.', 'active');

-- Matrix Nodes
INSERT INTO `matrix_nodes` (`id`, `user_id`, `package_type`, `level`, `position_index`, `parent_node_id`, `status`, `created_at`) VALUES
(1, 1, 'package_1', 1, 1, NULL, 'active', NOW()),
(2, 1, 'package_2', 1, 1, NULL, 'active', NOW()),
(3, 2, 'package_1', 2, 2, 1, 'active', NOW()),
(4, 3, 'package_1', 2, 3, 1, 'active', NOW()),
(5, 4, 'package_1', 2, 4, 1, 'active', NOW()),
(6, 5, 'package_1', 3, 5, 3, 'active', NOW()),
(7, 2, 'package_2', 2, 2, 2, 'active', NOW()),
(8, 3, 'package_2', 2, 3, 2, 'active', NOW());

-- Transactions
INSERT INTO `transactions` (`user_id`, `type`, `amount_usdt`, `tx_hash`, `status`, `details`, `created_at`) VALUES
(2, 'deposit', 50.00, '0x8f23ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcdef1', 'completed', 'Approved deposit for Package 1 entry', NOW()),
(2, 'matrix_payout', 30.00, NULL, 'completed', 'Level 1 matrix payout bonus from child node #6', NOW()),
(3, 'deposit', 300.00, '0x1234ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcde02', 'completed', 'Approved deposit for Package 2 entry', NOW()),
(4, 'deposit', 50.00, '0x4567ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcde03', 'completed', 'Package 1 entry', NOW()),
(5, 'deposit', 50.00, '0x9999ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcde04', 'completed', 'Package 1 entry', NOW()),
(2, 'withdrawal', 30.00, '0x3333ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcde05', 'completed', 'Payout to wallet TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', NOW()),
(3, 'withdrawal', 50.00, NULL, 'pending', 'Pending member withdrawal request', NOW());

-- System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('usdt_conversion_rate', '90.00'),
('usdt_trc20_address', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
('usdt_bep20_address', '0x38CC4cd0433e64dAC73Dca87DDedDac27d12bC59'),
('usdt_trc20_qr_path', 'uploads/qr_codes/company_official_qr.png'),
('usdt_bep20_qr_path', 'uploads/qr_codes/company_bep20_qr.png'),
('company_usdt_wallet', '0x38CC4cd0433e64dAC73Dca87DDedDac27d12bC59'),
('company_usdt_network', 'BEP20'),
('company_qr_code', 'uploads/qr_codes/company_bep20_qr.png');
