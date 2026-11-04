<?php
/**
 * Public Landing Page
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/bfs_engine.php';

$pdo = getDatabaseConnection();
$stats = BfsMatrixEngine::getGlobalMatrixStats($pdo);

$pageTitle = 'Home - 3x Matrix Auto-Spillover Platform';
require_once __DIR__ . '/includes/header.php';
?>

<div class="relative overflow-hidden pt-12 pb-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Hero Section -->
        <div class="text-center max-w-3xl mx-auto">
            <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold uppercase tracking-wider mb-6">
                <i class="fa-solid fa-bolt"></i> Automated Spillover Engine
            </div>
            <h1 class="text-4xl sm:text-6xl font-black tracking-tight text-white leading-tight">
                3x Auto-Spillover <br/>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-400">
                    BFS USDT Network
                </span>
            </h1>
            <p class="mt-6 text-lg text-slate-400 leading-relaxed">
                Experience algorithmic fairness with our native Breadth-First Search (BFS) spillover mechanism.
                Earn up to 60% distribution across 6-depth and 7-depth matrices with automated booster rebirth slots.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="/auth/register.php" class="px-8 py-3.5 rounded-xl font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 shadow-lg shadow-emerald-500/25 transition transform hover:-translate-y-0.5">
                    Join Now with USDT
                </a>
                <a href="/auth/login.php" class="px-8 py-3.5 rounded-xl font-bold text-slate-300 bg-slate-800 hover:bg-slate-700 border border-slate-700 transition">
                    Member Access
                </a>
            </div>
        </div>

        <!-- Matrix Pools Grid -->
        <div class="mt-20 grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
            <!-- Package 1 Card -->
            <div class="rounded-2xl bg-gradient-to-b from-slate-800/80 to-slate-900 border border-emerald-500/30 p-8 shadow-xl relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl"></div>
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <span class="text-xs font-bold text-emerald-400 uppercase tracking-widest">Entry Level Pool</span>
                        <h3 class="text-2xl font-black text-white mt-1">Package I (6 Levels)</h3>
                    </div>
                    <div class="text-right">
                        <span class="text-3xl font-extrabold text-white">100</span>
                        <span class="text-xs text-emerald-400 font-bold block">USDT</span>
                    </div>
                </div>

                <ul class="space-y-3.5 text-sm text-slate-300 mb-8 border-t border-b border-slate-800 py-6">
                    <li class="flex items-center space-x-3">
                        <i class="fa-solid fa-check text-emerald-400"></i>
                        <span><strong>3x Matrix Structure:</strong> 3 &bull; 9 &bull; 27 &bull; 81 &bull; 243 &bull; 729</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fa-solid fa-check text-emerald-400"></i>
                        <span><strong>Breadth-First Spillover:</strong> Global automatic left-to-right filling</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fa-solid fa-check text-emerald-400"></i>
                        <span><strong>60% Distribution:</strong> 60 USDT shared across active uplines</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fa-solid fa-check text-emerald-400"></i>
                        <span><strong>Rebirth Triggers:</strong> Levels 4, 5, and 6 deduct 20 USDT rebirth fee & re-inject booster IDs</span>
                    </li>
                </ul>

                <a href="/auth/register.php?pkg=package_1" class="w-full block text-center py-3 rounded-xl bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/30 font-semibold transition">
                    Activate Package I
                </a>
            </div>

            <!-- Package 2 Card -->
            <div class="rounded-2xl bg-gradient-to-b from-slate-800/80 to-slate-900 border border-teal-500/30 p-8 shadow-xl relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-teal-500/10 rounded-full blur-2xl"></div>
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <span class="text-xs font-bold text-teal-400 uppercase tracking-widest">Deep Pro Parallel Pool</span>
                        <h3 class="text-2xl font-black text-white mt-1">Package II (7 Levels)</h3>
                    </div>
                    <div class="text-right">
                        <span class="text-3xl font-extrabold text-white">300</span>
                        <span class="text-xs text-teal-400 font-bold block">USDT</span>
                    </div>
                </div>

                <ul class="space-y-3.5 text-sm text-slate-300 mb-8 border-t border-b border-slate-800 py-6">
                    <li class="flex items-center space-x-3">
                        <i class="fa-solid fa-check text-teal-400"></i>
                        <span><strong>Deep 7-Depth Matrix:</strong> Scales up to 2,187 nodes on Level 7</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fa-solid fa-check text-teal-400"></i>
                        <span><strong>BFS Parallel Spillover:</strong> Independent queue from Package I</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fa-solid fa-check text-teal-400"></i>
                        <span><strong>60% Distribution:</strong> 180 USDT shared dynamically upline</span>
                    </li>
                    <li class="flex items-center space-x-3">
                        <i class="fa-solid fa-check text-teal-400"></i>
                        <span><strong>Rebirth Triggers:</strong> Levels 4, 5, 6, and 7 generate automated rebirth slots</span>
                    </li>
                </ul>

                <a href="/auth/register.php?pkg=package_2" class="w-full block text-center py-3 rounded-xl bg-teal-600/20 hover:bg-teal-600 text-teal-300 hover:text-white border border-teal-500/30 font-semibold transition">
                    Activate Package II
                </a>
            </div>
        </div>

        <!-- Global Live Stats -->
        <div class="mt-16 bg-slate-900/60 border border-slate-800 rounded-2xl p-6 max-w-4xl mx-auto">
            <h4 class="text-xs uppercase font-bold text-slate-400 tracking-wider text-center mb-6">Global Network Health</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-700/50">
                    <span class="text-2xl font-bold text-emerald-400"><?= (int)($stats['package_1']['total_nodes'] ?? 0) ?></span>
                    <span class="block text-xs text-slate-400 mt-1">Package 1 Total Nodes</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-700/50">
                    <span class="text-2xl font-bold text-emerald-400"><?= (int)($stats['package_1']['max_level_reached'] ?? 1) ?></span>
                    <span class="block text-xs text-slate-400 mt-1">Package 1 Max Depth</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-700/50">
                    <span class="text-2xl font-bold text-teal-400"><?= (int)($stats['package_2']['total_nodes'] ?? 0) ?></span>
                    <span class="block text-xs text-slate-400 mt-1">Package 2 Total Nodes</span>
                </div>
                <div class="p-4 rounded-xl bg-slate-800/40 border border-slate-700/50">
                    <span class="text-2xl font-bold text-teal-400"><?= (int)($stats['package_2']['max_level_reached'] ?? 1) ?></span>
                    <span class="block text-xs text-slate-400 mt-1">Package 2 Max Depth</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
