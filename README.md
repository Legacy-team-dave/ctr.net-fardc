# CTR.NET-FARDC

Application PHP de contrôle des effectifs militaires.

## Profils

- `ADMIN_IG`
  - Gestion des utilisateurs
  - Gestion des militaires
  - Supervision des contrôles et rapports
- `OPERATEUR`
  - Gestion des militaires
  - Saisie de contrôles
  - Accès au tableau de bord opérateur
- `CONTROLEUR`
  - Saisie de contrôles uniquement (interface simplifiée)
  - Redirection directe vers `modules/controles/ajouter.php`

## Mentions de contrôle (réelles)

Dans `modules/controles/ajouter.php`, les mentions enregistrées sont :

- `Présent` (militaire vivant)
- `Favorable` (bénéficiaire, contexte décédé)
- `Défavorable` (bénéficiaire, contexte décédé)

Le flux utilise aussi les statuts :

- `Vivant`
- `Décédé`

## Structure réelle

- `index.php`, `login.php`, `logout.php`, `profil.php`, `preferences.php`
- `includes/` : auth, fonctions globales, header/footer
- `modules/` : `administration`, `militaires`, `controles`, `litige`, `rapports`
- `ajax/` : endpoints AJAX
- `config/` : configuration base de données
- `assets/` : css, js, images, polices, données géographiques
- `backups/`

## Lancement

- `START.bat`
- `INSTALL.bat`
- `launch.bat`
- `launch.ps1`
- `lanceur.ps1`

## URL

- `http://localhost/ctr.net-fardc/login.php`

Selon la configuration Laragon, l'URL peut utiliser `127.0.0.1` et/ou un port spécifique.

## Base de données

Tables principales utilisées :

- `utilisateurs`
- `militaires`
- `controles`
- `logs`

## Documentation liée

- `SETUP.md`
- `ARCHITECTURE.md`
- `USER_GUIDE.md`
- `ADMIN_GUIDE.md`
- `OPERATEUR_GUIDE.md`
- `CONTROLEUR_GUIDE.md`
- `TROUBLESHOOTING.md`
