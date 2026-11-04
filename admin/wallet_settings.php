<?php
/**
 * Admin Official Company USDT Deposit Wallet & QR Code Configuration
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$user = getCurrentUser();
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $walletAddress = trim($_POST['wallet_address'] ?? '');
        $network = trim($_POST['network'] ?? 'TRC20');

        if (empty($walletAddress) || strlen($walletAddress) < 10) {
            $error = 'Please enter a valid USDT deposit wallet address (e.g., TRC20, BEP20).';
        } else {
            $currentConfig = getSystemDepositWallet();
            $qrCodePath = $currentConfig['qr_code_path'];

            if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $qrCodePath = handleQrCodeUpload($_FILES['qr_code']);
                } catch (Exception $e) {
                    $error = 'QR Code upload error: ' . $e->getMessage();
                }
            }

            if (!$error) {
                updateSystemDepositWallet($walletAddress, $network, $qrCodePath);
                logActivity((int)$user['id'], "Updated official company USDT deposit wallet ({$network}: {$walletAddress}) and QR code.");
                setFlash('success', 'Official company USDT deposit wallet and QR code updated successfully. All members will now see these updated payment instructions.');
                header('Location: /admin/wallet_settings.php');
                exit;
            }
        }
    }
}

$systemWallet = getSystemDepositWallet();
$pageTitle = 'Official USDT Wallet Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-building-columns text-emerald-400"></i> Official Company Deposit Wallet
            </h1>
            <p class="text-xs text-slate-400 mt-1">Configure the official USDT wallet address and QR code displayed to all platform members for deposits.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/transactions.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl transition flex items-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Back to Transactions
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Configuration Form -->
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-gear text-indigo-400"></i> Deposit Gateway Parameters
            </h2>

            <form method="POST" action="/admin/wallet_settings.php" enctype="multipart/form-data" class="space-y-5">
                <?= csrfField() ?>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="network">
                        Blockchain Network
                    </label>
                    <select id="network" name="network" required
                        class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="TRC20" <?= $systemWallet['network'] === 'TRC20' ? 'selected' : '' ?>>TRON (TRC20) - Fast & Low Fee</option>
                        <option value="BEP20" <?= $systemWallet['network'] === 'BEP20' ? 'selected' : '' ?>>BNB Smart Chain (BEP20)</option>
                        <option value="ERC20" <?= $systemWallet['network'] === 'ERC20' ? 'selected' : '' ?>>Ethereum (ERC20)</option>
                        <option value="POLYGON" <?= $systemWallet['network'] === 'POLYGON' ? 'selected' : '' ?>>Polygon (USDT-POS)</option>
                    </select>
                    <span class="text-[11px] text-slate-500 mt-1 block">Specify which network members should send their USDT on.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="wallet_address">
                        Official USDT Wallet Address
                    </label>
                    <input type="text" id="wallet_address" name="wallet_address" required
                        value="<?= e($systemWallet['wallet_address']) ?>"
                        class="w-full px-4 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        placeholder="e.g. TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t">
                    <span class="text-[11px] text-slate-500 mt-1 block">Members copy this recipient address when funding their account.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="qr_code">
                        Upload Official Wallet QR Code Image
                    </label>
                    <input type="file" id="qr_code" name="qr_code" accept="image/png, image/jpeg, image/webp, image/gif"
                        class="block w-full text-xs text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                    <span class="text-[11px] text-slate-500 mt-1 block">PNG, JPG, or WEBP up to 5MB. If uploaded, replaces the previous QR code image.</span>
                </div>

                <div class="pt-3 border-t border-slate-800">
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-900/30 transition flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Save Official Wallet & Publish to Members
                    </button>
                </div>
            </form>
        </div>

        <!-- Live Member Preview -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
            <div>
                <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-eye text-teal-400"></i> Member View Preview
                </h2>
                <p class="text-xs text-slate-400 mb-4">This is exactly what all platform members see in their Deposit screen:</p>

                <div class="p-4 bg-slate-950 rounded-xl border border-slate-800 text-center space-y-3">
                    <span class="inline-block px-3 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Official Deposit Gateway (<?= e($systemWallet['network']) ?>)
                    </span>

                    <div class="my-2">
                        <?php if (!empty($systemWallet['qr_code_path']) && file_exists(__DIR__ . '/../' . $systemWallet['qr_code_path'])): ?>
                            <img src="/<?= e($systemWallet['qr_code_path']) ?>" alt="Official QR Code" class="w-40 h-40 mx-auto object-contain rounded-xl border border-slate-700 bg-white p-2 shadow-lg">
                        <?php else: ?>
                            <div class="w-36 h-36 mx-auto bg-slate-900 rounded-xl border border-dashed border-slate-700 flex flex-col items-center justify-center text-slate-600">
                                <i class="fa-solid fa-qrcode text-4xl mb-1"></i>
                                <span class="text-[10px]">No QR Uploaded</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-left bg-slate-900 p-3 rounded-lg border border-slate-800">
                        <span class="text-[10px] text-slate-500 block uppercase font-mono">Company Deposit Address:</span>
                        <span class="text-xs text-emerald-400 font-mono break-all font-semibold select-all"><?= e($systemWallet['wallet_address']) ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-800 text-[11px] text-slate-500">
                <i class="fa-solid fa-circle-check text-emerald-400 mr-1"></i> Changes take effect across all member sessions instantly upon saving.
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
