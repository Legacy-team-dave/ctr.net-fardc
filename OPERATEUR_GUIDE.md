# Guide Opérateur - CTR.NET-FARDC

## Périmètre OPERATEUR

- Effectuer des contrôles
- Consulter les écrans opérationnels autorisés

## Accès

- Connexion : `login.php`
- Préférences : `preferences.php` (configuration initiale)
- Équipe : `equipes.php` (enregistrement membres d'équipe)
- Dashboard : `index.php`
- Contrôles : `modules/controles/ajouter.php` (redirection post-login si préférences configurées)

## Flux de configuration (premier accès)

1. `login.php` → `preferences.php` si aucune préférence n'est encore enregistrée
2. `preferences.php` → `equipes.php` après sauvegarde des préférences
3. `equipes.php` → `index.php` via le bouton de continuation

Si les préférences sont déjà enregistrées, la redirection post-login va directement vers `modules/controles/ajouter.php`.

## Mentions réellement saisies

Dans la page de contrôle :

- `Présent` (militaire vivant)
- `Favorable` (bénéficiaire)
- `Défavorable` (bénéficiaire)

## Rappels

- Respecter les contrôles d'accès (`check_profil`)
- Vérifier les informations du militaire avant validation
- Compléter les observations si nécessaire
