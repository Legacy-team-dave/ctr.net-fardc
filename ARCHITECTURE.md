# Architecture - CTR.NET-FARDC

## Vue d'ensemble

Le projet est une application PHP structurée autour de pages métier dans `modules/`, avec des fonctions transverses dans `includes/` et des endpoints asynchrones dans `ajax/`.

## Arborescence réelle (racine)

```text
ctr.net-fardc/
├── index.php
├── login.php
├── logout.php
├── profil.php
├── preferences.php
├── includes/
├── modules/
├── ajax/
├── config/
├── assets/
├── backups/
└── uploads/
```

## Modules métier

- `modules/administration/`
  - Gestion utilisateurs (profil `ADMIN_IG`)
- `modules/militaires/`
  - Gestion du référentiel militaires
- `modules/controles/`
  - Saisie et suivi des contrôles
  - Point clé : `modules/controles/ajouter.php`
- `modules/litige/`
  - Gestion des litiges
- `modules/rapports/`
  - Rapports/statistiques

## Contrôle d'accès

Le contrôle d'accès est appliqué via les fonctions d'authentification/autorisation présentes dans :

- `includes/functions.php`
- `includes/auth.php`

Exemple d'usage courant : `check_profil([...])`.

## Mentions de contrôle

Mentions effectivement utilisées dans la page de saisie (`modules/controles/ajouter.php`) :

- `Présent`
- `Favorable`
- `Défavorable`

Statuts de contexte utilisés :

- `Vivant`
- `Décédé`

## Frontend

- CSS : `assets/css/`
- JS : `assets/js/`
- Images/avatars : `assets/img/`, `assets/uploads/avatars/`

## Base de données

Configuration via :

- `config/database.php`
- `config/load_config.php`

Tables principales exploitées par l'application :

- `utilisateurs`
- `militaires`
- `controles`
- `logs`
