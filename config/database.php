<?php
/**
 * Database Connection Wrapper & Self-Healing Table Migration (PDO)
 * MLM 3x Matrix Auto-Spillover Platform
 */

declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'mlm_matrix';
        $user = getenv('DB_USER') ?: 'mlm_user';
        $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'mlm_pass123';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
            self::ensureTables(self::$instance);
            return self::$instance;
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please ensure the database server is running.'
            ]));
        }
    }

    /**
     * Self-healing database verification routine:
     * Automatically ensures all required tables, columns, indexes, and settings exist.
     */
    public static function ensureTables(PDO $pdo): void {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            // 1. Ensure `packages` table exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `packages` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `code` VARCHAR(50) NOT NULL UNIQUE,
                    `inr_price` DECIMAL(15, 2) NOT NULL,
                    `usdt_price` DECIMAL(15, 2) NOT NULL,
                    `matrix_type` ENUM('package_1', 'package_2') NOT NULL,
                    `description` TEXT NULL,
                    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // Seed default packages if empty (₹500 to ₹100,000 tiers + Tier 1 & Tier 2)
            $pkgCount = (int)$pdo->query("SELECT COUNT(*) FROM `packages`")->fetchColumn();
            if ($pkgCount === 0) {
                $packages = [
                    ['Tier 1 Starter (₹4,500 / 50 USDT)', 'tier_1_starter', 4500.00, 50.00, 'package_1', 'Tier 1 Entry: 6 Matrix Levels, 60% commission distribution, automated rebirth booster generation.'],
                    ['Tier 2 Pro (₹27,000 / 300 USDT)', 'tier_2_pro', 27000.00, 300.00, 'package_2', 'Tier 2 Entry: 7 Matrix Levels, 60% commission distribution, multi-stage rebirth booster generation.'],
                    ['Micro Booster (₹500 / ~5.55 USDT)', 'tier_micro_500', 500.00, 5.56, 'package_1', 'Entry micro-tier package with 6 levels access.'],
                    ['Basic Package (₹1,000 / ~11.11 USDT)', 'tier_basic_1000', 1000.00, 11.11, 'package_1', 'Standard entry package.'],
                    ['Silver Package (₹2,500 / ~27.78 USDT)', 'tier_silver_2500', 2500.00, 27.78, 'package_1', 'Silver tier package.'],
                    ['Gold Package (₹5,000 / ~55.56 USDT)', 'tier_gold_5000', 5000.00, 55.56, 'package_1', 'Gold tier package.'],
                    ['Platinum Package (₹10,000 / ~111.11 USDT)', 'tier_plat_10000', 10000.00, 111.11, 'package_1', 'Platinum tier package.'],
                    ['Diamond Package (₹25,000 / ~277.78 USDT)', 'tier_diam_25000', 25000.00, 277.78, 'package_2', 'Diamond tier package with 7 levels access.'],
                    ['Crown Ambassador (₹50,000 / ~555.56 USDT)', 'tier_crown_50000', 50000.00, 555.56, 'package_2', 'Crown tier with high-depth commissions.'],
                    ['Executive VIP (₹1,00,000 / ~1111.11 USDT)', 'tier_vip_100000', 100000.00, 1111.11, 'package_2', 'Top executive tier with max spillover priority.']
                ];

                $insStmt = $pdo->prepare("
                    INSERT INTO `packages` (`name`, `code`, `inr_price`, `usdt_price`, `matrix_type`, `description`, `status`)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                foreach ($packages as $pkg) {
                    $insStmt->execute($pkg);
                }
            }

            // 2. Ensure `usdt_payments` table exists
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `usdt_payments` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
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
                    PRIMARY KEY (`id`),
                    KEY `idx_usdt_user_id` (`user_id`),
                    KEY `idx_usdt_package_id` (`package_id`),
                    KEY `idx_usdt_tx_hash` (`transaction_hash`),
                    KEY `idx_usdt_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            // 3. Ensure columns exist on `users` table:
            // usdt_trc20_address, usdt_bep20_address, usdt_qr_path
            $userCols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('usdt_trc20_address', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `usdt_trc20_address` VARCHAR(128) NULL AFTER `usdt_network`");
            }
            if (!in_array('usdt_bep20_address', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `usdt_bep20_address` VARCHAR(128) NULL AFTER `usdt_trc20_address`");
            }
            if (!in_array('usdt_qr_path', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `usdt_qr_path` VARCHAR(255) NULL AFTER `usdt_bep20_address`");
            }

            // Sync legacy usdt_wallet / qr_code_path to new columns if new columns are null
            $pdo->exec("
                UPDATE `users`
                SET `usdt_trc20_address` = `usdt_wallet`
                WHERE `usdt_trc20_address` IS NULL AND `usdt_wallet` IS NOT NULL AND `usdt_network` = 'TRC20';

                UPDATE `users`
                SET `usdt_bep20_address` = `usdt_wallet`
                WHERE `usdt_bep20_address` IS NULL AND `usdt_wallet` IS NOT NULL AND `usdt_network` = 'BEP20';

                UPDATE `users`
                SET `usdt_qr_path` = `qr_code_path`
                WHERE `usdt_qr_path` IS NULL AND `qr_code_path` IS NOT NULL;
            ");

            // 4. Ensure `system_settings` table has corporate USDT keys
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `system_settings` (
                    `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
                    `setting_value` TEXT NULL,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");

            $defaultSettings = [
                'usdt_conversion_rate' => '90.00',
                'usdt_trc20_address'   => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'usdt_bep20_address'   => '0x572790c26FC062C247D0e1797D646F20a093AE14',
                'usdt_trc20_qr_path'   => 'uploads/qr_codes/company_official_qr.png',
                'usdt_bep20_qr_path'   => 'uploads/qr_codes/official_sample.png',
                // Keep backward-compatible keys
                'company_usdt_wallet'  => '0x572790c26FC062C247D0e1797D646F20a093AE14',
                'company_usdt_network' => 'BEP20',
                'company_qr_code'      => 'uploads/qr_codes/official_sample.png'
            ];

            $setStmt = $pdo->prepare("
                INSERT INTO `system_settings` (`setting_key`, `setting_value`)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE `setting_value` = IF(`setting_value` IS NULL OR `setting_value` = '', VALUES(`setting_value`), `setting_value`)
            ");
            foreach ($defaultSettings as $key => $val) {
                $setStmt->execute([$key, $val]);
            }

            $checked = true;
        } catch (Throwable $e) {
            error_log('Error in Database::ensureTables: ' . $e->getMessage());
        }
    }
}

if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection(): PDO {
        return Database::getConnection();
    }
}
