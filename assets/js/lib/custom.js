// Scripts personnalisés
$(function () {
    // Activer les tooltips Bootstrap
    $('[data-toggle="tooltip"]').tooltip();
    
    // Activation du menu AdminLTE
    $('[data-widget="pushmenu"]').PushMenu();
    
    // Gestion des confirmations de suppression
    $('.delete-confirm').click(function(e) {
        if(!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
            e.preventDefault();
        }
    });
});