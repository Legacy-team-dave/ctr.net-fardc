# Versioning - CTR.NET-FARDC

## 1.1.0 (2026-03-23)

### 🔐 Système de Chiffrement AES-256-CBC Implémenté

Nouvelle sécurité pour le code source:

- Ajout système de chiffrement **AES-256-CBC** complet
- Déchiffrement transparent au runtime (en mémoire)
- 8 fichiers sensibles chiffrés par défaut:
  - `config/database.php` — Identifiants BD
  - `config/load_config.php` — Configuration
  - `includes/functions.php` — Logique métier
  - `includes/auth.php` — Authentification
  - `includes/header.php` — Template principale
  - `login.php`, `logout.php`, `index.php`

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
