# Setup - CTR.NET-FARDC

## Prérequis

- Windows
- Laragon installé (`C:\laragon\`)
- PHP + MySQL actifs dans Laragon

## Installation rapide

1. Copier le projet dans `C:\laragon\www\ctr.net-fardc`
2. Lancer Laragon
3. Ouvrir : `http://localhost/ctr.net-fardc/login.php`

## Lancement via scripts

- `START.bat`
- `INSTALL.bat`
- `launch.bat`
- `launch.ps1`
- `lanceur.ps1`

## Profils applicatifs

- `ADMIN_IG`
- `OPERATEUR`
- `CONTROLEUR`

## Vérification minimale après setup

- Connexion utilisateur possible
- Accès à `index.php` pour `ADMIN_IG` / `OPERATEUR`
- Redirection `CONTROLEUR` vers `modules/controles/ajouter.php`
- Enregistrement d'un contrôle fonctionnel

## Mentions à valider en test

Dans `modules/controles/ajouter.php` :

- `Présent`
- `Favorable`
- `Défavorable`

## Dépannage rapide

- Si page inaccessible : vérifier Apache/MySQL dans Laragon
- Si erreur BD : vérifier `config/database.php`
- Si problème de droits : vérifier le profil dans la table `utilisateurs`
