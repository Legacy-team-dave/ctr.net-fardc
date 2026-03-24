# 🔐 Chiffrement des Sources - Aide-Mémoire Rapide

## 📋 Commandes Rapides

### 🚀 PREMIÈRE FOIS (Initialization)

```bash
# Option 1: Windows Batch (le plus simple)
encrypt_init.bat

# Option 2: PowerShell
.\encrypt_sources.ps1 -Action init

# Option 3: PHP CLI
php bin\encrypt.php init
```

**Résultat:** Clé générée et sauvegardée dans `.env`

---

### 🔐 CHIFFRER LES FICHIERS

```bash
# Chiffrer TOUS les fichiers sensibles
encrypt_all.bat
# OU
php bin\encrypt.php encrypt

# Chiffrer UN SEUL fichier
php bin\encrypt.php encrypt config/database.php
```

---

### 📊 VÉRIFIER L'ÉTAT

```bash
# Afficher le statut complet
encrypt_status.bat
# OU
php bin\encrypt.php status

# Lister les fichiers chiffrables
encrypt_list.bat
# OU
php bin\encrypt.php list
```

---

### 🔄 DÉCHIFFRER (si besoin de revenir)

```bash
# Déchiffrer UN fichier
php bin\encrypt.php decrypt config/database.php.encrypted

# Après modification, le re-chiffrer
php bin\encrypt.php encrypt config/database.php
```

---

### 🔑 ROTATION DE CLÉ (Maintenance avancée)

```bash
# Automatisé (déchiffre → nouveau clé → re-chiffre)
.\rotate_encryption_key.ps1

# Backups créés: .encryption_backups\encryption_key_YYYY-MM-DD_HHMMSS.bak
```

---

## 📁 Toutes les Interfaces

| Interface | Fichier | Type | Utilisation |
|-----------|---------|------|-------------|
| **Batch Simple** | `encrypt_init.bat` | GUI | Double-cliquer pour générer clé |
| **Batch Simple** | `encrypt_all.bat` | GUI | Double-cliquer pour chiffrer |
| **Batch Simple** | `encrypt_status.bat` | GUI | Double-cliquer pour voir statut |
| **Batch Simple** | `encrypt_list.bat` | GUI | Double-cliquer pour lister fichiers |
| **CLI PHP** | `php bin/encrypt.php` | Terminal | Contrôle complet |
| **PowerShell** | `encrypt_sources.ps1` | Script | Automatisation avancée |
| **Rotation** | `rotate_encryption_key.ps1` | Script | Changer clé en maintenance |

---

## 🔑 Gestion de la Clé (.env)

```env
# Fichier: .env (créé automatiquement)
ENCRYPTION_KEY=febe69f5888ca2472ce75b0b8928afa0d80d82be25d88e20b15a832834499ae9
```

**⚠️ Important:**
- `JAMAIS` commiter `.env` sur Git
- Garder une copie de sauvegarde
- En production: utiliser variable d'environnement

---

## 📊 Structure des Fichiers Créés

```
ctr.net-fardc/
├── config/
│   ├── encryption.php           ← Fonctions de chiffrement
│   └── encrypted_loader.php     ← Loader automatisé
├── bin/
│   └── encrypt.php              ← CLI principal
├── encrypt_init.bat             ← Gen clé
├── encrypt_all.bat              ← Chiffrer tous
├── encrypt_status.bat           ← Statut
├── encrypt_list.bat             ← Lister
├── encrypt_sources.ps1          ← Interface PS
├── rotate_encryption_key.ps1    ← Rotation
├── ENCRYPTION.md                ← Doc complète
├── ENCRYPTION_QUICKSTART.md     ← Démarrage rapide
├── ENCRYPTION_SUMMARY.txt       ← Ce résumé
└── .env                         ← Clé (créé par init)
```

---

## 🎯 Cas d'Usage Courants

### Cas 1: Je viens de cloner le projet

```bash
# 1. Générer votre clé
php bin\encrypt.php init

# 2. Chiffrer les fichiers
php bin\encrypt.php encrypt

# 3. Vérifier
php bin\encrypt.php status
```

### Cas 2: Je dois éditer un fichier chiffré

```bash
# 1. Déchiffrer
php bin\encrypt.php decrypt config/database.php.encrypted

# 2. Modifier config/database.php avec votre éditeur

# 3. Re-chiffrer
php bin\encrypt.php encrypt config/database.php
```

### Cas 3: Je déploie en production

```bash
# 1. Chiffrer localement (dev)
php bin\encrypt.php encrypt

# 2. Envoyer UNIQUEMENT les .encrypted (+ autres fichiers)
# Ignorer: les .php originaux, la vraie clé

# 3. Sur serveur, créer .env avec la clé
ENCRYPTION_KEY=febe69f5888ca2472ce75b0b8928afa0d80d82be25d88e20b15a832834499ae9

# 4. Application démarre → déchiffrement auto
```

### Cas 4: Changer la clé de chiffrement

```bash
# Rotation automatisée (éventuellement quotidienne)
.\rotate_encryption_key.ps1

# Backup: .encryption_backups\encryption_key_*.bak
```

---

## 🛠️ Dépannage Rapide

| Problème | Solution |
|----------|----------|
| `PHP non trouvé` | Installer Laragon OU ajouter PHP au PATH |
| `Clé non trouvée` | Exécuter `php bin/encrypt.php init` en premier |
| `Impossible de déchiffrer` | Vérifier ENCRYPTION_KEY dans `.env` |
| `Fichier corrompu` | Restaurer depuis sauvegarde [.encryption_backups/] |
| `Lent au démarrage` | Normal (< 5ms), vérifier le disque |

---

## 📚 Documentation Complète

- **ENCRYPTION.md** → Guide complet + API + FAQ
- **ENCRYPTION_QUICKSTART.md** → Pour démarrer vite
- **Ce fichier** → Aide-mémoire rapide

---

## ✅ Checklist Déploiement

```
☐ Clé générée localement (php bin/encrypt.php init)
☐ Fichiers chiffrés localement (php bin/encrypt.php encrypt)
☐ Testé en dev (l'app fonctionne normalement)
☐ .env.example commité (sans vraie clé)
☐ Fichiers .encrypted prêts pour deploy
☐ Clé configurée en var d'env serveur
☐ Testé en production
☐ Backup de clé sauvegardé en lieu sûr
```

---

## 🚀 TLDR (Too Long; Didn't Read)

```bash
# Première fois:
encrypt_init.bat

# Puis:
encrypt_all.bat

# Vérifier:
encrypt_status.bat

# Et voilà! Votre code est protégé. 🔒
```

---

**Besoin d'aide?** → `php bin\encrypt.php help`
**Documentation complète?** → Ouvrir `ENCRYPTION.md`
