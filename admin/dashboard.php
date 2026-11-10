<?php
/**
 * Admin Overview Dashboard
 * Platform Metrics, Total Users, Total USDT Volume, Global Matrix Health
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bfs_engine.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDatabaseConnection();

// Global Stats
$usersCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$membersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();

// Volume metrics
$totalDeposits = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE type = 'deposit' AND status = 'completed'")->fetchColumn();
$totalWithdrawals = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE type = 'withdrawal' AND status = 'completed'")->fetchColumn();
$totalMatrixPayouts = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE type = 'matrix_payout' AND status = 'completed'")->fetchColumn();
$totalRebirthFees = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE type = 'rebirth_fee' AND status = 'completed'")->fetchColumn();

$pendingDepositsCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'deposit' AND status = 'pending'")->fetchColumn();
$pendingWithdrawalsCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'withdrawal' AND status = 'pending'")->fetchColumn();

// Global Matrix Stats
$matrixStats = BfsMatrixEngine::getGlobalMatrixStats($pdo);

// Recent Transactions
$recentTx = $pdo->query("
    SELECT t.*, u.username
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.id DESC
    LIMIT 6
")->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-emerald-400"></i> Platform Control Center
            </h1>
            <p class="text-xs text-slate-400 mt-1">Global 3x BFS Matrix network statistics and USDT reserve control.</p>
        </div>

        <div class="flex items-center gap-3">
            <?php if ($pendingDepositsCount > 0 || $pendingWithdrawalsCount > 0): ?>
                <a href="/admin/transactions.php" class="px-4 py-2 bg-amber-500/20 text-amber-300 border border-amber-500/40 rounded-xl text-xs font-bold flex items-center gap-2 animate-pulse">
                    <i class="fa-solid fa-bell"></i>
                    <?= $pendingDepositsCount + $pendingWithdrawalsCount ?> Pending Actions
                </a>
            <?php endif; ?>
            <a href="/admin/users.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition">
                Manage Members
            </a>
            <a href="/admin/sql_import.php" class="px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white border border-purple-500 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-purple-600/20">
                <i class="fa-solid fa-database"></i>
                SQL Import
            </a>
        </div>
    </div>

    <!-- Financial KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total USDT Inflow</span>
            <div class="text-2xl font-black text-white mt-2">
                <?= formatUsdt($totalDeposits) ?> <span class="text-xs text-emerald-400 font-bold">USDT</span>
            </div>
            <p class="text-xs text-slate-500 mt-2">Verified completed deposits</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Matrix Payouts Outflow</span>
            <div class="text-2xl font-black text-teal-400 mt-2">
                <?= formatUsdt($totalMatrixPayouts) ?> <span class="text-xs text-slate-400 font-bold">USDT</span>
            </div>
            <p class="text-xs text-slate-500 mt-2">Distributed to qualified uplines</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Withdrawals</span>
            <div class="text-2xl font-black text-rose-400 mt-2">
                <?= formatUsdt($totalWithdrawals) ?> <span class="text-xs text-slate-400 font-bold">USDT</span>
            </div>
            <p class="text-xs text-slate-500 mt-2"><?= $pendingWithdrawalsCount ?> requests awaiting approval</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Network Members</span>
            <div class="text-2xl font-black text-indigo-400 mt-2">
                <?= $membersCount ?>
            </div>
            <p class="text-xs text-slate-500 mt-2">Active participating accounts</p>
        </div>
    </div>

    <!-- Matrix Health & BFS Tree Depth Status -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Package I Health -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <h2 class="text-lg font-bold text-white">Package I (6 Levels Pool)</h2>
                </div>
                <span class="text-xs font-mono font-bold text-emerald-400">50 USDT Entry</span>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-6">
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400">Total Nodes in Queue</span>
                    <div class="text-2xl font-extrabold text-white mt-1"><?= (int)$matrixStats['package_1']['total_nodes'] ?></div>
                </div>
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400">Current Depth Reached</span>
                    <div class="text-2xl font-extrabold text-emerald-400 mt-1">Level <?= (int)$matrixStats['package_1']['max_level_reached'] ?> / 6</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400">Rebirth Booster Slots</span>
                    <div class="text-2xl font-extrabold text-purple-400 mt-1"><?= (int)$matrixStats['package_1']['rebirth_count'] ?></div>
                </div>
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400">Active Earning Nodes</span>
                    <div class="text-2xl font-extrabold text-teal-400 mt-1"><?= (int)$matrixStats['package_1']['active_nodes'] ?></div>
                </div>
            </div>
        </div>

        <!-- Package II Health -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-teal-500"></span>
                    <h2 class="text-lg font-bold text-white">Package II (7 Levels Pool)</h2>
                </div>
                <span class="text-xs font-mono font-bold text-teal-400">300 USDT Entry</span>
            </div>

            <div class="grid grid-cols-2 gap-4 mt-6">
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400">Total Nodes in Queue</span>
                    <div class="text-2xl font-extrabold text-white mt-1"><?= (int)$matrixStats['package_2']['total_nodes'] ?></div>
                </div>
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400">Current Depth Reached</span>
                    <div class="text-2xl font-extrabold text-teal-400 mt-1">Level <?= (int)$matrixStats['package_2']['max_level_reached'] ?> / 7</div>
                </div>
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400">Rebirth Booster Slots</span>
                    <div class="text-2xl font-extrabold text-purple-400 mt-1"><?= (int)$matrixStats['package_2']['rebirth_count'] ?></div>
                </div>
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400">Active Earning Nodes</span>
                    <div class="text-2xl font-extrabold text-teal-400 mt-1"><?= (int)$matrixStats['package_2']['active_nodes'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent System Transactions -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-slate-400"></i> Latest Platform Operations
            </h2>
            <a href="/admin/transactions.php" class="text-xs text-emerald-400 hover:text-emerald-300 font-semibold">
                View All &rarr;
            </a>
        </div>

        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">ID</th>
                        <th class="py-3 px-3">User</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3">Amount</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Details</th>
                        <th class="py-3 px-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($recentTx as $tx): ?>
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="py-3 px-3 font-mono text-slate-400">#<?= (int)$tx['id'] ?></td>
                            <td class="py-3 px-3 font-semibold text-white"><?= e($tx['username']) ?></td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-800 text-slate-300 border border-slate-700">
                                    <?= str_replace('_', ' ', $tx['type']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-emerald-400">
                                <?= formatUsdt($tx['amount_usdt']) ?> USDT
                            </td>
                            <td class="py-3 px-3 capitalize font-semibold <?= $tx['status'] === 'completed' ? 'text-emerald-400' : ($tx['status'] === 'pending' ? 'text-amber-400' : 'text-rose-400') ?>">
                                <?= e($tx['status']) ?>
                            </td>
                            <td class="py-3 px-3 text-slate-400 truncate max-w-xs"><?= e($tx['details'] ?? '-') ?></td>
                            <td class="py-3 px-3 text-slate-400 whitespace-nowrap"><?= date('M d H:i', strtotime($tx['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
