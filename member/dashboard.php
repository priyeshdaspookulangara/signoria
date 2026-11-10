<?php
/**
 * Member Dashboard
 * Displays USDT Balance, Package I and Package II Matrix visualizers, and referral links
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bfs_engine.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$user = getCurrentUser();
$userId = (int)$user['id'];
$pdo = getDatabaseConnection();

// Handle Package upgrade/activation if not yet in a package
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_package') {
    $pkgToActivate = trim($_POST['package_type'] ?? '');
    if (in_array($pkgToActivate, ['package_1', 'package_2'], true)) {
        // Check if user already in this package
        $chkStmt = $pdo->prepare("SELECT id FROM matrix_nodes WHERE user_id = :uid AND package_type = :pkg LIMIT 1");
        $chkStmt->execute([':uid' => $userId, ':pkg' => $pkgToActivate]);
        if ($chkStmt->fetch()) {
            setFlash('warning', 'You already have an active node in this matrix package.');
        } else {
            $pkgConfig = BfsMatrixEngine::getConfig($pkgToActivate);
            $userBal = getUserUsdtBalance($userId);

            if ($userBal < $pkgConfig['entry_amount']) {
                setFlash('danger', "Insufficient USDT balance (" . formatUsdt($userBal) . " USDT). Required: " . formatUsdt($pkgConfig['entry_amount']) . " USDT. Please deposit first.");
            } else {
                $pdo->beginTransaction();
                try {
                    // Record package upgrade transaction
                    $txStmt = $pdo->prepare("
                        INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                        VALUES (:uid, 'package_upgrade', :amt, NULL, 'completed', :details, NOW())
                    ");
                    $txStmt->execute([
                        ':uid'     => $userId,
                        ':amt'     => $pkgConfig['entry_amount'],
                        ':details' => "Activated entry slot for {$pkgToActivate}",
                    ]);

                    // Place in BFS queue
                    $node = BfsMatrixEngine::placeNode($pdo, $userId, $pkgToActivate);

                    $pdo->commit();
                    setFlash('success', "Congratulations! You have joined {$pkgConfig['name']} at Position #{$node['position_index']} (Level {$node['level']}).");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Package activation failed: " . $e->getMessage());
                    setFlash('danger', "Failed to activate matrix package: " . $e->getMessage());
                }
            }
        }
        header('Location: /member/dashboard.php');
        exit;
    }
}

// Fetch balances and earnings
$usdtBalance = getUserUsdtBalance($userId);

// Fetch Total Payouts Earned (Matrix Payouts)
$payoutStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions
    WHERE user_id = :uid AND type = 'matrix_payout' AND status = 'completed'
");
$payoutStmt->execute([':uid' => $userId]);
$totalEarnings = (float)$payoutStmt->fetchColumn();

// Fetch Referral Income Earned
$refIncomeStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions
    WHERE user_id = :uid AND type = 'referral_income' AND status = 'completed'
");
$refIncomeStmt->execute([':uid' => $userId]);
$referralIncome = (float)$refIncomeStmt->fetchColumn();

// Fetch Direct Referrals Count
$refCountStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE sponsor_id = :uid");
$refCountStmt->execute([':uid' => $userId]);
$directReferralCount = (int)$refCountStmt->fetchColumn();

// Fetch Total Rebirth Deductions
$rebirthStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions
    WHERE user_id = :uid AND type = 'rebirth_fee' AND status = 'completed'
");
$rebirthStmt->execute([':uid' => $userId]);
$totalRebirthFees = (float)$rebirthStmt->fetchColumn();

// Fetch Active Matrix Trees for both packages
$treeP1 = BfsMatrixEngine::getUserMatrixTree($pdo, $userId, 'package_1');
$treeP2 = BfsMatrixEngine::getUserMatrixTree($pdo, $userId, 'package_2');

// Count user rebirth slots
$rbSlotsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM matrix_nodes
    WHERE user_id = :uid AND is_rebirth = 1
");
$rbSlotsStmt->execute([':uid' => $userId]);
$userRebirthSlots = (int)$rbSlotsStmt->fetchColumn();

// Fetch Sponsor Details
$sponsorName = 'System Root';
if (!empty($user['sponsor_id'])) {
    $spStmt = $pdo->prepare("SELECT username FROM users WHERE id = :id");
    $spStmt->execute([':id' => $user['sponsor_id']]);
    $sp = $spStmt->fetch();
    if ($sp) {
        $sponsorName = $sp['username'];
    }
}

$referralLink = getBaseUrl() . '/auth/register.php?ref=' . urlencode($user['username']);

$pageTitle = 'Member Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Welcome Header & Referral Link -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-850 to-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col lg:flex-row items-center justify-between gap-6">
        <div class="flex items-center space-x-4">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-emerald-500/20">
                <?= strtoupper(substr($user['username'], 0, 2)) ?>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-black text-white"><?= e($user['username']) ?></h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        Active Member
                    </span>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    Sponsor: <span class="text-slate-300 font-semibold"><?= e($sponsorName) ?></span> &bull;
                    Joined: <?= date('M d, Y', strtotime($user['created_at'])) ?>
                </p>
            </div>
        </div>

        <!-- Referral Link Bar -->
        <div class="w-full lg:w-auto flex-1 max-w-xl">
            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                <i class="fa-solid fa-share-nodes text-emerald-400 mr-1"></i> Your Referral Invitation Link
            </label>
            <div class="flex items-center space-x-2">
                <input type="text" id="refLinkInput" readonly value="<?= e($referralLink) ?>"
                    class="block w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs font-mono text-emerald-400 focus:outline-none">
                <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('refLinkInput').value); alert('Referral link copied to clipboard!');"
                    class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap">
                    <i class="fa-regular fa-copy"></i> Copy
                </button>
            </div>
        </div>
    </div>

    <!-- Metrics Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden">
            <div class="flex justify-between items-center">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Available Balance</span>
                <span class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400"><i class="fa-solid fa-wallet text-xs"></i></span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-extrabold text-white"><?= formatUsdt($usdtBalance) ?></span>
                <span class="text-[11px] font-bold text-emerald-400 ml-1">USDT</span>
            </div>
            <div class="mt-2.5">
                <a href="/member/wallet.php" class="text-[11px] text-emerald-400 hover:text-emerald-300 font-semibold flex items-center gap-1">
                    Wallet <i class="fa-solid fa-arrow-right text-[9px]"></i>
                </a>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden">
            <div class="flex justify-between items-center">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Direct Referrals</span>
                <span class="p-2 rounded-xl bg-indigo-500/10 text-indigo-400"><i class="fa-solid fa-users text-xs"></i></span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-extrabold text-indigo-400"><?= $directReferralCount ?></span>
                <span class="text-[11px] font-bold text-slate-400 ml-1">Members</span>
            </div>
            <p class="mt-2.5 text-[11px] text-slate-500">Personally sponsored</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden">
            <div class="flex justify-between items-center">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Referral Income</span>
                <span class="p-2 rounded-xl bg-cyan-500/10 text-cyan-400"><i class="fa-solid fa-hand-holding-dollar text-xs"></i></span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-extrabold text-cyan-400"><?= formatUsdt($referralIncome) ?></span>
                <span class="text-[11px] font-bold text-slate-400 ml-1">USDT</span>
            </div>
            <p class="mt-2.5 text-[11px] text-slate-500">Direct sponsor bonus</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden">
            <div class="flex justify-between items-center">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Matrix Earnings</span>
                <span class="p-2 rounded-xl bg-teal-500/10 text-teal-400"><i class="fa-solid fa-chart-line text-xs"></i></span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-extrabold text-white"><?= formatUsdt($totalEarnings) ?></span>
                <span class="text-[11px] font-bold text-teal-400 ml-1">USDT</span>
            </div>
            <p class="mt-2.5 text-[11px] text-slate-500">60% upline rewards</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden">
            <div class="flex justify-between items-center">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Rebirth Booster</span>
                <span class="p-2 rounded-xl bg-purple-500/10 text-purple-400"><i class="fa-solid fa-arrows-rotate text-xs"></i></span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-extrabold text-white"><?= $userRebirthSlots ?></span>
                <span class="text-[11px] font-bold text-purple-400 ml-1">Slots</span>
            </div>
            <p class="mt-2.5 text-[11px] text-slate-500"><?= formatUsdt($totalRebirthFees) ?> USDT Reinvested</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden">
            <div class="flex justify-between items-center">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Payout Wallet</span>
                <span class="p-2 rounded-xl bg-blue-500/10 text-blue-400"><i class="fa-solid fa-link text-xs"></i></span>
            </div>
            <div class="mt-3">
                <span class="text-xs font-mono text-slate-300 block truncate" title="<?= e($user['usdt_wallet']) ?>">
                    <?= e($user['usdt_wallet'] ?: 'Not configured') ?>
                </span>
            </div>
            <div class="mt-2.5">
                <a href="/member/profile.php" class="text-[11px] text-blue-400 hover:text-blue-300 font-semibold flex items-center gap-1">
                    Settings <i class="fa-solid fa-pen-to-square text-[9px]"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 3x Matrix Tree Visualizer Section -->
    <div class="space-y-8">
        <!-- Package 1 Visualizer -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                        <h2 class="text-xl font-bold text-white">Package I (6 Levels Pool - 50 USDT)</h2>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">3x Matrix BFS Auto-Spillover Tree Progression</p>
                </div>
                <?php if ($treeP1): ?>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-slate-400">Position: <strong class="text-emerald-400 font-mono">#<?= (int)$treeP1['position_index'] ?></strong></span>
                        <span class="text-slate-400">Current Level: <strong class="text-white font-mono"><?= (int)$treeP1['level'] ?></strong> / 6</span>
                        <span class="text-slate-400">Subtree Members: <strong class="text-emerald-400 font-mono"><?= (int)$treeP1['team_count'] ?></strong></span>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="activate_package">
                        <input type="hidden" name="package_type" value="package_1">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-md">
                            Activate Package I (50 USDT)
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Visual Matrix Tree Rendering -->
            <div class="mt-6 overflow-x-auto pb-6 custom-scroll">
                <?php if ($treeP1): ?>
                    <div class="min-w-[700px] flex flex-col items-center">
                        <!-- Level 1 (You) -->
                        <div class="flex flex-col items-center">
                            <div class="px-5 py-3 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white shadow-lg shadow-emerald-500/20 text-center border border-emerald-400">
                                <div class="text-[11px] uppercase tracking-wider font-bold opacity-80">You (Root)</div>
                                <div class="font-extrabold text-sm"><?= e($treeP1['username']) ?></div>
                                <div class="text-[10px] font-mono opacity-90">Pos #<?= (int)$treeP1['position_index'] ?> &bull; L<?= (int)$treeP1['level'] ?></div>
                            </div>
                            <div class="w-0.5 h-6 bg-slate-700"></div>
                        </div>

                        <!-- Level 2 (3 Direct Downlines) -->
                        <div class="w-full flex justify-center">
                            <div class="grid grid-cols-3 gap-6 w-full max-w-4xl pt-2 border-t-2 border-slate-700">
                                <?php
                                $childrenP1 = $treeP1['children'] ?? [];
                                for ($i = 0; $i < 3; $i++):
                                    $child = $childrenP1[$i] ?? null;
                                ?>
                                    <div class="flex flex-col items-center">
                                        <div class="w-0.5 h-4 bg-slate-700 -mt-2"></div>
                                        <?php if ($child): ?>
                                            <div class="w-full p-3 rounded-xl bg-slate-800 border <?= $child['is_rebirth'] ? 'border-purple-500/50 bg-purple-950/20' : 'border-slate-700' ?> text-center shadow">
                                                <div class="text-[10px] text-emerald-400 font-semibold">
                                                    Slot #<?= (int)$child['position_index'] ?> <?= $child['is_rebirth'] ? '(Rebirth)' : '' ?>
                                                </div>
                                                <div class="font-bold text-white text-xs truncate"><?= e($child['username']) ?></div>
                                                <div class="text-[9px] text-slate-400">Level <?= (int)$child['level'] ?></div>
                                            </div>

                                            <!-- Level 3 (Sub-children of this child) -->
                                            <?php if (!empty($child['children'])): ?>
                                                <div class="w-0.5 h-4 bg-slate-700"></div>
                                                <div class="grid grid-cols-3 gap-1.5 w-full pt-1 border-t border-slate-700">
                                                    <?php foreach ($child['children'] as $grandChild): ?>
                                                        <div class="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-center">
                                                            <div class="text-[9px] text-slate-300 truncate font-semibold"><?= e($grandChild['username']) ?></div>
                                                            <div class="text-[8px] text-emerald-400 font-mono">#<?= (int)$grandChild['position_index'] ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="w-full p-3 rounded-xl bg-slate-900/60 border border-dashed border-slate-700/60 text-center text-slate-500">
                                                <i class="fa-solid fa-user-clock text-sm mb-1 opacity-40"></i>
                                                <div class="text-[10px] font-medium">Next Spillover Slot</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 text-slate-500">
                        <i class="fa-solid fa-cube text-3xl mb-3 opacity-30"></i>
                        <p class="text-sm">You do not have an active node in Package I yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Package 2 Visualizer -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-teal-500"></span>
                        <h2 class="text-xl font-bold text-white">Package II (7 Levels Parallel Pool - 300 USDT)</h2>
                    </div>
                    <p class="text-xs text-slate-400 mt-1">Deep 7-Depth Matrix BFS Auto-Spillover Tree</p>
                </div>
                <?php if ($treeP2): ?>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-slate-400">Position: <strong class="text-teal-400 font-mono">#<?= (int)$treeP2['position_index'] ?></strong></span>
                        <span class="text-slate-400">Current Level: <strong class="text-white font-mono"><?= (int)$treeP2['level'] ?></strong> / 7</span>
                        <span class="text-slate-400">Subtree Members: <strong class="text-teal-400 font-mono"><?= (int)$treeP2['team_count'] ?></strong></span>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="activate_package">
                        <input type="hidden" name="package_type" value="package_2">
                        <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-bold transition shadow-md">
                            Activate Package II (300 USDT)
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Visual Matrix Tree Rendering -->
            <div class="mt-6 overflow-x-auto pb-6 custom-scroll">
                <?php if ($treeP2): ?>
                    <div class="min-w-[700px] flex flex-col items-center">
                        <!-- Level 1 (You) -->
                        <div class="flex flex-col items-center">
                            <div class="px-5 py-3 rounded-xl bg-gradient-to-tr from-teal-600 to-cyan-500 text-white shadow-lg shadow-teal-500/20 text-center border border-teal-400">
                                <div class="text-[11px] uppercase tracking-wider font-bold opacity-80">You (Root)</div>
                                <div class="font-extrabold text-sm"><?= e($treeP2['username']) ?></div>
                                <div class="text-[10px] font-mono opacity-90">Pos #<?= (int)$treeP2['position_index'] ?> &bull; L<?= (int)$treeP2['level'] ?></div>
                            </div>
                            <div class="w-0.5 h-6 bg-slate-700"></div>
                        </div>

                        <!-- Level 2 (3 Direct Downlines) -->
                        <div class="w-full flex justify-center">
                            <div class="grid grid-cols-3 gap-6 w-full max-w-4xl pt-2 border-t-2 border-slate-700">
                                <?php
                                $childrenP2 = $treeP2['children'] ?? [];
                                for ($i = 0; $i < 3; $i++):
                                    $child = $childrenP2[$i] ?? null;
                                ?>
                                    <div class="flex flex-col items-center">
                                        <div class="w-0.5 h-4 bg-slate-700 -mt-2"></div>
                                        <?php if ($child): ?>
                                            <div class="w-full p-3 rounded-xl bg-slate-800 border <?= $child['is_rebirth'] ? 'border-purple-500/50 bg-purple-950/20' : 'border-slate-700' ?> text-center shadow">
                                                <div class="text-[10px] text-teal-400 font-semibold">
                                                    Slot #<?= (int)$child['position_index'] ?> <?= $child['is_rebirth'] ? '(Rebirth)' : '' ?>
                                                </div>
                                                <div class="font-bold text-white text-xs truncate"><?= e($child['username']) ?></div>
                                                <div class="text-[9px] text-slate-400">Level <?= (int)$child['level'] ?></div>
                                            </div>

                                            <!-- Level 3 -->
                                            <?php if (!empty($child['children'])): ?>
                                                <div class="w-0.5 h-4 bg-slate-700"></div>
                                                <div class="grid grid-cols-3 gap-1.5 w-full pt-1 border-t border-slate-700">
                                                    <?php foreach ($child['children'] as $grandChild): ?>
                                                        <div class="p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-center">
                                                            <div class="text-[9px] text-slate-300 truncate font-semibold"><?= e($grandChild['username']) ?></div>
                                                            <div class="text-[8px] text-teal-400 font-mono">#<?= (int)$grandChild['position_index'] ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="w-full p-3 rounded-xl bg-slate-900/60 border border-dashed border-slate-700/60 text-center text-slate-500">
                                                <i class="fa-solid fa-user-clock text-sm mb-1 opacity-40"></i>
                                                <div class="text-[10px] font-medium">Next Spillover Slot</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10 text-slate-500">
                        <i class="fa-solid fa-cube text-3xl mb-3 opacity-30"></i>
                        <p class="text-sm">You do not have an active node in Package II yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
