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

## Migration vers v1.1.0+ (Chiffrement ajouté)

### Prérequis

- OpenSSL disponible (inclus dans Laragon)
- PHP 7.4+ (pour `openssl_*` fonctions)
- Sauvegarde complète de `config/database.php` et autres fichiers sensibles

### Étapes

1. **Backup initial** :
   ```bash
   cd C:\laragon\www\ctr.net-fardc
   xcopy config backups\config_backup /E
   ```

2. **Initialiser chiffrement** (une seule fois) :
   ```bash
   php bin/encrypt.php init
   ```
   → Génère `.env` avec `ENCRYPTION_KEY`

3. **Vérifier clé générée** :
   ```bash
   # Affiche la clé (vérifier qu'elle n'est pas vide)
   type .env | findstr ENCRYPTION_KEY
   ```

4. **Tester déchiffrement** :
   ```bash
   # Sans chiffrer les fichiers, vérifier que l'app démarre
   php -S localhost:8000
   ```

5. **Sauvegarde de la clé** :
   ```bash
   # CRITIQUE : copier .env hors ligne
   xcopy .env "C:\Backup_Securise\encryption_key_[date].env"
   ```

6. **Optionnel : Activer chiffrement** :
   ```bash
   php bin/encrypt.php encrypt
   # Cela crée versions .encrypted, les originaux restent (pour rollback facile)
   ```

7. **Vérifier chiffrement** :
   ```bash
   php bin/encrypt.php status
   # Tous les fichiers critiques doivent montrer [✓ Encrypté]
   ```

8. **Tester l'app** :
   - Naviguer `http://localhost/ctr.net-fardc/login.php`
   - Effectuer une connexion test
   - Vérifier les logs (action `CONNEXION` enregistrée)

### Rollback d'urgence (v1.1.0 → v1.0.x)

1. **Déchiffrer tous les fichiers** :
   ```bash
   php bin/encrypt.php decrypt
   ```

2. **Supprimer `.env`** :
   ```bash
   del .env
   ```

3. **Revenir à ancienne version** de code (git/backup)

4. **Redémarrer l'app**

### Important après migration

- ⚠️ **Ne pas oublier de sauvegarder `.env`** (sans lui, données inaccessibles)
- Git ignore automatiquement `.env` (voir `.gitignore.encryption`)
- Documenter la localisation du backup de clé
- Former le personnel d'administration à la gestion de la clé
- Tester la rotation de clé annuellement

## Migration vers v1.5.0+ (Nettoyage caches ajouté)

### Nouveaux fichiers

- `includes/cache_cleanup.php` — Script standalone de nettoyage
- `run_cache_cleanup.ps1` — Wrapper PowerShell
- `run_cache_cleanup.bat` — Wrapper Batch

### Changements

- `includes/functions.php` — Nouvelle fonction `nettoyer_caches()`
- `includes/backup_cron.php` — Appel automatique de `nettoyer_caches()` après purge

### Aucune action requise

Le nettoyage des caches est automatiquement intégré au job planifié existant. Si la tâche planifiée est déjà installée, le nettoyage s'exécutera au prochain cycle (toutes les 8h).
