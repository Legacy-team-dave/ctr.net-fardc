# Modifications récentes

## Nettoyage automatique des caches (Mars 2026 - v1.5.0)

### Nouvelle fonction

- **`includes/functions.php`** : Ajout de `nettoyer_caches($jours_logs = 90)` qui nettoie :
  - Fichiers temporaires XLSX orphelins (> 1h dans `sys_get_temp_dir()`)
  - Fichier verrou de sauvegarde obsolète (`backup_cron.lock` > 1h)
  - Tokens `remember_token` expirés (table `utilisateurs`)
  - Tokens `reset_token` expirés (table `utilisateurs`)
  - Logs anciens (> 90 jours via `nettoyer_anciens_logs()`)

### Nouveaux fichiers

- **`includes/cache_cleanup.php`** : Script standalone pour exécution cron ou manuelle
- **`run_cache_cleanup.ps1`** : Wrapper PowerShell pour nettoyage manuel
- **`run_cache_cleanup.bat`** : Wrapper Batch pour nettoyage manuel

### Intégration au job planifié

- **`includes/backup_cron.php`** : Le nettoyage des caches s’exécute automatiquement après chaque cycle backup + purge (toutes les 8h)
- Aucune configuration supplémentaire nécessaire

### Impact

- Les fichiers temporaires ne s’accumulent plus
- Les tokens expirés sont purgés automatiquement de la base
- Les logs anciens sont nettoyés sans intervention manuelle
- Rapport JSON détaillé à chaque exécution

## Profil CONTROLEUR réservé au mobile (Mars 2026 - v1.4.0)

### Fichiers modifiés

- **`login.php`** : Connexion web bloquée pour CONTROLEUR (remember_token, session, POST)
- **`includes/header.php`** : Suppression du layout top-nav et masquage sidebar/navbar CONTROLEUR
- **`modules/controles/ajouter.php`** : Accès limité à OPERATEUR, suppression du mode contrôleur (CSS/JS)
- **`config/protected_routes.php`** : CONTROLEUR retiré des 5 routes protégées web
- **`modules/administration/ajouter_utilisateur.php`** : Badge CONTROLEUR renommé "Mobile"

### Impact

- Le profil CONTROLEUR ne peut plus se connecter côté web
- Les comptes CONTROLEUR restent créables par l'admin (usage mobile uniquement)
- L'API REST (`api/auth.php`, `api/controles.php`, `api/profil.php`) reste fonctionnelle pour l'app mobile

## Gestion des équipes de contrôle (Mars 2026 - v1.3.0)

### Nouveau fichier

- **`equipes.php`** : Page d'enregistrement des membres de l'équipe de contrôle
  - Formulaire : noms, grade, rôle
  - Liste des membres avec suppression
  - Design cohérent avec `preferences.php` (page autonome)

### Nouvelle table

- **`equipes`** (id, noms, grade, role) — table standalone sans relations
- Création automatique au premier accès (`CREATE TABLE IF NOT EXISTS`)

### Flux mis à jour

- `preferences.php` redirige maintenant vers `equipes.php` (au lieu de `index.php`)
- Flux complet : `login → preferences → equipes → index`

## API Mobile et Notifications Temps Réel (Mars 2026 - v1.2.0)

Intégration complète de l'application mobile CTR.NET avec le système web.

### API REST créée

- **`api/auth.php`** : Authentification par token Bearer (login, logout, check session)
- **`api/controles.php`** : Recherche militaire, validation contrôle avec GPS, historique
- **`api/profil.php`** : Lecture et mise à jour du profil utilisateur
- **`api/controles_poll.php`** : Endpoint de polling pour détection automatique des contrôles mobiles
- **`api/.htaccess`** : Configuration CORS pour accès mobile

### Auto-refresh Web

- `modules/controles/liste.php` interroge `api/controles_poll.php` toutes les 10 secondes
- Affichage d'un toast notification (même design que mobile) lorsqu’un contrôle mobile est détecté
- Rechargement automatique du DataTable après détection

### Toast Unifié Web/Mobile

- Design identique : gradient coloré, border-radius 12px, animation slideIn/fadeOut
- Variantes : vert (Présent), jaune (Favorable), rouge (Défavorable)
- Durée affichage : 3 secondes

### Purge Archives 60 Jours

- `purge_backup_archives()` supprime désormais les archives de plus de **60 jours** automatiquement
- Nouveau paramètre `$max_days = 60` ajouté à la fonction
- Compteur `deleted_expired` ajouté au rapport de purge
- Conservation des 30 dernières archives non identiques maintenue

---

## Audit Complet de Documentation (Avril 2026)

Mise à jour systématique de tous les fichiers de documentation racine (`.md`, `.txt`, `.json`) pour refléter le système de chiffrement v1.1.0.

### Fichiers Mis à Jour

**Guides opérationnels & administratifs** :
- `ADMIN_GUIDE.md` — Ajout: section gestion du chiffrement
- `TROUBLESHOOTING.md` — Ajout: section "Problèmes de Chiffrement" avec 7 cas pratiques
- `FAQ.md` — Ajout: section "Chiffrement (v1.1.0+)" avec 16 Q&A

**Guides techniques & architecture** :
- `ARCHITECTURE.md` — Ajout: section "Chiffrement (v1.1.0+)" avec couches, clé, fichiers
- `FONCTIONNEMENT_APPLICATION.md` — Ajout: section 14 "Chiffrement des fichiers sensibles"

**Documents de configuration & démarrage** :
- `SETUP.md` — Ajout: section "Configuration du Chiffrement" avec 3 options (GUI/CLI/PowerShell)
- `QUICKSTART.txt` — Mise à jour: ajout étape 3 initialisation chiffrement + liste outils
- `STRUCTURE.txt` — Mise à jour: section "OUTILS DE CHIFFREMENT" + dossier `bin/` + fichiers `config/`

**Guide de migration** :
- `MIGRATION.md` — Ajout: section "Migration vers v1.1.0+ (Chiffrement ajouté)" avec étapes et rollback

**Documentation de présentation** :
- `PRESENTATION_CTR_NET_FARDC.md` — Mise à jour: Slide 8 ajout mention AES-256-CBC
- `PROMPT_PRESENTATION.md` — Mise à jour: section "Contexte fonctionnel" + chiffrement

**Exemples de code** :
- `EXEMPLES.md` — Ajout: section 6 "Chiffrement - Exemples" avec 9 cas pratiques

**Configuration & métadata** :
- `manifest.json` — Mise à jour: 
  - Version: 1.0.1 → 1.1.0
  - Description: + mention chiffrement AES-256-CBC
  - Section `encryption_tools` (NEW) avec détails des outils
  - Section `maintenance_scripts` (NEW) avec scripts de backup
- `VERSION.md` — Mise à jour: Ajout entrée v1.1.0 avec 8 fichiers créés, 4 interfaces, validation status
- `README.md` — Mise à jour: Ajout section "Système de Chiffrement" avec 3 cmd quick-start

**Résumé & validation** :
- `RESUME.md` — Mise à jour: Ajout mention chiffrement v1.1.0
- `VALIDATION.md` — Mise à jour: Ajout section "Validation Système de Chiffrement" avec tests fonctionnels

**Compilation & distribution** :
- `BUILD_EXE.md` — Mise à jour: Ajout section "⚠️ Important (v1.1.0+)" sur inclusion de `.env`

### Synthèse

- **Total fichiers mis à jour** : 17 fichiers de documentation
- **Nouvelles sections** : 14 sections substantielles
- **Q&A ajoutées** : 16 questions-réponses sur le chiffrement
- **Exemples pratiques** : 9 cas d'usage du chiffrement
- **Mentions de typos** : Correction "non trovée" → "non trouvée" et "Testher" → "Tester" dans `rotate_encryption_key.ps1`

---

## Système de Chiffrement des Sources (Mars 2026)

Implémentation d'un système complet de **chiffrement AES-256-CBC** pour protéger le code source contre la lecture en clair.

### Composants Ajoutés

- **`config/encryption.php`** : Fonctions de chiffrement/déchiffrement AES-256-CBC
- **`config/encrypted_loader.php`** : Loader automatisé + autoloader pour déchiffrement au runtime
- **`bin/encrypt.php`** : CLI PHP pour gérer le chiffrement des fichiers
- **`encrypt_sources.ps1`** : Interface PowerShell pour automatiser le chiffrement
- **`ENCRYPTION.md`** : Documentation complète du système

### Fonctionnalités

- ✅ Chiffrement AES-256-CBC (standard militaire)
- ✅ Déchiffrement automatique au runtime (transparent)
- ✅ Zéro modification du code applicatif requis
- ✅ Gestion centralisée des clés via `.env`
- ✅ Scripts d'automatisation (CLI + PowerShell)
- ✅ Performance optimisée (overhead < 5ms)

### Démarrage Rapide

```bash
# Générer une clé de chiffrement
php bin/encrypt.php init

# Chiffrer les fichiers sensibles
php bin/encrypt.php encrypt

# Vérifier l'état
php bin/encrypt.php status
```

### Fichiers Chiffrés par Défaut

- `config/database.php` — Identifiants BD
- `config/load_config.php` — Configuration sensible
- `includes/functions.php` — Logique métier
- `includes/auth.php` — Authentification
- `includes/header.php` — Template principale
- `login.php` — Page de connexion
- `logout.php` — Déconnexion
- `index.php` — Page d'accueil

---

## Mise à jour documentaire (Mars 2026)

Cette passe a aligné la documentation racine sur le comportement réel du code.

### Corrections principales

- Références supprimées à des composants non présents (`app/`, `api/`, `RoleMiddleware`, controllers fictifs).
- Alignement des profils réels : `ADMIN_IG`, `OPERATEUR`, `CONTROLEUR`.
- Alignement des mentions réelles dans le module contrôles :
  - `Présent`
  - `Favorable`
  - `Défavorable`
- Mise à jour de la structure réelle du projet dans les fichiers de référence.
- Purge automatique des sauvegardes rendue paramétrable (`MaxKeep`, défaut 30) dans le job planifié.
- Ajout d'un lanceur CMD `run_backup_purge.bat` pour la purge manuelle.
- Alignement des docs techniques sur la conservation des 30 dernières archives non identiques.
- Uniformisation des toasts d'accès non autorisé (style `login.php`) sur les pages protégées.
- Exclusion explicite des pages `modules/controles/ajouter.php` et `modules/litige/ajouter.php` pour le toast global.
- Correction du bouton déconnexion avec fallback sans SweetAlert (fonctionne même sans CDN / hors ligne).
- Renforcement du blocage client sur tous les liens internes vers routes protégées (même sans attribut `data-required-role`).
- **Configuration centralisée des routes protégées** : nouveau fichier `config/protected_routes.php` qui centralise le mapping `route → rôles` autorises. Ce fichier remplace la génération dynamique dans `header.php`, améliore la synchronisation client-serveur et facilite la maintenabilité.

### Fichiers révisés

- `README.md`
- `SETUP.md`
- `ARCHITECTURE.md`
- `STRUCTURE.txt`
- `CONTROLEUR_GUIDE.md`
- `RESUME.md`
- `VERSION.md`
- `VALIDATION.md`
- `manifest.json`
- `run_backup_job.ps1`
- `setup_backup_task.ps1`
- `setup_backup_task.bat`
- `run_backup_purge.bat`
- `config/protected_routes.php` **(NEW)**
- `config/encryption.php` **(NEW)**
- `config/encrypted_loader.php` **(NEW)**
- `bin/encrypt.php` **(NEW)**
- `encrypt_sources.ps1` **(NEW)**
- `encrypt.bat` **(NEW)**
- `encrypt_init.bat` **(NEW)**
- `encrypt_all.bat` **(NEW)**
- `encrypt_status.bat` **(NEW)**
- `encrypt_list.bat` **(NEW)**
- `rotate_encryption_key.ps1` **(NEW)**
- `ENCRYPTION.md` **(NEW)**
- `ENCRYPTION_QUICKSTART.md` **(NEW)**
- `ENCRYPTION_SUMMARY.txt` **(NEW)**
- `ENCRYPTION_COMMANDS.md` **(NEW)**
- `.gitignore.encryption` **(NEW)**
- `includes/functions.php`
- `includes/header.php`
- `login.php`
