<?php
/**
 * Global Header with Modern Sidebar (ASIDE) Layout for Authenticated Portals
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$flash = getFlash();
$title = $pageTitle ?? 'USDT 3x Matrix MLM';
$currentPath = $_SERVER['SCRIPT_NAME'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | BFS USDT Matrix</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        },
                        usdt: '#26A17B',
                        dark: {
                            800: '#1e293b',
                            850: '#172033',
                            900: '#0f172a',
                            950: '#020617',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Standalone Client-Side QR Code Generator -->
    <script src="/assets/js/qrcode.min.js"></script>
    <style>
        .custom-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scroll::-webkit-scrollbar-track {
            background: #1e293b;
        }
        .custom-scroll::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
    </style>
</head>
<body class="h-full bg-slate-950 font-sans antialiased text-slate-200">

<?php if ($currentUser): ?>
    <!-- Authenticated Layout: Sidebar (<aside>) + Main View Area -->
    <div class="flex h-screen overflow-hidden bg-slate-950">

        <!-- Mobile Sidebar Backdrop (Overlay) -->
        <div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm hidden md:hidden"></div>

        <!-- Sidebar (<aside>) -->
        <aside id="mainSidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 flex flex-col justify-between transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0 md:static md:inset-auto">
            <div class="flex flex-col h-full">
                <!-- Brand Header -->
                <div class="h-16 flex items-center justify-between px-5 border-b border-slate-800">
                    <a href="/member/dashboard.php" class="flex items-center space-x-2.5 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition">
                            <i class="fa-solid fa-network-wired text-white text-base"></i>
                        </div>
                        <div>
                            <span class="font-extrabold text-base tracking-tight text-white flex items-center gap-1.5">
                                MATRIX<span class="text-emerald-400">BFS</span>
                                <span class="text-[9px] uppercase font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-1 py-0.2 rounded">USDT</span>
                            </span>
                        </div>
                    </a>
                    <!-- Mobile Close Button -->
                    <button type="button" onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white p-1">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- User Mini Card in Sidebar -->
                <div class="p-4 border-b border-slate-800/80 bg-slate-950/40">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-teal-400 flex items-center justify-center text-white font-black text-sm shadow">
                            <?= strtoupper(substr($currentUser['username'], 0, 2)) ?>
                        </div>
                        <div class="overflow-hidden">
                            <div class="text-xs font-bold text-white truncate"><?= e($currentUser['username']) ?></div>
                            <span class="inline-block text-[10px] font-semibold uppercase px-2 py-0.5 rounded <?= $currentUser['role'] === 'admin' ? 'bg-purple-500/20 text-purple-300 border border-purple-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' ?>">
                                <?= e($currentUser['role']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 p-2 rounded-lg bg-slate-900 border border-slate-800 flex items-center justify-between">
                        <span class="text-[11px] text-slate-400">Balance:</span>
                        <span class="text-xs font-mono font-bold text-emerald-400">
                            <?= formatUsdt(getUserUsdtBalance((int)$currentUser['id'])) ?> USDT
                        </span>
                    </div>
                </div>

                <!-- Navigation Links List -->
                <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto custom-scroll">
                    <!-- Member Section Header -->
                    <div class="px-3 pt-1 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Member Portal
                    </div>

                    <a href="/member/dashboard.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= str_contains($currentPath, 'member/dashboard.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                        <i class="fa-solid fa-sitemap w-5 text-center mr-2.5 <?= str_contains($currentPath, 'member/dashboard.php') ? 'text-white' : 'text-teal-400' ?>"></i>
                        Matrix Visualizer
                    </a>

                    <a href="/member/wallet.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= str_contains($currentPath, 'member/wallet.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                        <i class="fa-solid fa-wallet w-5 text-center mr-2.5 <?= str_contains($currentPath, 'member/wallet.php') ? 'text-white' : 'text-emerald-400' ?>"></i>
                        USDT Wallet
                    </a>

                    <a href="/member/usdt_activate.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= (str_contains($currentPath, 'member/usdt_activate.php') || str_contains($currentPath, 'usdt_activate')) ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                        <i class="fa-solid fa-qrcode w-5 text-center mr-2.5 <?= str_contains($currentPath, 'usdt_activate') ? 'text-white' : 'text-emerald-400' ?>"></i>
                        USDT Activation
                    </a>

                    <a href="/member/profile.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= str_contains($currentPath, 'member/profile.php') ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                        <i class="fa-solid fa-user-gear w-5 text-center mr-2.5 <?= str_contains($currentPath, 'member/profile.php') ? 'text-white' : 'text-indigo-400' ?>"></i>
                        Profile & Settings
                    </a>

                    <?php if ($currentUser['role'] === 'admin'): ?>
                        <!-- Admin Section Header -->
                        <div class="px-3 pt-5 pb-1 text-[10px] font-bold uppercase tracking-wider text-purple-400 border-t border-slate-800 mt-4">
                            Admin Control
                        </div>

                        <a href="/admin/dashboard.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= str_contains($currentPath, 'admin/dashboard.php') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                            <i class="fa-solid fa-chart-pie w-5 text-center mr-2.5 <?= str_contains($currentPath, 'admin/dashboard.php') ? 'text-white' : 'text-purple-400' ?>"></i>
                            Admin Overview
                        </a>

                        <a href="/admin/users.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= str_contains($currentPath, 'admin/users.php') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                            <i class="fa-solid fa-users w-5 text-center mr-2.5 <?= str_contains($currentPath, 'admin/users.php') ? 'text-white' : 'text-blue-400' ?>"></i>
                            Members Management
                        </a>

                        <a href="/admin/transactions.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= str_contains($currentPath, 'admin/transactions.php') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                            <i class="fa-solid fa-money-bill-transfer w-5 text-center mr-2.5 <?= str_contains($currentPath, 'admin/transactions.php') ? 'text-white' : 'text-amber-400' ?>"></i>
                            USDT Transactions
                        </a>

                        <a href="/admin/usdt_activations.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= (str_contains($currentPath, 'admin/usdt_activations.php') || str_contains($currentPath, 'usdt-activations')) ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                            <i class="fa-solid fa-bolt w-5 text-center mr-2.5 <?= str_contains($currentPath, 'usdt_activations') ? 'text-white' : 'text-yellow-400' ?>"></i>
                            USDT Activations
                        </a>

                        <a href="/admin/wallet_settings.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= str_contains($currentPath, 'admin/wallet_settings.php') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                            <i class="fa-solid fa-building-columns w-5 text-center mr-2.5 <?= str_contains($currentPath, 'admin/wallet_settings.php') ? 'text-white' : 'text-emerald-400' ?>"></i>
                            Company Wallet & QR
                        </a>

                        <a href="/admin/logs.php" class="flex items-center px-3 py-2.5 text-xs font-semibold rounded-xl transition <?= str_contains($currentPath, 'admin/logs.php') ? 'bg-purple-600 text-white shadow-lg shadow-purple-600/30' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?>">
                            <i class="fa-solid fa-clock-rotate-left w-5 text-center mr-2.5 <?= str_contains($currentPath, 'admin/logs.php') ? 'text-white' : 'text-cyan-400' ?>"></i>
                            System Audit Logs
                        </a>
                    <?php endif; ?>
                </nav>

                <!-- Sidebar Footer Action -->
                <div class="p-3 border-t border-slate-800">
                    <a href="/auth/logout.php" class="w-full flex items-center justify-center px-3 py-2 rounded-xl text-xs font-bold text-rose-300 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/20 transition gap-2">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        Sign Out
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Area with Top Header -->
        <div class="flex-1 flex flex-col h-full overflow-hidden">
            <!-- Top Bar -->
            <header class="h-16 bg-slate-900 border-b border-slate-800 flex items-center justify-between px-4 sm:px-6 z-10 shrink-0">
                <div class="flex items-center gap-3">
                    <!-- Mobile Hamburger -->
                    <button type="button" onclick="toggleSidebar()" class="md:hidden p-2 rounded-lg bg-slate-800 text-slate-300 hover:text-white">
                        <i class="fa-solid fa-bars text-lg"></i>
                    </button>
                    <span class="text-xs uppercase font-extrabold tracking-wider text-slate-400 hidden sm:inline-block">
                        3x Auto-Spillover Breadth-First Search (BFS) Network
                    </span>
                </div>

                <div class="flex items-center space-x-3">
                    <a href="/member/wallet.php" class="flex items-center px-3 py-1.5 bg-emerald-950/60 border border-emerald-500/40 rounded-xl text-xs font-semibold text-emerald-300 hover:bg-emerald-900/60 transition">
                        <i class="fa-solid fa-circle-dollar-to-slot mr-1.5 text-emerald-400"></i>
                        <?= formatUsdt(getUserUsdtBalance((int)$currentUser['id'])) ?> USDT
                    </a>
                    <a href="/" target="_blank" class="p-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition text-xs" title="Public Portal">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    </a>
                </div>
            </header>

            <!-- Scrollable View Container -->
            <main class="flex-1 overflow-y-auto custom-scroll p-4 sm:p-6 lg:p-8 bg-slate-950">
                <?php if ($flash): ?>
                    <div class="mb-6">
                        <?php
                        $alertClasses = [
                            'success' => 'bg-emerald-500/10 border-emerald-500/40 text-emerald-300',
                            'danger'  => 'bg-rose-500/10 border-rose-500/40 text-rose-300',
                            'warning' => 'bg-amber-500/10 border-amber-500/40 text-amber-300',
                            'info'    => 'bg-sky-500/10 border-sky-500/40 text-sky-300',
                        ];
                        $class = $alertClasses[$flash['type']] ?? $alertClasses['info'];
                        ?>
                        <div class="flex items-center justify-between p-4 rounded-xl border <?= $class ?> shadow-lg">
                            <div class="flex items-center space-x-3">
                                <i class="fa-solid fa-circle-info text-lg"></i>
                                <span class="text-sm font-medium"><?= e($flash['message']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

<?php else: ?>
    <!-- Public Layout: Top Navbar -->
    <header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand Logo -->
                <div class="flex items-center space-x-3">
                    <a href="/" class="flex items-center space-x-2.5 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-400 flex items-center justify-center shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition">
                            <i class="fa-solid fa-network-wired text-white text-lg"></i>
                        </div>
                        <div>
                            <span class="font-extrabold text-lg tracking-tight text-white flex items-center gap-1.5">
                                MATRIX<span class="text-emerald-400">BFS</span>
                                <span class="text-[10px] uppercase font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-1.5 py-0.5 rounded">USDT</span>
                            </span>
                        </div>
                    </a>
                </div>

                <!-- Navigation Links -->
                <nav class="flex items-center space-x-2">
                    <a href="/" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Home</a>
                    <a href="/auth/login.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Sign In</a>
                    <a href="/auth/register.php" class="px-4 py-2 text-sm font-bold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/30 transition">
                        Register
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="flex-1">
        <?php if ($flash): ?>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
                <?php
                $alertClasses = [
                    'success' => 'bg-emerald-500/10 border-emerald-500/40 text-emerald-300',
                    'danger'  => 'bg-rose-500/10 border-rose-500/40 text-rose-300',
                    'warning' => 'bg-amber-500/10 border-amber-500/40 text-amber-300',
                    'info'    => 'bg-sky-500/10 border-sky-500/40 text-sky-300',
                ];
                $class = $alertClasses[$flash['type']] ?? $alertClasses['info'];
                ?>
                <div class="flex items-center justify-between p-4 rounded-xl border <?= $class ?> shadow-lg">
                    <div class="flex items-center space-x-3">
                        <i class="fa-solid fa-circle-info text-lg"></i>
                        <span class="text-sm font-medium"><?= e($flash['message']) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
<?php endif; ?>
