# Guide Utilisateur - CTR.NET-FARDC

## Profils

- `ADMIN_IG` : gestion globale
- `OPERATEUR` : militaires + contrôles
- `CONTROLEUR` : saisie contrôles uniquement

## Connexion

URL : `http://localhost/ctr.net-fardc/login.php`

## Contrôles

Le formulaire principal est dans `modules/controles/ajouter.php`.

Mentions réelles :

- `Présent`
- `Favorable`
- `Défavorable`

Statuts de contexte :

- `Vivant`
- `Décédé`

## Navigation selon profil

- `ADMIN_IG` et `OPERATEUR` accèdent au dashboard (`index.php`)
- `CONTROLEUR` est orienté vers la saisie de contrôle

## Références

- `ADMIN_GUIDE.md`
- `OPERATEUR_GUIDE.md`
- `CONTROLEUR_GUIDE.md`
- `TROUBLESHOOTING.md`
