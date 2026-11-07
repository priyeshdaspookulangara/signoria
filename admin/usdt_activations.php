<?php
/**
 * Admin USDT Activation & Payment Verification Engine
 * Approvals trigger atomic status update, package activation,
 * 3x BFS Matrix Placement, referral bonus distribution, and financial ledger logging.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bfs_engine.php';

requireAdmin();

$admin = getCurrentUser();
$adminId = (int)$admin['id'];
$pdo = getDatabaseConnection();

$error = null;
$success = null;

// Handle Review Actions (Approve / Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlash('danger', 'Security session expired (CSRF). Please try again.');
        header('Location: /admin/usdt_activations.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $paymentId = (int)($_POST['payment_id'] ?? 0);
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    // Fetch the payment record
    $pStmt = $pdo->prepare("
        SELECT p.*, u.username, u.email, u.sponsor_id, u.status AS user_status,
               k.name AS package_name, k.matrix_type, k.inr_price, k.usdt_price
        FROM usdt_payments p
        JOIN users u ON p.user_id = u.id
        JOIN packages k ON p.package_id = k.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $pStmt->execute([$paymentId]);
    $payment = $pStmt->fetch();

    if (!$payment) {
        setFlash('danger', 'USDT activation request not found.');
        header('Location: /admin/usdt_activations.php');
        exit;
    }

    if ($payment['status'] !== 'pending') {
        setFlash('warning', "This activation request has already been processed as '{$payment['status']}'.");
        header('Location: /admin/usdt_activations.php');
        exit;
    }

    if ($action === 'approve') {
        $pdo->beginTransaction();
        try {
            // 1. Mark payment as approved
            $upPmt = $pdo->prepare("
                UPDATE usdt_payments
                SET status = 'approved', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ");
            $upPmt->execute([$adminNotes ?: 'Approved by administrator', $adminId, $paymentId]);

            // 2. Activate user account if pending
            $upUser = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $upUser->execute([(int)$payment['user_id']]);

            // 3. Mark corresponding deposit transaction as completed
            $upTx = $pdo->prepare("
                UPDATE transactions
                SET status = 'completed', details = ?
                WHERE user_id = ? AND (tx_hash = ? OR details LIKE ?) AND status = 'pending'
                LIMIT 1
            ");
            $upTx->execute([
                "Approved USDT Activation #{$paymentId} ({$payment['package_name']})",
                (int)$payment['user_id'],
                $payment['transaction_hash'],
                "%#{$paymentId}%"
            ]);

            // 4. Place node in 3x BFS Matrix
            $matrixType = $payment['matrix_type'];
            $nodePlaced = BfsMatrixEngine::placeNode($pdo, (int)$payment['user_id'], $matrixType);

            $pdo->commit();

            logActivity($adminId, "Approved USDT Activation #{$paymentId} for user @{$payment['username']} ({$payment['usdt_amount']} USDT, {$payment['network']})");
            logActivity((int)$payment['user_id'], "Package {$payment['package_name']} activated via USDT Activation #{$paymentId}; Placed in BFS {$matrixType} at Pos #{$nodePlaced['position_index']}");

            setFlash('success', "USDT activation #{$paymentId} approved! User @{$payment['username']} activated and placed into {$matrixType} at Position #{$nodePlaced['position_index']} (Level {$nodePlaced['level']}).");
            header('Location: /admin/usdt_activations.php');
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log("Failed to approve USDT activation: " . $e->getMessage());
            setFlash('danger', 'Error approving activation: ' . $e->getMessage());
            header('Location: /admin/usdt_activations.php');
            exit;
        }

    } elseif ($action === 'reject') {
        $pdo->beginTransaction();
        try {
            // 1. Mark payment as rejected
            $upPmt = $pdo->prepare("
                UPDATE usdt_payments
                SET status = 'rejected', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?
            ");
            $upPmt->execute([$adminNotes ?: 'Rejected by administrator', $adminId, $paymentId]);

            // 2. Mark ledger transaction as failed
            $upTx = $pdo->prepare("
                UPDATE transactions
                SET status = 'failed', details = ?
                WHERE user_id = ? AND (tx_hash = ? OR details LIKE ?) AND status = 'pending'
                LIMIT 1
            ");
            $upTx->execute([
                "Rejected USDT Activation #{$paymentId}: " . ($adminNotes ?: 'Declined'),
                (int)$payment['user_id'],
                $payment['transaction_hash'],
                "%#{$paymentId}%"
            ]);

            $pdo->commit();

            logActivity($adminId, "Rejected USDT Activation #{$paymentId} for user @{$payment['username']}. Notes: {$adminNotes}");
            setFlash('warning', "USDT activation #{$paymentId} for user @{$payment['username']} was rejected.");
            header('Location: /admin/usdt_activations.php');
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            setFlash('danger', 'Error rejecting activation: ' . $e->getMessage());
            header('Location: /admin/usdt_activations.php');
            exit;
        }
    }
}

// Filtering & Search
$statusFilter = trim($_GET['status'] ?? 'pending');
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'pending';
}

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT p.*, u.username, u.email, u.status AS user_status,
           u.usdt_trc20_address, u.usdt_bep20_address,
           k.name AS package_name, k.matrix_type, k.inr_price,
           a.username AS reviewer_username
    FROM usdt_payments p
    JOIN users u ON p.user_id = u.id
    JOIN packages k ON p.package_id = k.id
    LEFT JOIN users a ON p.reviewed_by = a.id
    WHERE 1=1
";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND p.status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($search)) {
    $sql .= " AND (u.username LIKE :q OR u.email LIKE :q OR p.transaction_hash LIKE :q)";
    $params[':q'] = "%{$search}%";
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Statistics
$stats = [
    'pending'  => (int)$pdo->query("SELECT COUNT(*) FROM usdt_payments WHERE status = 'pending'")->fetchColumn(),
    'approved' => (int)$pdo->query("SELECT COUNT(*) FROM usdt_payments WHERE status = 'approved'")->fetchColumn(),
    'rejected' => (int)$pdo->query("SELECT COUNT(*) FROM usdt_payments WHERE status = 'rejected'")->fetchColumn(),
    'total_usdt_approved' => (float)$pdo->query("SELECT COALESCE(SUM(usdt_amount), 0) FROM usdt_payments WHERE status = 'approved'")->fetchColumn(),
];

$pageTitle = 'USDT Activation Requests';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fa-solid fa-bolt text-yellow-400"></i> USDT Package Activation Requests
            </h1>
            <p class="text-xs text-slate-400 mt-1">Review blockchain transaction codes (TXID), verify receipt proofs, and trigger BFS matrix placement.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/admin/wallet_settings.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl transition flex items-center gap-2">
                <i class="fa-solid fa-gear"></i> Gateway Settings
            </a>
            <a href="/admin/transactions.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl transition flex items-center gap-2">
                <i class="fa-solid fa-list-check"></i> Standard Ledger
            </a>
        </div>
    </div>

    <!-- Metric Counters -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <a href="/admin/usdt_activations.php?status=pending"
           class="p-4 rounded-2xl border transition <?= $statusFilter === 'pending' ? 'bg-amber-500/10 border-amber-500/50 shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-slate-700' ?>">
            <div class="text-[10px] font-bold uppercase tracking-wider text-amber-400">Pending Review</div>
            <div class="text-2xl font-black text-white mt-1"><?= number_format($stats['pending']) ?></div>
        </a>

        <a href="/admin/usdt_activations.php?status=approved"
           class="p-4 rounded-2xl border transition <?= $statusFilter === 'approved' ? 'bg-emerald-500/10 border-emerald-500/50 shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-slate-700' ?>">
            <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-400">Approved Activations</div>
            <div class="text-2xl font-black text-white mt-1"><?= number_format($stats['approved']) ?></div>
        </a>

        <a href="/admin/usdt_activations.php?status=rejected"
           class="p-4 rounded-2xl border transition <?= $statusFilter === 'rejected' ? 'bg-rose-500/10 border-rose-500/50 shadow-lg' : 'bg-slate-900 border-slate-800 hover:border-slate-700' ?>">
            <div class="text-[10px] font-bold uppercase tracking-wider text-rose-400">Rejected Requests</div>
            <div class="text-2xl font-black text-white mt-1"><?= number_format($stats['rejected']) ?></div>
        </a>

        <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800">
            <div class="text-[10px] font-bold uppercase tracking-wider text-teal-400">Approved Volume</div>
            <div class="text-2xl font-black font-mono text-emerald-400 mt-1"><?= formatUsdt($stats['total_usdt_approved']) ?> USDT</div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-4">
        <!-- Status Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto">
            <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All Requests'] as $stKey => $stLabel): ?>
                <a href="/admin/usdt_activations.php?status=<?= $stKey ?><?= !empty($search) ? '&q=' . urlencode($search) : '' ?>"
                    class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap <?= $statusFilter === $stKey ? 'bg-purple-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                    <?= $stLabel ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Search Form -->
        <form method="GET" action="/admin/usdt_activations.php" class="w-full md:w-80 flex items-center gap-2">
            <input type="hidden" name="status" value="<?= e($statusFilter) ?>">
            <div class="relative w-full">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search user or TXID..."
                    class="w-full pl-9 pr-3 py-2 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </div>
            </div>
            <button type="submit" class="px-3 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition">
                Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="/admin/usdt_activations.php?status=<?= e($statusFilter) ?>" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs transition" title="Clear Search">
                    <i class="fa-solid fa-xmark"></i>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Requests Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl overflow-hidden">
        <div class="overflow-x-auto custom-scroll">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3">ID</th>
                        <th class="py-3 px-3">Member</th>
                        <th class="py-3 px-3">Package Tier</th>
                        <th class="py-3 px-3">Network</th>
                        <th class="py-3 px-3">Payable USDT</th>
                        <th class="py-3 px-3">INR Value</th>
                        <th class="py-3 px-3">Transaction Code (TXID)</th>
                        <th class="py-3 px-3">Receipt</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-10 text-slate-500">
                                <i class="fa-solid fa-inbox text-3xl mb-2 block text-slate-600"></i>
                                No activation requests found matching the current filter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3 px-3 font-mono text-slate-400">#<?= (int)$r['id'] ?></td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-white"><?= e($r['username']) ?></div>
                                    <div class="text-[10px] text-slate-400"><?= e($r['email']) ?></div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-slate-200"><?= e($r['package_name']) ?></div>
                                    <span class="text-[10px] font-mono uppercase text-slate-400"><?= e($r['matrix_type']) ?></span>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold <?= $r['network'] === 'BEP20' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' ?>">
                                        <?= e($r['network']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 font-mono font-extrabold text-emerald-400">
                                    <?= formatUsdt($r['usdt_amount']) ?> USDT
                                </td>
                                <td class="py-3 px-3 font-mono text-slate-300">
                                    ₹<?= number_format((float)$r['inr_amount'], 2) ?>
                                </td>
                                <td class="py-3 px-3 font-mono text-[11px] max-w-xs">
                                    <?php
                                    $explorer = $r['network'] === 'BEP20'
                                        ? "https://bscscan.com/tx/" . urlencode($r['transaction_hash'])
                                        : "https://tronscan.org/#/transaction/" . urlencode($r['transaction_hash']);
                                    ?>
                                    <a href="<?= e($explorer) ?>" target="_blank" rel="noopener noreferrer"
                                        class="text-indigo-400 hover:text-indigo-300 underline flex items-center gap-1.5" title="<?= e($r['transaction_hash']) ?>">
                                        <?= substr($r['transaction_hash'], 0, 10) ?>...<?= substr($r['transaction_hash'], -8) ?>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                    </a>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if (!empty($r['screenshot_path']) && file_exists(__DIR__ . '/../' . $r['screenshot_path'])): ?>
                                        <button type="button" onclick="previewImage('/<?= e($r['screenshot_path']) ?>')"
                                            class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-indigo-300 text-[10px] font-semibold flex items-center gap-1">
                                            <i class="fa-solid fa-image"></i> View
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-600 text-[10px]">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php
                                    $stClass = match ($r['status']) {
                                        'approved' => 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
                                        'pending'  => 'bg-amber-500/20 text-amber-300 border-amber-500/30 animate-pulse',
                                        'rejected' => 'bg-rose-500/20 text-rose-300 border-rose-500/30',
                                        default    => 'bg-slate-800 text-slate-400'
                                    };
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase border <?= $stClass ?>">
                                        <?= e($r['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <?php if ($r['status'] === 'pending'): ?>
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Approve Action Button -->
                                            <button type="button" onclick="openReviewModal(<?= (int)$r['id'] ?>, 'approve', '<?= e($r['username']) ?>', '<?= formatUsdt($r['usdt_amount']) ?>', '<?= e($r['network']) ?>')"
                                                class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] shadow-sm transition flex items-center gap-1">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>

                                            <!-- Reject Action Button -->
                                            <button type="button" onclick="openReviewModal(<?= (int)$r['id'] ?>, 'reject', '<?= e($r['username']) ?>', '<?= formatUsdt($r['usdt_amount']) ?>', '<?= e($r['network']) ?>')"
                                                class="px-3 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-[11px] shadow-sm transition flex items-center gap-1">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-[10px] text-slate-500">
                                            by <?= e($r['reviewer_username'] ?? 'Admin') ?><br>
                                            <?= date('M d, H:i', strtotime($r['reviewed_at'] ?? $r['updated_at'])) ?>
                                        </div>
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

<!-- Review Confirmation Modal -->
<div id="reviewModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 id="modalTitle" class="text-base font-bold text-white flex items-center gap-2"></h3>
            <button type="button" onclick="closeReviewModal()" class="text-slate-400 hover:text-white text-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <p id="modalDesc" class="text-xs text-slate-300 leading-relaxed"></p>

        <form method="POST" action="/admin/usdt_activations.php" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" id="modalAction" value="">
            <input type="hidden" name="payment_id" id="modalPaymentId" value="">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1" for="modalNotes">
                    Admin Verification Notes
                </label>
                <textarea id="modalNotes" name="admin_notes" rows="2"
                    class="w-full px-3.5 py-2.5 bg-slate-800 border border-slate-700 rounded-xl text-white text-xs focus:outline-none focus:ring-2 focus:ring-purple-500"
                    placeholder="Optional notes or rejection reasons (e.g. Valid on-chain TXID verified)"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                <button type="button" onclick="closeReviewModal()" class="px-4 py-2 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold hover:bg-slate-700">
                    Cancel
                </button>
                <button type="submit" id="modalSubmitBtn" class="px-5 py-2 rounded-xl text-white text-xs font-bold shadow-md transition">
                    Confirm Action
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lightbox Image Modal -->
<div id="imageLightbox" class="fixed inset-0 z-50 bg-slate-950/90 hidden flex items-center justify-center p-4" onclick="closeLightbox()">
    <div class="relative max-w-2xl max-h-[90vh]">
        <img id="lightboxImg" src="" alt="Proof Receipt" class="max-w-full max-h-[85vh] object-contain rounded-2xl border border-slate-700 shadow-2xl">
        <button type="button" class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-slate-800 text-white flex items-center justify-center shadow-lg hover:bg-slate-700">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>
</div>

<script>
function openReviewModal(id, action, username, amount, network) {
    document.getElementById('modalPaymentId').value = id;
    document.getElementById('modalAction').value = action;
    const title = document.getElementById('modalTitle');
    const desc = document.getElementById('modalDesc');
    const btn = document.getElementById('modalSubmitBtn');
    const notes = document.getElementById('modalNotes');

    if (action === 'approve') {
        title.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-400"></i> Approve USDT Activation';
        desc.innerHTML = `Are you sure you want to approve activation request <strong>#${id}</strong> for <strong>@${username}</strong> (${amount} USDT on ${network})?<br><br>This will activate the user's account and automatically place them into the 3x BFS Matrix queue.`;
        btn.textContent = 'Approve & Place in Matrix';
        btn.className = 'px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold shadow-md transition';
        notes.placeholder = 'e.g. Valid TXID confirmed on blockchain.';
    } else {
        title.innerHTML = '<i class="fa-solid fa-circle-xmark text-rose-400"></i> Reject USDT Activation';
        desc.innerHTML = `Are you sure you want to reject activation request <strong>#${id}</strong> for <strong>@${username}</strong>?<br><br>The package will not be activated and no BFS node will be created.`;
        btn.textContent = 'Confirm Rejection';
        btn.className = 'px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold shadow-md transition';
        notes.placeholder = 'e.g. Invalid TXID or payment not received.';
    }

    document.getElementById('reviewModal').classList.remove('hidden');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
}

function previewImage(url) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('imageLightbox').classList.remove('hidden');
}

function closeLightbox() {
    document.getElementById('imageLightbox').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
