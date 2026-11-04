<?php
/**
 * Global Header
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$flash = getFlash();
$title = $pageTitle ?? 'USDT 3x Matrix MLM';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 text-slate-100">
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
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            400: '#34d399',
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
<body class="flex flex-col min-h-screen bg-slate-950 font-sans antialiased text-slate-200">
    <!-- Navbar -->
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
                <nav class="hidden md:flex items-center space-x-1">
                    <?php if ($currentUser): ?>
                        <?php if ($currentUser['role'] === 'admin'): ?>
                            <a href="/admin/dashboard.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                                <i class="fa-solid fa-chart-pie mr-1.5 text-emerald-400"></i> Admin Overview
                            </a>
                            <a href="/admin/users.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                                <i class="fa-solid fa-users mr-1.5 text-blue-400"></i> Members
                            </a>
                            <a href="/admin/transactions.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                                <i class="fa-solid fa-money-bill-transfer mr-1.5 text-amber-400"></i> Transactions
                            </a>
                            <a href="/admin/logs.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                                <i class="fa-solid fa-clock-rotate-left mr-1.5 text-purple-400"></i> Audit Logs
                            </a>
                            <span class="text-slate-700 px-1">|</span>
                        <?php endif; ?>

                        <a href="/member/dashboard.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                            <i class="fa-solid fa-sitemap mr-1.5 text-teal-400"></i> Matrix View
                        </a>
                        <a href="/member/wallet.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                            <i class="fa-solid fa-wallet mr-1.5 text-emerald-400"></i> USDT Wallet
                        </a>
                        <a href="/member/profile.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">
                            <i class="fa-solid fa-user-gear mr-1.5 text-indigo-400"></i> Profile
                        </a>
                    <?php else: ?>
                        <a href="/" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Home</a>
                        <a href="/auth/login.php" class="px-3 py-2 text-sm font-medium rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Login</a>
                        <a href="/auth/register.php" class="ml-2 px-4 py-2 text-sm font-medium rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/30 transition">
                            Register
                        </a>
                    <?php endif; ?>
                </nav>

                <!-- User Quick Info & Action -->
                <div class="flex items-center space-x-3">
                    <?php if ($currentUser): ?>
                        <div class="hidden sm:flex flex-col text-right">
                            <span class="text-xs text-slate-400"><?= e($currentUser['role'] === 'admin' ? 'Administrator' : 'Member') ?></span>
                            <span class="text-sm font-bold text-white"><?= e($currentUser['username']) ?></span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <a href="/member/wallet.php" class="flex items-center px-3 py-1.5 bg-emerald-950/60 border border-emerald-500/40 rounded-lg text-xs font-semibold text-emerald-300">
                                <i class="fa-solid fa-circle-dollar-to-slot mr-1.5 text-emerald-400"></i>
                                <?= formatUsdt(getUserUsdtBalance((int)$currentUser['id'])) ?> USDT
                            </a>
                            <a href="/auth/logout.php" title="Sign Out" class="p-2 rounded-lg bg-slate-800 hover:bg-red-500/20 hover:text-red-400 text-slate-400 transition">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="/auth/login.php" class="text-sm text-slate-300 hover:text-white px-3 py-1.5">Sign In</a>
                        <a href="/auth/register.php" class="px-3.5 py-1.5 text-sm font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition">Get Started</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
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
