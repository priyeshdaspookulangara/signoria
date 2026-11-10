<?php
/**
 * Admin User Management (CRUD: Edit, Delete with Full Cascade, Status Toggle, & BFS Check)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bfs_engine.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDatabaseConnection();
$adminUser = getCurrentUser();

// 1. Handle Delete Member Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);

    if ($targetUserId === (int)$adminUser['id']) {
        setFlash('danger', 'You cannot delete your own administrator account.');
    } else {
        $chkStmt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
        $chkStmt->execute([':id' => $targetUserId]);
        $targetUsername = $chkStmt->fetchColumn();

        if (!$targetUsername) {
            setFlash('danger', 'User not found.');
        } else {
            $pdo->beginTransaction();
            try {
                // Delete user's transactions
                $delTx = $pdo->prepare("DELETE FROM transactions WHERE user_id = :uid");
                $delTx->execute([':uid' => $targetUserId]);

                // Delete user's matrix nodes
                $delNodes = $pdo->prepare("DELETE FROM matrix_nodes WHERE user_id = :uid");
                $delNodes->execute([':uid' => $targetUserId]);

                // Delete user's activity logs
                $delLogs = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = :uid");
                $delLogs->execute([':uid' => $targetUserId]);

                // Clear sponsor_id references in other users who had this user as sponsor (set to null)
                $upSponsors = $pdo->prepare("UPDATE users SET sponsor_id = NULL WHERE sponsor_id = :uid");
                $upSponsors->execute([':uid' => $targetUserId]);

                // Delete the user record
                $delUser = $pdo->prepare("DELETE FROM users WHERE id = :uid");
                $delUser->execute([':uid' => $targetUserId]);

                $pdo->commit();

                logActivity((int)$adminUser['id'], "Admin completely deleted user '{$targetUsername}' (#{$targetUserId}) and purged all their transactions, matrix nodes, and logs.");
                setFlash('success', "User '{$targetUsername}' and all associated transactions, matrix nodes, and logs have been deleted successfully.");
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Failed to delete user: " . $e->getMessage());
                setFlash('danger', "Failed to delete user: " . $e->getMessage());
            }
        }
    }
    header('Location: /admin/users.php');
    exit;
}

// 2. Handle Edit Member Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $stateProvince = trim($_POST['state_province'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $usdtWallet = trim($_POST['usdt_wallet'] ?? '');
    $usdtNetwork = trim($_POST['usdt_network'] ?? 'TRC20');
    $status = trim($_POST['status'] ?? 'active');
    $role = trim($_POST['role'] ?? 'member');
    $newPassword = $_POST['new_password'] ?? '';

    if (empty($username) || empty($email)) {
        setFlash('danger', 'Username and email cannot be empty.');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('danger', 'Please enter a valid email address.');
    } elseif (!in_array($status, ['active', 'suspended', 'pending'], true)) {
        setFlash('danger', 'Invalid status selected.');
    } elseif (!in_array($role, ['admin', 'member'], true)) {
        setFlash('danger', 'Invalid role selected.');
    } else {
        // Check uniqueness of username among other accounts (email does not need to be unique)
        $uStmt = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1");
        $uStmt->execute([':u' => $username, ':id' => $targetUserId]);
        if ($uStmt->fetch()) {
            setFlash('danger', 'Username is already taken by another account.');
        } else {
            try {
                $validGenders = ['male', 'female', 'other', 'prefer_not_to_say'];
                $genderVal = in_array($gender, $validGenders, true) ? $gender : null;
                $dobVal = !empty($dob) ? $dob : null;

                if (!empty($newPassword) && strlen($newPassword) >= 6) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $upStmt = $pdo->prepare("
                        UPDATE users
                        SET username = :u, email = :e, phone = :phone, gender = :gender, dob = :dob,
                            address = :address, city = :city, district = :district, state_province = :state, country = :country,
                            usdt_wallet = :w, usdt_network = :net, status = :s, role = :r, password = :p
                        WHERE id = :id
                    ");
                    $upStmt->execute([
                        ':u'       => $username,
                        ':e'       => $email,
                        ':phone'   => !empty($phone) ? $phone : null,
                        ':gender'  => $genderVal,
                        ':dob'     => $dobVal,
                        ':address' => !empty($address) ? $address : null,
                        ':city'    => !empty($city) ? $city : null,
                        ':district'=> !empty($district) ? $district : null,
                        ':state'   => !empty($stateProvince) ? $stateProvince : null,
                        ':country' => !empty($country) ? $country : null,
                        ':w'       => !empty($usdtWallet) ? $usdtWallet : null,
                        ':net'     => !empty($usdtNetwork) ? $usdtNetwork : 'TRC20',
                        ':s'       => $status,
                        ':r'       => $role,
                        ':p'       => $hash,
                        ':id'      => $targetUserId,
                    ]);
                } else {
                    $upStmt = $pdo->prepare("
                        UPDATE users
                        SET username = :u, email = :e, phone = :phone, gender = :gender, dob = :dob,
                            address = :address, city = :city, district = :district, state_province = :state, country = :country,
                            usdt_wallet = :w, usdt_network = :net, status = :s, role = :r
                        WHERE id = :id
                    ");
                    $upStmt->execute([
                        ':u'       => $username,
                        ':e'       => $email,
                        ':phone'   => !empty($phone) ? $phone : null,
                        ':gender'  => $genderVal,
                        ':dob'     => $dobVal,
                        ':address' => !empty($address) ? $address : null,
                        ':city'    => !empty($city) ? $city : null,
                        ':district'=> !empty($district) ? $district : null,
                        ':state'   => !empty($stateProvince) ? $stateProvince : null,
                        ':country' => !empty($country) ? $country : null,
                        ':w'       => !empty($usdtWallet) ? $usdtWallet : null,
                        ':net'     => !empty($usdtNetwork) ? $usdtNetwork : 'TRC20',
                        ':s'       => $status,
                        ':r'       => $role,
                        ':id'      => $targetUserId,
                    ]);
                }

                logActivity((int)$adminUser['id'], "Admin updated details for user '{$username}' (#{$targetUserId}).");
                setFlash('success', "User #{$targetUserId} ('{$username}') updated successfully.");
            } catch (Exception $e) {
                error_log("Failed to edit user: " . $e->getMessage());
                setFlash('danger', "Failed to update user: " . $e->getMessage());
            }
        }
    }
    header('Location: /admin/users.php');
    exit;
}

// 3. Handle Status Quick-Toggle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $newStatus = trim($_POST['new_status'] ?? '');

    if ($targetUserId === (int)$adminUser['id']) {
        setFlash('danger', 'You cannot suspend your own administrator account.');
    } elseif (in_array($newStatus, ['active', 'suspended', 'pending'], true)) {
        $stmt = $pdo->prepare("UPDATE users SET status = :st WHERE id = :id");
        $stmt->execute([':st' => $newStatus, ':id' => $targetUserId]);

        logActivity((int)$adminUser['id'], "Admin updated status of user #{$targetUserId} to {$newStatus}");
        setFlash('success', "User #{$targetUserId} status updated to {$newStatus}.");
    }
    header('Location: /admin/users.php');
    exit;
}

// Fetch users with sponsor details and matrix node counts
$search = trim($_GET['q'] ?? '');
$sql = "
    SELECT u.*, sp.username AS sponsor_name,
        (SELECT COUNT(*) FROM matrix_nodes WHERE user_id = u.id AND package_type = 'package_1') AS p1_nodes,
        (SELECT COUNT(*) FROM matrix_nodes WHERE user_id = u.id AND package_type = 'package_2') AS p2_nodes
    FROM users u
    LEFT JOIN users sp ON u.sponsor_id = sp.id
";

if (!empty($search)) {
    $sql .= " WHERE u.username LIKE :q OR u.email LIKE :q OR u.usdt_wallet LIKE :q";
}
$sql .= " ORDER BY u.id ASC";

$stmt = $pdo->prepare($sql);
if (!empty($search)) {
    $stmt->execute([':q' => "%{$search}%"]);
} else {
    $stmt->execute();
}
$usersList = $stmt->fetchAll();

$pageTitle = 'Member Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-users text-blue-400"></i> Member Management
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review, edit, or purge member records, inspect matrix trees, and manage account statuses.</p>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
            <a href="/admin/sql_import.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-purple-600/20 whitespace-nowrap">
                <i class="fa-solid fa-database"></i> SQL Import
            </a>
            <form method="GET" action="/admin/users.php" class="flex items-center gap-2 w-full sm:w-auto">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search user or wallet..."
                    class="px-3.5 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 w-full sm:w-64">
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition">
                    Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="/admin/users.php" class="px-3 py-2 bg-slate-800 text-slate-400 hover:text-white rounded-xl text-xs transition">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">UID</th>
                        <th class="py-3 px-3">Username</th>
                        <th class="py-3 px-3">Role</th>
                        <th class="py-3 px-3">Sponsor</th>
                        <th class="py-3 px-3">USDT Balance</th>
                        <th class="py-3 px-3">Matrix Nodes</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($usersList)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-500">No users found matching query.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-3 font-mono text-slate-400">#<?= (int)$u['id'] ?></td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-white"><?= e($u['username']) ?></div>
                                    <div class="text-[11px] text-slate-400"><?= e($u['email']) ?></div>
                                    <div class="text-[10px] font-mono text-slate-500 truncate max-w-[200px]" title="<?= e($u['usdt_wallet'] ?? '') ?>">
                                        <?= e($u['usdt_wallet'] ?: 'No wallet configured') ?>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $u['role'] === 'admin' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-slate-800 text-slate-300' ?>">
                                        <?= e($u['role']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-slate-300 font-medium">
                                    <?= e($u['sponsor_name'] ?? 'None (Root)') ?>
                                </td>
                                <td class="py-3 px-3 font-mono font-bold text-emerald-400">
                                    <?= formatUsdt(getUserUsdtBalance((int)$u['id'])) ?> USDT
                                </td>
                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-1.5 text-[11px]">
                                        <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-semibold" title="Package 1 Nodes">
                                            P1: <?= (int)$u['p1_nodes'] ?>
                                        </span>
                                        <span class="px-2 py-0.5 rounded bg-teal-500/10 text-teal-400 border border-teal-500/20 font-semibold" title="Package 2 Nodes">
                                            P2: <?= (int)$u['p2_nodes'] ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $stBadge = match ($u['status']) {
                                        'active'    => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        'suspended' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                        default     => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?= $stBadge ?>">
                                        <?= e($u['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Edit Button (Opens Modal) -->
                                        <button type="button"
                                            onclick='openEditModal(<?= json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                            class="px-2.5 py-1 bg-blue-500/20 hover:bg-blue-500/30 text-blue-300 border border-blue-500/30 rounded-lg text-[11px] font-semibold transition flex items-center gap-1">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>

                                        <?php if ((int)$u['id'] !== (int)$adminUser['id']): ?>
                                            <!-- Delete Button -->
                                            <form method="POST" action="/admin/users.php" class="inline" onsubmit="return confirm('⚠️ WARNING: Deleting user \'<?= e($u['username']) ?>\' will PERMANENTLY REMOVE all their transactions, matrix tree nodes, and activity logs.\n\nAre you sure you want to proceed?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                                                <button type="submit"
                                                    class="px-2.5 py-1 bg-rose-500/20 hover:bg-rose-500/40 text-rose-300 border border-rose-500/30 rounded-lg text-[11px] font-semibold transition flex items-center gap-1">
                                                    <i class="fa-solid fa-trash-can"></i> Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div id="editModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto custom-scroll">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 sticky top-0 bg-slate-900 z-10">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-user-pen text-blue-400"></i> Edit Member Details
            </h3>
            <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-white p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="/admin/users.php" class="mt-5 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="target_user_id" id="editUserId">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editUsername">Username</label>
                    <input type="text" id="editUsername" name="username" required
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editEmail">Email Address</label>
                    <input type="email" id="editEmail" name="email" required
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editPhone">Phone Number</label>
                    <input type="text" id="editPhone" name="phone"
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="+123456789">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editGender">Gender</label>
                    <select id="editGender" name="gender" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Unspecified --</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                        <option value="prefer_not_to_say">Prefer not to say</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editDob">Date of Birth</label>
                    <input type="date" id="editDob" name="dob"
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editCountry">Country</label>
                    <input type="text" id="editCountry" name="country"
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Address Fields -->
            <div class="border-t border-slate-800 pt-3">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editAddress">Street Address</label>
                <input type="text" id="editAddress" name="address"
                    class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editCity">City / Place</label>
                    <input type="text" id="editCity" name="city"
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editDistrict">District</label>
                    <input type="text" id="editDistrict" name="district"
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editState">State / Province</label>
                    <input type="text" id="editState" name="state_province"
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Wallet Details -->
            <div class="border-t border-slate-800 pt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editUsdtNetwork">USDT Network</label>
                    <select id="editUsdtNetwork" name="usdt_network" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="TRC20">TRC20</option>
                        <option value="BEP20">BEP20</option>
                        <option value="ERC20">ERC20</option>
                        <option value="POLYGON">POLYGON</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editUsdtWallet">USDT Payout Wallet</label>
                    <input type="text" id="editUsdtWallet" name="usdt_wallet"
                        class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t">
                </div>
            </div>

            <!-- QR Code preview if exists -->
            <div id="qrCodePreviewArea" class="hidden p-3 bg-slate-950 rounded-xl border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 block uppercase font-bold mb-1">Member Uploaded QR Code</span>
                <img id="qrCodePreviewImg" src="" alt="Member QR Code" class="w-28 h-28 mx-auto object-contain rounded-lg border border-slate-700 bg-white p-1">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-slate-800 pt-3">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editStatus">Status</label>
                    <select id="editStatus" name="status" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editRole">Role</label>
                    <select id="editRole" name="role" class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="member">Member</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="editNewPassword">
                    Reset Password <span class="text-slate-500 font-normal lowercase">(leave blank to keep current)</span>
                </label>
                <input type="password" id="editNewPassword" name="new_password" placeholder="New password"
                    class="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>

            <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-800">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl shadow-md transition">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editUsername').value = user.username;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editPhone').value = user.phone || '';
        document.getElementById('editGender').value = user.gender || '';
        document.getElementById('editDob').value = user.dob || '';
        document.getElementById('editCountry').value = user.country || '';
        document.getElementById('editAddress').value = user.address || '';
        document.getElementById('editCity').value = user.city || '';
        document.getElementById('editDistrict').value = user.district || '';
        document.getElementById('editState').value = user.state_province || '';
        document.getElementById('editUsdtWallet').value = user.usdt_wallet || '';
        document.getElementById('editUsdtNetwork').value = user.usdt_network || 'TRC20';
        document.getElementById('editStatus').value = user.status;
        document.getElementById('editRole').value = user.role;
        document.getElementById('editNewPassword').value = '';

        const qrArea = document.getElementById('qrCodePreviewArea');
        const qrImg = document.getElementById('qrCodePreviewImg');
        if (user.qr_code_path) {
            qrImg.src = '/' + user.qr_code_path;
            qrArea.classList.remove('hidden');
        } else {
            qrArea.classList.add('hidden');
        }

        document.getElementById('editModal').classList.remove('hidden');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
