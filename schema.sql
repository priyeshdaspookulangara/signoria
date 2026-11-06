-- =======================================================
-- MLM 3x Matrix Auto-Spillover BFS Engine Schema (USDT)
-- =======================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `matrix_nodes`;
DROP TABLE IF EXISTS `users`;
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
  `qr_code_path` VARCHAR(255) NULL,
  `role` ENUM('admin', 'member') NOT NULL DEFAULT 'member',
  `status` ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_users_sponsor` (`sponsor_id`),
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Matrix Nodes Table (BFS Tree Tracking)
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

-- 3. Transactions Table
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('deposit', 'withdrawal', 'matrix_payout', 'rebirth_fee', 'package_upgrade') NOT NULL,
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

-- 4. Activity Logs Table
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `action` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_logs_user` (`user_id`),
  INDEX `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. System Settings Table (Company USDT Deposit Wallet & QR Code)
CREATE TABLE `system_settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =======================================================
-- Default Seed Data
-- =======================================================

-- 1. Insert Admin (id: 1, pass: admin123)
-- 2. Insert Test Member (id: 2, pass: member123)
-- 3. Insert Additional seeded test members for immediate tree visualization
INSERT INTO `users` (`id`, `username`, `sponsor_id`, `email`, `password`, `usdt_wallet`, `role`, `status`, `created_at`) VALUES
(1, 'admin', NULL, 'admin@matrixmlm.com', '$2y$10$PFGAQc/UMuGtLnwXhFDbZeSXSY9iDLLNsoONAGJSvNocZ5v0wp7zC', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', 'admin', 'active', NOW()),
(2, 'alex_leader', 1, 'alex@example.com', '$2y$10$gkJ3ou2VEW3EGrNU6Xw03.psMFucWWAgFljJ9hrNrCcs1nUi4RwXi', 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', 'member', 'active', NOW()),
(3, 'bob_crypto', 2, 'bob@example.com', '$2y$10$gkJ3ou2VEW3EGrNU6Xw03.psMFucWWAgFljJ9hrNrCcs1nUi4RwXi', 'TPYmHEhy5n8TCEfYGqW2rPxsghSfzghPDn', 'member', 'active', NOW()),
(4, 'carol_trader', 2, 'carol@example.com', '$2y$10$gkJ3ou2VEW3EGrNU6Xw03.psMFucWWAgFljJ9hrNrCcs1nUi4RwXi', 'TVDGm34jDYz31GkXU1W2rPxsghSfzghPDm', 'member', 'active', NOW()),
(5, 'david_defi', 3, 'david@example.com', '$2y$10$gkJ3ou2VEW3EGrNU6Xw03.psMFucWWAgFljJ9hrNrCcs1nUi4RwXi', 'TK9Xn5H6eT31GkXU1W2rPxsghSfzghPDk', 'member', 'active', NOW());

-- Seed initial root matrix nodes for Package 1 & 2
-- Node 1: Root user 1 (Admin) in Package 1 (Pos 1, Level 1)
-- Node 2: Root user 1 (Admin) in Package 2 (Pos 1, Level 1)
INSERT INTO `matrix_nodes` (`id`, `user_id`, `package_type`, `level`, `position_index`, `parent_node_id`, `status`, `created_at`) VALUES
(1, 1, 'package_1', 1, 1, NULL, 'active', NOW()),
(2, 1, 'package_2', 1, 1, NULL, 'active', NOW()),
-- Children of Admin in Package 1 (Level 2: positions 2, 3, 4)
(3, 2, 'package_1', 2, 2, 1, 'active', NOW()),
(4, 3, 'package_1', 2, 3, 1, 'active', NOW()),
(5, 4, 'package_1', 2, 4, 1, 'active', NOW()),
-- Child under node 3 (alex_leader) in Package 1 (Level 3: position 5)
(6, 5, 'package_1', 3, 5, 3, 'active', NOW()),
-- Children in Package 2
(7, 2, 'package_2', 2, 2, 2, 'active', NOW()),
(8, 3, 'package_2', 2, 3, 2, 'active', NOW());

-- Seed sample completed transactions
INSERT INTO `transactions` (`user_id`, `type`, `amount_usdt`, `tx_hash`, `status`, `details`, `created_at`) VALUES
(2, 'deposit', 50.00, '0x8f23ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcdef1', 'completed', 'Approved deposit for Package 1 entry', NOW()),
(2, 'matrix_payout', 30.00, NULL, 'completed', 'Level 1 matrix payout bonus from child node #6', NOW()),
(3, 'deposit', 300.00, '0x1234ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcde02', 'completed', 'Approved deposit for Package 2 entry', NOW()),
(4, 'deposit', 50.00, '0x4567ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcde03', 'completed', 'Package 1 entry', NOW()),
(5, 'deposit', 50.00, '0x9999ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcde04', 'completed', 'Package 1 entry', NOW()),
(2, 'withdrawal', 30.00, '0x3333ba56c321a9e8754bde29871029cba82716354dfa9807123456789abcde05', 'completed', 'Payout to wallet TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', NOW()),
(3, 'withdrawal', 50.00, NULL, 'pending', 'Pending member withdrawal request', NOW());

-- Seed sample activity logs
INSERT INTO `activity_logs` (`user_id`, `action`, `ip_address`, `created_at`) VALUES
(1, 'System initialized schema and root tree matrices', '127.0.0.1', NOW()),
(2, 'User registered and placed into Package 1 (Node #3) and Package 2 (Node #7)', '127.0.0.1', NOW()),
(3, 'User registered and placed into Package 1 (Node #4) and Package 2 (Node #8)', '127.0.0.1', NOW()),
(4, 'User registered and placed into Package 1 (Node #5)', '127.0.0.1', NOW()),
(5, 'User registered and placed into Package 1 (Node #6)', '127.0.0.1', NOW());

-- Seed default company deposit settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('company_usdt_wallet', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
('company_usdt_network', 'TRC20'),
('company_qr_code', NULL);
