# 🔐 Démarrage Rapide - Système de Chiffrement

## ⚡ En 3 étapes

### 1. Générer une Clé Unique

Choisissez **UNE SEULE** méthode :

**Windows (GUI) :**
```
Double-cliquer: encrypt_init.bat
```

**Windows (CMD) :**
```cmd
cd c:\laragon\www\ctr.net-fardc
encrypt_init.bat
```

**PowerShell :**
```powershell
cd c:\laragon\www\ctr.net-fardc
.\encrypt_sources.ps1 -Action init
```

**PHP CLI :**
```bash
php bin/encrypt.php init
```

**✓ Résultat:** La clé est sauvegardée dans `.env`

---

### 2. Chiffrer les Fichiers Sensibles

**Windows (GUI) :**
```
Double-cliquer: encrypt_all.bat
```

**Windows (CMD) :**
```cmd
encrypt_all.bat
```

**PowerShell :**
```powershell
.\encrypt_sources.ps1 -Action encrypt
```

**PHP CLI :**
```bash
php bin/encrypt.php encrypt
```

**✓ Résultat:** Les fichiers `.encrypted` sont créés

---

### 3. Vérifier l'État

**Windows (GUI) :**
```
Double-cliquer: encrypt_status.bat
```

**Windows (CMD) :**
```cmd
encrypt_status.bat
```

**PowerShell :**
```powershell
.\encrypt_sources.ps1 -Action status
```

**PHP CLI :**
```bash
php bin/encrypt.php status
```

**✓ Résultat:** Affiche les fichiers chiffrés

---

## 📁 Fichiers de Chiffrement

```
ctr.net-fardc/
  ├── config/
  │   ├── encryption.php           ← Fonctions AES-256-CBC
  │   └── encrypted_loader.php     ← Loader automatisé
  ├── bin/
  │   └── encrypt.php              ← CLI de chiffrement
  ├── encrypt.bat                  ← Lanceur général
  ├── encrypt_init.bat             ← Générer clé
  ├── encrypt_all.bat              ← Chiffrer fichiers
  ├── encrypt_status.bat           ← Afficher statut
  ├── encrypt_list.bat             ← Lister fichiers
  ├── encrypt_sources.ps1          ← Interface PowerShell
  └── ENCRYPTION.md                ← Documentation complète
```

---

## 🔑 Gestion de la Clé

### Générer une Nouvelle Clé

```bash
php bin/encrypt.php init
```

⚠️ Les fichiers chiffrés avec l'**ancienne clé** ne pourront plus être lus!

### Sauvegarder la Clé

La clé est stockée dans `.env`:
```env
ENCRYPTION_KEY=aabbccdd...zzz
```

**IMPORTANT:**  
- ✅ Garder une copie de sauvegarde
- ✅ Ne JAMAIS commiter `.env` sur Git
- ✅ Stockage sécurisé en production

### Restaurer depuis Sauvegarde

```bash
# Restaurer .env depuis sauvegarde
cp .env.backup .env

# Vérifier l'accès aux fichiers chiffrés
php bin/encrypt.php status
```

---

## 🎯 Cas d'Usage

### Scenario 1: Chiffrer pour le Déploiement

```bash
# 1. Générer clé (développement)
php bin/encrypt.php init

# 2. Chiffrer fichiers
php bin/encrypt.php encrypt

# 3. Déployer SANS les originaux
# Copier uniquement:
#   - config/database.php.encrypted
#   - includes/functions.php.encrypted
#   - ... (tous les .encrypted)

# 4. Sur serveur prod, créer .env avec la clé
echo "ENCRYPTION_KEY=..." > /var/www/ctr.net-fardc/.env
```

### Scenario 2: Maintenir du Code Original

```bash
# Garder les originaux, ajouter versions chiffrées
php bin/encrypt.php encrypt

# Pour éditer:
php bin/encrypt.php decrypt config/database.php
# ... modifier ...
php bin/encrypt.php encrypt config/database.php
```

### Scenario 3: Rotation de Clé

```bash
# Sauvegarder l'ancienne clé
cp .env .env.old

# Générer nouvelle clé (écrase dans .env)
php bin/encrypt.php init

# Déchiffrer avec l'ancienne clé
export ENCRYPTION_KEY=$(grep ENCRYPTION_KEY .env.old | cut -d= -f2)
php bin/encrypt.php decrypt config/database.php.encrypted

# Re-chiffrer avec nouvelle clé (de .env)
php bin/encrypt.php encrypt config/database.php
```

---

## ⚙️ Configuration Avancée

### Chiffrer Un Fichier Spécifique

```bash
php bin/encrypt.php encrypt includes/auth.php
```

### Déchiffrer Un Fichier

```bash
php bin/encrypt.php decrypt includes/auth.php.encrypted
```

### Ajouter des Fichiers à Chiffrer

Éditer `bin/encrypt.php`, section `$filesToEncrypt`:

```php
$filesToEncrypt = [
    'config/database.php',
    'config/custom_secret.php',  // ← Ajouter
    'includes/functions.php',
    // ...
];
```

---

## 🔍 Dépannage

| Problème | Solution |
|----------|----------|
| **"PHP non trouvé"** | Installer Laragon ou ajouter PHP au PATH |
| **"Clé non trouvée"** | Exécuter `php bin/encrypt.php init` d'abord |
| **"Impossible de déchiffrer"** | Vérifier ENCRYPTION_KEY dans .env |
| **Fichier corrompu** | Restaurer depuis sauvegarde + re-chiffrer |

---

## 📚 Ressources

- 📖 **Documentation Complète:** `ENCRYPTION.md`
- 🔗 **Configuration:** `config/encryption.php`
- 🤖 **Interface CLI:** `bin/encrypt.php`
- 🪟 **PowerShell:** `encrypt_sources.ps1`

---

## ✅ Checklist de Déploiement

Before going to production:

- [ ] Générer une clé unique pour l'environnement
- [ ] Chiffrer tous les fichiers sensibles
- [ ] Tester le déchiffrement automatique sur dev
- [ ] Sauvegarder la clé de manière sécurisée
- [ ] Ajouter `.env` à `.gitignore`
- [ ] Configurer ENCRYPTION_KEY en variable d'environnement serveur
- [ ] Tester l'application en production avec fichiers chiffrés
- [ ] Documenter la procédure de rotation de clé

---

💡 **Besoin d'aide?** Consulter `ENCRYPTION.md` pour la documentation complète.
