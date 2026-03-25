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
├── equipes.php
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

## API REST Mobile (v1.2.0+)

Endpoints dédiés à l'application mobile CTR.NET (Ionic/Angular) :

```text
api/
├── auth.php            ← Authentification token Bearer (login, logout, check)
├── controles.php       ← Recherche militaire, validation contrôle + GPS, historique
├── profil.php          ← Lecture et mise à jour du profil utilisateur
├── controles_poll.php  ← Polling temps réel (détection nouveaux contrôles)
└── .htaccess           ← CORS + sécurité
```

### Auto-refresh et Notifications

- `modules/controles/liste.php` interroge `api/controles_poll.php` toutes les 10 secondes
- Détection automatique des contrôles effectués depuis l'app mobile
- Toast notification unifié (même design web/mobile : gradient, slideIn animation)
- Rechargement DataTable automatique après détection

## Base de données

Configuration via :

- `config/database.php`
- `config/load_config.php`

Tables principales exploitées par l'application :

- `utilisateurs`
- `militaires`
- `controles`
- `logs`
- `equipes`

## 🔐 Chiffrement (v1.1.0+)

Système de chiffrement AES-256-CBC intégré pour protéger les fichiers sensibles.

### Couche de chiffrement

- **`config/encryption.php`** — Fonctions cryptographiques (encrypt/decrypt/key management)
- **`config/encrypted_loader.php`** — Autoloader qui déchiffre automatiquement les fichiers `.encrypted` au runtime
- **`bin/encrypt.php`** — Interface CLI pour gérer l'encryption (init, encrypt, decrypt, status, list)

### Fichiers chiffrables

Liste par défaut utilisée par la commande `php bin/encrypt.php encrypt` :

```
config/database.php          (identifiants BD)
includes/auth.php            (logique authentification)
includes/functions.php       (logique métier)
includes/header.php          (dynamique page)
includes/load_config.php     (configuration chargée)
login.php                    (point d'accès)
logout.php                   (point d'accès)
index.php                    (point d'accès)
```

### Configuration

Clé d'encryption stockée dans :

- `.env` : Variable `ENCRYPTION_KEY` (généré lors de l'init)
- Support variable d'environnement : `getenv('ENCRYPTION_KEY')`
- Sauvegarde automatique dans `.encryption_backups/`

### Transparence

- ✅ **Zéro impact performance** : déchiffrement en mémoire (< 5ms/startup)
- ✅ **Déchiffrement automatique** : les fichiers `.encrypted` existants sont chargés automatiquement déchiffrés
- ✅ **Pas de modifications au code applicatif** : intégration via autoloader

Le chiffrement reste une action **opt-in** : l'installation seule ne chiffre pas les fichiers tant que la commande dédiée n'a pas été exécutée.

### Interfaces d'administration

Trois interfaces pour gérer le chiffrement :

1. **CLI** : `php bin/encrypt.php [command]`
2. **GUI Batch** : `encrypt_init.bat`, `encrypt_all.bat`, `encrypt_status.bat`, `encrypt_list.bat`
3. **PowerShell** : `./encrypt_sources.ps1`, `./rotate_encryption_key.ps1`

---

## Sauvegarde et purge

Mécanisme de sauvegarde incrémentale piloté par scripts racine :

- `setup_backup_task.ps1` / `setup_backup_task.bat` : installation de la tâche planifiée (toutes les 8h)
- `run_backup_job.ps1` : exécution du job (backup + purge + nettoyage caches)
- `run_backup_purge.ps1` / `run_backup_purge.bat` : purge manuelle
- `run_cache_cleanup.ps1` / `run_cache_cleanup.bat` : nettoyage caches manuel

Règle de purge appliquée :

- suppression des archives ZIP de plus de **60 jours**
- suppression des archives ZIP identiques (même hash)
- conservation des `N` dernières archives non identiques (par défaut `30` via `MaxKeep`)

## 🧹 Nettoyage automatique des caches (v1.5.0+)

Système de nettoyage intégré au job planifié (toutes les 8h) et exécutable manuellement.

### Cibles nettoyées

| Cible | Description | Seuil |
|-------|-------------|-------|
| Fichiers temporaires XLSX | Fichiers `xlsx_*` orphelins dans `sys_get_temp_dir()` | > 1 heure |
| Fichier verrou backup | `backup_cron.lock` obsolète | > 1 heure |
| Remember tokens | Tokens `remember_token` expirés en base | Date d’expiration dépassée |
| Reset tokens | Tokens `reset_token` expirés en base | Date d’expiration dépassée |
| Logs anciens | Entrées de la table `logs` | > 90 jours (paramétrable) |

### Architecture

```text
Job planifié Windows (toutes les 8h)
    ↓
backup_cron.php
    ├── maybe_create_backup()    ← Sauvegarde incrémentale
    ├── purge_backup_archives()  ← Purge archives
    └── nettoyer_caches()        ← Nettoyage caches (v1.5.0)
```

### Interfaces

- **Automatique** : intégré au job planifié existant
- **CLI** : `php includes/cache_cleanup.php [jours_logs]`
- **PowerShell** : `run_cache_cleanup.ps1 -JoursLogs 90`
- **Batch** : `run_cache_cleanup.bat 90`
