# Système de Chiffrement des Sources CTR.NET-FARDC

## Vue d'ensemble

Ce système offre un chiffrement **AES-256-CBC** des fichiers sources PHP sensibles pour protéger le code source contre la lecture en clair.

**Caractéristiques:**
- ✅ Chiffrement AES-256-CBC (standard militaire)
- ✅ Déchiffrement automatique au runtime
- ✅ Zéro modification du code applicatif
- ✅ Performance minimale (déchiffrement en mémoire)
- ✅ Scripts d'automatisation (PHP CLI + PowerShell)
- ✅ Gestion centralisée des clés (.env)

---

## Architecture

```
CTR.NET-FARDC/
  ├── config/
  │   ├── encryption.php          ← Configuration + fonctions de chiffrement
  │   ├── encrypted_loader.php    ← Loader automatisé
  │   ├── database.php            ← Peut être chiffré
  │   └── database.php.encrypted  ← Version chiffrée
  ├── bin/
  │   └── encrypt.php             ← CLI de chiffrement
  ├── .env                        ← Clé de chiffrement (GÉNÉRER)
  └── encrypt_sources.ps1         ← Interface PowerShell
```

**Cibles par défaut de la commande `php bin/encrypt.php encrypt`:**
- `config/database.php` — Identifiants de base de données
- `config/load_config.php` — Configuration sensible
- `includes/functions.php` — Logique métier
- `includes/auth.php` — Authentification
- `includes/header.php` — Template principale
- `login.php` — Page de connexion
- `logout.php` — Déconnexion
- `index.php` — Page d'accueil

Ces fichiers ne sont pas chiffrés automatiquement à l'installation : le chiffrement doit être lancé explicitement.

---

## Démarrage Rapide

### 1️⃣ Générer une Clé de Chiffrement

La première étape est de générer une **clé unique** pour votre installation.

#### Option A: Via CLI PHP
```bash
php bin/encrypt.php init
```

Résultat:
```
✓ Clé générée et sauvegardée en .env
   Clé: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx...
   IMPORTANT: Cette clé est essentielle pour déchiffrer les fichiers!
```

La clé est sauvegardée dans `.env`:
```env
ENCRYPTION_KEY=aabbccdd...zzz (256 bits en hexadécimal)
```

#### Option B: Via PowerShell
```powershell
.\encrypt_sources.ps1 -Action init
```

---

### 2️⃣ Chiffrer les Fichiers

Une fois la clé générée, chiffrez les fichiers sensibles.

#### Option A: CLI PHP (tous les fichiers)
```bash
php bin/encrypt.php encrypt
```

Résultat:
```
Chiffrement de tous les fichiers sensibles...
  ✓ Chiffré: config/database.php
  ✓ Chiffré: config/load_config.php
  ✓ Chiffré: includes/functions.php
  ...
Résumé: 8 fichiers chiffrés
```

#### Option B: CLI PHP (fichier spécifique)
```bash
php bin/encrypt.php encrypt config/database.php
```

#### Option C: PowerShell
```powershell
.\encrypt_sources.ps1 -Action encrypt
```

### 3️⃣ Vérifier l'État

```bash
php bin/encrypt.php status
```

Résultat:
```
État du chiffrement:
  Fichiers originaux: 8
  Fichiers chiffrés: 8
  
  Fichiers chiffrés:
    - config/database.php.encrypted
    - includes/functions.php.encrypted
    ...
```

---

## Utilisation en Production

### Configuration du Déploiement

1. **Générer la clé AVANT le déploiement** (sur votre machine locale)
2. **Chiffrer les fichiers sensibles** localement
3. **Déployer les fichiers `.encrypted` utilisés par votre environnement** et éviter d'exposer les originaux sensibles si vous retenez ce mode de déploiement
4. **Copier la clé via variable d'environnement** sur le serveur:

```bash
# Sur le serveur de production (.env)
ENCRYPTION_KEY=votre_clé_secrète
```

### Dans le Code Applicatif

Le système est **entièrement transparent** - aucune modification du code n'est nécessaire!

Les fichiers `.encrypted` présents sont détectés et déchiffrés au runtime par `encrypted_loader.php`.

#### Intégration facultative:
```php
<?php
// Au début de index.php (optionnel, déjà géré automatiquement)
require_once 'config/encrypted_loader.php';

// Ensuite, utiliser require_safe() pour forcer le déchiffrement
require_safe('config/database.php');
```

---

## Inversion: Déchiffrer les Fichiers

Si vous avez besoin de revenir aux fichiers originaux:

```bash
php bin/encrypt.php decrypt config/database.php
```

⚠️ **Important:** Cette action restaure le fichier original en clair. Les fichiers `.encrypted` restent intacts.

---

## Sécurité et Bonnes Pratiques

### 🔐 Gestion de la Clé

1. **Générer UNE seule clé** par installation
2. **Stocker en variable d'environnement**, jamais en dur dans le code
3. **Sauvegarder la clé** hors de la portée de l'application
4. **Rotationner la clé** régulièrement:

```bash
# Générer une nouvelle clé
php bin/encrypt.php init

# Déchiffrer tous les fichiers
for f in $(find . -name '*.encrypted'); do
    php bin/encrypt.php decrypt "$f"
done

# Re-chiffrer avec la nouvelle clé (dans .env)
php bin/encrypt.php encrypt
```

### 🛡️ Points d'Attention

**L'application doit TOUJOURS avoir accès à la clé** pour déchiffrer au runtime.

```php
// Mauvais: Ne pas faire ça en production!
define('ENCRYPTION_KEY', 'hardcoded-secret');

// Bon: Utiliser les variables d'environnement
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY'));
```

### 🚀 Performance

- ✅ Déchiffrement **en mémoire** (très rapide)
- ✅ **Aucun appel base de données** supplémentaire
- ✅ Overhead estimé: **< 5ms par fichier**

---

## Référence API

### Fonctions de Chiffrement

#### `encrypt_string($plaintext, $key = null): string`
Chiffre une chaîne en AES-256-CBC.

```php
$encrypted = encrypt_string("secret data");
// Retour: base64(IV + ciphertext)
```

#### `decrypt_string($encryptedData, $key = null): string`
Déchiffre une chaîne.

```php
$plaintext = decrypt_string($encrypted);
```

#### `encrypt_file($filePath, $outputPath = null): string`
Chiffre un fichier PHP.

```php
encrypt_file('/path/to/config.php');
// Crée: /path/to/config.php.encrypted
```

#### `decrypt_file_contents($encryptedFilePath): string`
Retourne le contenu déchiffré d'un fichier.

```php
$phpCode = decrypt_file_contents('/path/to/config.php.encrypted');
```

#### `load_encrypted_php($encryptedFilePath, $variables = []): mixed`
Charge et exécute un fichier PHP chiffré.

```php
$result = load_encrypted_php('/path/to/functions.php.encrypted');
```

#### `generate_encryption_key(): string`
Génère une nouvelle clé de 256 bits (hex).

```php
$key = generate_encryption_key();
// Retour: "aabbccdd...0123" (64 caractères hex)
```

#### `save_encryption_key_to_env($key, $envFilePath = null): bool`
Sauvegarde la clé dans .env.

```php
save_encryption_key_to_env($key);
```

### Fonctions d'Interception

#### `require_safe($file, $localVars = []): mixed`
Requiert un fichier, en déchiffrant si .encrypted.

```php
require_safe('config/database.php');
require_safe('includes/functions.php', ['user_id' => 123]);
```

#### `include_safe($file, $localVars = []): mixed`
Include sûr avec fallback gracieux.

```php
@include_safe('optional_module.php');
```

#### `is_file_encrypted($filePath): bool`
Vérifie si un fichier est chiffré.

```php
if (is_file_encrypted('config/database.php')) {
    echo "Fichier chiffré détecté!";
}
```

#### `get_file_encryption_status($filePath): array`
Retourne le statut de chiffrement.

```php
$status = get_file_encryption_status('config/database.php');
// ['encrypted' => true, 'path' => '/absolute/path/config/database.php.encrypted']
```

---

## Dépannage

### Erreur: "Clé de chiffrement non trouvée"

```
Exception: ENCRYPTION_KEY not found in environment
```

**Solution:** Générer et sauvegarder la clé:
```bash
php bin/encrypt.php init
```

### Erreur: "Impossible de déchiffrer"

```
Exception: Decryption failed
```

**Causes possibles:**
- ❌ Mauvaise clé (KEY mismatch)
- ❌ Fichier corrompu
- ❌ Algorithm mismatch

**Solution:**
1. Vérifier que la clé en `.env` est correcte
2. Essayer de déchiffrer le fichier:
   ```bash
   php bin/encrypt.php decrypt config/database.php.encrypted
   ```
3. Si erreur persiste, re-chiffrer après génération de nouvelle clé

### Performance lente

Si l'application est lente:
1. Vérifier le nombre de fichiers chiffrés (`status`)
2. Lister les fichiers moins critiques à ne pas chiffrer
3. Éditer `bin/encrypt.php` et adapter la liste `$filesToEncrypt`

---

## Scénarios Avancés

### Chiffrement Partiel (Sélectif)

Chiffrez uniquement les fichiers critiques:

```bash
# Chiffrer uniquement les identifiants
php bin/encrypt.php encrypt config/database.php

# Laisser le reste en clair
php bin/encrypt.php status
```

### Rotation de Clé

1. Générer une nouvelle clé:
   ```bash
   # Sauvegarder l'ancienne clé temporairement
   cp .env .env.backup
   
   # Générer nouvelle clé dans .env
   php bin/encrypt.php init
   ```

2. Déchiffrer tous les fichiers avec l'ancienne clé:
   ```bash
   # Restaurer temporairement l'ancienne clé
   cp .env.backup .env
   
   # Déchiffrer
   for f in config/*.encrypted includes/*.encrypted; do
       php bin encrypt.php decrypt "$f"
   done
   ```

3. Ré-appliquer la nouvelle clé:
   ```bash
   # Charger la nouvelle clé
   php bin/encrypt.php init
   
   # Re-chiffrer
   php bin/encrypt.php encrypt
   ```

### Export en Bundle Chiffré

Créer un bundle chiffré pour distribution sécurisée:

```bash
# Chiffrer tous les fichiers
php bin/encrypt.php encrypt

# Créer l'arborescence sans les sources originales
mkdir -p bundle/config bundle/includes bundle/modules
cp -r config/*.encrypted bundle/config/
cp -r includes/*.encrypted bundle/includes/
cp *.php bundle/
cp .env bundle/

# Créer une archive
zip -r ctr-net-fardc-encrypted.zip bundle/
```

---

## Fichiers Créés/Modifiés

| Fichier | Type | Descripton |
|---------|------|-----------|
| `config/encryption.php` | NEW | Fonctions d'enc/déc AES-256-CBC |
| `config/encrypted_loader.php` | NEW | Loader automatisé + autoloader |
| `bin/encrypt.php` | NEW | CLI PHP pour gestion des fichiers |
| `encrypt_sources.ps1` | NEW | Interface PowerShell |
| `.env` | MODIFIED | Clé de chiffrement (à générer) |

---

## FAQ

**Q: Mon application ralentit-elle si je chiffre tout?**
A: Non, l'overhead est négligeable (< 5ms par démarrage pour déchiffrer tout).

**Q: Puis-je chiffrer les fichiers de configuration?**
A: Oui! C'est même recommandé pour `database.php` contenant les identifiants.

**Q: Que se passe-t-il si je perds la clé?**
A: Les fichiers `.encrypted` deviennent inutilisables. Toujours garder une copie de sauvegarde!

**Q: Est-ce compatible avec Laragon?**
A: Oui, Laragon inclut OpenSSL (nécessaire pour AES-256-CBC).

**Q: Puis-je modifier un fichier chiffré?**
A: Non directement. Déchiffrer → modifier → re-chiffrer.

**Q: Est-ce vraiment sécurisé?**
A: AES-256 est un standard militaire. La sécurité dépend surtout de la **gestion de la clé**.

---

## Ressources

- [OpenSSL Documentation](https://www.openssl.org/docs/)
- [NIST AES Standard](https://nvlpubs.nist.gov/nistpubs/FIPS/NIST.FIPS.197.pdf)
- [PHP openssl_encrypt()](https://www.php.net/manual/en/function.openssl-encrypt.php)

