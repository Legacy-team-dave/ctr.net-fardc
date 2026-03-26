            </div><!-- /.container-fluid -->
            </section><!-- /.content -->
            </div><!-- /.content-wrapper -->

            <footer class="main-footer no-print">
                <div class="float-left" style="color: #2e7d32;">
                    <strong>IG-FARDC &copy; 2026 - Contrôle Effectifs Militaires.</strong> Tous droits réservés.
                </div>
                <div class="float-right d-none d-sm-inline-block" style="color: #2e7d32;">
                    <b>Version</b> 1.0 |
                    <?php if (isset($_SESSION['user_nom'])): ?>
                    <b>Connecté :</b> <?= htmlspecialchars($_SESSION['user_nom']) ?>
                    <?php endif; ?>
                </div>
            </footer>
            </div><!-- ./wrapper -->

            <!-- jQuery -->
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <!-- Bootstrap 5 Bundle -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
            <!-- AdminLTE -->
            <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
            <!-- Leaflet -->
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
            <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
            <script>
if (window.jQuery && $.fn.dataTable) {
    $.extend(true, $.fn.dataTable.defaults, {
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        language: {
            lengthMenu: "Afficher _MENU_ éléments"
        }
    });
}
            </script>
            <!-- Scripts personnalisés -->
            <script
                src="<?= isset($appBasePath) ? htmlspecialchars($appBasePath) : '/ctr.net-fardc' ?>/assets/js/custom.js">
            </script>
            </body>

            </html>