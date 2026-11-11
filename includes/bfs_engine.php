<?php
/**
 * 3x Matrix Breadth-First Search (BFS) Auto-Spillover & Rebirth Engine
 * Operating strictly with USDT (Tether)
 *
 * Matrix Pools:
 * - Package 1 (6 Levels): Entry 100 USDT, 60% upline distribution, automated rebirths at Levels 4, 5, 6
 * - Package 2 (7 Levels): Entry 300 USDT, 60% upline distribution, automated rebirths at Levels 4, 5, 6, 7
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

class BfsMatrixEngine {

    public const MATRIX_WIDTH = 3; // 3x Matrix

    public const PACKAGE_CONFIG = [
        'package_1' => [
            'name'           => 'Package I (Standard 6-Depth Pool)',
            'max_levels'     => 6,
            'entry_amount'   => 50.00,
            'rebirth_fee'    => 10.00,
            'rebirth_levels' => [4, 5, 6],
            'distribution_pct' => 0.60,
            // Effective commission per head (60% of potential base rate [1.5, 2, 4, 6, 8, 10])
            'effective_level_payouts' => [
                1 => 0.90,  // Level 1: 3 * 0.90 = 2.70 USDT
                2 => 1.20,  // Level 2: 9 * 1.20 = 10.80 USDT
                3 => 2.40,  // Level 3: 27 * 2.40 = 64.80 USDT
                4 => 3.60,  // Level 4: 81 * 3.60 = 291.60 USDT
                5 => 4.80,  // Level 5: 243 * 4.80 = 1,166.40 USDT
                6 => 6.00,  // Level 6: 729 * 6.00 = 4,374.00 USDT
            ],
        ],
        'package_2' => [
            'name'           => 'Package II (Deep 7-Depth Parallel Pool)',
            'max_levels'     => 7,
            'entry_amount'   => 300.00,
            'rebirth_fee'    => 50.00,
            'rebirth_levels' => [4, 5, 6, 7],
            'distribution_pct' => 0.60,
            // Effective commission per head (60% of potential base rate [10, 20, 30, 40, 50, 60, 70])
            'effective_level_payouts' => [
                1 => 6.00,   // Level 1: 3 * 6.00 = 18.00 USDT
                2 => 12.00,  // Level 2: 9 * 12.00 = 108.00 USDT
                3 => 18.00,  // Level 3: 27 * 18.00 = 486.00 USDT
                4 => 24.00,  // Level 4: 81 * 24.00 = 1,944.00 USDT
                5 => 30.00,  // Level 5: 243 * 30.00 = 7,290.00 USDT
                6 => 36.00,  // Level 6: 729 * 36.00 = 26,244.00 USDT
                7 => 42.00,  // Level 7: 2187 * 42.00 = 91,854.00 USDT
            ],
        ],
    ];

    /**
     * Get package configuration
     */
    public static function getConfig(string $packageType): array {
        if (!isset(self::PACKAGE_CONFIG[$packageType])) {
            throw new InvalidArgumentException("Invalid package type: {$packageType}");
        }
        return self::PACKAGE_CONFIG[$packageType];
    }

    /**
     * Calculate level from 1-based BFS position_index in a 3-ary tree
     * Level 1: 1 (nodes 1)
     * Level 2: 3 (nodes 2 to 4)
     * Level 3: 9 (nodes 5 to 13)
     * Level L: 3^(L-1) nodes. Sum for L levels: (3^L - 1) / 2
     */
    public static function getLevelFromPosition(int $pos): int {
        if ($pos <= 1) {
            return 1;
        }
        $level = 1;
        $cumulative = 1;
        while ($cumulative < $pos) {
            $level++;
            $cumulative += (int)pow(self::MATRIX_WIDTH, $level - 1);
        }
        return $level;
    }

    /**
     * Calculate 1-based parent position in a standard 3-ary complete tree
     * For 1-based index pos >= 2: parent_pos = floor((pos - 2) / 3) + 1
     */
    public static function getParentPosition(int $pos): ?int {
        if ($pos <= 1) {
            return null;
        }
        return (int)floor(($pos - 2) / self::MATRIX_WIDTH) + 1;
    }

    /**
     * Find next available BFS position in the global pool for a package.
     * In a pure BFS 3-ary auto-spillover queue, the next position is:
     * max(position_index) + 1, filling strictly left-to-right, level by level.
     */
    public static function getNextBfsPosition(PDO $pdo, string $packageType): int {
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(position_index), 0) + 1 AS next_pos
            FROM matrix_nodes
            WHERE package_type = :pkg
        ");
        $stmt->execute([':pkg' => $packageType]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Place user or rebirth slot into the 3x BFS Matrix
     *
     * @param int $userId Target user ID
     * @param string $packageType 'package_1' or 'package_2'
     * @param bool $isRebirth Whether this is an automated rebirth booster slot
     * @param int|null $sourceNodeId Triggering node ID if this is a rebirth
     * @return array Created node details
     */
    public static function placeNode(PDO $pdo, int $userId, string $packageType, bool $isRebirth = false, ?int $sourceNodeId = null): array {
        $config = self::getConfig($packageType);

        $nextPos = self::getNextBfsPosition($pdo, $packageType);
        $level = self::getLevelFromPosition($nextPos);

        $parentNodeId = null;
        if ($nextPos > 1) {
            $parentPos = self::getParentPosition($nextPos);
            // Locate the parent node record in this package
            $stmt = $pdo->prepare("
                SELECT id, user_id FROM matrix_nodes
                WHERE package_type = :pkg AND position_index = :parent_pos
                LIMIT 1
            ");
            $stmt->execute([
                ':pkg'        => $packageType,
                ':parent_pos' => $parentPos,
            ]);
            $parentRow = $stmt->fetch();
            if ($parentRow) {
                $parentNodeId = (int)$parentRow['id'];
            }
        }

        // Insert new matrix node
        $stmt = $pdo->prepare("
            INSERT INTO matrix_nodes
            (user_id, package_type, level, position_index, parent_node_id, status, is_rebirth, rebirth_source_node_id, created_at)
            VALUES
            (:uid, :pkg, :lvl, :pos, :parent_id, 'active', :is_rebirth, :source_id, NOW())
        ");
        $stmt->execute([
            ':uid'        => $userId,
            ':pkg'        => $packageType,
            ':lvl'        => $level,
            ':pos'        => $nextPos,
            ':parent_id'  => $parentNodeId,
            ':is_rebirth' => $isRebirth ? 1 : 0,
            ':source_id'  => $sourceNodeId,
        ]);

        $newNodeId = (int)$pdo->lastInsertId();

        $nodeData = [
            'id'             => $newNodeId,
            'user_id'        => $userId,
            'package_type'   => $packageType,
            'level'          => $level,
            'position_index' => $nextPos,
            'parent_node_id' => $parentNodeId,
            'is_rebirth'     => $isRebirth,
        ];

        // 1. Process 60% Matrix distribution to eligible uplines
        self::distributeMatrixRewards($pdo, $nodeData);

        // 2. Check if this placement or upline reached a rebirth milestone
        self::checkAndTriggerRebirths($pdo, $nodeData);

        // 3. Log activity
        $desc = $isRebirth
            ? "Rebirth booster slot activated at Level {$level} (Pos #{$nextPos}) in {$packageType}"
            : "User placed at Level {$level} (Pos #{$nextPos}) in {$packageType}";
        logActivity($userId, $desc);

        return $nodeData;
    }

    /**
     * Distribute matrix rewards to direct and indirect uplines in the matrix tree.
     * Each qualified upline at relative depth receives their exact Effective Commission per Head
     * (60% of potential total earnings per downline node as published in the business plan).
     */
    public static function distributeMatrixRewards(PDO $pdo, array $node): void {
        $config = self::getConfig($node['package_type']);
        $maxLevels = $config['max_levels'];
        $payoutRates = $config['effective_level_payouts'] ?? [];

        // Traverse up the tree to collect upline parent nodes
        $uplines = [];
        $currentParentId = $node['parent_node_id'];
        $depthCount = 0;

        while ($currentParentId !== null && $depthCount < $maxLevels) {
            $stmt = $pdo->prepare("
                SELECT id, user_id, parent_node_id, level, status
                FROM matrix_nodes
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $currentParentId]);
            $parentNode = $stmt->fetch();

            if (!$parentNode) {
                break;
            }

            $uplines[] = $parentNode;
            $currentParentId = $parentNode['parent_node_id'];
            $depthCount++;
        }

        if (empty($uplines)) {
            // No uplines (root node placed) - pool remains in company/admin reserve
            return;
        }

        foreach ($uplines as $idx => $upline) {
            $relativeDepth = $idx + 1;
            // Get the effective commission per head for this downline depth
            $payoutAmount = (float)($payoutRates[$relativeDepth] ?? 0.0);

            if ($payoutAmount > 0) {
                $txStmt = $pdo->prepare("
                    INSERT INTO transactions
                    (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                    VALUES
                    (:uid, 'matrix_payout', :amt, NULL, 'completed', :details, NOW())
                ");
                $txStmt->execute([
                    ':uid'     => (int)$upline['user_id'],
                    ':amt'     => $payoutAmount,
                    ':details' => "Matrix Level {$relativeDepth} payout bonus from Node #{$node['id']} in {$node['package_type']}",
                ]);
            }
        }
    }

    /**
     * Check if nodes qualified for automated rebirth mechanics.
     * When a node's level is in rebirth_levels, or when a node's subtree fills,
     * a rebirth fee is deducted and a new booster slot is queued into BFS.
     */
    public static function checkAndTriggerRebirths(PDO $pdo, array $node): void {
        // Booster / rebirth slots should not trigger secondary rebirth cascades
        if (!empty($node['is_rebirth'])) {
            return;
        }

        $config = self::getConfig($node['package_type']);
        $rebirthLevels = $config['rebirth_levels'];

        // Check if newly placed primary node reached a designated rebirth trigger level
        // and hasn't already spawned a rebirth booster slot
        if (in_array($node['level'], $rebirthLevels, true)) {
            // Check if user has sufficient funds or if rebirth is auto-debited
            $userId = (int)$node['user_id'];

            // Check if this specific node already triggered a rebirth
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM matrix_nodes
                WHERE rebirth_source_node_id = :node_id
            ");
            $stmt->execute([':node_id' => $node['id']]);
            $alreadyRebirthed = (int)$stmt->fetchColumn() > 0;

            if (!$alreadyRebirthed) {
                $rebirthFee = $config['rebirth_fee'];

                // Deduct rebirth fee
                $txStmt = $pdo->prepare("
                    INSERT INTO transactions
                    (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                    VALUES
                    (:uid, 'rebirth_fee', :fee, NULL, 'completed', :details, NOW())
                ");
                $txStmt->execute([
                    ':uid'     => $userId,
                    ':fee'     => $rebirthFee,
                    ':details' => "Automated rebirth slot fee for reaching Level {$node['level']} in {$node['package_type']}",
                ]);

                // Update node status
                $upStmt = $pdo->prepare("UPDATE matrix_nodes SET status = 'rebirthed' WHERE id = :id");
                $upStmt->execute([':id' => $node['id']]);

                // Spawn new booster slot into BFS queue
                self::placeNode($pdo, $userId, $node['package_type'], true, $node['id']);
            }
        }
    }

    /**
     * Retrieve visual tree structure for a user in a given package.
     * Returns the user's primary/active node and their direct downstream children (up to 3 levels deep).
     */
    public static function getUserMatrixTree(PDO $pdo, int $userId, string $packageType): ?array {
        // Find user's earliest active node in this package
        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.email, u.usdt_wallet
            FROM matrix_nodes m
            JOIN users u ON m.user_id = u.id
            WHERE m.user_id = :uid AND m.package_type = :pkg
            ORDER BY m.id ASC
            LIMIT 1
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':pkg' => $packageType,
        ]);
        $rootNode = $stmt->fetch();

        if (!$rootNode) {
            return null;
        }

        // Fetch direct 1st level children
        $rootNode['children'] = self::getNodeChildren($pdo, (int)$rootNode['id'], 1, 2);

        // Calculate total team size under this node
        $rootNode['team_count'] = self::countDescendants($pdo, (int)$rootNode['id']);

        return $rootNode;
    }

    /**
     * Recursively fetch children nodes up to target depth
     */
    private static function getNodeChildren(PDO $pdo, int $parentNodeId, int $currentDepth, int $maxDepth): array {
        if ($currentDepth > $maxDepth) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT m.*, u.username, u.email, u.usdt_wallet
            FROM matrix_nodes m
            JOIN users u ON m.user_id = u.id
            WHERE m.parent_node_id = :pid
            ORDER BY m.position_index ASC
        ");
        $stmt->execute([':pid' => $parentNodeId]);
        $children = $stmt->fetchAll();

        foreach ($children as &$child) {
            $child['children'] = self::getNodeChildren($pdo, (int)$child['id'], $currentDepth + 1, $maxDepth);
        }

        return $children;
    }

    /**
     * Count total descendants in matrix tree for a node
     */
    public static function countDescendants(PDO $pdo, int $nodeId): int {
        $count = 0;
        $queue = [$nodeId];

        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $stmt = $pdo->prepare("SELECT id FROM matrix_nodes WHERE parent_node_id = :pid");
            $stmt->execute([':pid' => $currentId]);
            $childIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $count += count($childIds);
            foreach ($childIds as $cid) {
                $queue[] = (int)$cid;
            }
        }

        return $count;
    }

    /**
     * Get global matrix statistics
     */
    public static function getGlobalMatrixStats(PDO $pdo): array {
        $stats = [];
        foreach (['package_1', 'package_2'] as $pkg) {
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_nodes,
                    COALESCE(MAX(level), 1) AS max_level_reached,
                    SUM(CASE WHEN is_rebirth = 1 THEN 1 ELSE 0 END) AS rebirth_count,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_nodes
                FROM matrix_nodes
                WHERE package_type = :pkg
            ");
            $stmt->execute([':pkg' => $pkg]);
            $stats[$pkg] = $stmt->fetch();
        }
        return $stats;
    }

    /**
     * Retroactively recalculate all matrix payout commissions for placed members.
     * Deletes previous 'matrix_payout' transactions and re-evaluates upline earnings
     * for all placed matrix nodes in ascending order (chronological by node ID),
     * applying the 60% effective per-head payout schedule.
     */
    public static function recalculateAllMatrixPayouts(PDO $pdo): array {
        $pdo->beginTransaction();
        try {
            // 1. Delete all existing matrix_payout transactions
            $delStmt = $pdo->prepare("DELETE FROM transactions WHERE type = 'matrix_payout'");
            $delStmt->execute();
            $deletedCount = $delStmt->rowCount();

            // 2. Fetch all placed matrix nodes in insertion order
            $stmt = $pdo->query("SELECT * FROM matrix_nodes ORDER BY id ASC");
            $nodes = $stmt->fetchAll();

            $payoutCount = 0;
            $totalDistributed = 0.0;

            foreach ($nodes as $node) {
                // If the node has a parent, distribute matrix rewards to its uplines
                if ($node['parent_node_id'] !== null) {
                    $config = self::getConfig($node['package_type']);
                    $maxLevels = $config['max_levels'];
                    $payoutRates = $config['effective_level_payouts'] ?? [];

                    $uplines = [];
                    $currentParentId = $node['parent_node_id'];
                    $depthCount = 0;

                    while ($currentParentId !== null && $depthCount < $maxLevels) {
                        $pStmt = $pdo->prepare("
                            SELECT id, user_id, parent_node_id, level, status
                            FROM matrix_nodes
                            WHERE id = :id
                            LIMIT 1
                        ");
                        $pStmt->execute([':id' => $currentParentId]);
                        $parentNode = $pStmt->fetch();

                        if (!$parentNode) {
                            break;
                        }

                        $uplines[] = $parentNode;
                        $currentParentId = $parentNode['parent_node_id'];
                        $depthCount++;
                    }

                    foreach ($uplines as $idx => $upline) {
                        $relativeDepth = $idx + 1;
                        $payoutAmount = (float)($payoutRates[$relativeDepth] ?? 0.0);

                        if ($payoutAmount > 0) {
                            $txStmt = $pdo->prepare("
                                INSERT INTO transactions
                                (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                                VALUES
                                (:uid, 'matrix_payout', :amt, NULL, 'completed', :details, :created)
                            ");
                            $createdAt = !empty($node['created_at']) ? $node['created_at'] : date('Y-m-d H:i:s');
                            $txStmt->execute([
                                ':uid'     => (int)$upline['user_id'],
                                ':amt'     => $payoutAmount,
                                ':details' => "Matrix Level {$relativeDepth} payout bonus from Node #{$node['id']} in {$node['package_type']}",
                                ':created' => $createdAt,
                            ]);

                            $payoutCount++;
                            $totalDistributed += $payoutAmount;
                        }
                    }
                }
            }

            $pdo->commit();

            return [
                'success'           => true,
                'deleted_previous'  => $deletedCount,
                'nodes_evaluated'   => count($nodes),
                'payouts_generated' => $payoutCount,
                'total_distributed' => round($totalDistributed, 2),
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if assigning $sponsorId to $userId would cause a cycle in the sponsorship hierarchy
     */
    public static function wouldCreateSponsorCycle(PDO $pdo, int $userId, int $sponsorId): bool {
        if ($userId === $sponsorId) {
            return true;
        }

        $currentId = $sponsorId;
        $visited = [$userId];

        while ($currentId !== null && $currentId > 0) {
            if (in_array($currentId, $visited, true)) {
                return true;
            }
            $visited[] = $currentId;

            $stmt = $pdo->prepare("SELECT sponsor_id FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $currentId]);
            $currentId = $stmt->fetchColumn();
            if ($currentId === false || $currentId === null) {
                break;
            }
            $currentId = (int)$currentId;
        }

        return false;
    }

    /**
     * Change or add sponsor for a member.
     * Clears existing sponsor commission/calculation data originating from this member,
     * updates sponsor_id, and calculates and credits new commissions for the new sponsor.
     *
     * @param PDO $pdo Database connection
     * @param int $targetUserId The member whose sponsor is changing
     * @param int|null $newSponsorId The new sponsor ID (or null for none/system root)
     * @param int|null $adminActorId Admin executing the change for logging
     * @param float $referralBonusPct Referral commission percent (default 10%)
     * @return array Result summary
     */
    public static function changeMemberSponsor(
        PDO $pdo,
        int $targetUserId,
        ?int $newSponsorId,
        ?int $adminActorId = null,
        float $referralBonusPct = 0.10
    ): array {
        // Fetch target user
        $uStmt = $pdo->prepare("SELECT id, username, sponsor_id FROM users WHERE id = :id LIMIT 1");
        $uStmt->execute([':id' => $targetUserId]);
        $targetUser = $uStmt->fetch();

        if (!$targetUser) {
            throw new InvalidArgumentException("User #{$targetUserId} not found.");
        }

        $oldSponsorId = !empty($targetUser['sponsor_id']) ? (int)$targetUser['sponsor_id'] : null;

        // Normalizing new sponsor ID (null if <= 0)
        if ($newSponsorId !== null && $newSponsorId <= 0) {
            $newSponsorId = null;
        }

        // Check self-sponsorship
        if ($newSponsorId !== null && $newSponsorId === $targetUserId) {
            throw new InvalidArgumentException("A user cannot be their own sponsor.");
        }

        // Validate sponsor exists if provided
        $newSponsorUsername = null;
        if ($newSponsorId !== null) {
            $spStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = :id LIMIT 1");
            $spStmt->execute([':id' => $newSponsorId]);
            $newSponsor = $spStmt->fetch();
            if (!$newSponsor) {
                throw new InvalidArgumentException("Selected sponsor (ID #{$newSponsorId}) does not exist.");
            }
            $newSponsorUsername = $newSponsor['username'];

            // Prevent circular sponsor loops
            if (self::wouldCreateSponsorCycle($pdo, $targetUserId, $newSponsorId)) {
                throw new InvalidArgumentException("Cannot assign @{$newSponsorUsername} as sponsor because it creates a circular sponsorship chain.");
            }
        }

        // Fetch old sponsor username if existed
        $oldSponsorUsername = 'None (Root)';
        if ($oldSponsorId !== null) {
            $oldSpStmt = $pdo->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
            $oldSpStmt->execute([':id' => $oldSponsorId]);
            $oldSpRow = $oldSpStmt->fetchColumn();
            if ($oldSpRow) {
                $oldSponsorUsername = $oldSpRow;
            }
        }

        $pdo->beginTransaction();
        try {
            // 1. Clear existing commission / referral data originating from this user
            // Search pattern for transactions generated from this user
            $userPattern = "%@{$targetUser['username']}%";
            $delTx = $pdo->prepare("
                DELETE FROM transactions
                WHERE type = 'referral_income'
                  AND (details LIKE :pattern OR details LIKE :pattern_id)
            ");
            $delTx->execute([
                ':pattern'    => $userPattern,
                ':pattern_id' => "%from user #{$targetUserId}%",
            ]);
            $clearedTxCount = $delTx->rowCount();

            // 2. Update user's sponsor_id
            $upUser = $pdo->prepare("UPDATE users SET sponsor_id = :sp WHERE id = :id");
            $upUser->execute([
                ':sp' => $newSponsorId,
                ':id' => $targetUserId,
            ]);

            // 3. Generate new commission data if new sponsor is assigned
            $newCommissionsGenerated = 0;
            $totalNewCommissionUsdt = 0.00;
            $newCommissionsDetails = [];

            if ($newSponsorId !== null && $newSponsorId !== $targetUserId) {
                // Find all active primary matrix nodes belonging to this target user to calculate referral earnings
                $nodeStmt = $pdo->prepare("
                    SELECT package_type, is_rebirth
                    FROM matrix_nodes
                    WHERE user_id = :uid AND is_rebirth = 0
                    GROUP BY package_type
                ");
                $nodeStmt->execute([':uid' => $targetUserId]);
                $userPackages = $nodeStmt->fetchAll();

                // If user doesn't have matrix nodes but has completed package upgrades or activations, check those
                if (empty($userPackages)) {
                    // Check transactions or approved USDT payments
                    $pmtStmt = $pdo->prepare("
                        SELECT k.matrix_type
                        FROM usdt_payments p
                        JOIN packages k ON p.package_id = k.id
                        WHERE p.user_id = :uid AND p.status = 'approved'
                        GROUP BY k.matrix_type
                    ");
                    $pmtStmt->execute([':uid' => $targetUserId]);
                    $userPackages = $pmtStmt->fetchAll();
                }

                // If user has packages, generate direct referral commission for each active package
                $pctDisplay = round($referralBonusPct * 100, 1);
                foreach ($userPackages as $pkgRow) {
                    $pkgType = $pkgRow['package_type'] ?? $pkgRow['matrix_type'] ?? 'package_1';
                    $config = self::getConfig($pkgType);
                    $packageCost = (float)$config['entry_amount'];
                    $commissionAmount = round($packageCost * $referralBonusPct, 2);

                    if ($commissionAmount > 0) {
                        $txRef = $pdo->prepare("
                            INSERT INTO transactions (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                            VALUES (:uid, 'referral_income', :amt, NULL, 'completed', :details, NOW())
                        ");
                        $details = "Direct Referral Income ({$pctDisplay}%) from member @{$targetUser['username']} ({$pkgType})";
                        $txRef->execute([
                            ':uid'     => $newSponsorId,
                            ':amt'     => $commissionAmount,
                            ':details' => $details,
                        ]);

                        $newCommissionsGenerated++;
                        $totalNewCommissionUsdt += $commissionAmount;
                        $newCommissionsDetails[] = [
                            'package' => $pkgType,
                            'amount'  => $commissionAmount,
                        ];
                    }
                }
            }

            // 4. Log activities
            $sponsorLogText = $newSponsorId !== null ? "@{$newSponsorUsername} (#{$newSponsorId})" : "None (Root)";
            $logMsg = "Admin updated sponsor for @{$targetUser['username']} (ID #{$targetUserId}) from '{$oldSponsorUsername}' to '{$sponsorLogText}'. Cleared {$clearedTxCount} existing commission records; generated {$newCommissionsGenerated} new commission records totaling " . formatUsdt($totalNewCommissionUsdt) . " USDT.";

            if ($adminActorId !== null) {
                logActivity($adminActorId, $logMsg);
            }
            logActivity($targetUserId, "Sponsor updated to '{$sponsorLogText}'. Referral calculations refreshed.");

            $pdo->commit();

            return [
                'success'                  => true,
                'target_user_id'           => $targetUserId,
                'target_username'          => $targetUser['username'],
                'old_sponsor_id'           => $oldSponsorId,
                'old_sponsor_username'     => $oldSponsorUsername,
                'new_sponsor_id'           => $newSponsorId,
                'new_sponsor_username'     => $newSponsorUsername,
                'cleared_commissions_count'=> $clearedTxCount,
                'new_commissions_count'    => $newCommissionsGenerated,
                'new_commissions_usdt'     => round($totalNewCommissionUsdt, 2),
                'details'                  => $newCommissionsDetails,
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
