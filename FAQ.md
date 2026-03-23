# FAQ - CTR.NET-FARDC

## Quels profils existent ?

- `ADMIN_IG`
- `OPERATEUR`
- `CONTROLEUR`

## Quelles mentions de contrôle existent réellement ?

Dans `modules/controles/ajouter.php` :

- `Présent`
- `Favorable`
- `Défavorable`

## Pourquoi le CONTROLEUR ne voit pas le dashboard ?

C'est le comportement normal : le profil `CONTROLEUR` est limité à la saisie de contrôles.

## Où sont les fonctions de sécurité d'accès ?

- `includes/functions.php`
- `includes/auth.php`

## Quel dossier contient les endpoints asynchrones ?

- `ajax/`

## Où corriger la connexion base de données ?

- `config/database.php`
