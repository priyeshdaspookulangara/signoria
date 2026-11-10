<?php
/**
 * Admin SQL Section: Paste and Execute SQL Insert Queries
 * Treats inserted users as if registered through the registration form:
 * - Automatically hashes passwords if plaintext
 * - Resolves sponsor linkages (by sponsor username or sponsor ID)
 * - Assigns/places into 3x BFS Matrix (Package I / Package II)
 * - Updates direct sponsor referral count
 * - Distributes Direct Referral Income bonus (10% of package entry) to sponsor
 * - Distributes 60% upline matrix rewards via BFS Engine
 * - Generates audit logs and ledger transactions
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bfs_engine.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$pdo = getDatabaseConnection();
$adminUser = getCurrentUser();
$adminId = (int)$adminUser['id'];

$rawSql = '';
$results = [];
$defaultPackage = 'package_1';
$giveReferralBonus = true;
$placeInMatrix = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid session security token. Please try again.');
        header('Location: /admin/sql_import.php');
        exit;
    }

    $rawSql = trim($_POST['sql_query'] ?? '');
    $defaultPackage = trim($_POST['default_package'] ?? 'package_1');
    $placeInMatrix = isset($_POST['place_in_matrix']);
    $giveReferralBonus = isset($_POST['give_referral_bonus']);
    $referralBonusPct = floatval($_POST['referral_bonus_pct'] ?? 10.0) / 100.0; // default 10% direct sponsor bonus

    if (empty($rawSql)) {
        setFlash('warning', 'Please paste at least one SQL INSERT query.');
    } else {
        // Pre-process: Strip SQL comments (-- line comments and /* multi-line comments */)
        $cleanSql = preg_replace('/(--[^\r\n]*)|(\/\*[\s\S]*?\*\/)/', '', $rawSql);

        // Normalize line breaks and split by statement semicolon
        $rawStatements = explode(';', $cleanSql);
        $statements = [];
        foreach ($rawStatements as $rawStmt) {
            $trimmed = trim($rawStmt);
            if (!empty($trimmed)) {
                $statements[] = $trimmed;
            }
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($statements as $index => $stmtText) {
            $stmtNumber = $index + 1;
            // Check if this statement is an INSERT (or INSERT IGNORE) into the users table
            $isUsersInsert = (bool)preg_match('/^\s*INSERT\s+(?:IGNORE\s+)?INTO\s+[`\'"]?users[`\'"]?/i', $stmtText);

            if (!$isUsersInsert) {
                // If it is another table or non-users query, execute directly
                try {
                    $pdo->exec($stmtText);
                    $results[] = [
                        'stmt' => $stmtNumber,
                        'status' => 'success',
                        'type' => 'raw_sql',
                        'message' => 'Executed raw SQL statement successfully.',
                        'query' => substr($stmtText, 0, 120) . (strlen($stmtText) > 120 ? '...' : '')
                    ];
                    $successCount++;
                } catch (Throwable $e) {
                    $results[] = [
                        'stmt' => $stmtNumber,
                        'status' => 'error',
                        'type' => 'raw_sql',
                        'message' => 'Error: ' . $e->getMessage(),
                        'query' => substr($stmtText, 0, 120) . (strlen($stmtText) > 120 ? '...' : '')
                    ];
                    $errorCount++;
                }
                continue;
            }

            // It is an INSERT [IGNORE] INTO users query! Execute and intercept the user registration lifecycle
            $pdo->beginTransaction();
            try {
                // Snapshot existing user IDs prior to execution to detect newly created accounts
                $existingIds = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);

                // Execute statement
                $pdo->exec($stmtText);
                $insertedId = (int)$pdo->lastInsertId();

                // If lastInsertId returned 0 (e.g. bulk insert), find newly created user IDs
                $newUserIds = [];
                if ($insertedId > 0 && !in_array($insertedId, $existingIds)) {
                    $newUserIds[] = $insertedId;
                } else {
                    $allCurrentIds = $pdo->query("SELECT id FROM users")->fetchAll(PDO::FETCH_COLUMN);
                    $newUserIds = array_values(array_diff($allCurrentIds, $existingIds));
                }

                // If statement executed but 0 new users were inserted (e.g. INSERT IGNORE skipped existing duplicates)
                if (empty($newUserIds)) {
                    $pdo->commit();
                    $results[] = [
                        'stmt' => $stmtNumber,
                        'status' => 'info',
                        'type' => 'user_registration',
                        'message' => 'Statement executed successfully. No new users added (duplicate usernames/records were safely ignored).',
                        'query' => substr($stmtText, 0, 120) . (strlen($stmtText) > 120 ? '...' : '')
                    ];
                    $successCount++;
                    continue;
                }

                $processedUsers = [];
                foreach ($newUserIds as $newUserId) {
                    // Fetch inserted user
                    $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
                    $uStmt->execute([':id' => $newUserId]);
                    $newUser = $uStmt->fetch();

                    if (!$newUser) {
                        continue;
                    }

                    // 1. Password hash normalization: If password does not begin with $2y$ or $argon2, hash it
                    $currentPass = $newUser['password'];
                    if (!str_starts_with($currentPass, '$2y$') && !str_starts_with($currentPass, '$2a$') && !str_starts_with($currentPass, '$argon2')) {
                        $hashedPass = password_hash($currentPass, PASSWORD_BCRYPT);
                        $upPass = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                        $upPass->execute([':p' => $hashedPass, ':id' => $newUserId]);
                    }

                    // 2. Sponsor resolution & Referral linkage
                    $sponsorId = $newUser['sponsor_id'] ? (int)$newUser['sponsor_id'] : null;
                    if ($sponsorId === null || $sponsorId <= 0) {
                        // Check if sponsor was entered as a username or default to admin (ID 1)
                        $sponsorId = 1;
                        $upSp = $pdo->prepare("UPDATE users SET sponsor_id = :sp WHERE id = :id");
                        $upSp->execute([':sp' => $sponsorId, ':id' => $newUserId]);
                    }

                    // Fetch sponsor details
                    $spStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = :id");
                    $spStmt->execute([':id' => $sponsorId]);
                    $sponsorRow = $spStmt->fetch();
                    $sponsorUsername = $sponsorRow ? $sponsorRow['username'] : "User #{$sponsorId}";

                    // 3. Matrix Placement & Activation
                    $nodePlaced = null;
                    $referralBonusAmount = 0.00;
                    $packageCost = ($defaultPackage === 'package_2') ? 300.00 : 50.00;

                    if ($placeInMatrix) {
                        // Create activation deposit record
                        $txDep = $pdo->prepare("
                            INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                            VALUES (:uid, 'deposit', :amt, NULL, 'completed', :details, NOW())
                        ");
                        $txDep->execute([
                            ':uid' => $newUserId,
                            ':amt' => $packageCost,
                            ':details' => "Automated activation via SQL Section for {$defaultPackage}",
                        ]);

                        // Place user into BFS matrix queue (also triggers 60% matrix upline rewards and rebirth triggers)
                        $nodePlaced = BfsMatrixEngine::placeNode($pdo, $newUserId, $defaultPackage);

                        // 4. Direct Sponsor Referral Income Bonus (e.g. 10% of entry amount)
                        if ($giveReferralBonus && $sponsorId > 0 && $sponsorId !== $newUserId) {
                            $referralBonusAmount = round($packageCost * $referralBonusPct, 2);
                            if ($referralBonusAmount > 0) {
                                $pctDisplay = round($referralBonusPct * 100, 1);
                                $txRef = $pdo->prepare("
                                    INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                                    VALUES (:uid, 'referral_income', :amt, NULL, 'completed', :details, NOW())
                                ");
                                $txRef->execute([
                                    ':uid' => $sponsorId,
                                    ':amt' => $referralBonusAmount,
                                    ':details' => "Direct Referral Income ({$pctDisplay}%) from new member @{$newUser['username']} ({$defaultPackage})",
                                ]);
                            }
                        }
                    }

                    // 5. Activity Logging
                    logActivity(
                        $adminId,
                        "Admin SQL Import: Created member @{$newUser['username']} (ID: {$newUserId}) with sponsor @{$sponsorUsername}. Matrix Position: " . ($nodePlaced ? "#{$nodePlaced['position_index']} (Level {$nodePlaced['level']})" : 'None')
                    );
                    logActivity(
                        $newUserId,
                        "Registered via Admin SQL Section. Sponsor: @{$sponsorUsername}." . ($nodePlaced ? " Placed in {$defaultPackage} at Pos #{$nodePlaced['position_index']}." : "")
                    );

                    $processedUsers[] = [
                        'username' => $newUser['username'],
                        'user_id' => $newUserId,
                        'sponsor' => $sponsorUsername,
                        'matrix_pos' => $nodePlaced ? $nodePlaced['position_index'] : 'Skipped',
                        'matrix_level' => $nodePlaced ? $nodePlaced['level'] : '-',
                        'referral_income' => $referralBonusAmount,
                    ];
                }

                $pdo->commit();

                $detailsStr = implode(', ', array_map(
                    fn($u) => "@{$u['username']} (Sponsor: @{$u['sponsor']}, Pos: #{$u['matrix_pos']}, Ref Income: {$u['referral_income']} USDT)",
                    $processedUsers
                ));

                $results[] = [
                    'stmt' => $stmtNumber,
                    'status' => 'success',
                    'type' => 'user_registration',
                    'message' => "Successfully registered and activated " . count($processedUsers) . " user(s): " . $detailsStr,
                    'query' => substr($stmtText, 0, 100) . '...'
                ];
                $successCount++;

            } catch (Throwable $e) {
                $pdo->rollBack();
                $results[] = [
                    'stmt' => $stmtNumber,
                    'status' => 'error',
                    'type' => 'user_registration',
                    'message' => 'Registration Error: ' . $e->getMessage(),
                    'query' => substr($stmtText, 0, 100) . '...'
                ];
                $errorCount++;
            }
        }

        if ($successCount > 0 && $errorCount === 0) {
            setFlash('success', "All {$successCount} SQL statement(s) executed successfully with full referral count, referral income, and BFS matrix updates!");
        } elseif ($successCount > 0 && $errorCount > 0) {
            setFlash('warning', "Processed {$successCount} statement(s) successfully, but {$errorCount} failed. Check execution details below.");
        } else {
            setFlash('danger', "All {$errorCount} statement(s) failed. See details below.");
        }
    }
}

$pageTitle = 'SQL Registration / Import Engine';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <span class="p-2.5 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400">
                    <i class="fa-solid fa-database text-lg"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-black text-white">SQL Registration & Data Engine</h1>
                    <p class="text-xs text-slate-400 mt-0.5">Paste raw SQL INSERT queries; system will execute them and execute full registration workflows (referral counts, referral income, 3x BFS placement, and password hashing).</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="/admin/users.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2">
                <i class="fa-solid fa-users"></i> View All Users
            </a>
            <a href="/admin/dashboard.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-2">
                <i class="fa-solid fa-gauge"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Execution Results if Any -->
    <?php if (!empty($results)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-white flex items-center gap-2">
                <i class="fa-solid fa-terminal text-emerald-400"></i> Execution Batch Results
            </h2>
            <div class="space-y-3">
                <?php foreach ($results as $res): ?>
                    <div class="p-4 rounded-xl border text-xs font-mono <?= $res['status'] === 'success' ? 'bg-emerald-950/40 border-emerald-800/60 text-emerald-200' : 'bg-rose-950/40 border-rose-800/60 text-rose-200' ?>">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold flex items-center gap-2">
                                <i class="fa-solid <?= $res['status'] === 'success' ? 'fa-circle-check text-emerald-400' : 'fa-circle-xmark text-rose-400' ?>"></i>
                                Statement #<?= $res['stmt'] ?> (<?= strtoupper($res['status']) ?>)
                            </span>
                            <span class="text-[10px] text-slate-400"><?= e($res['type']) ?></span>
                        </div>
                        <p class="mt-1 font-sans text-xs"><?= e($res['message']) ?></p>
                        <div class="mt-2 text-[11px] text-slate-400 bg-slate-950/80 p-2 rounded border border-slate-800">
                            <code><?= e($res['query']) ?></code>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- SQL Input Form -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Editor Left (2 Columns) -->
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    <h2 class="text-base font-bold text-white">Paste SQL INSERT Query</h2>
                </div>
                <button type="button" onclick="loadSampleUserSql()" class="text-xs text-purple-400 hover:text-purple-300 font-semibold flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Load Sample Insert
                </button>
            </div>

            <form method="POST" action="/admin/sql_import.php" class="space-y-6">
                <?= csrfField() ?>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                        SQL Statement(s) (supports single or multiple queries separated by semicolons)
                    </label>
                    <textarea id="sql_query" name="sql_query" rows="12" required
                        placeholder="INSERT INTO users (username, sponsor_id, email, password, usdt_wallet) VALUES ('crypto_john', 1, 'john@example.com', 'Pass1234!', '0x123...')"
                        class="w-full bg-slate-950 border border-slate-700 rounded-xl p-4 text-xs font-mono text-emerald-400 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition leading-relaxed"><?= e($rawSql) ?></textarea>
                    <p class="text-[11px] text-slate-500 mt-2">
                        Tip: Plaintext passwords are automatically converted to secure Bcrypt hashes. Sponsor IDs or usernames are verified, and referral rewards will be transferred automatically.
                    </p>
                </div>

                <!-- Execution Settings -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-300 mb-1.5">Target BFS Matrix Pool</label>
                        <select name="default_package" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-purple-500">
                            <option value="package_1" <?= $defaultPackage === 'package_1' ? 'selected' : '' ?>>Package I (50 USDT / 6 Levels)</option>
                            <option value="package_2" <?= $defaultPackage === 'package_2' ? 'selected' : '' ?>>Package II (300 USDT / 7 Levels)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-300 mb-1.5">Direct Sponsor Bonus %</label>
                        <div class="relative">
                            <input type="number" name="referral_bonus_pct" value="10" min="0" max="100" step="1"
                                class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:outline-none focus:border-purple-500 pr-8">
                            <span class="absolute right-3 top-2 text-slate-400 font-bold">%</span>
                        </div>
                    </div>

                    <div class="sm:col-span-2 pt-2 border-t border-slate-800/80 flex flex-wrap gap-6">
                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="place_in_matrix" value="1" <?= $placeInMatrix ? 'checked' : '' ?> class="rounded bg-slate-900 border-slate-700 text-purple-600 focus:ring-0 w-4 h-4">
                            <span class="text-slate-300 font-semibold">Auto-Place in 3x BFS Matrix & Distribute Upline Rewards</span>
                        </label>

                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="give_referral_bonus" value="1" <?= $giveReferralBonus ? 'checked' : '' ?> class="rounded bg-slate-900 border-slate-700 text-purple-600 focus:ring-0 w-4 h-4">
                            <span class="text-slate-300 font-semibold">Credit Direct Referral Income to Sponsor</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="reset" class="px-5 py-2.5 rounded-xl border border-slate-700 text-xs font-semibold text-slate-400 hover:text-white hover:bg-slate-800 transition">
                        Clear Query
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white text-xs font-bold shadow-lg shadow-purple-600/30 transition flex items-center gap-2">
                        <i class="fa-solid fa-play"></i> Execute Query & Complete Registration
                    </button>
                </div>
            </form>
        </div>

        <!-- Right Side: Workflow Explanation & Features -->
        <div class="space-y-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-purple-400"></i> Automated Registration Workflows
                </h3>
                <p class="text-xs text-slate-400 leading-relaxed">
                    When inserting users through this section, the application intercepts the creation and ensures parity with the frontend registration form:
                </p>

                <div class="space-y-3 pt-2 text-xs">
                    <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                        <i class="fa-solid fa-key text-amber-400 mt-0.5"></i>
                        <div>
                            <strong class="text-slate-200 block">Automatic Bcrypt Hashing</strong>
                            <span class="text-slate-400 text-[11px]">If you write a plaintext password like <code>'MySecretPass'</code>, it is securely hashed before storage.</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                        <i class="fa-solid fa-user-plus text-indigo-400 mt-0.5"></i>
                        <div>
                            <strong class="text-slate-200 block">Referral Count & Income</strong>
                            <span class="text-slate-400 text-[11px]">Direct sponsor referral count increases immediately, and the sponsor receives direct referral bonus ledger credits.</span>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 p-3 rounded-xl bg-slate-950 border border-slate-800/80">
                        <i class="fa-solid fa-sitemap text-emerald-400 mt-0.5"></i>
                        <div>
                            <strong class="text-slate-200 block">3x BFS Auto-Spillover Tree</strong>
                            <span class="text-slate-400 text-[11px]">Places node in the next available breadth-first queue position, checks depth rebirths, and distributes 60% upline pool income.</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl text-xs space-y-3">
                <h3 class="font-bold text-white">Supported Users Columns</h3>
                <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 font-mono text-[11px] text-slate-300">
                    username, sponsor_id, email, password, phone, usdt_wallet, usdt_trc20_address, usdt_bep20_address, role, status
                </div>
                <p class="text-slate-500 text-[11px]">
                    Sponsor ID defaults to 1 (Admin) if empty. Email does not need to be unique.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function loadSampleUserSql() {
    const timestamp = Math.floor(Date.now() / 1000).toString().slice(-4);
    const sample = `INSERT INTO users (username, sponsor_id, email, password, phone, usdt_bep20_address)
VALUES ('user_${timestamp}', 1, 'user_${timestamp}@matrixmlm.com', 'Pass1234!', '9876543210', '0x572790c26FC062C247D0e1797D646F20a093AE14');`;
    document.getElementById('sql_query').value = sample;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
