    </main>

    <!-- Barra de Navegación Inferior para Teléfonos Móviles -->
    <nav class="mobile-bottom-nav">
        <?php if (($role ?? '') === 'admin'): ?>
            <a href="dashboard_admin.php" class="mobile-nav-btn <?= basename($_SERVER['PHP_SELF']) === 'dashboard_admin.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                <span>Panel</span>
            </a>
            <a href="exportar_carnets.php" class="mobile-nav-btn <?= basename($_SERVER['PHP_SELF']) === 'exportar_carnets.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                    <line x1="7" y1="8" x2="17" y2="8"></line>
                    <line x1="7" y1="12" x2="13" y2="12"></line>
                </svg>
                <span>Carnets</span>
            </a>
        <?php else: ?>
            <a href="dashboard_profesor.php" class="mobile-nav-btn <?= basename($_SERVER['PHP_SELF']) === 'dashboard_profesor.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <span>Clases</span>
            </a>
            <a href="escanear.php" class="mobile-nav-btn <?= basename($_SERVER['PHP_SELF']) === 'escanear.php' ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Escanear</span>
            </a>
        <?php endif; ?>
        
        <button type="button" class="mobile-nav-btn" onclick="window.offlineAttendanceManager.syncQueue()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
            </svg>
            <span>Sincronizar</span>
        </button>

        <a href="logout.php" class="mobile-nav-btn text-rose-500">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Salir</span>
        </a>
    </nav>

    <!-- Pie de Página Institucional (Desktop) -->
    <footer class="hide-mobile mt-auto py-5 border-t border-white/50 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-3">
            <div>
                <strong><?= htmlspecialchars(INSTITUCION_NOMBRE) ?></strong> &bull; Año Escolar <?= htmlspecialchars(INSTITUCION_ANIO_ESCOLAR) ?>
            </div>
            <div class="flex items-center gap-3">
                <span class="chip chip-blue" style="font-size: 11px;">PHP 8 + MySQL PDO</span>
                <span class="chip chip-mint" style="font-size: 11px;">Resiliencia Offline</span>
                <span class="chip chip-rose" style="font-size: 11px;">Anti-Fraude IP</span>
            </div>
        </div>
    </footer>

    <!-- Scripts Esenciales -->
    <script src="assets/js/offline_manager.js"></script>
    <?php if (isset($includeScannerScript) && $includeScannerScript): ?>
        <script src="assets/js/scanner.js"></script>
    <?php endif; ?>
    <?php if (isset($includeChartsScript) && $includeChartsScript): ?>
        <script src="assets/js/dashboard_charts.js"></script>
    <?php endif; ?>
</body>
</html>
