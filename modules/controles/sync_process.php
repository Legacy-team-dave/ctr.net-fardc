<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);

redirect_with_flash(
    app_url('modules/controles/sync.php'),
    'info',
    'La synchronisation se fait désormais depuis la page dédiée.'
);
