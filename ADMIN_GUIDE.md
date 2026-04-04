# Guide Administrateur - CTR.NET-FARDC

## Périmètre ADMIN_IG

- Gérer les utilisateurs
- Gérer les militaires
- Superviser les contrôles
- Accéder aux rapports et écrans d'administration

## Contrôle d'accès

Le projet utilise les fonctions de contrôle d'accès dans :

- `includes/functions.php`
- `includes/auth.php`

Exemple : `check_profil(['ADMIN_IG'])`.

## Profils à administrer

- `ADMIN_IG`
- `OPERATEUR`
- `CONTROLEUR`
- `ENROLEUR`

### Règles actuelles de création de compte

- `ADMIN_IG` : actif immédiatement et mot de passe saisi manuellement ;
- `OPERATEUR`, `CONTROLEUR`, `ENROLEUR` : mot de passe par défaut `987654321` ;
- ces trois profils sont créés **inactifs** jusqu’à activation par un administrateur.

## Mentions de contrôle à connaître

- `Présent`
- `Favorable`
- `Défavorable`

## Points d'attention

- Vérifier les droits lors de la création/modification d'utilisateurs
- Contrôler la cohérence des données militaires
- Vérifier les journaux (`logs`) en cas d'incident
- La table `equipes` est gérée par l'opérateur (standalone, aucune relation)

## 🧹 Nettoyage des caches (v1.5.0+)

### Exécution automatique

Le nettoyage des caches s’exécute automatiquement toutes les 8 heures (intégré au job de sauvegarde planifié).

Cibles nettoyées automatiquement :
- Fichiers temporaires XLSX orphelins
- Fichier verrou de sauvegarde obsolète
- Tokens "remember me" et "reset password" expirés
- Logs de plus de 90 jours

### Exécution manuelle

```powershell
# Via PowerShell (défaut: 90 jours de logs conservés)
./run_cache_cleanup.ps1 -JoursLogs 90

# Via Batch
run_cache_cleanup.bat 90

# Via CLI PHP
php includes/cache_cleanup.php 90
```

Le rapport JSON retourné détaille chaque catégorie nettoyée.

## 🔐 Gestion du Chiffrement (v1.1.0+)

### Initialisation Unique

Après installation, initialiser le chiffrement une seule fois :

```powershell
php bin/encrypt.php init
```

Cela génère une clé secrète sauvegardée dans `.env`.

### Vérifier l'État du Chiffrement

```bash
php bin/encrypt.php status
```

Affiche quels fichiers sont chiffrés et lesquels ne le sont pas.

### Chiffrer les Fichiers Sensibles

```bash
php bin/encrypt.php encrypt
```

Par défaut, la commande cible la liste définie dans `bin/encrypt.php`, notamment :
- `config/database.php` (identifiants BD)
- `includes/auth.php` (logique d'authentification)
- `includes/functions.php` (logique applicative)
- ainsi que 5 autres fichiers critiques listés par la commande `php bin/encrypt.php list`

Le chiffrement n'est pas activé automatiquement à l'installation : il doit être déclenché explicitement.

### Rotation de Clé (Annuellement)

Si vous suspectez une compromission de clé :

```powershell
./rotate_encryption_key.ps1
```

Cela :
1. Sauvegarde l'ancienne clé
2. Génère une nouvelle clé
3. Déchiffre tous les fichiers avec l'ancienne
4. Re-chiffre avec la nouvelle

### Sauvegarde de Clé

**CRITIQUE** : Sauvegarder régulièrement `.env` en lieu sûr (ne pas commiter dans Git).

```powershell
# Exemple: Copier manuellement
Copy-Item .env "C:\Backups\encryption_key_$(Get-Date -Format yyyyMMdd).env"
```

### Documentation Complète

Consulter :
- [ENCRYPTION.md](ENCRYPTION.md) — API & API avancée
- [ENCRYPTION_QUICKSTART.md](ENCRYPTION_QUICKSTART.md) — Démarrage rapide
- [ENCRYPTION_COMMANDS.md](ENCRYPTION_COMMANDS.md) — Toutes les commandes
