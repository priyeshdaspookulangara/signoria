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

    // Total credits: completed deposits and completed matrix_payouts
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_usdt), 0) AS total_in
        FROM transactions
        WHERE user_id = :uid AND status = 'completed' AND type IN ('deposit', 'matrix_payout')
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
