<!-- Modal de gestion de conflit de synchronisation -->
<div class="modal fade" id="syncConflictModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Conflit de synchronisation détecté</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <h6>Version locale</h6>
            <pre id="conflict-local" class="bg-light p-2 rounded"></pre>
            <button class="btn btn-success w-100 mt-2" id="keep-local">Garder la version locale</button>
          </div>
          <div class="col-md-6">
            <h6>Version serveur</h6>
            <pre id="conflict-server" class="bg-light p-2 rounded"></pre>
            <button class="btn btn-primary w-100 mt-2" id="keep-server">Prendre la version serveur</button>
          </div>
        </div>
        <div class="mt-3">
          <button class="btn btn-secondary w-100" id="merge-manual">Fusionner manuellement</button>
        </div>
      </div>
    </div>
  </div>
</div>
