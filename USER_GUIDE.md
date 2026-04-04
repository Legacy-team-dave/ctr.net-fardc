# Guide Utilisateur - CTR.NET-FARDC

## Profils

- `ADMIN_IG` : gestion globale
- `OPERATEUR` : militaires + contrôles
- `CONTROLEUR` : réservé à l'application mobile `CTR.NET` (non accessible côté web)
- `ENROLEUR` : réservé à l'application mobile `ENROL.NET` (non accessible côté web)

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
- `CONTROLEUR` n'accède plus au web (profil mobile uniquement)

## Références

- `ADMIN_GUIDE.md`
- `OPERATEUR_GUIDE.md`
- `CONTROLEUR_GUIDE.md` (guide mobile)
- `TROUBLESHOOTING.md`
