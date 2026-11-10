<?php
/**
 * Registration Handler with Optional Direct USDT Payment Checkout
 * Dynamic Amount QR Code Generator & Blockchain TXID Submission
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bfs_engine.php';

// Redirect if already logged in
requireGuest();

$pdo = getDatabaseConnection();

// Fetch corporate USDT deposit settings
$sysStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (
    'usdt_conversion_rate', 'usdt_trc20_address', 'usdt_bep20_address', 'company_usdt_wallet', 'company_usdt_network'
)");
$settings = $sysStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$conversionRate = (float)($settings['usdt_conversion_rate'] ?? 90.00);
if ($conversionRate <= 0) $conversionRate = 90.00;

$trc20Address = $settings['usdt_trc20_address'] ?? 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$bep20Address = $settings['usdt_bep20_address'] ?? ($settings['company_usdt_wallet'] ?? '0x572790c26FC062C247D0e1797D646F20a093AE14');

// Fetch active packages
$packages = $pdo->query("SELECT * FROM packages WHERE status = 'active' ORDER BY inr_price ASC")->fetchAll();

$error = null;
$sponsorUsername = trim($_GET['ref'] ?? '');
$pkgParam = trim($_GET['pkg'] ?? 'package_1');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username        = trim($_POST['username'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $password        = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $sponsor         = trim($_POST['sponsor'] ?? '');
    $payMethod       = trim($_POST['payment_method'] ?? 'instant'); // 'instant' or 'usdt'
    $packageId       = (int)($_POST['package_id'] ?? 1);
    $packageType     = $_POST['package_type'] ?? 'package_1';

    // Direct USDT payment fields
    $network         = strtoupper(trim($_POST['network'] ?? 'BEP20'));
    $txHash          = trim($_POST['transaction_hash'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All required fields (Username, Email, Password) must be completed.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($username) < 3 || strlen($username) > 30) {
        $error = 'Username must be between 3 and 30 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $error = 'Username may only contain letters, numbers, underscores, and dashes.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Password confirmation does not match.';
    } elseif ($payMethod === 'usdt' && (empty($txHash) || strlen($txHash) < 10)) {
        $error = 'Please enter the blockchain Transaction Code / TXID (minimum 10 characters) for your USDT payment.';
    } else {
        // Resolve selected package details
        $selectedPkg = null;
        foreach ($packages as $p) {
            if ((int)$p['id'] === $packageId) {
                $selectedPkg = $p;
                break;
            }
        }
        if (!$selectedPkg && !empty($packages)) {
            $selectedPkg = $packages[0];
            $packageId = (int)$selectedPkg['id'];
        }
        if ($selectedPkg) {
            $packageType = $selectedPkg['matrix_type'];
        }

        // Verify Sponsor
        $matchedSponsorId = null;
        if (!empty($sponsor)) {
            $sStmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
            $sStmt->execute([':u' => $sponsor]);
            $sponsorRow = $sStmt->fetch();
            if ($sponsorRow) {
                $matchedSponsorId = (int)$sponsorRow['id'];
            }
        }

        // Default sponsor to Admin (ID: 1) if no valid sponsor provided
        if ($matchedSponsorId === null) {
            $matchedSponsorId = 1;
        }

        // Check username uniqueness
        $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $uStmt->execute([':u' => $username]);

        if ($uStmt->fetch()) {
            $error = 'Username already exists. Please choose another username.';
        } else {
            // Begin Transaction
            $pdo->beginTransaction();
            try {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // Account status: 'pending' if paying with USDT awaiting admin approval, 'active' if instant/demo mode
                $accountStatus = ($payMethod === 'usdt') ? 'pending' : 'active';

                $insStmt = $pdo->prepare("
                    INSERT INTO users (username, sponsor_id, email, password, usdt_wallet, usdt_network, role, status, created_at)
                    VALUES (:u, :sp, :e, :p, NULL, :net, 'member', :status, NOW())
                ");
                $insStmt->execute([
                    ':u'      => $username,
                    ':sp'     => $matchedSponsorId,
                    ':e'      => $email,
                    ':p'      => $hashedPassword,
                    ':net'    => $network,
                    ':status' => $accountStatus,
                ]);

                $newUserId = (int)$pdo->lastInsertId();

                if ($payMethod === 'usdt') {
                    // Record USDT payment request
                    $calcInr = (float)$selectedPkg['inr_price'];
                    $calcUsdt = round($calcInr / $conversionRate, 2);
                    $depositAddress = ($network === 'TRC20') ? $trc20Address : $bep20Address;

                    // Optional screenshot receipt
                    $screenshotPath = null;
                    if (!empty($_FILES['screenshot']['name'])) {
                        $upload = handleQrCodeUpload($_FILES['screenshot'], 'receipt');
                        if ($upload['success']) {
                            $screenshotPath = $upload['path'];
                        }
                    }

                    $pmtStmt = $pdo->prepare("
                        INSERT INTO usdt_payments (
                            user_id, package_id, network, deposit_address, inr_amount,
                            usdt_rate, usdt_amount, transaction_hash, screenshot_path, status, created_at
                        ) VALUES (
                            :uid, :pkg, :net, :addr, :inr,
                            :rate, :usdt, :tx, :screenshot, 'pending', NOW()
                        )
                    ");
                    $pmtStmt->execute([
                        ':uid'        => $newUserId,
                        ':pkg'        => $packageId,
                        ':net'        => $network,
                        ':addr'       => $depositAddress,
                        ':inr'        => $calcInr,
                        ':rate'       => $conversionRate,
                        ':usdt'       => $calcUsdt,
                        ':tx'         => $txHash,
                        ':screenshot' => $screenshotPath
                    ]);

                    $pmtId = $pdo->lastInsertId();

                    // Pending transaction in ledger
                    $txStmt = $pdo->prepare("
                        INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                        VALUES (:uid, 'deposit', :amt, :hash, 'pending', :details, NOW())
                    ");
                    $txStmt->execute([
                        ':uid'     => $newUserId,
                        ':amt'     => $calcUsdt,
                        ':hash'    => $txHash,
                        ':details' => "Registration USDT payment #{$pmtId} for {$selectedPkg['name']}",
                    ]);

                    $pdo->commit();

                    logActivity($newUserId, "User registered with USDT payment #{$pmtId} (TXID: {$txHash})");

                    setFlash('success', "Registration submitted! Your payment of {$calcUsdt} USDT ({$network}) is pending administrator verification. Once approved, you will be placed into the BFS matrix.");
                    header('Location: /auth/login.php');
                    exit;

                } else {
                    // Instant Activation Mode
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
                }

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Registration failed: " . $e->getMessage());
                $error = 'Registration could not be completed: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Register Account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-[85vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl w-full bg-slate-900/95 border border-slate-800 p-8 sm:p-10 rounded-3xl shadow-2xl backdrop-blur-md">
        <div class="text-center">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 mb-3">
                <i class="fa-solid fa-user-plus text-white text-xl"></i>
            </div>
            <h2 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Create Matrix Account</h2>
            <p class="mt-1.5 text-xs sm:text-sm text-slate-400">Join the BFS Auto-Spillover Network via USDT Payment Gateway</p>
        </div>

        <?php if ($error): ?>
            <div class="mt-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs sm:text-sm flex items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation text-rose-400 text-base shrink-0"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form class="mt-6 space-y-5" method="POST" action="/auth/register.php" enctype="multipart/form-data" id="regForm">
            <?= csrfField() ?>
            <input type="hidden" name="network" id="network_input" value="BEP20">

            <!-- Credentials -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="username">
                        Username <span class="text-rose-400">*</span>
                    </label>
                    <input id="username" name="username" type="text" required
                        value="<?= e($_POST['username'] ?? '') ?>"
                        class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                        placeholder="e.g. Satoshi_99">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="email">
                        Email Address <span class="text-rose-400">*</span>
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
                        Password <span class="text-rose-400">*</span>
                    </label>
                    <input id="password" name="password" type="password" required
                        class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                        placeholder="Min. 6 characters">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="password_confirm">
                        Confirm Password <span class="text-rose-400">*</span>
                    </label>
                    <input id="password_confirm" name="password_confirm" type="password" required
                        class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm"
                        placeholder="Repeat password">
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
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="package_select">
                        Select Package Tier
                    </label>
                    <select id="package_select" name="package_id" class="block w-full px-3.5 py-2.5 bg-slate-800/80 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm font-medium">
                        <?php foreach ($packages as $pkg): ?>
                            <option value="<?= (int)$pkg['id'] ?>"
                                data-inr="<?= (float)$pkg['inr_price'] ?>"
                                data-usdt="<?= (float)$pkg['usdt_price'] ?>"
                                <?= ((int)($_POST['package_id'] ?? 0) === (int)$pkg['id']) ? 'selected' : '' ?>>
                                <?= e($pkg['name']) ?> — ₹<?= number_format((float)$pkg['inr_price']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Activation / Payment Mode Selector -->
            <div class="border-t border-slate-800 pt-3">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                    Payment & Activation Method
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-3 rounded-2xl bg-slate-800/80 border border-slate-700 cursor-pointer hover:border-emerald-500 transition">
                        <input type="radio" name="payment_method" value="usdt" checked onchange="togglePayMethod()" class="text-emerald-500 focus:ring-emerald-500">
                        <div>
                            <span class="block text-xs font-bold text-white flex items-center gap-1.5">
                                <i class="fa-solid fa-qrcode text-emerald-400"></i> Pay with USDT
                            </span>
                            <span class="text-[10px] text-slate-400">Dynamic QR + TXID</span>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 rounded-2xl bg-slate-800/80 border border-slate-700 cursor-pointer hover:border-emerald-500 transition">
                        <input type="radio" name="payment_method" value="instant" onchange="togglePayMethod()" class="text-emerald-500 focus:ring-emerald-500">
                        <div>
                            <span class="block text-xs font-bold text-white flex items-center gap-1.5">
                                <i class="fa-solid fa-bolt text-amber-400"></i> Instant Entry
                            </span>
                            <span class="text-[10px] text-slate-400">Direct Demo Matrix Entry</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Dynamic USDT Payment Section (Visible when Pay with USDT is active) -->
            <div id="usdtSection" class="p-5 rounded-2xl bg-slate-950 border border-slate-800 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800/80">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-network-wired text-emerald-400"></i> Corporate Receiving Gateway
                    </span>
                    <span id="regBadgeAmount" class="text-xs font-mono font-extrabold text-emerald-400">
                        0.00 USDT
                    </span>
                </div>

                <!-- Network Switcher -->
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" id="regNetBEP20" onclick="regSwitchNetwork('BEP20')"
                        class="py-2.5 px-3 rounded-xl border text-xs font-bold transition bg-emerald-600/20 border-emerald-500 text-emerald-300 text-center">
                        BEP-20 (BNB Chain)
                    </button>
                    <button type="button" id="regNetTRC20" onclick="regSwitchNetwork('TRC20')"
                        class="py-2.5 px-3 rounded-xl border text-xs font-bold transition bg-slate-800/60 border-slate-700 text-slate-400 text-center hover:text-white">
                        TRC-20 (Tron)
                    </button>
                </div>

                <!-- Dynamic QR Display -->
                <div class="p-3 bg-white rounded-2xl max-w-[180px] aspect-square mx-auto flex items-center justify-center shadow-lg">
                    <div id="regDynamicQr"></div>
                </div>

                <!-- Deposit Address Input & Copy -->
                <div>
                    <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1" id="regNetLabel">Deposit Address (BEP-20)</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="regDepositAddress" readonly
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-white font-mono text-xs select-all focus:outline-none"
                            value="<?= e($bep20Address) ?>">
                        <button type="button" onclick="copyRegDepositAddress()"
                            class="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold shrink-0 transition">
                            <span id="regCopyText">Copy</span>
                        </button>
                    </div>
                </div>

                <!-- TXID Input -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="reg_tx_hash">
                        Receive Transaction Code / Hash (TXID) <span class="text-rose-400">*</span>
                    </label>
                    <input type="text" id="reg_tx_hash" name="transaction_hash"
                        class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        placeholder="e.g. 0x... or 64-character blockchain TXID">
                </div>

                <!-- Receipt Screenshot -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="reg_screenshot">
                        Payment Screenshot / Receipt <span class="text-slate-500 text-[10px] lowercase">(optional)</span>
                    </label>
                    <input type="file" id="reg_screenshot" name="screenshot" accept="image/png, image/jpeg, image/webp"
                        class="block w-full text-xs text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                    class="w-full flex justify-center py-3.5 px-4 rounded-2xl text-sm font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 shadow-xl shadow-emerald-600/30 transition">
                    Register & Enter BFS Queue
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

<script>
const REG_TRC20 = <?= json_encode($trc20Address) ?>;
const REG_BEP20 = <?= json_encode($bep20Address) ?>;
const REG_RATE  = <?= (float)$conversionRate ?>;

let regNetwork = 'BEP20';

function getRegPackageData() {
    const sel = document.getElementById('package_select');
    const opt = sel.options[sel.selectedIndex];
    const inr = parseFloat(opt.getAttribute('data-inr') || '0');
    const usdt = (inr / REG_RATE).toFixed(2);
    return { inr, usdt };
}

function updateRegAmountsAndQr() {
    const data = getRegPackageData();
    document.getElementById('regBadgeAmount').textContent = `${data.usdt} USDT (${regNetwork})`;

    const addr = (regNetwork === 'BEP20') ? REG_BEP20 : REG_TRC20;
    const uri = (regNetwork === 'BEP20')
        ? `ethereum:${addr}?amount=${data.usdt}`
        : `tron:${addr}?amount=${data.usdt}&token=USDT`;

    const container = document.getElementById('regDynamicQr');
    container.innerHTML = '';
    if (typeof QRCode !== 'undefined') {
        new QRCode(container, {
            text: uri,
            width: 160,
            height: 160,
            colorDark: "#020617",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    }
}

function regSwitchNetwork(net) {
    regNetwork = net;
    document.getElementById('network_input').value = net;
    const btnB = document.getElementById('regNetBEP20');
    const btnT = document.getElementById('regNetTRC20');
    const addr = document.getElementById('regDepositAddress');
    const label = document.getElementById('regNetLabel');

    if (net === 'BEP20') {
        btnB.className = "py-2.5 px-3 rounded-xl border text-xs font-bold transition bg-emerald-600/20 border-emerald-500 text-emerald-300 text-center";
        btnT.className = "py-2.5 px-3 rounded-xl border text-xs font-bold transition bg-slate-800/60 border-slate-700 text-slate-400 text-center hover:text-white";
        addr.value = REG_BEP20;
        label.textContent = "Deposit Address (BEP-20 / BNB Chain)";
    } else {
        btnT.className = "py-2.5 px-3 rounded-xl border text-xs font-bold transition bg-emerald-600/20 border-emerald-500 text-emerald-300 text-center";
        btnB.className = "py-2.5 px-3 rounded-xl border text-xs font-bold transition bg-slate-800/60 border-slate-700 text-slate-400 text-center hover:text-white";
        addr.value = REG_TRC20;
        label.textContent = "Deposit Address (TRC-20 / Tron)";
    }
    updateRegAmountsAndQr();
}

function copyRegDepositAddress() {
    const input = document.getElementById('regDepositAddress');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const span = document.getElementById('regCopyText');
        span.textContent = 'Copied!';
        setTimeout(() => { span.textContent = 'Copy'; }, 2000);
    });
}

function togglePayMethod() {
    const radios = document.getElementsByName('payment_method');
    let method = 'usdt';
    for (const r of radios) {
        if (r.checked) method = r.value;
    }
    const section = document.getElementById('usdtSection');
    const txInput = document.getElementById('reg_tx_hash');
    if (method === 'usdt') {
        section.style.display = 'block';
        txInput.required = true;
    } else {
        section.style.display = 'none';
        txInput.required = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('package_select').addEventListener('change', updateRegAmountsAndQr);
    updateRegAmountsAndQr();
    togglePayMethod();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
