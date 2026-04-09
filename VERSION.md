# Versioning - CTR.NET-FARDC

## 1.6.0 (2026-04-04)

### 📚 Documentation, enrôlement et comptes mobiles

- ajout du rôle `ENROLEUR` dans la documentation fonctionnelle ;
- documentation alignée sur les deux applications mobiles `CTR.NET` et `ENROL.NET` ;
- rappel des règles de création de compte : `987654321` pour `OPERATEUR`, `CONTROLEUR`, `ENROLEUR` ;
- comptes non-admin créés inactifs par défaut ;
- messages de connexion mobile clarifiés côté API.

## 1.5.0 (2026-03-24)

### 🧹 Nettoyage automatique des caches

- Nouvelle fonction `nettoyer_caches()` dans `includes/functions.php`
- Nettoyage intégré au job planifié (exécuté après backup + purge, toutes les 8h)
- Nouveau script standalone `includes/cache_cleanup.php`
- Scripts manuels : `run_cache_cleanup.ps1` / `run_cache_cleanup.bat`
- Cibles nettoyées :
  - Fichiers temporaires XLSX orphelins (> 1h)
  - Fichier verrou de sauvegarde obsolète (> 1h)
  - Tokens "remember me" expirés (table `utilisateurs`)
  - Tokens de reset de mot de passe expirés (table `utilisateurs`)
  - Logs anciens (> 90 jours par défaut)
- Rapport détaillé en retour (JSON)
- Documentation mise à jour (14 fichiers)

## 1.4.7 (2026-04-08)

- Harmonisation de version : alignement sur la version 1.4.7 pour cohérence avec les autres applications (mobile, web, front).
- Voir détails dans les fichiers VERSION.md des autres projets pour la liste complète des changements.

## 1.4.0 (2026-03-24)

### 🔒 Profil CONTROLEUR réservé au mobile

- Connexion web bloquée pour le profil `CONTROLEUR` (3 points d'entrée sécurisés)
- Suppression du layout `layout-top-nav` et du mode contrôleur (`controleur-mode`)
- Suppression de `CONTROLEUR` des routes protégées web
- API REST conservée pour l'application mobile `CTR.NET`
- Badge profil `CONTROLEUR` renommé "Mobile" dans l'administration
- Documentation mise à jour (9 fichiers)

## 1.3.0 (2026-03-24)

### 👥 Gestion des équipes de contrôle

- Nouveau fichier `equipes.php` — page d'enregistrement des membres d'équipe
- Nouvelle table `equipes` (id, noms, grade, role) — table standalone sans relations
- Flux de première configuration : `preferences.php` → `equipes.php` → `index.php`
- Design cohérent avec `preferences.php` (page autonome, thème vert FARDC)

## 1.2.0 (2026-03-24)

### 📱 API REST pour Application Mobile

Nouvelle API REST pour l'application mobile CTR.NET :

- `api/auth.php` — Authentification par token Bearer (login, logout, check session)
- `api/controles.php` — Recherche militaire, validation contrôle, historique
- `api/profil.php` — Lecture et mise à jour du profil utilisateur
- `api/controles_poll.php` — Endpoint de polling pour détection temps réel des contrôles mobiles
- `api/.htaccess` — Configuration CORS + sécurité

### 🔄 Auto-refresh et Notifications Temps Réel

- Polling automatique dans `modules/controles/liste.php` (toutes les 10 secondes)
- Toast notification côté web lorsqu’un contrôle est effectué depuis le mobile
- Design toast unifié web/mobile : gradient coloré, border-radius 12px, slideIn animation
- Rechargement automatique du DataTable après détection d'un nouveau contrôle

### 🗑️ Purge Automatique 60 Jours

- `purge_backup_archives()` supprime désormais les archives de plus de **60 jours**
- Nouveau paramètre `$max_days` (défaut: 60) en plus de `$max_unique_keep` (défaut: 30)
- Compteur `deleted_expired` dans le rapport de purge

---

## 1.1.0 (2026-03-23)

### 🔐 Système de Chiffrement AES-256-CBC Implémenté

Nouvelle sécurité pour le code source:

- Ajout système de chiffrement **AES-256-CBC** complet
- Déchiffrement transparent au runtime (en mémoire)
- La commande `php bin/encrypt.php encrypt` cible par défaut 8 fichiers sensibles:
  - `config/database.php` — Identifiants BD
  - `config/load_config.php` — Configuration
  - `includes/functions.php` — Logique métier
  - `includes/auth.php` — Authentification
  - `includes/header.php` — Template principale
  - `login.php`, `logout.php`, `index.php`

  Le chiffrement reste manuel : l'installation n'active pas automatiquement ces fichiers `.encrypted`.

### Interfaces de Gestion

- **CLI PHP:** `php bin/encrypt.php <cmd>` (init, encrypt, status, list, decrypt)
- **Batch GUI:** `encrypt_init.bat`, `encrypt_all.bat`, `encrypt_status.bat` (point-and-click)
- **PowerShell:** `encrypt_sources.ps1 -Action <action>` (interface PowerShell)
- **Maintenance:** `rotate_encryption_key.ps1` (rotation de clé)

### Documentation Chiffrement

8 fichiers de documentation pour implantation & support:
- `ENCRYPTION.md` — Guide complet & référence API
- `ENCRYPTION_QUICKSTART.md` — Démarrage 3 étapes
- `ENCRYPTION_COMMANDS.md` — Aide-mémoire commandes
- `ENCRYPTION_EXAMPLES.md` — Cas pratiques réels
- `ENCRYPTION_IMPLEMENTATION_GUIDE.md` — Pour IT/DevOps
- `ENCRYPTION_SUMMARY.txt` — Résumé visuel
- `ENCRYPTION_STRUCTURE.txt` — Architecture système
- `ENCRYPTION_INDEX.txt` — Index documentation

### Configuration

- `config/encryption.php` — Fonctions crypto (5.5 KB)
- `config/encrypted_loader.php` — Loader auto (5.5 KB)
- `.env` — Clé secrète (auto-générée)
- `.gitignore.encryption` — Règles Git

### Validation

- ✅ Syntaxe PHP: 100% (3/3 fichiers)
- ✅ Clé AES-256: Générée & testée
- ✅ Déchiffrement: Transparent & fonctionnel
- ✅ Performance: < 5ms/démarrage

---

## 1.0.1 (2026-03-23)

### Documentation alignée au code réel

- Profils confirmés : `ADMIN_IG`, `OPERATEUR`, `CONTROLEUR`
- Mentions confirmées dans `modules/controles/ajouter.php` :
  - `Présent`
  - `Favorable`
  - `Défavorable`
- Nettoyage des références d'architecture non présentes dans le dépôt

## 1.0.0

Version opérationnelle initiale de l'application.
