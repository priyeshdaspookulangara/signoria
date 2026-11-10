<?php
/**
 * 3x Matrix Breadth-First Search (BFS) Auto-Spillover & Rebirth Engine
 * Operating strictly with USDT (Tether)
 *
 * Matrix Pools:
 * - Package 1 (6 Levels): Entry 50 USDT, 60% upline distribution, automated rebirths at Levels 4, 5, 6 (5 rebirths total: L4: 1, L5: 1, L6: 3)
 * - Package 2 (7 Levels): Entry 300 USDT, 60% upline distribution, automated rebirths at Levels 4, 5, 6, 7 (10 rebirths total: L4: 1, L5: 2, L6: 3, L7: 4)
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
            'rebirth_levels' => [
                4 => 1,
                5 => 1,
                6 => 3,
            ], // 5 rebirths total (L4: 1, L5: 1, L6: 3)
            'distribution_pct' => 0.60, // 60% of entry amount distributed to active uplines
        ],
        'package_2' => [
            'name'           => 'Package II (Deep 7-Depth Parallel Pool)',
            'max_levels'     => 7,
            'entry_amount'   => 300.00,
            'rebirth_fee'    => 50.00,
            'rebirth_levels' => [
                4 => 1,
                5 => 2,
                6 => 3,
                7 => 4,
            ], // 10 rebirths total (L4: 1, L5: 2, L6: 3, L7: 4)
            'distribution_pct' => 0.60, // 60% of entry amount distributed to active uplines
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

        // 3. Check if primary node qualified for automated Tier 2 upgrade from Level 5
        self::checkAndTriggerTier2Upgrade($pdo, $nodeData);

        // 3. Log activity
        $desc = $isRebirth
            ? "Rebirth booster slot activated at Level {$level} (Pos #{$nextPos}) in {$packageType}"
            : "User placed at Level {$level} (Pos #{$nextPos}) in {$packageType}";
        logActivity($userId, $desc);

        return $nodeData;
    }

    /**
     * Distribute 60% entry volume to direct and indirect uplines in the matrix tree.
     * The 60% is shared across active uplines up to max pool depth (6 for P1, 7 for P2).
     */
    public static function distributeMatrixRewards(PDO $pdo, array $node): void {
        $config = self::getConfig($node['package_type']);
        $totalPoolReward = $config['entry_amount'] * $config['distribution_pct']; // e.g. 100 * 0.60 = 60 USDT
        $maxLevels = $config['max_levels'];

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

        // Weighted tiered distribution across uplines:
        // Direct parent gets 40% of the reward pool, Level 2 upline gets 25%, Level 3 gets 15%, remainder divided evenly among higher depths.
        // Or if fewer uplines exist, distribute proportionally.
        $weights = [0.40, 0.25, 0.15, 0.10, 0.05, 0.03, 0.02];
        $totalWeightsUsed = 0.0;
        $payoutList = [];

        foreach ($uplines as $idx => $upline) {
            $w = $weights[$idx] ?? 0.02;
            $payoutList[] = [
                'user_id' => (int)$upline['user_id'],
                'node_id' => (int)$upline['id'],
                'depth'   => $idx + 1,
                'weight'  => $w,
            ];
            $totalWeightsUsed += $w;
        }

        foreach ($payoutList as $item) {
            // Normalize weight so 100% of the 60% reward pool is distributed to available uplines
            $normalizedPct = $item['weight'] / $totalWeightsUsed;
            $payoutAmount = round($totalPoolReward * $normalizedPct, 2);

            if ($payoutAmount > 0) {
                $txStmt = $pdo->prepare("
                    INSERT INTO transactions
                    (user_id, type, amount_usdt, tx_hash, status, details, created_at)
                    VALUES
                    (:uid, 'matrix_payout', :amt, NULL, 'completed', :details, NOW())
                ");
                $txStmt->execute([
                    ':uid'     => $item['user_id'],
                    ':amt'     => $payoutAmount,
                    ':details' => "Matrix Level {$item['depth']} payout bonus from Node #{$node['id']} in {$node['package_type']}",
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
        $nodeLevel = (int)$node['level'];

        // Check if newly placed primary node reached a designated rebirth trigger level
        if (isset($rebirthLevels[$nodeLevel])) {
            $requiredRebirthCount = (int)$rebirthLevels[$nodeLevel];
            $userId = (int)$node['user_id'];

            // Check how many rebirths this specific node has already spawned
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM matrix_nodes
                WHERE rebirth_source_node_id = :node_id
            ");
            $stmt->execute([':node_id' => $node['id']]);
            $existingRebirths = (int)$stmt->fetchColumn();

            $rebirthsToCreate = $requiredRebirthCount - $existingRebirths;

            if ($rebirthsToCreate > 0) {
                $rebirthFee = $config['rebirth_fee'];

                // Update node status to rebirthed
                $upStmt = $pdo->prepare("UPDATE matrix_nodes SET status = 'rebirthed' WHERE id = :id");
                $upStmt->execute([':id' => $node['id']]);

                for ($i = 1; $i <= $rebirthsToCreate; $i++) {
                    $rebirthIndex = $existingRebirths + $i;
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
                        ':details' => "Automated rebirth slot #{$rebirthIndex}/{$requiredRebirthCount} fee for reaching Level {$node['level']} in {$node['package_type']}",
                    ]);

                    // Spawn new booster slot into BFS queue
                    self::placeNode($pdo, $userId, $node['package_type'], true, (int)$node['id']);
                }
            }
        }
    }

    /**
     * Check if a primary Tier 1 node reached Level 5 and qualifies for automated
     * reservation and upgrade of 300 USDT into Tier 2 (package_2).
     */
    public static function checkAndTriggerTier2Upgrade(PDO $pdo, array $node): void {
        // Booster / rebirth slots should not trigger upgrades
        if (!empty($node['is_rebirth'])) {
            return;
        }

        // Only Tier 1 nodes reaching Level 5 qualify
        if ($node['package_type'] !== 'package_1' || (int)$node['level'] < 5) {
            return;
        }

        $userId = (int)$node['user_id'];

        // Check if user already has an active or rebirthed node in package_2
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM matrix_nodes
            WHERE user_id = :uid AND package_type = 'package_2'
        ");
        $stmt->execute([':uid' => $userId]);
        $alreadyInPackage2 = (int)$stmt->fetchColumn() > 0;

        if ($alreadyInPackage2) {
            return;
        }

        // Check if user already had 300 USDT reserved for package_upgrade
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM transactions
            WHERE user_id = :uid AND type = 'package_upgrade' AND amount_usdt >= 300.00
        ");
        $stmt->execute([':uid' => $userId]);
        $alreadyUpgraded = (int)$stmt->fetchColumn() > 0;

        if ($alreadyUpgraded) {
            return;
        }

        $upgradeAmount = 300.00;

        // Deduct 300 USDT upgrade charge reserved from Level 5 Tier 1 earnings
        $txStmt = $pdo->prepare("
            INSERT INTO transactions
            (user_id, type, amount_usdt, tx_hash, status, details, created_at)
            VALUES
            (:uid, 'package_upgrade', :amt, NULL, 'completed', :details, NOW())
        ");
        $txStmt->execute([
            ':uid'     => $userId,
            ':amt'     => $upgradeAmount,
            ':details' => "Automated plan upgrade reservation of 300 USDT to Tier 2 (Package II) from Level 5 of Tier 1",
        ]);

        // Place user into Tier 2 (package_2)
        self::placeNode($pdo, $userId, 'package_2');
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
}
