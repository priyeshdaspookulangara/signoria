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
            'wallet_address' => $rows['company_usdt_wallet'] ?? '0x38CC4cd0433e64dAC73Dca87DDedDac27d12bC59',
            'network'        => $rows['company_usdt_network'] ?? 'BEP20',
            'qr_code_path'   => $rows['company_qr_code'] ?? 'uploads/qr_codes/company_bep20_qr.png',
        ];
    } catch (Exception $e) {
        return [
            'wallet_address' => '0x38CC4cd0433e64dAC73Dca87DDedDac27d12bC59',
            'network'        => 'BEP20',
            'qr_code_path'   => 'uploads/qr_codes/company_bep20_qr.png',
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

/**
 * Send an email notification (HTML + text fallback)
 */
function sendEmailNotification(string $toEmail, string $subject, string $htmlBody, string $plainTextBody = ''): bool {
    if (empty($plainTextBody)) {
        $plainTextBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
    }

    $boundary = '==Multipart_Boundary_x' . md5((string)time()) . 'x';

    // Construct headers
    $fromName = 'Zenith Assets';
    $fromEmail = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'zenithassets.com');
    // Sanitize host if containing port
    $hostParts = explode(':', $fromEmail);
    $fromEmail = $hostParts[0];

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = "From: {$fromName} <{$fromEmail}>";
    $headers[] = "Reply-To: {$fromEmail}";
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $plainTextBody . "\r\n\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=\"UTF-8\"\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";

    $message .= "--{$boundary}--";

    // Attempt native mail delivery
    $sent = false;
    try {
        $sent = @mail($toEmail, $subject, $message, implode("\r\n", $headers));
    } catch (\Throwable $e) {
        error_log("Failed to send email to {$toEmail}: " . $e->getMessage());
        $sent = false;
    }

    return $sent;
}

/**
 * Send registration confirmation email to customer
 */
function sendRegistrationConfirmationEmail(array $userParams): bool {
    $email = trim($userParams['email'] ?? '');
    $username = trim($userParams['username'] ?? '');
    $sponsorName = trim($userParams['sponsor'] ?? 'Direct System');
    $packageName = trim($userParams['package_name'] ?? 'Package I (50 USDT)');
    $status = trim($userParams['status'] ?? 'active'); // 'active' or 'pending'
    $txHash = trim($userParams['tx_hash'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $loginUrl = $siteUrl . '/auth/login.php';

    $subject = "Welcome to Zenith Assets - Registration Confirmation";

    $statusBadge = ($status === 'active')
        ? '<span style="display:inline-block;padding:4px 12px;background-color:#10b981;color:#ffffff;border-radius:9999px;font-size:12px;font-weight:bold;text-transform:uppercase;">Active</span>'
        : '<span style="display:inline-block;padding:4px 12px;background-color:#f59e0b;color:#ffffff;border-radius:9999px;font-size:12px;font-weight:bold;text-transform:uppercase;">Pending Verification</span>';

    $statusMessage = ($status === 'active')
        ? '<p style="color:#d1d5db;font-size:14px;line-height:1.6;margin:12px 0;">Your membership is <strong>active</strong> and you have been placed into the BFS matrix network.</p>'
        : '<p style="color:#d1d5db;font-size:14px;line-height:1.6;margin:12px 0;">Your USDT payment proof has been submitted and is currently being verified by an administrator. Once approved, your account will be activated and placed into the BFS matrix.</p>';

    $txRow = '';
    if (!empty($txHash)) {
        $txRow = '
        <tr>
            <td style="padding:10px 0;border-bottom:1px solid #2d3748;color:#9ca3af;font-size:14px;">Transaction TXID:</td>
            <td style="padding:10px 0;border-bottom:1px solid #2d3748;color:#e2e8f0;font-size:13px;font-family:monospace;word-break:break-all;text-align:right;">' . htmlspecialchars($txHash) . '</td>
        </tr>';
    }

    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Welcome to Zenith Assets</title>
    </head>
    <body style="margin:0;padding:0;background-color:#0b0f19;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#e2e8f0;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0b0f19;padding:40px 15px;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#161d31;border:1px solid #283046;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.5);">
                        <!-- Header -->
                        <tr>
                            <td style="padding:32px 32px 24px;text-align:center;background:linear-gradient(135deg,#1e1b4b 0%,#0f172a 100%);border-bottom:1px solid #283046;">
                                <h1 style="margin:0 0 8px 0;font-size:26px;color:#ffffff;letter-spacing:0.5px;">ZENITH ASSETS</h1>
                                <p style="margin:0;font-size:14px;color:#93c5fd;letter-spacing:1px;text-transform:uppercase;font-weight:600;">Autonomous Matrix Financial Network</p>
                            </td>
                        </tr>
                        <!-- Body Content -->
                        <tr>
                            <td style="padding:32px;">
                                <h2 style="margin:0 0 16px;font-size:20px;color:#ffffff;">Registration Confirmation</h2>
                                <p style="margin:0 0 16px;color:#cbd5e1;font-size:15px;line-height:1.6;">
                                    Hello <strong style="color:#60a5fa;">' . htmlspecialchars($username) . '</strong>,
                                </p>
                                <p style="margin:0 0 20px;color:#cbd5e1;font-size:15px;line-height:1.6;">
                                    Thank you for registering with Zenith Assets. Your customer registration has been successfully received.
                                </p>
                                ' . $statusMessage . '
                                <!-- Account Details Card -->
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background-color:#0f172a;border:1px solid #1e293b;border-radius:8px;padding:16px 20px;">
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #1e293b;color:#94a3b8;font-size:14px;">Username:</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #1e293b;color:#ffffff;font-size:14px;font-weight:600;text-align:right;">' . htmlspecialchars($username) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #1e293b;color:#94a3b8;font-size:14px;">Email Address:</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #1e293b;color:#ffffff;font-size:14px;text-align:right;">' . htmlspecialchars($email) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #1e293b;color:#94a3b8;font-size:14px;">Sponsor:</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #1e293b;color:#ffffff;font-size:14px;text-align:right;">' . htmlspecialchars($sponsorName) . '</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:10px 0;border-bottom:1px solid #1e293b;color:#94a3b8;font-size:14px;">Selected Package:</td>
                                        <td style="padding:10px 0;border-bottom:1px solid #1e293b;color:#38bdf8;font-size:14px;font-weight:600;text-align:right;">' . htmlspecialchars($packageName) . '</td>
                                    </tr>
                                    ' . $txRow . '
                                    <tr>
                                        <td style="padding:10px 0;color:#94a3b8;font-size:14px;">Account Status:</td>
                                        <td style="padding:10px 0;text-align:right;">' . $statusBadge . '</td>
                                    </tr>
                                </table>

                                <!-- CTA Button -->
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin:30px 0 10px;">
                                    <tr>
                                        <td align="center">
                                            <a href="' . htmlspecialchars($loginUrl) . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;border-radius:8px;box-shadow:0 4px 12px rgba(37,99,235,0.4);">Access Your Account</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td style="padding:20px 32px;background-color:#0f172a;border-top:1px solid #283046;text-align:center;">
                                <p style="margin:0 0 6px 0;font-size:12px;color:#64748b;">
                                    &copy; ' . date('Y') . ' Zenith Assets. All rights reserved.
                                </p>
                                <p style="margin:0;font-size:11px;color:#475569;">
                                    If you did not register for this account, please disregard this email.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $plainText = "Welcome to Zenith Assets!\n\n"
        . "Hello {$username},\n\n"
        . "Thank you for registering with Zenith Assets. Your registration details are as follows:\n\n"
        . "Username: {$username}\n"
        . "Email: {$email}\n"
        . "Sponsor: {$sponsorName}\n"
        . "Package: {$packageName}\n"
        . (!empty($txHash) ? "TXID: {$txHash}\n" : "")
        . "Status: " . ucfirst($status) . "\n\n"
        . "You can log in to your account at: {$loginUrl}\n\n"
        . "Best regards,\nZenith Assets Team";

    return sendEmailNotification($email, $subject, $htmlBody, $plainText);
}
