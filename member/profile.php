<?php
/**
 * Member Profile & USDT Payout Wallets Configuration
 * Supports Personal TRC20 and BEP20 Payout Addresses + Personal QR Code Upload
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$pdo = getDatabaseConnection();

// Fetch latest comprehensive user profile from DB
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $gender = trim($_POST['gender'] ?? '');
            $dob = trim($_POST['dob'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $district = trim($_POST['district'] ?? '');
            $stateProvince = trim($_POST['state_province'] ?? '');
            $country = trim($_POST['country'] ?? '');

            // Payout wallet fields (dual network support)
            $usdtTrc20 = trim($_POST['usdt_trc20_address'] ?? '');
            $usdtBep20 = trim($_POST['usdt_bep20_address'] ?? '');
            $usdtNetwork = trim($_POST['usdt_network'] ?? 'BEP20');

            // Primary fallback wallet address
            $primaryWallet = ($usdtNetwork === 'TRC20') ? $usdtTrc20 : $usdtBep20;
            if (empty($primaryWallet)) {
                $primaryWallet = !empty($usdtBep20) ? $usdtBep20 : $usdtTrc20;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $validGenders = ['male', 'female', 'other', 'prefer_not_to_say'];
                $genderVal = in_array($gender, $validGenders, true) ? $gender : null;
                $dobVal = !empty($dob) ? $dob : null;

                // Handle Personal Payout QR Code image upload if provided
                $qrCodePath = $user['usdt_qr_path'] ?: $user['qr_code_path'];
                if (isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $qrCodePath = handleQrCodeUpload($_FILES['qr_code']);
                    } catch (Exception $e) {
                        $error = 'QR Code upload error: ' . $e->getMessage();
                    }
                }

                if (!$error) {
                    $upStmt = $pdo->prepare("
                        UPDATE users SET
                            email = :e,
                            phone = :phone,
                            gender = :gender,
                            dob = :dob,
                            address = :address,
                            city = :city,
                            district = :district,
                            state_province = :state,
                            country = :country,
                            usdt_wallet = :wallet,
                            usdt_network = :network,
                            usdt_trc20_address = :trc20,
                            usdt_bep20_address = :bep20,
                            usdt_qr_path = :qr,
                            qr_code_path = :qr_legacy
                        WHERE id = :id
                    ");
                    $upStmt->execute([
                        ':e'         => $email,
                        ':phone'     => !empty($phone) ? $phone : null,
                        ':gender'    => $genderVal,
                        ':dob'       => $dobVal,
                        ':address'   => !empty($address) ? $address : null,
                        ':city'      => !empty($city) ? $city : null,
                        ':district'  => !empty($district) ? $district : null,
                        ':state'     => !empty($stateProvince) ? $stateProvince : null,
                        ':country'   => !empty($country) ? $country : null,
                        ':wallet'    => !empty($primaryWallet) ? $primaryWallet : null,
                        ':network'   => !empty($usdtNetwork) ? $usdtNetwork : 'BEP20',
                        ':trc20'     => !empty($usdtTrc20) ? $usdtTrc20 : null,
                        ':bep20'     => !empty($usdtBep20) ? $usdtBep20 : null,
                        ':qr'        => $qrCodePath,
                        ':qr_legacy' => $qrCodePath,
                        ':id'        => $userId,
                    ]);

                    logActivity($userId, "Updated profile personal details and USDT payout wallet settings");
                    setFlash('success', 'Profile and USDT payout settings updated successfully.');
                    header('Location: /member/profile.php');
                    exit;
                }
            }
        } elseif ($action === 'change_password') {
            $currPass = $_POST['current_password'] ?? '';
            $newPass  = $_POST['new_password'] ?? '';
            $confPass = $_POST['confirm_password'] ?? '';

            if (empty($currPass) || empty($newPass) || empty($confPass)) {
                $error = 'All password fields are required.';
            } elseif (!password_verify($currPass, $user['password'])) {
                $error = 'Current password does not match.';
            } elseif ($newPass !== $confPass) {
                $error = 'New password and confirmation do not match.';
            } elseif (strlen($newPass) < 6) {
                $error = 'New password must be at least 6 characters.';
            } else {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $pStmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                $pStmt->execute([':p' => $hash, ':id' => $userId]);

                logActivity($userId, "Changed account password");
                setFlash('success', 'Password updated successfully.');
                header('Location: /member/profile.php');
                exit;
            }
        }
    }
}

$pageTitle = 'Profile & Wallet Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8 max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Account & Wallet Settings</h1>
            <p class="text-slate-400 text-xs mt-1">Manage your personal profile, dual USDT payout addresses, and payout QR code.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 rounded-full text-xs font-mono font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                User ID: #<?= (int)$user['id'] ?> (<?= e($user['username']) ?>)
            </span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-base"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/member/profile.php" enctype="multipart/form-data" class="space-y-6">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Personal Information (7 Cols) -->
            <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
                <h2 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-id-card text-emerald-400"></i> Personal Information
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Username</label>
                        <input type="text" disabled value="<?= e($user['username']) ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-400 text-sm cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required value="<?= e($user['email']) ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="phone">Phone / Mobile</label>
                        <input type="tel" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            placeholder="+1 (555) 000-0000">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="gender">Gender</label>
                        <select id="gender" name="gender"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">Select Gender</option>
                            <option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= ($user['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                            <option value="prefer_not_to_say" <?= ($user['gender'] ?? '') === 'prefer_not_to_say' ? 'selected' : '' ?>>Prefer not to say</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob" value="<?= e($user['dob'] ?? '') ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="country">Country</label>
                        <input type="text" id="country" name="country" value="<?= e($user['country'] ?? '') ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            placeholder="United States, India, UK, etc.">
                    </div>
                </div>

                <div class="border-t border-slate-800/80 pt-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                        <i class="fa-solid fa-map-location-dot text-indigo-400"></i> Address Details
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-3">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="address">Street Address</label>
                            <textarea id="address" name="address" rows="2"
                                class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                placeholder="Apartment, suite, building, street address"><?= e($user['address'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="city">City / Place</label>
                            <input type="text" id="city" name="city" value="<?= e($user['city'] ?? '') ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="district">District / County</label>
                            <input type="text" id="district" name="district" value="<?= e($user['district'] ?? '') ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="state_province">State / Province</label>
                            <input type="text" id="state_province" name="state_province" value="<?= e($user['state_province'] ?? '') ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: USDT Payout Wallets & Personal QR (5 Cols) -->
            <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5 flex flex-col justify-between">
                <div class="space-y-4">
                    <h2 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                        <i class="fa-solid fa-wallet text-amber-400"></i> Personal USDT Payout Settings
                    </h2>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="usdt_network">Primary Payout Network</label>
                        <select id="usdt_network" name="usdt_network"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 font-semibold">
                            <option value="BEP20" <?= ($user['usdt_network'] ?? 'BEP20') === 'BEP20' ? 'selected' : '' ?>>BNB Smart Chain (BEP20) - Fast Payouts</option>
                            <option value="TRC20" <?= ($user['usdt_network'] ?? '') === 'TRC20' ? 'selected' : '' ?>>Tron Network (TRC20) - Low Fee</option>
                        </select>
                    </div>

                    <!-- BEP-20 Address -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="usdt_bep20_address">
                            <i class="fa-solid fa-cube text-amber-400 mr-1"></i> USDT Address (BEP-20 / BSC)
                        </label>
                        <input type="text" id="usdt_bep20_address" name="usdt_bep20_address"
                            value="<?= e($user['usdt_bep20_address'] ?? ($user['usdt_network'] === 'BEP20' ? $user['usdt_wallet'] : '')) ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-amber-500"
                            placeholder="e.g. 0x71C8395B0F751beF48cf1E8e8A3669a8B8497645">
                    </div>

                    <!-- TRC-20 Address -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="usdt_trc20_address">
                            <i class="fa-solid fa-bolt text-rose-400 mr-1"></i> USDT Address (TRC-20 / Tron)
                        </label>
                        <input type="text" id="usdt_trc20_address" name="usdt_trc20_address"
                            value="<?= e($user['usdt_trc20_address'] ?? ($user['usdt_network'] === 'TRC20' ? $user['usdt_wallet'] : '')) ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-rose-500"
                            placeholder="e.g. TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t">
                    </div>

                    <!-- Personal QR Code Upload -->
                    <div class="border-t border-slate-800/80 pt-4">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            <i class="fa-solid fa-qrcode text-indigo-400 mr-1"></i> Personal Payout QR Code
                        </label>

                        <?php
                        $userQr = !empty($user['usdt_qr_path']) ? $user['usdt_qr_path'] : $user['qr_code_path'];
                        ?>
                        <?php if (!empty($userQr) && file_exists(__DIR__ . '/../' . $userQr)): ?>
                            <div class="mb-3 p-3 bg-slate-950 rounded-xl border border-slate-800 text-center">
                                <p class="text-[11px] text-slate-400 mb-2">Saved Payout QR Code:</p>
                                <img src="/<?= e($userQr) ?>" alt="Payout QR Code" class="w-32 h-32 mx-auto object-contain rounded-lg border border-slate-700 bg-white p-1 shadow-md">
                            </div>
                        <?php else: ?>
                            <div class="mb-3 p-3 bg-slate-950/50 rounded-xl border border-dashed border-slate-800 text-center">
                                <i class="fa-solid fa-qrcode text-2xl text-slate-600 mb-1"></i>
                                <p class="text-[10px] text-slate-400">No personal payout QR uploaded yet.</p>
                            </div>
                        <?php endif; ?>

                        <input type="file" id="qr_code" name="qr_code" accept="image/png, image/jpeg, image/webp"
                            class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                        <span class="text-[10px] text-slate-500 mt-1 block">PNG, JPG, or WEBP up to 5MB.</span>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-900/30 transition">
                        Save Profile & Payout Settings
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Change Password Section -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl max-w-xl">
        <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2 border-b border-slate-800 pb-3">
            <i class="fa-solid fa-shield-halved text-indigo-400"></i> Change Password
        </h2>

        <form method="POST" action="/member/profile.php" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_password">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required
                    class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition">
                    Update Password
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
