<?php
/**
 * Utility functions for USDT formatting, sanitization, logging and helpers
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Sanitize string for HTML output (XSS protection)
 */
function e(?string $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format USDT amount strictly with 2 decimal precision
 */
function formatUsdt(float|string|int|null $amount): string {
    $num = (float)($amount ?? 0);
    return number_format($num, 2, '.', ',');
}

/**
 * Parse monetary input to clean decimal string (18,2 compatible)
 */
function parseUsdt(float|string|int|null $amount): string {
    $cleaned = filter_var((string)$amount, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $val = round((float)$cleaned, 2);
    return sprintf('%.2f', max(0, $val));
}

/**
 * Calculate user's available USDT balance from transactions
 * Deposits (+) + Matrix Payouts (+) - Withdrawals (completed & pending) (-) - Rebirth Fees (-) - Package Upgrade (-)
 */
function getUserUsdtBalance(int $userId): float {
    $pdo = getDatabaseConnection();

    // Total credits: completed deposits, completed matrix_payouts, and referral_income
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_usdt), 0) AS total_in
        FROM transactions
        WHERE user_id = :uid AND status = 'completed' AND type IN ('deposit', 'matrix_payout', 'referral_income')
    ");
    $stmt->execute([':uid' => $userId]);
    $totalIn = (float)$stmt->fetchColumn();

    // Total debits: completed rebirth fees, package upgrades, and pending/completed withdrawals
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_usdt), 0) AS total_out
        FROM transactions
        WHERE user_id = :uid
          AND (
            (type IN ('rebirth_fee', 'package_upgrade') AND status = 'completed')
            OR (type = 'withdrawal' AND status IN ('pending', 'completed'))
          )
    ");
    $stmt->execute([':uid' => $userId]);
    $totalOut = (float)$stmt->fetchColumn();

    $balance = $totalIn - $totalOut;
    return max(0.0, round($balance, 2));
}

/**
 * Log activity in activity_logs table
 */
function logActivity(?int $userId, string $action): void {
    try {
        $pdo = getDatabaseConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, created_at) VALUES (:uid, :act, :ip, NOW())");
        $stmt->execute([
            ':uid' => $userId,
            ':act' => $action,
            ':ip'  => $ip,
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Flash message helpers
 */
function setFlash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message,
    ];
}

function getFlash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Helper to get current base URL
 */
function getBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . $host;
}

/**
 * CSRF Protection Helpers
 */
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function generateCsrfToken(): string {
    return csrfToken();
}

function csrfField(): string {
    $token = csrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verifyCsrfToken(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Handle secure QR Code image upload
 * Supports JPG, PNG, WEBP, GIF up to 5MB
 * Returns relative path or throws RuntimeException
 */
function handleQrCodeUpload(array $file): string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload failed with error code: " . $file['error']);
    }

    // Limit size to 5MB
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException("Uploaded file exceeds 5MB size limit.");
    }

    // Verify MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowedMimes[$mimeType])) {
        throw new RuntimeException("Invalid file format ({$mimeType}). Only JPG, PNG, WEBP, and GIF images are allowed.");
    }

    $ext = $allowedMimes[$mimeType];
    $filename = 'qr_' . bin2hex(random_bytes(16)) . '.' . $ext;
    $targetDir = __DIR__ . '/../uploads/qr_codes';

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $targetPath = $targetDir . '/' . $filename;
    // Handle both HTTP POST uploads and CLI / fallback uploads safely
    if (is_uploaded_file($file['tmp_name'])) {
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException("Could not save the uploaded QR code image to disk.");
        }
    } else {
        if (!copy($file['tmp_name'], $targetPath)) {
            throw new RuntimeException("Could not save the QR code image to disk.");
        }
        @unlink($file['tmp_name']);
    }

    return 'uploads/qr_codes/' . $filename;
}

/**
 * Retrieve system-wide company USDT deposit wallet configuration
 */
function getSystemDepositWallet(): array {
    $pdo = getDatabaseConnection();
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return [
            'wallet_address' => $rows['company_usdt_wallet'] ?? 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'network'        => $rows['company_usdt_network'] ?? 'TRC20',
            'qr_code_path'   => $rows['company_qr_code'] ?? null,
        ];
    } catch (Exception $e) {
        return [
            'wallet_address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
            'network'        => 'TRC20',
            'qr_code_path'   => null,
        ];
    }
}

/**
 * Update system-wide company USDT deposit wallet configuration
 */
function updateSystemDepositWallet(string $wallet, string $network, ?string $qrCodePath): void {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([':k' => 'company_usdt_wallet', ':v' => $wallet]);
    $stmt->execute([':k' => 'company_usdt_network', ':v' => $network]);
    if ($qrCodePath !== null) {
        $stmt->execute([':k' => 'company_qr_code', ':v' => $qrCodePath]);
    }
}
