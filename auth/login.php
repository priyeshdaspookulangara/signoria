<?php
/**
 * User Login Handler & Form
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireGuest();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameOrEmail = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usernameOrEmail) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("
            SELECT id, username, email, password, role, status
            FROM users
            WHERE username = :u OR email = :e
            LIMIT 1
        ");
        $stmt->execute([':u' => $usernameOrEmail, ':e' => $usernameOrEmail]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact administration.';
            } else {
                // Regenerate session ID for security
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];

                logActivity((int)$user['id'], "User signed in successfully.");

                if ($user['role'] === 'admin') {
                    header('Location: /admin/dashboard.php');
                } else {
                    header('Location: /member/dashboard.php');
                }
                exit;
            }
        } else {
            $error = 'Invalid credentials provided. Please check your username and password.';
        }
    }
}

$pageTitle = 'Sign In';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-slate-900/90 border border-slate-800 p-8 sm:p-10 rounded-2xl shadow-2xl backdrop-blur-sm">
        <div class="text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 mb-3">
                <i class="fa-solid fa-lock text-white text-xl"></i>
            </div>
            <h2 class="text-2xl font-black text-white tracking-tight">Member Portal Login</h2>
            <p class="mt-2 text-sm text-slate-400">Access your USDT Matrix Dashboard & Visualizer</p>
        </div>

        <?php if ($error): ?>
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form class="mt-8 space-y-6" method="POST" action="/auth/login.php">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="username_or_email">
                        Username or Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <input id="username_or_email" name="username_or_email" type="text" required
                            value="<?= e($_POST['username_or_email'] ?? '') ?>"
                            class="block w-full pl-10 pr-3 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-sm"
                            placeholder="Enter username or email">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="password">
                        Password
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i class="fa-solid fa-key"></i>
                        </div>
                        <input id="password" name="password" type="password" required
                            class="block w-full pl-10 pr-3 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-sm"
                            placeholder="Enter password">
                    </div>
                </div>
            </div>

            <div>
                <button type="submit"
                    class="w-full flex justify-center py-3 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 shadow-lg shadow-emerald-600/30 transition">
                    Sign In
                </button>
            </div>

            <div class="text-center text-xs text-slate-400 pt-2">
                Don't have an account?
                <a href="/auth/register.php" class="text-emerald-400 hover:text-emerald-300 font-semibold underline underline-offset-4">
                    Register with Sponsor
                </a>
            </div>

            <!-- Demo Credentials Helper -->
            <div class="pt-4 border-t border-slate-800 text-[11px] text-slate-500 text-center">
                <span class="font-semibold text-slate-400">Demo Logins:</span> <br>
                Admin: <code class="text-emerald-400">admin</code> / <code class="text-slate-300">admin123</code> &bull;
                Member: <code class="text-teal-400">alex_leader</code> / <code class="text-slate-300">member123</code>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
