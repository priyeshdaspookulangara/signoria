<?php
/**
 * Member Profile & USDT Wallet Configuration
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$user = getCurrentUser();
$userId = (int)$user['id'];
$pdo = getDatabaseConnection();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $usdtWallet = trim($_POST['usdt_wallet'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (empty($usdtWallet) || strlen($usdtWallet) < 10) {
            $error = 'Please provide a valid TRC20 / BEP20 USDT wallet address.';
        } else {
            // Check email uniqueness if changed
            $chkStmt = $pdo->prepare("SELECT id FROM users WHERE email = :e AND id != :id LIMIT 1");
            $chkStmt->execute([':e' => $email, ':id' => $userId]);
            if ($chkStmt->fetch()) {
                $error = 'Email address is already in use by another account.';
            } else {
                $upStmt = $pdo->prepare("UPDATE users SET email = :e, usdt_wallet = :w WHERE id = :id");
                $upStmt->execute([':e' => $email, ':w' => $usdtWallet, ':id' => $userId]);

                logActivity($userId, "Updated profile email and USDT wallet address.");
                setFlash('success', 'Profile and payout wallet address updated successfully.');
                header('Location: /member/profile.php');
                exit;
            }
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($currentPass, $hash)) {
            $error = 'Current password does not match.';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New password confirmation does not match.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
            $stmt->execute([':p' => $newHash, ':id' => $userId]);

            logActivity($userId, "Changed security password.");
            setFlash('success', 'Password successfully changed.');
            header('Location: /member/profile.php');
            exit;
        }
    }
}

$pageTitle = 'Member Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div>
        <h1 class="text-2xl font-black text-white flex items-center gap-2">
            <i class="fa-solid fa-user-gear text-indigo-400"></i> Account Settings & Payout Wallet
        </h1>
        <p class="text-xs text-slate-400 mt-1">Manage your security credentials and recipient address for automated matrix payouts.</p>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Profile & USDT Wallet Address -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-address-card text-emerald-400"></i> Profile Information
            </h2>

            <form method="POST" action="/member/profile.php" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Username</label>
                    <input type="text" disabled value="<?= e($user['username']) ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-400 text-sm cursor-not-allowed">
                    <span class="text-[10px] text-slate-500">Usernames are immutable for matrix tree integrity.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="email">Email Address</label>
                    <input type="email" id="email" name="email" required value="<?= e($user['email']) ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="usdt_wallet">
                        USDT Payout Wallet (TRC20 / BEP20)
                    </label>
                    <input type="text" id="usdt_wallet" name="usdt_wallet" required value="<?= e($user['usdt_wallet']) ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        placeholder="TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t">
                    <span class="text-[10px] text-slate-500">Matrix bonuses & withdrawals will be sent here.</span>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition">
                        Save Profile Details
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-shield-halved text-indigo-400"></i> Change Password
            </h2>

            <form method="POST" action="/member/profile.php" class="space-y-4">
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required
                        class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required
                        class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        placeholder="Min 6 characters">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                        class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition">
                        Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
