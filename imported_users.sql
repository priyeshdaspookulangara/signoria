-- =====================================================================
-- Auto-Generated User Inserts from Forwarded Chat Records
-- Note: 'INSERT IGNORE' automatically skips any duplicate usernames/emails.
-- Default Sponsor ID: 1 (Admin) or root sponsor
-- Default Password: 'password' (bcrypt: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
-- =====================================================================

INSERT IGNORE INTO `users` (`username`, `sponsor_id`, `email`, `password`, `usdt_wallet`, `role`, `status`, `created_at`) VALUES
('rajesh.kd', 1, 'rajeshkd21@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('davis.kd', 1, 'daviskd63@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('sojan.kd', 1, 'sojankd77@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('shaju.kd', 1, 'shajukd75@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('benny.kd', 1, 'bennykd57@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('sunny.kd', 1, 'sunnykd70@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('antony.kd', 1, 'antonykd60@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('johnson.kd', 1, 'johnsonkd64@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('francis.kd', 1, 'franciskd66@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('paulson.kd', 1, 'paulsonkd62@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('joy.kd', 1, 'joykd58@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('george.kd', 1, 'georgekd58@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('thomas.kd', 1, 'thomaskd60@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('babu.kd', 1, 'babukd59@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('varghese.kd', 1, 'varghesekd53@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('vincent.kd', 1, 'vincentkd55@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('jimmy.kd', 1, 'jimmykd67@yahoo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW()),
('jayadevan.v', 1, 'jayadevan274@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '', 'member', 'active', NOW());
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
