<?php
/**
 * Admin User Management (CRUD & Status Toggle & BFS Tree Position Check)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bfs_engine.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDatabaseConnection();
$adminUser = getCurrentUser();

// Handle Status Toggle (active, suspended)
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

// Fetch users with sponsor details and matrix node count
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-users text-blue-400"></i> Member Management
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review user accounts, inspect matrix tree placements, and update account permissions.</p>
        </div>

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
                                    <?php if ($u['id'] !== $adminUser['id']): ?>
                                        <form method="POST" action="/admin/users.php" class="inline-block">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="target_user_id" value="<?= (int)$u['id'] ?>">
                                            <?php if ($u['status'] === 'active'): ?>
                                                <input type="hidden" name="new_status" value="suspended">
                                                <button type="submit" onclick="return confirm('Suspend user <?= e($u['username']) ?>?');"
                                                    class="px-2.5 py-1 bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 border border-rose-500/30 rounded-lg text-[11px] font-semibold transition">
                                                    Suspend
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit"
                                                    class="px-2.5 py-1 bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 border border-emerald-500/30 rounded-lg text-[11px] font-semibold transition">
                                                    Activate
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-500">Current Admin</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
