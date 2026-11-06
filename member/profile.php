<?php
/**
 * Member Profile & USDT Wallet Configuration
 * Expanded to support optional profile details (Phone, Gender, DOB, Address, City, District, State, Country)
 * and USDT Wallet details (Address, Network, QR Code Upload)
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

            // USDT wallet fields
            $usdtWallet = trim($_POST['usdt_wallet'] ?? '');
            $usdtNetwork = trim($_POST['usdt_network'] ?? 'TRC20');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Validate optional gender (email uniqueness is not required)
                $validGenders = ['male', 'female', 'other', 'prefer_not_to_say'];
                $genderVal = in_array($gender, $validGenders, true) ? $gender : null;
                $dobVal = !empty($dob) ? $dob : null;

                    // Handle QR Code image upload if provided
                    $qrCodePath = $user['qr_code_path'];
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
                                qr_code_path = :qr
                            WHERE id = :id
                        ");
                        $upStmt->execute([
                            ':e'        => $email,
                            ':phone'    => !empty($phone) ? $phone : null,
                            ':gender'   => $genderVal,
                            ':dob'      => $dobVal,
                            ':address'  => !empty($address) ? $address : null,
                            ':city'     => !empty($city) ? $city : null,
                            ':district' => !empty($district) ? $district : null,
                            ':state'    => !empty($stateProvince) ? $stateProvince : null,
                            ':country'  => !empty($country) ? $country : null,
                            ':wallet'   => !empty($usdtWallet) ? $usdtWallet : null,
                            ':network'  => !empty($usdtNetwork) ? $usdtNetwork : 'TRC20',
                            ':qr'       => $qrCodePath,
                            ':id'       => $userId,
                        ]);

                        logActivity($userId, "Updated profile personal details and USDT wallet information.");
                        setFlash('success', 'Profile and wallet details successfully updated.');
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
}

$pageTitle = 'Member Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-user-gear text-indigo-400"></i> Account Settings & Payout Wallet
            </h1>
            <p class="text-xs text-slate-400 mt-1">Manage personal details, location, security credentials, and USDT wallet with QR code.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="px-3 py-1 text-xs rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                User ID: <span class="text-emerald-400 font-mono font-bold">#<?= e((string)$user['id']) ?></span>
            </span>
            <span class="px-3 py-1 text-xs rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                Role: <span class="text-indigo-400 font-bold uppercase"><?= e($user['role']) ?></span>
            </span>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="/member/profile.php" enctype="multipart/form-data" class="space-y-8">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="update_profile">

        <!-- Grid 1: Basic & Location Details -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Basic Details -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
                <h2 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-id-card text-emerald-400"></i> Personal & Contact Information
                    <span class="text-[11px] font-normal text-slate-400 ml-auto">(Details are optional)</span>
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Username</label>
                        <input type="text" disabled value="<?= e($user['username']) ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-400 text-sm cursor-not-allowed">
                        <span class="text-[10px] text-slate-500">Immutable for BFS matrix tree integrity.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required value="<?= e($user['email']) ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            placeholder="+1 (555) 000-0000">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="gender">Gender</label>
                        <select id="gender" name="gender"
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="">-- Select Gender --</option>
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
                            placeholder="United States, UK, Canada, etc.">
                    </div>
                </div>

                <div class="border-t border-slate-800/80 pt-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                        <i class="fa-solid fa-map-location-dot text-indigo-400"></i> Address & Location
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
                                class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                placeholder="New York, London, etc.">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="district">District / County</label>
                            <input type="text" id="district" name="district" value="<?= e($user['district'] ?? '') ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                placeholder="District name">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="state_province">State / Province</label>
                            <input type="text" id="state_province" name="state_province" value="<?= e($user['state_province'] ?? '') ?>"
                                class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                placeholder="State / Region">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wallet & QR Code -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
                <h2 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                    <i class="fa-solid fa-wallet text-amber-400"></i> USDT Payout Wallet
                </h2>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="usdt_network">USDT Network</label>
                    <select id="usdt_network" name="usdt_network"
                        class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 font-medium">
                        <option value="TRC20" <?= ($user['usdt_network'] ?? 'TRC20') === 'TRC20' ? 'selected' : '' ?>>TRON (TRC20) - Low Fee</option>
                        <option value="BEP20" <?= ($user['usdt_network'] ?? '') === 'BEP20' ? 'selected' : '' ?>>BNB Smart Chain (BEP20)</option>
                        <option value="ERC20" <?= ($user['usdt_network'] ?? '') === 'ERC20' ? 'selected' : '' ?>>Ethereum (ERC20)</option>
                        <option value="POLYGON" <?= ($user['usdt_network'] ?? '') === 'POLYGON' ? 'selected' : '' ?>>Polygon (USDT-POS)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="usdt_wallet">USDT Payout Address</label>
                    <input type="text" id="usdt_wallet" name="usdt_wallet" value="<?= e($user['usdt_wallet'] ?? '') ?>"
                        class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-amber-500"
                        placeholder="e.g. TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t">
                    <span class="text-[10px] text-slate-400 mt-1 block">Your matrix commissions & withdrawals will be sent here.</span>
                </div>

                <div class="border-t border-slate-800/80 pt-4">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                        <i class="fa-solid fa-qrcode text-indigo-400 mr-1"></i> Wallet QR Code Image
                    </label>

                    <?php if (!empty($user['qr_code_path']) && file_exists(__DIR__ . '/../' . $user['qr_code_path'])): ?>
                        <div class="mb-4 p-3 bg-slate-950 rounded-xl border border-slate-800 text-center">
                            <p class="text-[11px] text-slate-400 mb-2">Saved QR Code:</p>
                            <img src="/<?= e($user['qr_code_path']) ?>" alt="Wallet QR Code" class="w-36 h-36 mx-auto object-contain rounded-lg border border-slate-700 bg-white p-1 shadow-md">
                        </div>
                    <?php else: ?>
                        <div class="mb-3 p-4 bg-slate-950/50 rounded-xl border border-dashed border-slate-800 text-center">
                            <i class="fa-solid fa-qrcode text-3xl text-slate-600 mb-1"></i>
                            <p class="text-[11px] text-slate-400">No QR Code uploaded yet.</p>
                        </div>
                    <?php endif; ?>

                    <input type="file" id="qr_code" name="qr_code" accept="image/png, image/jpeg, image/webp, image/gif"
                        class="block w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                    <span class="text-[10px] text-slate-500 mt-1 block">PNG, JPG, WEBP up to 5MB.</span>
                </div>

                <button type="submit" class="w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-900/30 transition">
                    Save Profile & Wallet Details
                </button>
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
