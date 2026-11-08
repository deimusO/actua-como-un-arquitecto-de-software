    </main>

    <!-- Pie de Página Institucional -->
    <footer class="mt-auto py-6 border-t border-white/50 text-center text-xs text-slate-500">
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
