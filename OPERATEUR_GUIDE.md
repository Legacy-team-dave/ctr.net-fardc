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

1. `login.php` → `preferences.php` (sélection garnisons/catégories)
2. `preferences.php` → `equipes.php` (enregistrement membres d'équipe)
3. `equipes.php` → `index.php` (dashboard)

## Mentions réellement saisies

Dans la page de contrôle :

- `Présent` (militaire vivant)
- `Favorable` (bénéficiaire)
- `Défavorable` (bénéficiaire)

## Rappels

- Respecter les contrôles d'accès (`check_profil`)
- Vérifier les informations du militaire avant validation
- Compléter les observations si nécessaire
