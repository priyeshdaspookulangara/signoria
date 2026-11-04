<?php
/**
 * Admin Audit Logs
 * Comprehensive view of all system, member, and placement activities
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDatabaseConnection();

$limit = 100;
$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.role
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.id DESC
    LIMIT :lim
");
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="pb-6 border-b border-slate-800">
        <h1 class="text-2xl font-black text-white flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-purple-400"></i> System Audit Logs
        </h1>
        <p class="text-xs text-slate-400 mt-1">Immutable event trails for user actions, matrix node placements, and admin activities.</p>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">Log ID</th>
                        <th class="py-3 px-3">User</th>
                        <th class="py-3 px-3">Action Description</th>
                        <th class="py-3 px-3">IP Address</th>
                        <th class="py-3 px-3">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-8 text-slate-500 font-sans">No audit events recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-3 text-slate-500">#<?= (int)$log['id'] ?></td>
                                <td class="py-3 px-3 font-sans">
                                    <?php if ($log['username']): ?>
                                        <span class="font-bold text-white"><?= e($log['username']) ?></span>
                                        <span class="text-[10px] text-slate-400 block"><?= e($log['role']) ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-500">System/Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 font-sans text-slate-300">
                                    <?= e($log['action']) ?>
                                </td>
                                <td class="py-3 px-3 text-slate-400 text-[11px]">
                                    <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                                </td>
                                <td class="py-3 px-3 text-slate-400 whitespace-nowrap text-[11px]">
                                    <?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?>
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
