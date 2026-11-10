-- =========================================================================================
-- SQL INSERT Statements for:
-- 1. Sheela (name: seela Balan, phone: +91 70346 13983, email: sheela@zenitharassets.com)
-- 2. Byju ND (phone: +91 85478 44689, email: byjund@zenitharassets.com)
-- 3. Zenithar (name: Zenithar Assets, email: info@zenithar)
-- 4. 4 additional IDs of Zenithar (name: Zenithar Assets, email: info@zenithar)
--
-- Note:
-- - Uses 'INSERT IGNORE' to prevent duplicate errors if rerun.
-- - Default password is 'password' (bcrypt hashed).
-- - Sponsor ID is set to 1 (Admin/root).
-- - Email does not have a UNIQUE constraint in schema, allowing multiple Zenithar IDs under info@zenithar.
-- =========================================================================================

INSERT IGNORE INTO `users` (
    `username`,
    `sponsor_id`,
    `email`,
    `phone`,
    `password`,
    `usdt_wallet`,
    `usdt_network`,
    `role`,
    `status`,
    `created_at`
) VALUES
-- 1. Sheela (seela Balan)
('sheela', 1, 'sheela@zenitharassets.com', '+91 70346 13983', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'TRC20', 'member', 'active', NOW()),

-- 2. Byju ND
('byjund', 1, 'byjund@zenitharassets.com', '+91 85478 44689', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'TRC20', 'member', 'active', NOW()),

-- 3. Zenithar (Primary ID)
('zenithar', 1, 'info@zenithar', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'TRC20', 'member', 'active', NOW()),

-- 4. Four additional IDs of Zenithar (sharing info@zenithar)
('zenithar_1', 1, 'info@zenithar', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'TRC20', 'member', 'active', NOW()),
('zenithar_2', 1, 'info@zenithar', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'TRC20', 'member', 'active', NOW()),
('zenithar_3', 1, 'info@zenithar', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'TRC20', 'member', 'active', NOW()),
('zenithar_4', 1, 'info@zenithar', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'TRC20', 'member', 'active', NOW());
