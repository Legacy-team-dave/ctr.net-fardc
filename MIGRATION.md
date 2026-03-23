# Migration - CTR.NET-FARDC

Ce document décrit une migration **réaliste** pour ce dépôt tel qu'il existe aujourd'hui.

## Référence d'architecture actuelle

- Pages racine : `index.php`, `login.php`, `logout.php`, `profil.php`
- Fonctions transverses : `includes/functions.php`, `includes/auth.php`
- Modules : `modules/administration`, `modules/militaires`, `modules/controles`, `modules/litige`, `modules/rapports`
- AJAX : `ajax/`

## Principes de migration recommandés

1. Garder `includes/functions.php` comme couche commune.
2. Migrer page par page (pas de refonte massive).
3. Conserver les contrôles d'accès via `check_profil([...])`.
4. Tester chaque module après modification.

## Priorités

### 1) Authentification et profils

- Vérifier `login.php` et les redirections par profil.
- Vérifier les profils supportés : `ADMIN_IG`, `OPERATEUR`, `CONTROLEUR`.

### 2) Module contrôles

- Page clé : `modules/controles/ajouter.php`
- Mentions à préserver : `Présent`, `Favorable`, `Défavorable`
- Statuts de contexte : `Vivant`, `Décédé`

### 3) Documentation

Mettre à jour toute documentation après chaque changement validé.
