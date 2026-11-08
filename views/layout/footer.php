<?php
/**
 * views/layout/footer.php
 *
 * Shared closing tags for ALL authenticated pages.
 * Place at the very bottom of every page, after all content.
 *
 * Optional variable:
 *   $extra_scripts — raw HTML string of extra <script> tags to inject
 *                    before </body>. Set in the page file before including.
 *
 * Usage at the bottom of any page:
 *
 *   $extra_scripts = '<script src="assets/js/appointments.js"></script>';
 *   include 'views/layout/footer.php';
 */
$extra_scripts = $extra_scripts ?? '';
?>

    <!-- ─────────────────────────────────────────
         Logout confirmation modal (shared)
    ───────────────────────────────────────── -->
    <div id="logout-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
        <div class="bg-white dark:bg-[#1e293b] rounded-xl shadow-xl p-6 w-full max-w-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="bg-red-100 dark:bg-red-900/30 rounded-full p-2">
                    <span class="material-symbols-outlined text-red-600 dark:text-red-400">logout</span>
                </div>
                <h3 id="logout-modal-title" class="text-lg font-bold text-[#0d131b] dark:text-white">Confirm Logout</h3>
            </div>
            <p class="text-sm text-[#4c6c9a] dark:text-slate-400 mb-6">
                Are you sure you want to log out of your account?
            </p>
            <div class="flex gap-3 justify-end">
                <button onclick="closeLogoutModal()"
                        class="px-4 py-2 rounded-lg border border-[#e7ecf3] dark:border-slate-600 text-sm font-semibold text-[#4c6c9a] dark:text-slate-300 hover:bg-[#f0f4f8] dark:hover:bg-slate-700 transition-colors">
                    Cancel
                </button>
                <a href="logout.php"
                   class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-semibold transition-colors">
                    Yes, Log Out
                </a>
            </div>
        </div>
    </div>

    <!-- ─────────────────────────────────────────
         Shared JS: logout modal + mobile sidebar
    ───────────────────────────────────────── -->
    <script>
    // Logout modal
    function confirmLogout(e) {
        e.preventDefault();
        document.getElementById('logout-modal').classList.remove('hidden');
    }
    function closeLogoutModal() {
        document.getElementById('logout-modal').classList.add('hidden');
    }
    document.getElementById('logout-modal')?.addEventListener('click', function(e) {
        if (e.target === this) closeLogoutModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLogoutModal();
    });

    // Mobile sidebar toggle
    const mobileToggle = document.getElementById('mobile-menu-toggle');
    const sidebar = document.querySelector('aside');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('flex');
        });
    }

    // Dark mode: persist preference
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
    }
    </script>

    <?php echo $extra_scripts; ?>

</body>
</html>

