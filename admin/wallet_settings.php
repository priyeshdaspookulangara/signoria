<?php
/**
 * Admin Official Company USDT Deposit Gateways & Live Rate Settings
 * Dual Network Support: TRC-20 (Tron) & BEP-20 (BNB Smart Chain)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$admin = getCurrentUser();
$pdo = getDatabaseConnection();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $rate       = (float)($_POST['usdt_conversion_rate'] ?? 90.00);
        $trc20Addr  = trim($_POST['usdt_trc20_address'] ?? '');
        $bep20Addr  = trim($_POST['usdt_bep20_address'] ?? '');

        if ($rate <= 0) {
            $error = 'Conversion rate must be greater than 0.';
        } elseif (empty($trc20Addr) || empty($bep20Addr)) {
            $error = 'Both TRC20 and BEP20 corporate deposit addresses are required.';
        } else {
            // Fetch current QR paths
            $currSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            $trc20Qr = $currSettings['usdt_trc20_qr_path'] ?? null;
            $bep20Qr = $currSettings['usdt_bep20_qr_path'] ?? ($currSettings['company_qr_code'] ?? null);

            // Handle TRC20 QR Upload
            if (isset($_FILES['trc20_qr']) && $_FILES['trc20_qr']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $trc20Qr = handleQrCodeUpload($_FILES['trc20_qr']);
                } catch (Exception $e) {
                    $error = 'TRC20 QR Upload error: ' . $e->getMessage();
                }
            }

            // Handle BEP20 QR Upload
            if (!$error && isset($_FILES['bep20_qr']) && $_FILES['bep20_qr']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $bep20Qr = handleQrCodeUpload($_FILES['bep20_qr']);
                } catch (Exception $e) {
                    $error = 'BEP20 QR Upload error: ' . $e->getMessage();
                }
            }

            if (!$error) {
                $updates = [
                    'usdt_conversion_rate' => number_format($rate, 2, '.', ''),
                    'usdt_trc20_address'   => $trc20Addr,
                    'usdt_bep20_address'   => $bep20Addr,
                    'usdt_trc20_qr_path'   => $trc20Qr,
                    'usdt_bep20_qr_path'   => $bep20Qr,
                    // Backward-compatible settings
                    'company_usdt_wallet'  => $bep20Addr,
                    'company_usdt_network' => 'BEP20',
                    'company_qr_code'      => $bep20Qr
                ];

                $upStmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");

                foreach ($updates as $k => $v) {
                    $upStmt->execute([$k, $v]);
                }

                logActivity((int)$admin['id'], "Updated Corporate USDT Gateway settings (Rate: ₹{$rate}, TRC20: {$trc20Addr}, BEP20: {$bep20Addr})");
                setFlash('success', 'Corporate USDT gateways and conversion rate updated successfully.');
                header('Location: /admin/wallet_settings.php');
                exit;
            }
        }
    }
}

// Fetch current settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$rate       = (float)($settings['usdt_conversion_rate'] ?? 90.00);
$trc20Addr  = $settings['usdt_trc20_address'] ?? 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
$bep20Addr  = $settings['usdt_bep20_address'] ?? ($settings['company_usdt_wallet'] ?? '0x38CC4cd0433e64dAC73Dca87DDedDac27d12bC59');
$trc20Qr    = $settings['usdt_trc20_qr_path'] ?? 'uploads/qr_codes/company_official_qr.png';
$bep20Qr    = $settings['usdt_bep20_qr_path'] ?? ($settings['company_qr_code'] ?? 'uploads/qr_codes/company_bep20_qr.png');

$pageTitle = 'Corporate USDT Gateway Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-6xl mx-auto">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-building-columns text-emerald-400"></i> Corporate USDT Gateways & Rates
            </h1>
            <p class="text-xs text-slate-400 mt-1">Configure live INR-to-USDT conversion rates, dual network deposit wallets (TRC-20 & BEP-20), and official QR codes.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/usdt_activations.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold rounded-xl transition flex items-center gap-2 shadow-md">
                <i class="fa-solid fa-bolt"></i> View Activations
            </a>
            <a href="/admin/transactions.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Transactions
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/wallet_settings.php" enctype="multipart/form-data" class="space-y-6">
        <?= csrfField() ?>

        <!-- Conversion Rate Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-coins text-amber-400"></i> Currency Conversion Engine
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="usdt_conversion_rate">
                        USDT Exchange Rate (INR per 1 USDT) <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 font-bold">
                            ₹
                        </div>
                        <input type="number" step="0.01" min="1" id="usdt_conversion_rate" name="usdt_conversion_rate" required
                            value="<?= number_format($rate, 2, '.', '') ?>"
                            class="w-full pl-8 pr-4 py-3 bg-slate-800 border border-slate-700 rounded-2xl text-white font-mono text-base font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <span class="text-[11px] text-slate-500 mt-1 block">Used automatically to compute USDT amounts in dynamic QR codes and member activation flows.</span>
                </div>
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 text-center">
                    <div class="text-[10px] uppercase font-bold text-slate-400">Current Formula</div>
                    <div class="text-emerald-400 font-mono font-bold text-sm mt-1">
                        USDT = (Package INR / <?= number_format($rate, 2) ?>)
                    </div>
                </div>
            </div>
        </div>

        <!-- Dual Gateway Setup: BEP-20 and TRC-20 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- BEP-20 (BNB Smart Chain) Gateway -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-cube text-amber-400"></i> BEP-20 (BNB Smart Chain) Gateway
                    </h2>
                    <span class="text-[10px] uppercase font-mono font-extrabold px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 border border-amber-500/30">
                        Primary Fast Net
                    </span>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="usdt_bep20_address">
                        Corporate BEP-20 Address <span class="text-rose-400">*</span>
                    </label>
                    <input type="text" id="usdt_bep20_address" name="usdt_bep20_address" required
                        value="<?= e($bep20Addr) ?>"
                        class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="0x38CC4cd0433e64dAC73Dca87DDedDac27d12bC59">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        BEP-20 QR Code Image
                    </label>
                    <?php if (!empty($bep20Qr) && file_exists(__DIR__ . '/../' . $bep20Qr)): ?>
                        <div class="mb-3 p-3 bg-slate-950 rounded-2xl border border-slate-800 text-center">
                            <img src="/<?= e($bep20Qr) ?>" alt="BEP20 QR" class="w-32 h-32 mx-auto object-contain rounded-xl bg-white p-2 border border-slate-700 shadow-md">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="bep20_qr" accept="image/png, image/jpeg, image/webp"
                        class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                </div>
            </div>

            <!-- TRC-20 (Tron Network) Gateway -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-bolt text-rose-400"></i> TRC-20 (Tron Network) Gateway
                    </h2>
                    <span class="text-[10px] uppercase font-mono font-extrabold px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30">
                        Tron USDT
                    </span>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="usdt_trc20_address">
                        Corporate TRC-20 Address <span class="text-rose-400">*</span>
                    </label>
                    <input type="text" id="usdt_trc20_address" name="usdt_trc20_address" required
                        value="<?= e($trc20Addr) ?>"
                        class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-rose-500"
                        placeholder="TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        TRC-20 QR Code Image
                    </label>
                    <?php if (!empty($trc20Qr) && file_exists(__DIR__ . '/../' . $trc20Qr)): ?>
                        <div class="mb-3 p-3 bg-slate-950 rounded-2xl border border-slate-800 text-center">
                            <img src="/<?= e($trc20Qr) ?>" alt="TRC20 QR" class="w-32 h-32 mx-auto object-contain rounded-xl bg-white p-2 border border-slate-700 shadow-md">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="trc20_qr" accept="image/png, image/jpeg, image/webp"
                        class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                </div>
            </div>
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-sm shadow-xl shadow-emerald-950/40 transition flex items-center justify-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> Save Corporate Gateways & Rates
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
