<?php
/**
 * User Registration with Sponsor Referral Lookup & 3x BFS Placement
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bfs_engine.php';

requireGuest();

$error = null;
$success = null;

$refParam = trim($_GET['ref'] ?? '');
$pkgParam = trim($_GET['pkg'] ?? 'package_1');

$pdo = getDatabaseConnection();

// Fetch sponsor if ref parameter provided
$sponsorUsername = '';
$sponsorId = null;

if (!empty($refParam)) {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = :ref LIMIT 1");
    $stmt->execute([':ref' => $refParam]);
    $sponsor = $stmt->fetch();
    if ($sponsor) {
        $sponsorUsername = $sponsor['username'];
        $sponsorId = (int)$sponsor['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $sponsorInput = trim($_POST['sponsor'] ?? '');
    $packageType = trim($_POST['package_type'] ?? 'package_1');

    // Validate Package Type
    if (!in_array($packageType, ['package_1', 'package_2'], true)) {
        $error = 'Please select a valid matrix package.';
    } elseif (strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username must be 3-30 characters long and contain only letters, numbers, and underscores.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Password confirmation does not match.';
    } else {
        // Resolve Sponsor ID
        $matchedSponsorId = null;
        if (!empty($sponsorInput)) {
            $sStmt = $pdo->prepare("SELECT id FROM users WHERE username = :sp LIMIT 1");
            $sStmt->execute([':sp' => $sponsorInput]);
            $foundSponsor = $sStmt->fetch();
            if ($foundSponsor) {
                $matchedSponsorId = (int)$foundSponsor['id'];
            } else {
                $error = 'Specified sponsor username was not found. Please verify or leave blank to assign default root sponsor.';
            }
        }

        // If no sponsor specified, default to Admin (ID 1)
        if (!$error && $matchedSponsorId === null) {
            $rootStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $matchedSponsorId = (int)($rootStmt->fetchColumn() ?: 1);
        }

        if (!$error) {
            // Check username uniqueness (email does not need to be unique)
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
            $uStmt->execute([':u' => $username]);
            if ($uStmt->fetch()) {
                $error = 'Username already exists. Please choose another username.';
            } else {
                // Begin Transaction for atomic registration and matrix entry
                $pdo->beginTransaction();
                try {
                    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                    $insStmt = $pdo->prepare("
                        INSERT INTO users (username, sponsor_id, email, password, usdt_wallet, role, status, created_at)
                        VALUES (:u, :sp, :e, :p, NULL, 'member', 'active', NOW())
                    ");
                    $insStmt->execute([
                        ':u'  => $username,
                        ':sp' => $matchedSponsorId,
                        ':e'  => $email,
                        ':p'  => $hashedPassword,
                    ]);

                    $newUserId = (int)$pdo->lastInsertId();

                    // Register initial entry deposit transaction record
                    $pkgConfig = BfsMatrixEngine::getConfig($packageType);
                    $entryAmount = $pkgConfig['entry_amount'];

                    $txStmt = $pdo->prepare("
                        INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                        VALUES (:uid, 'deposit', :amt, :hash, 'completed', :details, NOW())
                    ");
                    $mockTxHash = '0x' . bin2hex(random_bytes(16));
                    $txStmt->execute([
                        ':uid'     => $newUserId,
                        ':amt'     => $entryAmount,
                        ':hash'    => $mockTxHash,
                        ':details' => "Automated activation entry deposit for {$packageType}",
                    ]);

                    // Place the new member into the 3x BFS Matrix
                    $nodePlaced = BfsMatrixEngine::placeNode($pdo, $newUserId, $packageType);

                    $pdo->commit();

                    // Auto-login user
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $newUserId;
                    $_SESSION['role'] = 'member';
                    $_SESSION['username'] = $username;

                    logActivity($newUserId, "User registered and placed into {$packageType} at Position #{$nodePlaced['position_index']}");

                    setFlash('success', "Welcome {$username}! Your account is active and placed into {$packageType} at Level {$nodePlaced['level']}.");
                    header('Location: /member/dashboard.php');
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Registration failed: " . $e->getMessage());
                    $error = 'Registration could not be completed. Please try again.';
                }
            }
        }
    }
}

$pageTitle = 'Register';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-[85vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-xl w-full bg-slate-900/90 border border-slate-800 p-8 sm:p-10 rounded-2xl shadow-2xl backdrop-blur-sm">
        <div class="text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 mb-3">
                <i class="fa-solid fa-user-plus text-white text-xl"></i>
            </div>
            <h2 class="text-2xl font-black text-white tracking-tight">Create Matrix Account</h2>
            <p class="mt-2 text-sm text-slate-400">Join the BFS Auto-Spillover Network via USDT</p>
        </div>

        <?php if ($error): ?>
            <div class="mt-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form class="mt-6 space-y-5" method="POST" action="/auth/register.php">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="username">
                        Username
                    </label>
                    <input id="username" name="username" type="text" required
                        value="<?= e($_POST['username'] ?? '') ?>"
                        class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                        placeholder="e.g. Satoshi_99">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="email">
                        Email Address
                    </label>
                    <input id="email" name="email" type="email" required
                        value="<?= e($_POST['email'] ?? '') ?>"
                        class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                        placeholder="name@domain.com">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="password">
                        Password
                    </label>
                    <input id="password" name="password" type="password" required
                        class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                        placeholder="Min. 6 characters">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="password_confirm">
                        Confirm Password
                    </label>
                    <input id="password_confirm" name="password_confirm" type="password" required
                        class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                        placeholder="Repeat password">
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-800/60 border border-slate-700/80 flex items-start gap-3">
                <i class="fa-solid fa-wallet text-emerald-400 mt-0.5"></i>
                <div class="text-xs text-slate-300 leading-relaxed">
                    <span class="font-bold text-white">USDT Payout Wallet Setup:</span> You can configure your payout wallet address (TRC20, BEP20, ERC20, Polygon) and upload your wallet QR code anytime inside the <span class="text-emerald-400 font-semibold">Member Profile & Settings</span> area.
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="sponsor">
                        Sponsor Username (Optional)
                    </label>
                    <input id="sponsor" name="sponsor" type="text"
                        value="<?= e($_POST['sponsor'] ?? $sponsorUsername) ?>"
                        class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                        placeholder="admin">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="package_type">
                        Choose Matrix Pool
                    </label>
                    <select id="package_type" name="package_type" class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
                        <option value="package_1" <?= ($_POST['package_type'] ?? $pkgParam) === 'package_1' ? 'selected' : '' ?>>
                            Package I (6 Levels) - 50 USDT
                        </option>
                        <option value="package_2" <?= ($_POST['package_type'] ?? $pkgParam) === 'package_2' ? 'selected' : '' ?>>
                            Package II (7 Levels) - 300 USDT
                        </option>
                    </select>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="w-full flex justify-center py-3.5 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 shadow-lg shadow-emerald-600/30 transition">
                    Activate Account & Enter BFS Queue
                </button>
            </div>

            <div class="text-center text-xs text-slate-400 pt-2">
                Already registered?
                <a href="/auth/login.php" class="text-emerald-400 hover:text-emerald-300 font-semibold underline underline-offset-4">
                    Sign in to your account
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
