<?php
/**
 * Global Footer
 */
declare(strict_types=1);
?>

<?php if (isset($currentUser) && $currentUser): ?>
            </main>
        </div>
    </div>
<?php else: ?>
    </main>

    <!-- Public Global Footer -->
    <footer class="bg-slate-900 border-t border-slate-800 py-6 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center justify-between text-xs text-slate-400 gap-4">
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Breadth-First Search (BFS) Auto-Spillover Network &bull; 100% USDT Tether Settlements</span>
            </div>
            <div>
                &copy; <?= date('Y') ?> Matrix BFS MLM Platform. Secure Native PHP 8 Architecture.
            </div>
        </div>
    </footer>
<?php endif; ?>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        if (sidebar) {
            sidebar.classList.toggle('-translate-x-full');
        }
        if (backdrop) {
            backdrop.classList.toggle('hidden');
        }
    }
</script>
</body>
</html>
