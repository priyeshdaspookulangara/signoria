<?php
/**
 * Test script for Sponsor Change / Add and Commission Clearing & Recalculation
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/bfs_engine.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = getDatabaseConnection();

echo "Starting Sponsor Change and Commission Recalculation Tests...\n";

// 1. Setup test users
$pdo->exec("DELETE FROM transactions WHERE details LIKE '%test_member_%' OR details LIKE '%test_sp_%'");
$pdo->exec("DELETE FROM matrix_nodes WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'test_member_%' OR username LIKE 'test_sp_%')");
$pdo->exec("DELETE FROM users WHERE username LIKE 'test_member_%' OR username LIKE 'test_sp_%'");

// Create Sponsor A (id sp1), Sponsor B (id sp2), and Target Member M
$passHash = password_hash('password123', PASSWORD_BCRYPT);
$pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES ('test_sp_a', 'spa@example.com', :p, 'member', 'active')")->execute([':p' => $passHash]);
$spAId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES ('test_sp_b', 'spb@example.com', :p, 'member', 'active')")->execute([':p' => $passHash]);
$spBId = (int)$pdo->lastInsertId();

$pdo->prepare("INSERT INTO users (username, sponsor_id, email, password, role, status) VALUES ('test_member_m', :sp, 'm@example.com', :p, 'member', 'active')")->execute([':sp' => $spAId, ':p' => $passHash]);
$memberId = (int)$pdo->lastInsertId();

echo "Created test users: spA (#$spAId), spB (#$spBId), member (#$memberId)\n";

// Place member into Package 1 (50 USDT) and Package 2 (300 USDT)
$node1 = BfsMatrixEngine::placeNode($pdo, $memberId, 'package_1');
$node2 = BfsMatrixEngine::placeNode($pdo, $memberId, 'package_2');

// Initial commission for Sponsor A (10% of Package 1 = 5 USDT, 10% of Package 2 = 30 USDT -> Total 35 USDT)
$tx1 = $pdo->prepare("INSERT INTO transactions (user_id, type, amount_usdt, status, details) VALUES (:uid, 'referral_income', 5.00, 'completed', 'Direct Referral Income (10%) from new member @test_member_m (package_1)')");
$tx1->execute([':uid' => $spAId]);

$tx2 = $pdo->prepare("INSERT INTO transactions (user_id, type, amount_usdt, status, details) VALUES (:uid, 'referral_income', 30.00, 'completed', 'Direct Referral Income (10%) from new member @test_member_m (package_2)')");
$tx2->execute([':uid' => $spAId]);

// Verify initial commission for spA
$chkSpa = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE user_id = {$spAId} AND type = 'referral_income'")->fetchColumn();
assert($chkSpa == 35.00, "Initial spA commission should be 35.00, got: $chkSpa");
echo "PASS: Initial spA commission is 35.00 USDT\n";

// 2. Change sponsor from spA to spB
$result = BfsMatrixEngine::changeMemberSponsor($pdo, $memberId, $spBId, 1, 0.10);
assert($result['success'] === true, "changeMemberSponsor should return true");
assert($result['cleared_commissions_count'] == 2, "Should have cleared 2 previous transactions");
assert($result['new_commissions_count'] == 2, "Should have generated 2 new commission records");
assert($result['new_commissions_usdt'] == 35.00, "Should have generated 35.00 USDT in new commission");

// Verify spA commission is now cleared
$chkSpaAfter = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE user_id = {$spAId} AND type = 'referral_income'")->fetchColumn();
assert($chkSpaAfter == 0.00, "spA commission should now be cleared (0.00), got: $chkSpaAfter");
echo "PASS: spA commission was successfully cleared (0.00 USDT)\n";

// Verify spB commission is now generated
$chkSpb = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE user_id = {$spBId} AND type = 'referral_income'")->fetchColumn();
assert($chkSpb == 35.00, "spB commission should now be 35.00, got: $chkSpb");
echo "PASS: spB commission was successfully generated (35.00 USDT)\n";

// Verify member sponsor_id in users table
$curSp = (int)$pdo->query("SELECT sponsor_id FROM users WHERE id = {$memberId}")->fetchColumn();
assert($curSp === $spBId, "Member sponsor_id should be spB");
echo "PASS: Member sponsor_id updated to spB\n";

// 3. Remove sponsor (set to null / System Root)
$resultNull = BfsMatrixEngine::changeMemberSponsor($pdo, $memberId, null, 1);
assert($resultNull['success'] === true);
assert($resultNull['cleared_commissions_count'] == 2, "Should have cleared 2 previous transactions from spB");
assert($resultNull['new_commissions_count'] == 0, "No new commissions should be generated when sponsor is null");

$chkSpbAfter = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE user_id = {$spBId} AND type = 'referral_income'")->fetchColumn();
assert($chkSpbAfter == 0.00, "spB commission should now be cleared (0.00), got: $chkSpbAfter");

$curSpNull = $pdo->query("SELECT sponsor_id FROM users WHERE id = {$memberId}")->fetchColumn();
assert($curSpNull === null || $curSpNull === false, "Member sponsor_id should be NULL");
echo "PASS: Sponsor removed to root; existing commission cleared\n";

// 4. Add sponsor back (adding sponsor to member without sponsor)
$resultAdd = BfsMatrixEngine::changeMemberSponsor($pdo, $memberId, $spAId, 1, 0.10);
assert($resultAdd['new_commissions_count'] == 2);
assert($resultAdd['new_commissions_usdt'] == 35.00);

$chkSpaReadded = (float)$pdo->query("SELECT COALESCE(SUM(amount_usdt), 0) FROM transactions WHERE user_id = {$spAId} AND type = 'referral_income'")->fetchColumn();
assert($chkSpaReadded == 35.00, "spA commission should now be 35.00, got: $chkSpaReadded");
echo "PASS: Sponsor re-added; new commission calculated and credited\n";

// 5. Test validation: Self-sponsorship prevention
try {
    BfsMatrixEngine::changeMemberSponsor($pdo, $memberId, $memberId);
    assert(false, "Should have thrown exception on self-sponsorship");
} catch (InvalidArgumentException $e) {
    echo "PASS: Correctly rejected self-sponsorship: " . $e->getMessage() . "\n";
}

// 6. Test validation: Cycle prevention (spA sponsor of member -> set member as sponsor of spA)
try {
    BfsMatrixEngine::changeMemberSponsor($pdo, $spAId, $memberId);
    assert(false, "Should have thrown exception on circular sponsorship");
} catch (InvalidArgumentException $e) {
    echo "PASS: Correctly rejected circular sponsorship: " . $e->getMessage() . "\n";
}

// Clean up test data
$pdo->exec("DELETE FROM transactions WHERE details LIKE '%test_member_%' OR details LIKE '%test_sp_%'");
$pdo->exec("DELETE FROM matrix_nodes WHERE user_id IN (SELECT id FROM users WHERE username LIKE 'test_member_%' OR username LIKE 'test_sp_%')");
$pdo->exec("DELETE FROM users WHERE username LIKE 'test_member_%' OR username LIKE 'test_sp_%'");

echo "\nALL TESTS PASSED SUCCESSFULLY!\n";
