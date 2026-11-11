<?php
/**
 * Member Genealogy / Matrix Tree Visualizer
 * Shows 3 levels of the 3x BFS Matrix tree by default.
 * Each node is interactive and clickable, drilling down into that user/node's sub-genealogy.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bfs_engine.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$currentUser = getCurrentUser();
$currentUserId = (int)$currentUser['id'];
$pdo = getDatabaseConnection();

// Selected package (default package_1)
$packageType = trim($_GET['package'] ?? 'package_1');
if (!in_array($packageType, ['package_1', 'package_2'], true)) {
    $packageType = 'package_1';
}

$pkgConfig = BfsMatrixEngine::getConfig($packageType);

// Fetch logged in user's root node in this package
$userRootStmt = $pdo->prepare("
    SELECT id, position_index, level, status
    FROM matrix_nodes
    WHERE user_id = :uid AND package_type = :pkg
    ORDER BY id ASC
    LIMIT 1
");
$userRootStmt->execute([':uid' => $currentUserId, ':pkg' => $packageType]);
$userRootNode = $userRootStmt->fetch();

// Target node ID to display (if specified)
$targetNodeId = isset($_GET['node_id']) ? (int)$_GET['node_id'] : 0;

$tree = null;
if ($targetNodeId > 0) {
    // Load requested node's tree up to 4 levels so Level 3 nodes can expand inline
    $tree = BfsMatrixEngine::getNodeMatrixTree($pdo, $targetNodeId, 4);
} elseif ($userRootNode) {
    // Default to current user's root node tree up to 4 levels
    $tree = BfsMatrixEngine::getNodeMatrixTree($pdo, (int)$userRootNode['id'], 4);
}

// Build parent breadcrumb if viewing a descendant node
$parentNodes = [];
if ($tree && $tree['parent_node_id']) {
    $currPid = $tree['parent_node_id'];
    $loopGuard = 0;
    while ($currPid !== null && $loopGuard < 10) {
        $pStmt = $pdo->prepare("
            SELECT m.id, m.parent_node_id, m.position_index, u.username
            FROM matrix_nodes m
            JOIN users u ON m.user_id = u.id
            WHERE m.id = :id
            LIMIT 1
        ");
        $pStmt->execute([':id' => $currPid]);
        $pNode = $pStmt->fetch();
        if (!$pNode) {
            break;
        }
        $parentNodes[] = $pNode;
        $currPid = $pNode['parent_node_id'];
        $loopGuard++;
    }
    $parentNodes = array_reverse($parentNodes);
}

$pageTitle = 'Matrix Genealogy Tree';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header with Package Switcher & Reset -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between pb-6 border-b border-slate-800 gap-4">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-teal-500 to-emerald-600 flex items-center justify-center text-white shadow-lg shadow-teal-500/20">
                    <i class="fa-solid fa-sitemap text-lg"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white flex items-center gap-2">
                        Interactive Matrix Genealogy
                    </h1>
                    <p class="text-xs text-slate-400">
                        Explore 3 downline levels. Click on any member node to drill down into their sub-tree.
                    </p>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Package Switcher Tabs -->
            <div class="inline-flex p-1 bg-slate-900 border border-slate-800 rounded-xl">
                <a href="/member/genealogy.php?package=package_1"
                   class="px-4 py-2 text-xs font-bold rounded-lg transition <?= $packageType === 'package_1' ? 'bg-emerald-600 text-white shadow-md' : 'text-slate-400 hover:text-white' ?>">
                   Package I (50 USDT)
                </a>
                <a href="/member/genealogy.php?package=package_2"
                   class="px-4 py-2 text-xs font-bold rounded-lg transition <?= $packageType === 'package_2' ? 'bg-teal-600 text-white shadow-md' : 'text-slate-400 hover:text-white' ?>">
                   Package II (300 USDT)
                </a>
            </div>

            <!-- Reset to My Tree Button -->
            <?php if ($userRootNode && ($targetNodeId > 0 && $targetNodeId !== (int)$userRootNode['id'])): ?>
                <a href="/member/genealogy.php?package=<?= e($packageType) ?>"
                   class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5 shadow">
                    <i class="fa-solid fa-rotate-left text-amber-400"></i>
                    <span>Back to My Root</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Breadcrumb Path Bar -->
    <?php if (!empty($parentNodes)): ?>
        <div class="bg-slate-900/80 border border-slate-800 rounded-xl px-4 py-2.5 flex items-center gap-2 text-xs overflow-x-auto custom-scroll">
            <span class="text-slate-500 font-semibold uppercase text-[10px] tracking-wider">Upline Trail:</span>
            <?php if ($userRootNode): ?>
                <a href="/member/genealogy.php?package=<?= e($packageType) ?>&node_id=<?= (int)$userRootNode['id'] ?>"
                   class="text-emerald-400 hover:underline font-bold">
                    My Root (#<?= (int)$userRootNode['position_index'] ?>)
                </a>
                <span class="text-slate-600">/</span>
            <?php endif; ?>
            <?php foreach ($parentNodes as $p): ?>
                <?php if ($userRootNode && (int)$p['id'] === (int)$userRootNode['id']) continue; ?>
                <a href="/member/genealogy.php?package=<?= e($packageType) ?>&node_id=<?= (int)$p['id'] ?>"
                   class="text-slate-300 hover:text-white font-medium">
                    <?= e($p['username']) ?> (#<?= (int)$p['position_index'] ?>)
                </a>
                <span class="text-slate-600">/</span>
            <?php endforeach; ?>
            <span class="text-white font-bold bg-slate-800 px-2 py-0.5 rounded text-[11px]">
                <?= e($tree['username'] ?? '') ?> (Current View)
            </span>
        </div>
    <?php endif; ?>

    <?php if (!$tree): ?>
        <!-- No Active Node in this Package -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center shadow-xl">
            <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-slate-800 text-slate-500 flex items-center justify-center text-3xl">
                <i class="fa-solid fa-network-wired opacity-40"></i>
            </div>
            <h3 class="text-lg font-bold text-white mb-2">No Active Matrix Node Found</h3>
            <p class="text-xs text-slate-400 max-w-md mx-auto mb-6">
                You do not have an active node in <?= e($pkgConfig['name']) ?> yet. Activate your position to unlock your 3x BFS tree genealogy.
            </p>
            <a href="/member/usdt_activate.php" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold transition shadow-lg shadow-emerald-600/30">
                Activate Package
            </a>
        </div>
    <?php else: ?>
        <!-- Current Root Node Info Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-lg flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white font-black text-lg shadow-md">
                    <?= strtoupper(substr($tree['username'], 0, 2)) ?>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-black text-white text-base"><?= e($tree['username']) ?></span>
                        <?php if ((int)$tree['user_id'] === $currentUserId): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">You</span>
                        <?php endif; ?>
                        <?php if ($tree['is_rebirth']): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/20 text-purple-300 border border-purple-500/30">Rebirth Booster</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5 font-mono">
                        Global Position: <strong class="text-emerald-400">#<?= (int)$tree['position_index'] ?></strong> &bull;
                        Tree Level: <strong class="text-white"><?= (int)$tree['level'] ?></strong> &bull;
                        Subtree Total: <strong class="text-teal-400"><?= (int)$tree['team_count'] ?> members</strong>
                    </div>
                </div>
            </div>

            <div class="text-xs text-slate-400 flex items-center gap-2">
                <i class="fa-solid fa-hand-pointer text-amber-400 text-sm"></i>
                <span>Click any downline node below to drill into their tree</span>
            </div>
        </div>

        <!-- 3-Level Visual Matrix Tree Board Matching User UI -->
        <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-8 shadow-2xl">
            <div class="overflow-x-auto pb-12 custom-scroll">
                <div class="min-w-[960px] flex flex-col items-center select-none">

                    <!-- ================= Level 1: Root Head Card ================= -->
                    <div class="flex flex-col items-center">
                        <div class="px-8 py-4 rounded-2xl bg-gradient-to-b from-emerald-500 to-emerald-600 text-white shadow-xl shadow-emerald-500/30 text-center border border-emerald-300 min-w-[240px]">
                            <div class="text-[11px] uppercase tracking-wider font-extrabold text-emerald-100 mb-1">
                                CURRENT HEAD (L1)
                            </div>
                            <div class="font-black text-xl text-white tracking-wide truncate"><?= e($tree['username']) ?></div>
                            <div class="text-xs font-mono font-medium text-emerald-100 mt-1">
                                Pos #<?= (int)$tree['position_index'] ?> &bull; L<?= (int)$tree['level'] ?>
                            </div>
                        </div>
                        <!-- Primary Trunk Connector -->
                        <div class="w-0.5 h-10 bg-slate-700"></div>
                    </div>

                    <!-- ================= Level 2: 3 Child Cards ================= -->
                    <div class="w-full flex justify-center">
                        <div class="grid grid-cols-3 gap-6 w-full max-w-5xl pt-3 border-t-2 border-slate-700">
                            <?php
                            $childrenL2 = $tree['children'] ?? [];
                            for ($i = 0; $i < 3; $i++):
                                $child = $childrenL2[$i] ?? null;
                            ?>
                                <div class="flex flex-col items-center">
                                    <div class="w-0.5 h-4 bg-slate-700 -mt-3"></div>

                                    <?php if ($child): ?>
                                        <!-- Level 2 Card -->
                                        <div class="w-full p-4 rounded-2xl bg-slate-800/80 border <?= $child['is_rebirth'] ? 'border-purple-500/60 bg-purple-950/20' : 'border-slate-700/80' ?> text-center shadow-lg relative group">
                                            <div class="flex items-center justify-between text-xs mb-2">
                                                <span class="text-emerald-400 font-bold font-mono text-xs">#<?= (int)$child['position_index'] ?></span>
                                                <span class="text-[10px] px-2 py-0.5 rounded-md bg-slate-700/60 text-slate-300 font-semibold">Level 2</span>
                                            </div>
                                            <a href="/member/genealogy.php?package=<?= e($packageType) ?>&node_id=<?= (int)$child['id'] ?>"
                                               title="Click to view <?= e($child['username']) ?>'s genealogy"
                                               class="font-black text-white text-base hover:text-emerald-300 transition block truncate">
                                                <?= e($child['username']) ?>
                                            </a>
                                            <div class="text-xs text-slate-400 mt-1">
                                                Level <?= (int)$child['level'] ?> <?= $child['is_rebirth'] ? '<span class="text-purple-400 font-bold">(Rebirth)</span>' : '' ?>
                                            </div>
                                        </div>

                                        <!-- Connector Stem from Level 2 to Level 3 -->
                                        <div class="w-0.5 h-8 bg-slate-700"></div>

                                        <!-- ================= Level 3: 3 Grandchildren for each Child ================= -->
                                        <div class="grid grid-cols-3 gap-2.5 w-full pt-2 border-t-2 border-slate-700">
                                            <?php
                                            $childrenL3 = $child['children'] ?? [];
                                            for ($j = 0; $j < 3; $j++):
                                                $grandChild = $childrenL3[$j] ?? null;
                                            ?>
                                                <div class="flex flex-col items-center">
                                                    <div class="w-0.5 h-3 bg-slate-700 -mt-2"></div>
                                                    <?php if ($grandChild): ?>
                                                        <?php
                                                        $childCount = !empty($grandChild['children']) ? count($grandChild['children']) : 0;
                                                        $nodeDomId = 'node_sub_' . (int)$grandChild['id'];
                                                        // Determine alignment & bending based on column index
                                                        // $i: 0 = Left L2 subtree, 1 = Center L2 subtree, 2 = Right L2 subtree
                                                        // $j: 0 = Left L3 child, 1 = Center L3 child, 2 = Right L3 child
                                                        // Global column index: $globalCol = ($i * 3) + $j (0 to 8)
                                                        $globalCol = ($i * 3) + $j;
                                                        ?>
                                                        <!-- Level 3 Card with Single-Node Expansion -->
                                                        <div class="w-full flex flex-col items-center relative">
                                                            <div onclick="toggleSingleNode('<?= $nodeDomId ?>', this);"
                                                                 class="w-full py-2.5 px-2 rounded-xl bg-slate-800/90 hover:bg-slate-750 border <?= $grandChild['is_rebirth'] ? 'border-purple-500/50' : 'border-slate-700' ?> text-center shadow transition cursor-pointer select-none">
                                                                <div class="text-[11px] text-white font-extrabold truncate">
                                                                    <?= e($grandChild['username']) ?>
                                                                </div>
                                                                <div class="text-[9px] text-emerald-400 font-mono font-bold mt-0.5">
                                                                    #<?= (int)$grandChild['position_index'] ?>
                                                                </div>
                                                                <?php if ($childCount > 0): ?>
                                                                    <div class="mt-1.5">
                                                                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                                                                            + <?= $childCount ?>
                                                                        </span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>

                                                            <!-- Level 4 Expanded Subtree directly beneath this node (Popup style like screenshot) -->
                                                            <?php if ($childCount > 0): ?>
                                                                <?php if ($globalCol <= 1): ?>
                                                                    <!-- Left-most nodes: bend connector and shift dropdown to the right (as depicted in screenshot) -->
                                                                    <div id="<?= $nodeDomId ?>" class="single-expanded-subtree hidden flex-col absolute top-full left-1/2 z-30 pt-1 pointer-events-auto min-w-[280px]">
                                                                        <!-- Connector stem: vertical down from card center, horizontal right, then down to container -->
                                                                        <div class="flex flex-col items-start w-full">
                                                                            <div class="w-0.5 h-3 bg-slate-600"></div>
                                                                            <div class="w-24 h-0.5 bg-slate-600"></div>
                                                                            <div class="w-0.5 h-3 bg-slate-600 ml-24"></div>
                                                                        </div>

                                                                        <!-- Horizontal bracket connector -->
                                                                        <div class="w-full pt-1.5 border-t-2 border-slate-600 ml-2">
                                                                            <div class="grid grid-cols-3 gap-2 w-full bg-slate-900/95 p-2 rounded-2xl border border-slate-700 shadow-2xl backdrop-blur-md">
                                                                <?php elseif ($globalCol >= 7): ?>
                                                                    <!-- Right-most nodes: bend connector and shift dropdown to the left to prevent right edge overflow -->
                                                                    <div id="<?= $nodeDomId ?>" class="single-expanded-subtree hidden flex-col items-end absolute top-full right-1/2 z-30 pt-1 pointer-events-auto min-w-[280px]">
                                                                        <!-- Connector stem: vertical down from card center, horizontal left, then down to container -->
                                                                        <div class="flex flex-col items-end w-full">
                                                                            <div class="w-0.5 h-3 bg-slate-600"></div>
                                                                            <div class="w-24 h-0.5 bg-slate-600"></div>
                                                                            <div class="w-0.5 h-3 bg-slate-600 mr-24"></div>
                                                                        </div>

                                                                        <!-- Horizontal bracket connector -->
                                                                        <div class="w-full pt-1.5 border-t-2 border-slate-600 mr-2">
                                                                            <div class="grid grid-cols-3 gap-2 w-full bg-slate-900/95 p-2 rounded-2xl border border-slate-700 shadow-2xl backdrop-blur-md">
                                                                <?php else: ?>
                                                                    <!-- Middle nodes: centered directly below node -->
                                                                    <div id="<?= $nodeDomId ?>" class="single-expanded-subtree hidden flex-col items-center absolute top-full left-1/2 -translate-x-1/2 z-30 pt-1 pointer-events-auto min-w-[280px]">
                                                                        <!-- Direct drop down connector line -->
                                                                        <div class="w-0.5 h-6 bg-slate-600"></div>

                                                                        <!-- Horizontal bracket connector -->
                                                                        <div class="w-full pt-1.5 border-t-2 border-slate-600">
                                                                            <div class="grid grid-cols-3 gap-2 w-full bg-slate-900/95 p-2 rounded-2xl border border-slate-700 shadow-2xl backdrop-blur-md">
                                                                <?php endif; ?>
                                                                            <?php foreach ($grandChild['children'] as $greatGrandChild): ?>
                                                                                <div class="flex flex-col items-center">
                                                                                    <div class="w-0.5 h-2 bg-slate-600 -mt-2"></div>
                                                                                    <a href="/member/genealogy.php?package=<?= e($packageType) ?>&node_id=<?= (int)$greatGrandChild['id'] ?>"
                                                                                       title="Click to view <?= e($greatGrandChild['username']) ?>'s tree"
                                                                                       class="w-full py-2 px-1 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-600 text-center block shadow transition transform hover:-translate-y-0.5">
                                                                                        <div class="text-[10px] text-white font-black truncate">
                                                                                            <?= e($greatGrandChild['username']) ?>
                                                                                        </div>
                                                                                        <div class="text-[8px] text-emerald-400 font-mono font-bold mt-0.5">
                                                                                            #<?= (int)$greatGrandChild['position_index'] ?>
                                                                                        </div>
                                                                                        <?php if (!empty($greatGrandChild['children'])): ?>
                                                                                            <span class="inline-block mt-1 px-1.5 py-0.2 rounded-full text-[8px] font-bold bg-emerald-500/20 text-emerald-300">
                                                                                                + <?= count($greatGrandChild['children']) ?>
                                                                                            </span>
                                                                                        <?php endif; ?>
                                                                                    </a>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="w-full py-3 px-2 rounded-xl bg-slate-900/50 border border-dashed border-slate-800 text-center text-slate-600">
                                                            <div class="text-[9px] font-mono">Empty</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endfor; ?>
                                        </div>

                                    <?php else: ?>
                                        <!-- Empty Level 2 Slot -->
                                        <div class="w-full p-4 rounded-2xl bg-slate-900/50 border border-dashed border-slate-800 text-center text-slate-500">
                                            <i class="fa-solid fa-user-clock text-lg mb-1 opacity-30"></i>
                                            <div class="text-xs font-medium">Available Slot</div>
                                            <div class="text-[10px] text-slate-600">Waiting for Auto-Spillover</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleSingleNode(subContainerId, triggerCard) {
    const targetContainer = document.getElementById(subContainerId);
    if (!targetContainer) return;

    const isCurrentlyOpen = !targetContainer.classList.contains('hidden');

    // Close all open expanded subtrees first
    document.querySelectorAll('.single-expanded-subtree').forEach(function(el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
    });

    // Reset all trigger card active styling
    document.querySelectorAll('.l3-trigger-active').forEach(function(card) {
        card.classList.remove('l3-trigger-active', 'ring-2', 'ring-emerald-500', 'bg-slate-750');
    });

    // If it wasn't open previously, open it now (accordion behavior)
    if (!isCurrentlyOpen) {
        targetContainer.classList.remove('hidden');
        targetContainer.classList.add('flex');
        if (triggerCard) {
            triggerCard.classList.add('l3-trigger-active', 'ring-2', 'ring-emerald-500', 'bg-slate-750');
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
