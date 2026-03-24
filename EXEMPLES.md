# Exemples réels - CTR.NET-FARDC

## 1) Protéger une page par profil

```php
<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);
```

## 2) Page réservée admin

```php
<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG']);
```

## 3) Accès saisie contrôle pour les 3 profils

```php
<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR']);
```

## 4) Enregistrement d'une mention (schéma)

Mentions effectivement utilisées dans `modules/controles/ajouter.php` :

- `Présent`
- `Favorable`
- `Défavorable`

Exemple de paramètres GET utilisés par le flux :

```text
?action=valider&matricule=...&mention=Présent
?action=valider&matricule=...&mention=Favorable
?action=valider&matricule=...&mention=Défavorable
```

## 5) Vérification de session

```php
<?php
require_once 'includes/functions.php';
require_login();
```

## 6) 🔐 Chiffrement - Exemples (v1.1.0+)

### 6.1) Initialiser le chiffrement

```bash
# CLI
php bin/encrypt.php init

# GUI (Windows)
double-cliquer encrypt_init.bat

# PowerShell
./encrypt_sources.ps1 init
```

Résultat : Fichier `.env` créé avec `ENCRYPTION_KEY=...`

### 6.2) Chiffrer tous les fichiers sensibles

```bash
# CLI
php bin/encrypt.php encrypt

# GUI (Windows)
double-cliquer encrypt_all.bat

# PowerShell
./encrypt_sources.ps1 encrypt
```

Résultat : Fichiers `.encrypted` créés pour `config/database.php`, `includes/auth.php`, etc.

### 6.3) Vérifier l'état du chiffrement

```bash
# CLI
php bin/encrypt.php status

# GUI (Windows)
double-cliquer encrypt_status.bat
```

Résultat :
```
[✓ Encrypté]   config/database.php
[✓ Encrypté]   includes/auth.php
[✓ Non chiffré] config/load_config.php (pas en liste)
...
```

### 6.4) Déchiffrer un fichier spécifique

```bash
php bin/encrypt.php decrypt config/database.php
```

Résultat : `config/database.php` revient en clair, `config/database.php.encrypted` supprimé.

### 6.5) Lister les fichiers chiffrables

```bash
php bin/encrypt.php list
```

Affiche tous les fichiers qui PEUVENT être chiffrés (par défault 8 fichiers).

### 6.6) Rotation de clé (changement complet)

```powershell
./rotate_encryption_key.ps1
```

Processus :
1. Sauvegarde ancienne clé → `.encryption_backups/encryption_key_backup_[timestamp].txt`
2. Génère nouvelle clé → `.env`
3. Déchiffre tous les fichiers avec ancienne clé
4. Re-chiffre tous avec nouvelle clé
5. Affiche rapport final

Durée : quelques secondes, l'app reste accessible.

### 6.7) Ajouter un nouveau fichier à protéger

Le fichier doit d'abord exister.

Pour l'inclure dans la liste de chiffrement, modifier `/config/encryption.php` section `get_encryptable_files()` et ajouter le chemin.

Exemple :
```php
$files = [
    'config/database.php',
    'includes/auth.php',
    'config/mon_nouveau_fichier.php',  // Ajouter ici
    ...
];
```

Puis relancer :
```bash
php bin/encrypt.php encrypt
```

### 6.8 ) Développeur : Charger automatiquement un fichier décrypté

Les fichiers avec extension `.encrypted` sont chargés automatiquement en clair :

```php
// Dans index.php ou header.php, les inclusions transparentes fonctionnent :
require_once 'config/database.php.encrypted';  // Déchiffré automatiquement
$db = new PDO(...);  // Aucune différence avec le clair
```

Aucun code à modifier : l'autoloader `/config/encrypted_loader.php` gère tout.

### 6.9) Debug : Vérifier la clé secrète

```bash
# Vérifier que .env existe et contient ENCRYPTION_KEY
type .env

# Ou en PowerShell
Get-Content .env | Select-String ENCRYPTION_KEY
```

Résultat attendu : `ENCRYPTION_KEY=aabbccdd...` (chaîne hex 64 caractères)
