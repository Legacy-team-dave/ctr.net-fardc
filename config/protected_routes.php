<?php

/**
 * Protected Routes Configuration
 * 
 * Centralized mapping of routes to required roles.
 * Used by both server-side authorization (check_profil) and client-side link validation.
 * 
 * Format: 'route' => ['role1', 'role2', ...]
 */

return [
    // Index and Dashboard
    '/ctr.net-fardc/index.php' => ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR'],

    // Administration Module
    '/ctr.net-fardc/modules/administration/liste.php' => ['ADMIN_IG'],
    '/ctr.net-fardc/modules/administration/ajouter.php' => ['ADMIN_IG'],
    '/ctr.net-fardc/modules/administration/modifier.php' => ['ADMIN_IG'],
    '/ctr.net-fardc/modules/administration/supprimer.php' => ['ADMIN_IG'],

    // Contrôles Module
    '/ctr.net-fardc/modules/controles/liste.php' => ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR'],
    '/ctr.net-fardc/modules/controles/ajouter.php' => ['ADMIN_IG', 'OPERATEUR'],
    '/ctr.net-fardc/modules/controles/modifier.php' => ['ADMIN_IG', 'OPERATEUR'],
    '/ctr.net-fardc/modules/controles/supprimer.php' => ['ADMIN_IG'],
    '/ctr.net-fardc/modules/controles/consulter.php' => ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR'],

    // Litiges Module
    '/ctr.net-fardc/modules/litige/liste.php' => ['ADMIN_IG', 'OPERATEUR'],
    '/ctr.net-fardc/modules/litige/ajouter.php' => ['ADMIN_IG', 'OPERATEUR'],
    '/ctr.net-fardc/modules/litige/modifier.php' => ['ADMIN_IG', 'OPERATEUR'],
    '/ctr.net-fardc/modules/litige/supprimer.php' => ['ADMIN_IG'],

    // Reports Module
    '/ctr.net-fardc/modules/rapports/index.php' => ['ADMIN_IG', 'OPERATEUR'],
    '/ctr.net-fardc/modules/rapports/controles.php' => ['ADMIN_IG', 'OPERATEUR'],
    '/ctr.net-fardc/modules/rapports/litiges.php' => ['ADMIN_IG', 'OPERATEUR'],

    // Settings and Profile
    '/ctr.net-fardc/parametres.php' => ['ADMIN_IG'],
    '/ctr.net-fardc/profil.php' => ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR'],
    '/ctr.net-fardc/preferences.php' => ['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR'],
];
