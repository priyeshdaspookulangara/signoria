<?php
/**
 * Session checks and role-guard helper functions
 * MLM 3x Matrix Platform
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Configure secure session parameters if session not started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Return current authenticated user data array or null
 */
function getCurrentUser(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null && $cachedUser['id'] === (int)$_SESSION['user_id']) {
        return $cachedUser;
    }

    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT id, username, sponsor_id, email, usdt_wallet, role, status, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int)$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $cachedUser = $user;
            return $user;
        }
    } catch (Exception $e) {
        error_log("Failed to fetch current user: " . $e->getMessage());
    }

    return null;
}

/**
 * Check if a user is authenticated
 */
function isAuthenticated(): bool {
    return isset($_SESSION['user_id']) && getCurrentUser() !== null;
}

/**
 * Alias for isAuthenticated()
 */
function isLoggedIn(): bool {
    return isAuthenticated();
}

/**
 * Check if the authenticated user is an administrator
 */
function isAdmin(): bool {
    $user = getCurrentUser();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * Check if user is active
 */
function isUserActive(): bool {
    $user = getCurrentUser();
    return $user !== null && $user['status'] === 'active';
}

/**
 * Guard: Require user to be authenticated
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        setFlash('danger', 'Please log in to access this page.');
        header('Location: /auth/login.php');
        exit;
    }

    if (!isUserActive()) {
        $user = getCurrentUser();
        $status = $user ? $user['status'] : 'unknown';
        session_destroy();
        session_start();
        setFlash('danger', "Your account is {$status}. Please contact administrator.");
        header('Location: /auth/login.php');
        exit;
    }
}

/**
 * Guard: Require user to have admin role
 */
function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        setFlash('danger', 'Access denied. Administrator privileges required.');
        header('Location: /member/dashboard.php');
        exit;
    }
}

/**
 * Guard: Require user to be a regular member or redirect admin to admin panel
 */
function requireMember(): void {
    requireAuth();
    // Allow admin to also view member pages if needed or view their own member tree
}

/**
 * Guard: Guest only (redirect if already logged in)
 */
function requireGuest(): void {
    if (isAuthenticated()) {
        if (isAdmin()) {
            header('Location: /admin/dashboard.php');
        } else {
            header('Location: /member/dashboard.php');
        }
        exit;
    }
}
