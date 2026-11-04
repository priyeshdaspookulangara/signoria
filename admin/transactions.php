<?php
/**
 * Admin Transactions Panel
 * Review, verify, and approve/reject USDT deposits and member withdrawal requests.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bfs_engine.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDatabaseConnection();
$adminUser = getCurrentUser();

$error = null;
$success = null;

// Handle Transaction Approvals / Rejections
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $txId = (int)($_POST['tx_id'] ?? 0);
    $action = $_POST['action'];

    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $txId]);
    $tx = $stmt->fetch();

    if (!$tx) {
        setFlash('danger', 'Transaction record not found.');
    } elseif ($tx['status'] !== 'pending') {
        setFlash('warning', "Transaction #{$txId} has already been marked as {$tx['status']}.");
    } else {
        if ($action === 'approve_deposit') {
            // Mark deposit completed
            $pdo->beginTransaction();
            try {
                $upStmt = $pdo->prepare("
                    UPDATE transactions
                    SET status = 'completed', details = CONCAT(COALESCE(details, ''), ' [Approved by Admin]')
                    WHERE id = :id
                ");
                $upStmt->execute([':id' => $txId]);

                logActivity((int)$adminUser['id'], "Admin approved deposit #{$txId} of {$tx['amount_usdt']} USDT for user #{$tx['user_id']}");
                $pdo->commit();
                setFlash('success', "Deposit #{$txId} approved! USDT balance has been credited to user #{$tx['user_id']}.");
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', 'Failed to approve deposit: ' . $e->getMessage());
            }

        } elseif ($action === 'reject_deposit') {
            $upStmt = $pdo->prepare("
                UPDATE transactions
                SET status = 'failed', details = CONCAT(COALESCE(details, ''), ' [Rejected by Admin]')
                WHERE id = :id
            ");
            $upStmt->execute([':id' => $txId]);
            logActivity((int)$adminUser['id'], "Admin rejected deposit #{$txId}");
            setFlash('info', "Deposit #{$txId} has been marked as rejected.");

        } elseif ($action === 'approve_withdrawal') {
            $payoutTxHash = trim($_POST['payout_tx_hash'] ?? '');
            if (empty($payoutTxHash)) {
                setFlash('danger', 'Please provide the blockchain payout TXID to confirm withdrawal settlement.');
            } else {
                $pdo->beginTransaction();
                try {
                    $upStmt = $pdo->prepare("
                        UPDATE transactions
                        SET status = 'completed', tx_hash = :hash, details = CONCAT(COALESCE(details, ''), ' [Settled by Admin]')
                        WHERE id = :id
                    ");
                    $upStmt->execute([
                        ':hash' => $payoutTxHash,
                        ':id'   => $txId,
                    ]);

                    logActivity((int)$adminUser['id'], "Admin approved withdrawal #{$txId} of {$tx['amount_usdt']} USDT (TXID: {$payoutTxHash})");
                    $pdo->commit();
                    setFlash('success', "Withdrawal #{$txId} marked as completed with TXID {$payoutTxHash}.");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    setFlash('danger', 'Failed to process withdrawal: ' . $e->getMessage());
                }
            }

        } elseif ($action === 'reject_withdrawal') {
            // Marking withdrawal failed will automatically restore user's available balance!
            $upStmt = $pdo->prepare("
                UPDATE transactions
                SET status = 'failed', details = CONCAT(COALESCE(details, ''), ' [Rejected by Admin]')
                WHERE id = :id
            ");
            $upStmt->execute([':id' => $txId]);
            logActivity((int)$adminUser['id'], "Admin rejected withdrawal #{$txId}. Funds restored to user balance.");
            setFlash('info', "Withdrawal #{$txId} rejected. Funds restored to user balance.");
        }
    }

    header('Location: /admin/transactions.php');
    exit;
}

// Filter transactions by type or status
$filterType = trim($_GET['type'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');

$sql = "
    SELECT t.*, u.username, u.email, u.usdt_wallet AS user_payout_wallet
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE 1=1
";
$params = [];

if (!empty($filterType)) {
    $sql .= " AND t.type = :type";
    $params[':type'] = $filterType;
}

if (!empty($filterStatus)) {
    $sql .= " AND t.status = :status";
    $params[':status'] = $filterStatus;
}

$sql .= " ORDER BY (t.status = 'pending') DESC, t.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$pageTitle = 'Transactions Control';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-money-bill-transfer text-amber-400"></i> USDT Financial Transactions
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review incoming deposits proof and settle member withdrawal requests.</p>
        </div>

        <!-- Filters Form -->
        <form method="GET" action="/admin/transactions.php" class="flex flex-wrap items-center gap-2 text-xs">
            <select name="type" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none">
                <option value="">All Types</option>
                <option value="deposit" <?= $filterType === 'deposit' ? 'selected' : '' ?>>Deposits</option>
                <option value="withdrawal" <?= $filterType === 'withdrawal' ? 'selected' : '' ?>>Withdrawals</option>
                <option value="matrix_payout" <?= $filterType === 'matrix_payout' ? 'selected' : '' ?>>Matrix Payouts</option>
                <option value="rebirth_fee" <?= $filterType === 'rebirth_fee' ? 'selected' : '' ?>>Rebirth Fees</option>
                <option value="package_upgrade" <?= $filterType === 'package_upgrade' ? 'selected' : '' ?>>Package Upgrades</option>
            </select>

            <select name="status" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none">
                <option value="">All Statuses</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl transition">
                Filter
            </button>
            <?php if (!empty($filterType) || !empty($filterStatus)): ?>
                <a href="/admin/transactions.php" class="px-3 py-2 bg-slate-800 text-slate-400 hover:text-white rounded-xl transition">
                    Reset
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Transactions List Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">TX ID</th>
                        <th class="py-3 px-3">User</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3">Amount</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">Proof / Payout Hash / Details</th>
                        <th class="py-3 px-3">Date</th>
                        <th class="py-3 px-3 text-right">Review Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-500">No transactions found matching criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr class="hover:bg-slate-800/40 transition <?= $tx['status'] === 'pending' ? 'bg-amber-950/10' : '' ?>">
                                <td class="py-3 px-3 font-mono text-slate-400">#<?= (int)$tx['id'] ?></td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-white"><?= e($tx['username']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono truncate max-w-[160px]" title="<?= e($tx['user_payout_wallet'] ?? '') ?>">
                                        <?= e($tx['user_payout_wallet'] ?: 'No wallet') ?>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $badge = match ($tx['type']) {
                                        'deposit' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                        'withdrawal' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                        'matrix_payout' => 'bg-teal-500/20 text-teal-300 border-teal-500/30',
                                        'rebirth_fee' => 'bg-purple-500/20 text-purple-300 border-purple-500/30',
                                        'package_upgrade' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                        default => 'bg-slate-800 text-slate-300',
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded border text-[10px] font-bold uppercase <?= $badge ?>">
                                        <?= str_replace('_', ' ', $tx['type']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-mono font-bold <?= in_array($tx['type'], ['deposit', 'matrix_payout']) ? 'text-emerald-400' : 'text-slate-200' ?>">
                                    <?= in_array($tx['type'], ['deposit', 'matrix_payout']) ? '+' : '-' ?><?= formatUsdt($tx['amount_usdt']) ?> USDT
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $stBadge = match ($tx['status']) {
                                        'completed' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                                        'pending'   => 'bg-amber-500/10 text-amber-300 border-amber-500/20 animate-pulse font-bold',
                                        default     => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded border text-[10px] uppercase <?= $stBadge ?>">
                                        <?= e($tx['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-slate-400 max-w-xs truncate">
                                    <?php if ($tx['tx_hash']): ?>
                                        <div class="font-mono text-[11px] text-emerald-300" title="<?= e($tx['tx_hash']) ?>">
                                            TXID: <?= substr($tx['tx_hash'], 0, 16) ?>...
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-[11px] text-slate-400"><?= e($tx['details'] ?? '-') ?></div>
                                </td>
                                <td class="py-3 px-3 text-slate-400 whitespace-nowrap">
                                    <?= date('M d, Y H:i', strtotime($tx['created_at'])) ?>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <?php if ($tx['status'] === 'pending'): ?>
                                        <?php if ($tx['type'] === 'deposit'): ?>
                                            <div class="flex items-center justify-end gap-1.5">
                                                <form method="POST" action="/admin/transactions.php" class="inline">
                                                    <input type="hidden" name="tx_id" value="<?= (int)$tx['id'] ?>">
                                                    <input type="hidden" name="action" value="approve_deposit">
                                                    <button type="submit" onclick="return confirm('Approve deposit of <?= formatUsdt($tx['amount_usdt']) ?> USDT?');"
                                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-[11px] font-bold shadow transition">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="/admin/transactions.php" class="inline">
                                                    <input type="hidden" name="tx_id" value="<?= (int)$tx['id'] ?>">
                                                    <input type="hidden" name="action" value="reject_deposit">
                                                    <button type="submit" onclick="return confirm('Reject this deposit?');"
                                                        class="px-2.5 py-1 bg-rose-600/30 hover:bg-rose-600 text-rose-200 rounded-lg text-[11px] font-bold transition">
                                                        Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($tx['type'] === 'withdrawal'): ?>
                                            <form method="POST" action="/admin/transactions.php" class="flex flex-col items-end gap-1">
                                                <input type="hidden" name="tx_id" value="<?= (int)$tx['id'] ?>">
                                                <input type="hidden" name="action" value="approve_withdrawal">
                                                <div class="flex items-center gap-1">
                                                    <input type="text" name="payout_tx_hash" required placeholder="Paste Payout TXID"
                                                        class="px-2 py-1 bg-slate-950 border border-slate-700 rounded text-[11px] text-white font-mono w-32 focus:outline-none">
                                                    <button type="submit" onclick="return confirm('Confirm payout of <?= formatUsdt($tx['amount_usdt']) ?> USDT?');"
                                                        class="px-2.5 py-1 bg-teal-600 hover:bg-teal-500 text-white rounded-lg text-[11px] font-bold shadow transition">
                                                        Settle
                                                    </button>
                                                </div>
                                                <button type="submit" formaction="/admin/transactions.php" name="action" value="reject_withdrawal" formnovalidate
                                                    onclick="return confirm('Reject and refund withdrawal of <?= formatUsdt($tx['amount_usdt']) ?> USDT?');"
                                                    class="text-[10px] text-rose-400 hover:underline">
                                                    Reject & Refund
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-500 font-mono">Settled</span>
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
