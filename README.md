# CTR.NET-FARDC

Application PHP de contrôle des effectifs militaires.

## Profils

- `ADMIN_IG`
  - Gestion des utilisateurs
  - Gestion des militaires
  - Supervision des contrôles et rapports
- `OPERATEUR`
  - Saisie de contrôles
  - Accès au tableau de bord opérateur
- `CONTROLEUR`
  - Profil réservé à l'application mobile CTR.NET Mobile
  - Non accessible côté web (connexion bloquée)

## Mentions de contrôle (réelles)

Dans `modules/controles/ajouter.php`, les mentions enregistrées sont :

- `Présent` (militaire vivant)
- `Favorable` (bénéficiaire, contexte décédé)
- `Défavorable` (bénéficiaire, contexte décédé)

Le flux utilise aussi les statuts :

- `Vivant`
- `Décédé`

## Structure réelle

- `index.php`, `login.php`, `logout.php`, `profil.php`, `preferences.php`, `equipes.php`
- `includes/` : auth, fonctions globales, header/footer
- `modules/` : `administration`, `militaires`, `controles`, `litige`, `rapports`
- `ajax/` : endpoints AJAX
- `api/` : API REST pour application mobile (auth, contrôles, profil, polling)
- `config/` : configuration base de données, chiffrement
- `assets/` : css, js, images, polices, données géographiques
- `backups/`

## Lancement

- `START.bat`
- `INSTALLER.bat`
- `launch.bat`
- `launch.ps1`
- `lanceur.ps1`

`INSTALLER.bat` crée un raccourci bureau `.lnk` (avec icône `assets/img/ig_fardc.ico` si disponible), pointant en priorité vers `launch.ps1` (fallback `launch.bat`). Un double-clic sur ce raccourci lance Laragon, attend le démarrage des services (Apache/MySQL), puis ouvre l'application dans le navigateur.

## Sauvegarde automatique consolidée

Les sauvegardes sont effectuées via un job Windows planifié, avec mise à jour d'une archive ZIP consolidée unique et rotation automatique.

## 🔐 Système de Chiffrement

**CTR.NET-FARDC inclut un système de chiffrement AES-256-CBC complète** pour protéger le code source contre la lecture en clair.

### Démarrage Rapide

```bash
# 1. Initialiser (générer clé unique)
php bin/encrypt.php init

# 2. Chiffrer fichiers sensibles
php bin/encrypt.php encrypt

# 3. Vérifier stato
php bin/encrypt.php status
```

### Caractéristiques

- ✅ **Algorithme:** AES-256-CBC (standard militaire NIST FIPS 197)
- ✅ **Transparent:** Déchiffrement automatique au runtime
- ✅ **Zéro impact:** Aucune modification du code applicatif
- ✅ **Interfaces multiples:** GUI (batch), CLI (PHP), PowerShell

### Documentation Chiffrement

Pour une documentation complète et des exemples pratiques:

- **[ENCRYPTION.md](ENCRYPTION.md)** — Guide complet
- **[ENCRYPTION_QUICKSTART.md](ENCRYPTION_QUICKSTART.md)** — 3 étapes
- **[ENCRYPTION_EXAMPLES.md](ENCRYPTION_EXAMPLES.md)** — Cas réels
- **[ENCRYPTION_IMPLEMENTATION_GUIDE.md](ENCRYPTION_IMPLEMENTATION_GUIDE.md)** — Guide IT

### Fichiers Clés

**Configuration & Crypto:**

- `config/encryption.php` — Fonctions AES-256-CBC
- `config/encrypted_loader.php` — Déchiffrement automatique
- `.env` — Clé secrète (à générer une fois)

**Sauvegardes Automatiques :**

- Fréquence : toutes les 8 heures (tâche planifiée Windows)
- Format : CSV (Excel compatible) + XLSX
- Mode : consolidé (ancienne + nouvelles données dans la même archive ZIP)
- Archive principale : `backups/backup_consolide_latest.zip`
- Sources : `controles`, `litiges`, `non_vus`
- Scripts : `setup_backup_task.bat`, `setup_backup_task.ps1`, `run_backup_job.ps1`
- Purge auto : suppression des doublons + conservation des 30 dernières archives non identiques
- Purge auto paramétrable : `setup_backup_task.ps1 -MaxKeep 30` (ou `setup_backup_task.bat 30`)
- Envoi e-mail (optionnel) : configurer config/backup_mail.json (adresse expéditeur + destinataire + SMTP)

**🧹 Nettoyage automatique des caches :**

- Exécution automatique : intégré au job planifié (toutes les 8h après backup + purge)
- Cibles : fichiers temporaires XLSX, verrous obsolètes, tokens expirés, anciens logs (> 90 jours)
- Script de nettoyage manuel : `run_cache_cleanup.ps1 -JoursLogs 90` ou `run_cache_cleanup.bat 90`

## URL

- Écran d'accueil : `http://127.0.0.1/ctr.net-fardc/splash_screen.php`
- Connexion directe : `http://127.0.0.1/ctr.net-fardc/login.php`

Selon la configuration Laragon, l'URL peut utiliser `localhost` et/ou un port spécifique.

## Base de données

Tables principales utilisées :

- `utilisateurs`
- `militaires`
- `controles`
- `logs`
- `equipes`

Script SQL de synchronisation centrale:

- `sql/sync_init.sql`
- `sql/sync_rollback.sql`

## Documentation liée

- `SETUP.md`
- `ARCHITECTURE.md`
- `FAQ.md`
- `FONCTIONNEMENT_APPLICATION.md`
- `USER_GUIDE.md`
- `ADMIN_GUIDE.md`
- `OPERATEUR_GUIDE.md`
- `CONTROLEUR_GUIDE.md`
- `TROUBLESHOOTING.md`
- `SYNCHRONISATION.md`
- `PRESENTATION_CTR_NET_FARDC.md`
- `PROMPT_PRESENTATION.md`

## Application mobile associée

- Projet : `ctr-net-mobile/` (Ionic 8.0.0 + Angular 20.0.0 + Capacitor 8.2.0)
- Profil unique : CONTROLEUR
- Build APK automatique via GitHub Actions
- Documentation : `ctr-net-mobile/README.md`
- Fonctionnement combiné : `ctr-net-mobile/FONCTIONNEMENT_COMPLET_WEB_MOBILE.md`

