<?php
/**
 * Automated Test Suite for 3x BFS MLM Matrix System
 */
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bfs_engine.php';

function assertCondition(bool $cond, string $msg): void {
    if (!$cond) {
        echo "❌ FAILED: {$msg}\n";
        exit(1);
    }
    echo "✅ PASSED: {$msg}\n";
}

$pdo = getDatabaseConnection();
echo "--- Starting MLM Matrix System Tests ---\n\n";

// 1. Test BFS Math Helper Functions
echo "[1] Testing BFS Position & Level Mapping...\n";
assertCondition(BfsMatrixEngine::getLevelFromPosition(1) === 1, "Pos 1 is Level 1");
assertCondition(BfsMatrixEngine::getLevelFromPosition(2) === 2, "Pos 2 is Level 2");
assertCondition(BfsMatrixEngine::getLevelFromPosition(4) === 2, "Pos 4 is Level 2");
assertCondition(BfsMatrixEngine::getLevelFromPosition(5) === 3, "Pos 5 is Level 3");
assertCondition(BfsMatrixEngine::getLevelFromPosition(13) === 3, "Pos 13 is Level 3");
assertCondition(BfsMatrixEngine::getLevelFromPosition(14) === 4, "Pos 14 is Level 4");
assertCondition(BfsMatrixEngine::getLevelFromPosition(40) === 4, "Pos 40 is Level 4");
assertCondition(BfsMatrixEngine::getLevelFromPosition(41) === 5, "Pos 41 is Level 5");

assertCondition(BfsMatrixEngine::getParentPosition(1) === null, "Pos 1 has no parent");
assertCondition(BfsMatrixEngine::getParentPosition(2) === 1, "Pos 2 parent is Pos 1");
assertCondition(BfsMatrixEngine::getParentPosition(3) === 1, "Pos 3 parent is Pos 1");
assertCondition(BfsMatrixEngine::getParentPosition(4) === 1, "Pos 4 parent is Pos 1");
assertCondition(BfsMatrixEngine::getParentPosition(5) === 2, "Pos 5 parent is Pos 2");
assertCondition(BfsMatrixEngine::getParentPosition(7) === 2, "Pos 7 parent is Pos 2");
assertCondition(BfsMatrixEngine::getParentPosition(8) === 3, "Pos 8 parent is Pos 3");
assertCondition(BfsMatrixEngine::getParentPosition(10) === 3, "Pos 10 parent is Pos 3");
assertCondition(BfsMatrixEngine::getParentPosition(11) === 4, "Pos 11 parent is Pos 4");
assertCondition(BfsMatrixEngine::getParentPosition(13) === 4, "Pos 13 parent is Pos 4");
assertCondition(BfsMatrixEngine::getParentPosition(14) === 5, "Pos 14 parent is Pos 5");

// 2. Test Package Configurations
echo "\n[2] Testing Package Configurations...\n";
$cfg1 = BfsMatrixEngine::getConfig('package_1');
assertCondition($cfg1['max_levels'] === 6, "Package 1 max levels is 6");
assertCondition($cfg1['entry_amount'] === 100.0, "Package 1 entry is 100 USDT");
assertCondition(in_array(4, $cfg1['rebirth_levels']), "Package 1 has Level 4 rebirth");
assertCondition(in_array(5, $cfg1['rebirth_levels']), "Package 1 has Level 5 rebirth");
assertCondition(in_array(6, $cfg1['rebirth_levels']), "Package 1 has Level 6 rebirth");

$cfg2 = BfsMatrixEngine::getConfig('package_2');
assertCondition($cfg2['max_levels'] === 7, "Package 2 max levels is 7");
assertCondition($cfg2['entry_amount'] === 300.0, "Package 2 entry is 300 USDT");
assertCondition(in_array(7, $cfg2['rebirth_levels']), "Package 2 has Level 7 rebirth");

// 3. Test BFS Spillover Placement & Sequential Filling
echo "\n[3] Testing BFS Sequential Placement & Auto-Spillover in Package 1...\n";
// Let's create dummy test users to fill Level 3 completely and trigger Level 4
$initialNextPos = BfsMatrixEngine::getNextBfsPosition($pdo, 'package_1');
echo "Initial next pos in package 1: {$initialNextPos}\n";

// Place test user 5 into package 1 until we reach position 14 (Level 4 trigger)
$placedCount = 0;
while (true) {
    $nextPos = BfsMatrixEngine::getNextBfsPosition($pdo, 'package_1');
    if ($nextPos >= 15) {
        break;
    }
    $node = BfsMatrixEngine::placeNode($pdo, 5, 'package_1');
    $placedCount++;
}
echo "Placed {$placedCount} new test slots in package 1\n";

$checkPos14 = $pdo->query("SELECT * FROM matrix_nodes WHERE package_type = 'package_1' AND position_index = 14")->fetch();
assertCondition(!empty($checkPos14), "Position 14 exists in Package 1");
assertCondition((int)$checkPos14['level'] === 4, "Position 14 reached Level 4");
assertCondition($checkPos14['status'] === 'rebirthed', "Position 14 reached Level 4 and triggered status = rebirthed");

// Check that rebirth booster slot was queued
$rebirthNode = $pdo->query("SELECT * FROM matrix_nodes WHERE package_type = 'package_1' AND is_rebirth = 1 AND rebirth_source_node_id = {$checkPos14['id']}")->fetch();
assertCondition(!empty($rebirthNode), "Rebirth booster slot created for Level 4 node");
assertCondition((int)$rebirthNode['position_index'] > 14, "Rebirth booster slot queued downstream at position #{$rebirthNode['position_index']}");

// Check rebirth transaction fee deducted
$rebirthTx = $pdo->query("SELECT * FROM transactions WHERE user_id = 5 AND type = 'rebirth_fee' AND status = 'completed'")->fetch();
assertCondition(!empty($rebirthTx), "Rebirth fee transaction record generated");
assertCondition((float)$rebirthTx['amount_usdt'] === 20.00, "Rebirth fee for Package 1 is 20.00 USDT");

// 4. Test 60% Matrix Payout Rewards
echo "\n[4] Testing 60% Upline Matrix Distribution...\n";
$payoutsCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'matrix_payout' AND status = 'completed'")->fetchColumn();
assertCondition($payoutsCount > 0, "Matrix payouts distributed into transactions ledger ({$payoutsCount} records)");

// 5. Test USDT Balances and User Ledger Calculation
echo "\n[5] Testing User USDT Balance Calculations...\n";
$bal5 = getUserUsdtBalance(5);
assertCondition(is_float($bal5) || is_numeric($bal5), "Balance function returns numeric value");
echo "User 5 current computed USDT balance: " . formatUsdt($bal5) . " USDT\n";

// 6. Test Tree Visualizer Extraction
echo "\n[6] Testing Matrix Visualizer Subtree Fetching...\n";
$tree = BfsMatrixEngine::getUserMatrixTree($pdo, 1, 'package_1');
assertCondition(!empty($tree), "Tree root node found for user 1");
assertCondition(count($tree['children']) === 3, "Root node has 3 direct children in 3x matrix");
assertCondition($tree['team_count'] > 3, "Root node has calculated descendants: {$tree['team_count']}");

echo "\n--- ALL AUTOMATED TESTS PASSED SUCCESSFULLY! ---\n";
