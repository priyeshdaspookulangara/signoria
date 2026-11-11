<?php
/**
 * Member USDT Package Activation Engine
 * Dynamic Client-Side Amount QR Generation (TRC20 & BEP20)
 * Blockchain TXID Submission & Proof Upload
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();
$currentUser = getCurrentUser();
$pdo = getDatabaseConnection();

// Fetch corporate USDT deposit settings
$sysStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (
    'usdt_conversion_rate', 'usdt_trc20_address', 'usdt_bep20_address', 'usdt_trc20_qr_path', 'usdt_bep20_qr_path', 'company_usdt_wallet', 'company_usdt_network'
)");
$settings = $sysStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$conversionRate = (float)($settings['usdt_conversion_rate'] ?? 90.00);
if ($conversionRate <= 0) {
    $conversionRate = 90.00;
}

$trc20Address = $settings['usdt_trc20_address'] ?? 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$bep20Address = $settings['usdt_bep20_address'] ?? ($settings['company_usdt_wallet'] ?? '0x38CC4cd0433e64dAC73Dca87DDedDac27d12bC59');

// Fetch active packages
$packages = $pdo->query("SELECT * FROM packages WHERE status = 'active' ORDER BY inr_price ASC")->fetchAll();

$error = null;
$success = null;

// Handle USDT Activation Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired (CSRF). Please refresh and submit again.';
    } else {
        $packageId = (int)($_POST['package_id'] ?? 0);
        $network = strtoupper(trim($_POST['network'] ?? 'BEP20'));
        $txHash = trim($_POST['transaction_hash'] ?? '');
        $inrAmount = (float)($_POST['inr_amount'] ?? 0);
        $usdtAmount = (float)($_POST['usdt_amount'] ?? 0);

        // Verify package exists
        $pkgStmt = $pdo->prepare("SELECT * FROM packages WHERE id = ? AND status = 'active'");
        $pkgStmt->execute([$packageId]);
        $pkg = $pkgStmt->fetch();

        if (!$pkg) {
            $error = 'Invalid package selected. Please select a valid activation package.';
        } elseif (!in_array($network, ['TRC20', 'BEP20'], true)) {
            $error = 'Invalid network selection. Please choose TRC20 or BEP20.';
        } elseif (empty($txHash) || strlen($txHash) < 10) {
            $error = 'Please enter a valid blockchain Transaction Hash / TXID (minimum 10 characters).';
        } else {
            // Check for duplicate pending or approved TXID
            $dupStmt = $pdo->prepare("SELECT id, status FROM usdt_payments WHERE transaction_hash = ? AND status IN ('pending', 'approved') LIMIT 1");
            $dupStmt->execute([$txHash]);
            $existingTx = $dupStmt->fetch();

            if ($existingTx) {
                $error = 'This transaction hash (TXID) has already been submitted (' . htmlspecialchars($existingTx['status']) . ').';
            } else {
                // Determine deposit address according to network
                $depositAddress = ($network === 'TRC20') ? $trc20Address : $bep20Address;

                // Enforce exact calculation
                $calcInr = (float)$pkg['inr_price'];
                $calcUsdt = round($calcInr / $conversionRate, 2);

                // Optional Screenshot Upload
                $screenshotPath = null;
                if (!empty($_FILES['screenshot']['name'])) {
                    $upload = handleQrCodeUpload($_FILES['screenshot'], 'receipt');
                    if ($upload['success']) {
                        $screenshotPath = $upload['path'];
                    } else {
                        $error = 'Receipt screenshot upload failed: ' . $upload['error'];
                    }
                }

                if (!$error) {
                    try {
                        $ins = $pdo->prepare("
                            INSERT INTO usdt_payments (
                                user_id, package_id, network, deposit_address, inr_amount,
                                usdt_rate, usdt_amount, transaction_hash, screenshot_path, status, created_at
                            ) VALUES (
                                :uid, :pkg, :net, :addr, :inr,
                                :rate, :usdt, :tx, :screenshot, 'pending', NOW()
                            )
                        ");

                        $ins->execute([
                            ':uid'        => (int)$currentUser['id'],
                            ':pkg'        => (int)$pkg['id'],
                            ':net'        => $network,
                            ':addr'       => $depositAddress,
                            ':inr'        => $calcInr,
                            ':rate'       => $conversionRate,
                            ':usdt'       => $calcUsdt,
                            ':tx'         => $txHash,
                            ':screenshot' => $screenshotPath
                        ]);

                        $paymentId = $pdo->lastInsertId();

                        // Also record in transactions table as pending deposit for comprehensive ledgering
                        $ledgerStmt = $pdo->prepare("
                            INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                            VALUES (?, 'deposit', ?, ?, 'pending', ?, NOW())
                        ");
                        $ledgerStmt->execute([
                            (int)$currentUser['id'],
                            $calcUsdt,
                            $txHash,
                            "USDT Activation Request #{$paymentId} for package: {$pkg['name']} ({$network})"
                        ]);

                        logActivity((int)$currentUser['id'], "Submitted USDT Activation #{$paymentId} ({$calcUsdt} USDT via {$network}, TXID: {$txHash})");

                        setFlash('success', 'USDT activation request submitted successfully! Your account package will be activated once verified by admin.');
                        header('Location: /member/usdt_activate.php');
                        exit;
                    } catch (PDOException $e) {
                        $error = 'Database error submitting activation request: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Fetch user's activation history
$historyStmt = $pdo->prepare("
    SELECT p.*, k.name AS package_name, k.matrix_type
    FROM usdt_payments p
    LEFT JOIN packages k ON p.package_id = k.id
    WHERE p.user_id = ?
    ORDER BY p.id DESC
");
$historyStmt->execute([(int)$currentUser['id']]);
$userPayments = $historyStmt->fetchAll();

$pageTitle = 'USDT Package Activation';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8 max-w-6xl mx-auto">
    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950/60 to-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-2">
                    <i class="fa-solid fa-bolt"></i> Instant Dynamic Payment Engine
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">USDT Package Activation</h1>
                <p class="text-slate-400 text-sm mt-1 max-w-xl">
                    Select your package tier, choose network (BEP20 / TRC20), scan the dynamic amount-encoded QR code, and submit your blockchain TXID.
                </p>
            </div>
            <div class="flex items-center gap-3 bg-slate-900/90 border border-slate-700/60 rounded-2xl px-4 py-3 shadow-inner">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg">
                    ₹
                </div>
                <div>
                    <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Live Conversion Rate</div>
                    <div class="text-white font-mono font-extrabold text-base">
                        1 USDT = <span class="text-emerald-400">₹<?= number_format($conversionRate, 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-lg shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Interactive Grid: Payment Setup & Dynamic QR -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Step 1 & 2: Package & Submission Form (7 Cols) -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl space-y-6">
            <h2 class="text-lg font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-cart-shopping text-emerald-400"></i>
                1. Select Package & Payment Details
            </h2>

            <form method="POST" action="/member/usdt_activate.php" enctype="multipart/form-data" id="activationForm" class="space-y-5">
                <?= csrfField() ?>
                <input type="hidden" name="inr_amount" id="inr_amount" value="">
                <input type="hidden" name="usdt_amount" id="usdt_amount" value="">
                <input type="hidden" name="network" id="network_input" value="BEP20">

                <!-- Package Dropdown -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="package_select">
                        Select Package Tier <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <select id="package_select" name="package_id" required
                            class="w-full px-4 py-3 bg-slate-800/90 border border-slate-700 rounded-2xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 font-medium">
                            <?php foreach ($packages as $pkg): ?>
                                <option value="<?= (int)$pkg['id'] ?>"
                                    data-inr="<?= (float)$pkg['inr_price'] ?>"
                                    data-usdt="<?= (float)$pkg['usdt_price'] ?>"
                                    data-matrix="<?= e($pkg['matrix_type']) ?>">
                                    <?= e($pkg['name']) ?> — ₹<?= number_format((float)$pkg['inr_price']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Network Switcher -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Choose Transfer Network <span class="text-rose-400">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" id="netBtnBEP20" onclick="switchNetwork('BEP20')"
                            class="flex items-center justify-center gap-2 py-3 px-4 rounded-2xl border text-xs font-bold transition bg-emerald-600/20 border-emerald-500 text-emerald-300 shadow-md">
                            <i class="fa-solid fa-cube text-amber-400 text-base"></i>
                            <div class="text-left">
                                <span class="block">BEP-20 (BNB Chain)</span>
                                <span class="text-[10px] text-emerald-400/80 font-normal">Fast & Low Gas</span>
                            </div>
                        </button>
                        <button type="button" id="netBtnTRC20" onclick="switchNetwork('TRC20')"
                            class="flex items-center justify-center gap-2 py-3 px-4 rounded-2xl border text-xs font-bold transition bg-slate-800/60 border-slate-700 text-slate-400 hover:text-white">
                            <i class="fa-solid fa-bolt text-rose-400 text-base"></i>
                            <div class="text-left">
                                <span class="block">TRC-20 (Tron)</span>
                                <span class="text-[10px] text-slate-500 font-normal">Standard USDT</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Live Summary Bar -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="text-[10px] uppercase font-bold text-slate-400">Package INR</div>
                        <div class="text-base font-extrabold text-white mt-0.5" id="summaryInr">₹0.00</div>
                    </div>
                    <div class="border-x border-slate-800">
                        <div class="text-[10px] uppercase font-bold text-slate-400">Exchange Rate</div>
                        <div class="text-base font-extrabold text-slate-300 mt-0.5">₹<?= number_format($conversionRate, 2) ?></div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase font-bold text-emerald-400">Payable USDT</div>
                        <div class="text-base font-mono font-extrabold text-emerald-400 mt-0.5" id="summaryUsdt">0.00 USDT</div>
                    </div>
                </div>

                <!-- Transaction Hash (TXID) Input -->
                <div class="pt-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="transaction_hash">
                        Receive Transaction Code / Hash (TXID) <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i class="fa-solid fa-hashtag text-xs"></i>
                        </div>
                        <input type="text" id="transaction_hash" name="transaction_hash" required
                            class="w-full pl-9 pr-3.5 py-3 bg-slate-800 border border-slate-700 rounded-2xl text-white font-mono text-xs placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            placeholder="e.g. 0x5727... or 64-character blockchain transaction hash">
                    </div>
                    <span class="text-[11px] text-slate-400 mt-1 block">Copy the completed TXID/Hash from your crypto wallet or exchange withdrawal screen.</span>
                </div>

                <!-- Screenshot / Receipt Proof -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="screenshot">
                        Payment Screenshot / Receipt <span class="text-slate-500 text-[10px] lowercase">(optional)</span>
                    </label>
                    <input type="file" id="screenshot" name="screenshot" accept="image/png, image/jpeg, image/webp"
                        class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                    <span class="text-[10px] text-slate-500 mt-1 block">PNG, JPG, or WEBP up to 5MB.</span>
                </div>

                <button type="submit" class="w-full py-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-500 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-sm shadow-xl shadow-emerald-950/50 transition">
                    Submit Activation Request
                </button>
            </form>
        </div>

        <!-- Step 2: Dynamic QR Code Canvas & Payment Details (5 Cols) -->
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl flex flex-col justify-between space-y-6">
            <div>
                <h2 class="text-lg font-bold text-white flex items-center justify-between border-b border-slate-800 pb-3">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-qrcode text-indigo-400"></i>
                        2. Scan & Pay
                    </span>
                    <span id="badgeNetwork" class="text-[10px] uppercase font-extrabold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        BEP-20
                    </span>
                </h2>

                <!-- Auto-Amount Badge -->
                <div class="mt-4 p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-center">
                    <span class="text-xs font-bold text-emerald-300" id="badgeAutoAmount">
                        Auto-Amount QR: 0.00 USDT (BEP20)
                    </span>
                </div>

                <!-- Dynamic QR Code Container -->
                <div class="my-5 p-5 bg-white rounded-2xl shadow-xl mx-auto flex flex-col items-center justify-center max-w-[260px] aspect-square">
                    <div id="dynamicQrCanvas" class="flex items-center justify-center"></div>
                </div>

                <!-- Receiving Address & Copy Action -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Official Receiving Wallet Address:</span>
                        <span id="networkLabel" class="text-[10px] font-mono font-bold text-slate-300">BNB Smart Chain (BEP20)</span>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="text" id="displayDepositAddress" readonly
                            class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono text-[11px] focus:outline-none select-all"
                            value="<?= e($bep20Address) ?>">
                        <button type="button" onclick="copyDepositAddress()"
                            class="px-3.5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold flex items-center gap-1.5 transition shrink-0"
                            title="Copy Wallet Address">
                            <i class="fa-solid fa-copy"></i>
                            <span id="copyBtnText">Copy</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Warning Notice Banner -->
            <div class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-200/90 text-xs space-y-1">
                <div class="font-bold flex items-center gap-1.5 text-amber-300">
                    <i class="fa-solid fa-triangle-exclamation"></i> Important Notice
                </div>
                <p id="networkWarning" class="text-[11px] leading-relaxed">
                    Only send <strong>BEP20 USDT</strong> to this address via BNB Smart Chain. Sending via another network or sending other coins may result in permanent loss.
                </p>
            </div>
        </div>
    </div>

    <!-- Activation History Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl">
        <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-slate-400"></i> My Activation Requests History
        </h2>

        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Req ID</th>
                        <th class="py-3 px-3">Package</th>
                        <th class="py-3 px-3">Network</th>
                        <th class="py-3 px-3">USDT Amount</th>
                        <th class="py-3 px-3">INR Value</th>
                        <th class="py-3 px-3">Transaction Code (TXID)</th>
                        <th class="py-3 px-3">Receipt</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($userPayments)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-8 text-slate-500">No USDT activation requests found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($userPayments as $req): ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-3 font-mono text-slate-400">#<?= (int)$req['id'] ?></td>
                                <td class="py-3 px-3 font-bold text-white"><?= e($req['package_name'] ?? 'Package') ?></td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold <?= $req['network'] === 'BEP20' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
                                        <?= e($req['network']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-mono font-bold text-emerald-400"><?= formatUsdt($req['usdt_amount']) ?> USDT</td>
                                <td class="py-3 px-3 font-mono text-slate-300">₹<?= number_format((float)$req['inr_amount'], 2) ?></td>
                                <td class="py-3 px-3 font-mono text-[11px] text-slate-300 max-w-xs truncate">
                                    <?php
                                    $explorerUrl = $req['network'] === 'BEP20'
                                        ? "https://bscscan.com/tx/" . urlencode($req['transaction_hash'])
                                        : "https://tronscan.org/#/transaction/" . urlencode($req['transaction_hash']);
                                    ?>
                                    <a href="<?= e($explorerUrl) ?>" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-400 underline flex items-center gap-1" title="<?= e($req['transaction_hash']) ?>">
                                        <?= substr($req['transaction_hash'], 0, 14) ?>...<?= substr($req['transaction_hash'], -8) ?>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                    </a>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if (!empty($req['screenshot_path']) && file_exists(__DIR__ . '/../' . $req['screenshot_path'])): ?>
                                        <a href="/<?= e($req['screenshot_path']) ?>" target="_blank" class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-indigo-300 text-[10px] font-semibold inline-flex items-center gap-1">
                                            <i class="fa-solid fa-image"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-600 text-[10px]">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $badge = match ($req['status']) {
                                        'approved' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                        'pending'  => 'bg-amber-500/20 text-amber-300 border-amber-500/30 animate-pulse',
                                        'rejected' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                        default    => 'bg-slate-800 text-slate-400 border-slate-700'
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?= $badge ?>">
                                        <?= e($req['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-slate-400 whitespace-nowrap">
                                    <?= date('M d, Y H:i', strtotime($req['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Live Configuration Variables
const TRC20_ADDRESS = <?= json_encode($trc20Address) ?>;
const BEP20_ADDRESS = <?= json_encode($bep20Address) ?>;
const CONVERSION_RATE = <?= (float)$conversionRate ?>;

let currentNetwork = 'BEP20';
let qrInstance = null;

function getCurrentPackageData() {
    const sel = document.getElementById('package_select');
    const opt = sel.options[sel.selectedIndex];
    const inr = parseFloat(opt.getAttribute('data-inr') || '0');
    const usdt = (inr / CONVERSION_RATE).toFixed(2);
    return { inr, usdt, name: opt.text };
}

function updateAmountsAndQr() {
    const data = getCurrentPackageData();

    // Update Hidden Fields
    document.getElementById('inr_amount').value = data.inr.toFixed(2);
    document.getElementById('usdt_amount').value = data.usdt;

    // Update Live Summary
    document.getElementById('summaryInr').textContent = '₹' + data.inr.toLocaleString('en-IN', {minimumFractionDigits: 2});
    document.getElementById('summaryUsdt').textContent = data.usdt + ' USDT';

    // Update Auto-Amount Badge
    document.getElementById('badgeAutoAmount').textContent = `Auto-Amount QR: ${data.usdt} USDT (${currentNetwork})`;

    // Generate URI conforming to protocol specifications
    const address = currentNetwork === 'BEP20' ? BEP20_ADDRESS : TRC20_ADDRESS;
    let paymentUri = '';

    if (currentNetwork === 'BEP20') {
        // EIP-681 standard compliant URI for Ethereum / BSC
        paymentUri = `ethereum:${address}?amount=${data.usdt}`;
    } else {
        // Tron URI standard with token parameter
        paymentUri = `tron:${address}?amount=${data.usdt}&token=USDT`;
    }

    renderQr(paymentUri);
}

function switchNetwork(network) {
    currentNetwork = network;
    document.getElementById('network_input').value = network;

    const btnBep = document.getElementById('netBtnBEP20');
    const btnTrc = document.getElementById('netBtnTRC20');
    const badgeNet = document.getElementById('badgeNetwork');
    const displayAddr = document.getElementById('displayDepositAddress');
    const networkLabel = document.getElementById('networkLabel');
    const warning = document.getElementById('networkWarning');

    if (network === 'BEP20') {
        btnBep.className = "flex items-center justify-center gap-2 py-3 px-4 rounded-2xl border text-xs font-bold transition bg-emerald-600/20 border-emerald-500 text-emerald-300 shadow-md";
        btnTrc.className = "flex items-center justify-center gap-2 py-3 px-4 rounded-2xl border text-xs font-bold transition bg-slate-800/60 border-slate-700 text-slate-400 hover:text-white";
        badgeNet.textContent = "BEP-20";
        badgeNet.className = "text-[10px] uppercase font-extrabold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30";
        displayAddr.value = BEP20_ADDRESS;
        networkLabel.textContent = "BNB Smart Chain (BEP20)";
        warning.innerHTML = "Only send <strong>BEP20 USDT</strong> to this address via BNB Smart Chain. Sending via another network or sending other coins may result in permanent loss.";
    } else {
        btnTrc.className = "flex items-center justify-center gap-2 py-3 px-4 rounded-2xl border text-xs font-bold transition bg-emerald-600/20 border-emerald-500 text-emerald-300 shadow-md";
        btnBep.className = "flex items-center justify-center gap-2 py-3 px-4 rounded-2xl border text-xs font-bold transition bg-slate-800/60 border-slate-700 text-slate-400 hover:text-white";
        badgeNet.textContent = "TRC-20";
        badgeNet.className = "text-[10px] uppercase font-extrabold px-2 py-0.5 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30";
        displayAddr.value = TRC20_ADDRESS;
        networkLabel.textContent = "Tron Network (TRC20)";
        warning.innerHTML = "Only send <strong>TRC20 USDT</strong> to this address via Tron Network. Sending via another network or sending other coins may result in permanent loss.";
    }

    updateAmountsAndQr();
}

function renderQr(text) {
    const container = document.getElementById('dynamicQrCanvas');
    container.innerHTML = '';

    if (typeof QRCode !== 'undefined') {
        qrInstance = new QRCode(container, {
            text: text,
            width: 220,
            height: 220,
            colorDark: "#020617",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    } else {
        container.innerHTML = `<div class="text-xs text-slate-500 text-center p-4">Loading QR generator...</div>`;
    }
}

function copyDepositAddress() {
    const input = document.getElementById('displayDepositAddress');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        const btnText = document.getElementById('copyBtnText');
        btnText.textContent = "Copied!";
        setTimeout(() => { btnText.textContent = "Copy"; }, 2000);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('package_select').addEventListener('change', updateAmountsAndQr);
    updateAmountsAndQr();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
