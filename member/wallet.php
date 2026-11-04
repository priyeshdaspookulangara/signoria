<?php
/**
 * Member Wallet Management
 * USDT Deposit Request, Withdrawal Requests, and Full Financial Ledger History
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$user = getCurrentUser();
$userId = (int)$user['id'];
$pdo = getDatabaseConnection();

$error = null;
$companyDepositWallet = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'; // Platform master USDT TRC20 address

// 1. Handle Deposit Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deposit') {
    $amount = (float)parseUsdt($_POST['amount'] ?? 0);
    $txHash = trim($_POST['tx_hash'] ?? '');

    if ($amount < 10) {
        $error = 'Minimum deposit amount is 10.00 USDT.';
    } elseif (empty($txHash) || strlen($txHash) < 10) {
        $error = 'Please provide a valid blockchain transaction hash (TXID).';
    } else {
        // Check if TX hash already used
        $chkStmt = $pdo->prepare("SELECT id FROM transactions WHERE tx_hash = :hash LIMIT 1");
        $chkStmt->execute([':hash' => $txHash]);
        if ($chkStmt->fetch()) {
            $error = 'This transaction hash has already been submitted.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                VALUES (:uid, 'deposit', :amt, :hash, 'pending', 'Member submitted USDT deposit proof', NOW())
            ");
            $stmt->execute([
                ':uid'  => $userId,
                ':amt'  => $amount,
                ':hash' => $txHash,
            ]);

            logActivity($userId, "Submitted deposit request of {$amount} USDT (TXID: {$txHash})");
            setFlash('success', 'Deposit request submitted successfully! Admin will verify and credit your balance.');
            header('Location: /member/wallet.php');
            exit;
        }
    }
}

// 2. Handle Withdrawal Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    $amount = (float)parseUsdt($_POST['amount'] ?? 0);
    $currentBalance = getUserUsdtBalance($userId);

    if (empty($user['usdt_wallet'])) {
        $error = 'Please set your USDT payout wallet address in your profile before requesting withdrawals.';
    } elseif ($amount < 10) {
        $error = 'Minimum withdrawal amount is 10.00 USDT.';
    } elseif ($amount > $currentBalance) {
        $error = 'Insufficient funds. Requested: ' . formatUsdt($amount) . ' USDT, Available: ' . formatUsdt($currentBalance) . ' USDT.';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
            VALUES (:uid, 'withdrawal', :amt, NULL, 'pending', :details, NOW())
        ");
        $stmt->execute([
            ':uid'     => $userId,
            ':amt'     => $amount,
            ':details' => 'Withdrawal to payout wallet: ' . $user['usdt_wallet'],
        ]);

        logActivity($userId, "Requested withdrawal of {$amount} USDT to {$user['usdt_wallet']}");
        setFlash('success', 'Withdrawal request submitted! Funds will be transferred once approved by administrator.');
        header('Location: /member/wallet.php');
        exit;
    }
}

// Fetch user's transaction history
$txStmt = $pdo->prepare("
    SELECT * FROM transactions
    WHERE user_id = :uid
    ORDER BY id DESC
    LIMIT 50
");
$txStmt->execute([':uid' => $userId]);
$transactions = $txStmt->fetchAll();

$balance = getUserUsdtBalance($userId);

$pageTitle = 'USDT Wallet';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Wallet Header Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-emerald-950/40 to-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2.5">
                <i class="fa-solid fa-wallet text-emerald-400"></i> USDT Tether Wallet
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Manage your deposits, fast matrix withdrawals, and complete ledger records.
            </p>
        </div>
        <div class="text-right">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Net Withdrawable Balance</span>
            <div class="text-3xl font-extrabold text-emerald-400 mt-0.5">
                <?= formatUsdt($balance) ?> <span class="text-sm text-slate-400 font-bold">USDT</span>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-400"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Two-Column Actions: Deposit & Withdraw -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Deposit Box -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-circle-down text-emerald-400"></i> Deposit USDT
                    </h2>
                    <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 font-semibold">TRC20 / BEP20</span>
                </div>

                <div class="mt-4 p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                    <span class="text-xs text-slate-400 block font-medium">Send USDT to Platform Vault Address:</span>
                    <div class="flex items-center space-x-2">
                        <input type="text" id="depositAddr" readonly value="<?= e($companyDepositWallet) ?>"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-xs font-mono text-emerald-300">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('depositAddr').value); alert('Vault address copied!');"
                            class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs font-bold transition">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-500">Only send TRC20 or BEP20 Tether tokens. Minimum deposit: 10 USDT.</p>
                </div>

                <!-- Form to Submit TX Hash -->
                <form class="mt-6 space-y-4" method="POST" action="/member/wallet.php">
                    <input type="hidden" name="action" value="deposit">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="deposit_amount">
                            Amount Sent (USDT)
                        </label>
                        <input type="number" step="0.01" min="10" name="amount" id="deposit_amount" required
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            placeholder="e.g. 100.00">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="tx_hash">
                            Transaction Hash (TXID)
                        </label>
                        <input type="text" name="tx_hash" id="tx_hash" required
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white font-mono text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            placeholder="e.g. 0x8f23ba56c321a9e8754bde29871029cba82716354dfa...">
                    </div>

                    <button type="submit" class="w-full py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-md transition">
                        Submit Deposit Proof
                    </button>
                </form>
            </div>
        </div>

        <!-- Withdraw Box -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-circle-up text-rose-400"></i> Request Withdrawal
                    </h2>
                    <span class="text-xs text-slate-400">Available: <strong class="text-emerald-400"><?= formatUsdt($balance) ?> USDT</strong></span>
                </div>

                <div class="mt-4 p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <span class="text-xs text-slate-400 block font-medium">Destination USDT Address:</span>
                    <div class="mt-1 font-mono text-xs text-slate-200 truncate">
                        <?= e($user['usdt_wallet'] ?: 'No address specified yet. Please configure in Profile.') ?>
                    </div>
                    <?php if (empty($user['usdt_wallet'])): ?>
                        <a href="/member/profile.php" class="text-xs text-emerald-400 underline font-semibold mt-2 inline-block">Set Address Now</a>
                    <?php endif; ?>
                </div>

                <form class="mt-6 space-y-4" method="POST" action="/member/wallet.php">
                    <input type="hidden" name="action" value="withdraw">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5" for="withdraw_amount">
                            Withdrawal Amount (USDT)
                        </label>
                        <input type="number" step="0.01" min="10" max="<?= $balance ?>" name="amount" id="withdraw_amount" required
                            class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-rose-500"
                            placeholder="Min 10.00 USDT">
                    </div>

                    <p class="text-[11px] text-slate-400">Withdrawal requests are processed promptly after network verification.</p>

                    <button type="submit" <?= empty($user['usdt_wallet']) || $balance < 10 ? 'disabled' : '' ?>
                        class="w-full py-3 rounded-xl bg-rose-600 hover:bg-rose-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold text-sm shadow-md transition">
                        Request Payout
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Financial Ledger History Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list-check text-slate-400"></i> Transaction History
        </h2>

        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">ID</th>
                        <th class="py-3 px-3">Type</th>
                        <th class="py-3 px-3">Amount (USDT)</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3">TX Hash / Details</th>
                        <th class="py-3 px-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-500">No transaction records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-3 font-mono text-slate-400">#<?= (int)$tx['id'] ?></td>
                                <td class="py-3 px-3 font-semibold">
                                    <?php
                                    $badge = match ($tx['type']) {
                                        'deposit' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                        'matrix_payout' => 'bg-teal-500/20 text-teal-300 border-teal-500/30',
                                        'withdrawal' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                        'rebirth_fee' => 'bg-purple-500/20 text-purple-300 border-purple-500/30',
                                        'package_upgrade' => 'bg-amber-500/20 text-amber-300 border-amber-500/30',
                                        default => 'bg-slate-800 text-slate-300 border-slate-700',
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded border text-[10px] uppercase font-bold <?= $badge ?>">
                                        <?= str_replace('_', ' ', $tx['type']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-mono font-bold <?= in_array($tx['type'], ['deposit', 'matrix_payout']) ? 'text-emerald-400' : 'text-slate-300' ?>">
                                    <?= in_array($tx['type'], ['deposit', 'matrix_payout']) ? '+' : '-' ?><?= formatUsdt($tx['amount_usdt']) ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $statusBadge = match ($tx['status']) {
                                        'completed' => 'text-emerald-400',
                                        'pending'   => 'text-amber-400 animate-pulse',
                                        'failed'    => 'text-rose-400',
                                        default     => 'text-slate-400',
                                    };
                                    ?>
                                    <span class="font-semibold capitalize <?= $statusBadge ?>"><?= e($tx['status']) ?></span>
                                </td>
                                <td class="py-3 px-3 text-slate-400 max-w-xs truncate">
                                    <?php if ($tx['tx_hash']): ?>
                                        <span class="font-mono text-[11px] text-slate-300" title="<?= e($tx['tx_hash']) ?>">
                                            <?= substr($tx['tx_hash'], 0, 16) ?>...
                                        </span>
                                    <?php endif; ?>
                                    <span class="block text-[11px] text-slate-500"><?= e($tx['details'] ?? '') ?></span>
                                </td>
                                <td class="py-3 px-3 text-slate-400 whitespace-nowrap">
                                    <?= date('M d, Y H:i', strtotime($tx['created_at'])) ?>
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
