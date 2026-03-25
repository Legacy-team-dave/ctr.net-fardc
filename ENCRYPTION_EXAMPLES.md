# 🔐 Exemples Pratiques - Chiffrement des Sources

## 📖 Exemples d'Utilisation Réelle

### Exemple 1: Première Initialisation

**Situation:** Vous venez de cloner le projet pour la première fois.

```bash
# Étape 1: Générer une clé unique (UNE SEULE FOIS)
$ php bin/encrypt.php init

Génération d'une nouvelle clé de chiffrement...
✓ Clé générée et sauvegardée en .env
   Clé: febe69f5888ca2472ce75b0b8928afa0d80d82be25d88e20b15a832834499ae9
   IMPORTANT: Cette clé est essentielle pour déchiffrer les fichiers!

# Vérifier que .env a été créé
$ type .env
ENCRYPTION_KEY=febe69f5888ca2472ce75b0b8928afa0d80d82be25d88e20b15a832834499ae9

# Étape 2: Chiffrer les fichiers sensibles
$ php bin/encrypt.php encrypt

Chiffrement de tous les fichiers sensibles...
  ✓ Chiffré: config/database.php
  ✓ Chiffré: config/load_config.php
  ✓ Chiffré: includes/functions.php
  ✓ Chiffré: includes/auth.php
  ✓ Chiffré: includes/header.php
  ✓ Chiffré: login.php
  ✓ Chiffré: logout.php
  ✓ Chiffré: index.php

Résumé: 8 fichiers chiffrés

# Étape 3: Vérifier l'état
$ php bin/encrypt.php status

État du chiffrement:
  Fichiers originaux: 8
  Fichiers chiffrés: 8
  
  Fichiers chiffrés:
    - config/database.php
    - config/load_config.php
    - includes/functions.php
    - includes/auth.php
    - includes/header.php
    - login.php
    - logout.php
    - index.php

# ✅ Prêt pour le commit et le déploiement du mode chiffré choisi
$ git add config/*.encrypted includes/*.encrypted *.encrypted
$ git commit -m "Chiffrement des sources sensibles"
```

---

### Exemple 2: Éditer un Fichier Chiffré

**Situation:** Vous devez modifier `config/database.php` qui est chiffré.

```bash
# Étape 1: Déchiffrer
$ php bin/encrypt.php decrypt config/database.php.encrypted

Déchiffrement de: config/database.php.encrypted
✓ Fichier déchiffré et restauré: database.php

# Vérifier
$ dir config/database.php
config/database.php        (1.2 KB)  ← Maintenant en clair

# Étape 2: Modifier avec votre éditeur
$ code config/database.php
# ... Changements, sauvegarde, fermer l'éditeur

# Étape 3: Re-chiffrer
$ php bin/encrypt.php encrypt config/database.php

Chiffrement de: config/database.php
✓ Fichier chiffré: database.php.encrypted

# Vérifier l'état final
$ php bin/encrypt.php status

État du chiffrement:
  Fichiers originaux: 8
  Fichiers chiffrés: 8   ← Toujours 8 chiffré

# ✅ Débogué et re-sécurisé!
```

---

### Exemple 3: Déploiement en Production

**Situation:** Vous devez déployer l'application en production avec chiffrement.

#### Côté Développement (Votre Machine)

```bash
# 1. Vous avez déjà chiffré localement
$ php bin/encrypt.php status
  Fichiers chiffrés: 8

# 2. Notez votre clé (déjà en .env, sauvegardez-la!)
$ type .env
ENCRYPTION_KEY=febe69f5888ca2472ce75b0b8928afa0d80d82be25d88e20b15a832834499ae9

# 3. Créer un .env.example SANS vraie clé (pour Git)
$ echo "ENCRYPTION_KEY=YOUR_KEY_HERE" > .env.example
$ git add .env.example
$ git add config/*.encrypted
$ git commit -m "Ready for production deployment"

# 4. Sauvegarder la clé de manière sécurisée (LastPass, Bitwarden, etc.)
```

#### Côté Production (Serveur)

```bash
# 1. Déployer les fichiers retenus pour le mode chiffré
#    En pratique, copier notamment:
#    - ✓ config/database.php.encrypted
#    - ✓ config/load_config.php.encrypted
#    - ✓ includes/functions.php.encrypted
#    - ✓ includes/auth.php.encrypted
#    - ✓ includes/header.php.encrypted
#    - ✓ login.php.encrypted
#    - ✓ logout.php.encrypted
#    - ✓ index.php.encrypted
#    - ✓ config/encryption.php (pas chiffré!)
#    - ✓ config/encrypted_loader.php (pas chiffré!)

# 2. Sur le serveur, créer .env avec la clé
$ echo "ENCRYPTION_KEY=febe69f5888ca2472ce75b0b8928afa0d80d82be25d88e20b15a832834499ae9" > /var/www/ctr.net-fardc/.env

# 3. Tester l'application
$ curl http://staging.exemple.com/
# ✓ Application démarre
# ✓ Déchiffrement automatique
# ✓ Tout fonctionne normalement!

# 4. Vérifier les fichiers chiffrés
$ ls -la /var/www/ctr.net-fardc/config/*.encrypted
  database.php.encrypted
  load_config.php.encrypted
  # ...

# ✅ Production configurée pour utiliser les fichiers chiffrés!
```

---

### Exemple 4: Ajouter un Nouveau Fichier à Chiffrer

**Situation:** Vous créez `config/secrets.php` que vous voulez chiffrer.

```bash
# 1. Créer le fichier
$ cat > config/secrets.php << 'EOF'
<?php
// Configuration secrète
define('API_KEY', 'super-secret-key');
define('DB_MASTER_KEY', 'another-secret');
EOF

# 2. Vérifier avant chiffrement
$ php bin/encrypt.php list
Fichiers chiffrables:
  [ORIGINAL] config/database.php
  [ORIGINAL] config/load_config.php
  ...
  # ⊘ secrets.php n'est pas listé (pas configuré par défaut)

# 3. Éditer bin/encrypt.php pour ajouter le fichier
# Chercher la section $filesToEncrypt et ajouter:
$filesToEncrypt = [
    'config/database.php',
    'config/load_config.php',
    'config/secrets.php',        // ← AJOUTER
    // ...
];

# 4. Chiffrer le nouveau fichier
$ php bin/encrypt.php encrypt config/secrets.php

Chiffrement de: config/secrets.php
✓ Fichier chiffré: secrets.php.encrypted

# 5. Vérifier
$ php bin/encrypt.php list
Fichiers chiffrables:
  [CHIFFRÉ] config/database.php
  ...
  [CHIFFRÉ] config/secrets.php

# ✅ Nouveau fichier protégé!
```

---

### Exemple 5: Rotation Programmée de Clé (Maintenance)

**Situation:** Vous effectuez une rotation de clé tous les 6 mois pour sécurité.

```bash
# Étape 1: Faire une sauvegarde
$ copy .env .env.backup.2026.03

# Étape 2: Lancer la rotation via le script dédié
$ .\rotate_encryption_key.ps1

╔═════════════════════════════════════════════╗
║  Rotation de Clé de Chiffrement            ║
╚═════════════════════════════════════════════╝

[1/5] Vérification de la clé actuelle...
✓ Clé actuelle détectée: febe69f5888...

[2/5] Sauvegarde de la clé actuelle...
✓ Clé sauvegardée: .encryption_backups\encryption_key_2026-03-23_153022.bak

[3/5] Génération de la nouvelle clé...
✓ Nouvelle clé générée et sauvegardée

[4/5] Déchiffrement avec l'ancienne clé...
✓ Déchiffré: config/database.php.encrypted
✓ Déchiffré: includes/functions.php.encrypted
...

[5/5] Re-chiffrement avec la nouvelle clé...
✓ Re-chiffré: config/database.php
✓ Re-chiffré: includes/functions.php
...

╔═════════════════════════════════════════════╗
║  ✓ Rotation de Clé Complètée              ║
╚═════════════════════════════════════════════╝

Résumé:
  Ancienne clé: febe69f5888...
  Nouvelle clé: a1b2c3d4e5f6...
  Sauvegarde: .encryption_backups\encryption_key_2026-03-23_153022.bak

# Étape 3: Tester l'application
$ php index.php
# ✓ Fonctionne normalement

# Étape 4: Mettre à jour la clé en production
# Envoyer la nouvelle clé (a1b2c3d4e5f6...) au serveur

# Étape 5: Committer les changements
$ git add config/*.encrypted
$ git commit -m "Rotation de clé de chiffrement (maj 6 mois)"

# ✅ Clé renouvelée!
```

---

### Exemple 6: Dépannage - Fichier Corrompu

**Situation:** Un fichier `.encrypted` est corrompu et l'app crashe.

```bash
# Étape 1: Identifier le problème
$ php index.php
Fatal error: Decryption failed in config/encrypted_loader.php on line 45
  → Fichier: config/database.php.encrypted

# Étape 2: Restaurer depuis sauvegarde
$ copy .encryption_backups\encryption_key_2026-03-22_120000.bak .env
$ php bin/encrypt.php decrypt config/database.php.encrypted
✓ Fichier déchiffré et restauré: database.php

# Étape 3: Vérifier l'intégrité
$ php -l config/database.php
No syntax errors detected in config/database.php

# Étape 4: Appliquer la clé actuelle
$ copy .env.current .env
$ php bin/encrypt.php encrypt config/database.php
✓ Fichier chiffré: database.php.encrypted

# Étape 5: Tester
$ php index.php
# ✓ Ça fonctionne!

# ✅ Récupéré du crash!
```

---

### Exemple 7: Vérification Avant Déploiement

**Situation:** Checklist prévisionnaire avant le passage en production.

```bash
# 1️⃣ Vérifier que tous les fichiers sensibles sont chiffrés
$ php bin/encrypt.php status
État du chiffrement:
  Fichiers chiffrés: 8
  [CHIFFRÉ] config/database.php        ✓ IMPORTANT
  [CHIFFRÉ] config/load_config.php     ✓ IMPORTANT
  [CHIFFRÉ] includes/functions.php     ✓ IMPORTANT
  [CHIFFRÉ] login.php                  ✓ OK
  ...

# 2️⃣ Vérifier que la clé est bien générée
$ type .env
ENCRYPTION_KEY=febe69f5... ✓ PRÉSENT

# 3️⃣ Vérifier que .env n'est PAS en Git
$ git ls-files | findstr ".env"
# aucun résultat = ✓ pas committée

# 4️⃣ Vérifier que .env.example existe (sans vraie clé)
$ type .env.example
ENCRYPTION_KEY=YOUR_KEY_HERE ✓ TEMPLATE OK

# 5️⃣ Opérabilité localement
$ php index.php
# ✓ Pas d'erreur de déchiffrement
# ✓ Application fonctionnelle

# 6️⃣ Sauvegarde de la clé
$ copy .env clé-prod-2026-03-23.txt
# Stocker dans manager (LastPass/Bitwarden/VaultKeeper)

# ✅ CHECKLIST COMPLÈTE - PRÊT POUR PRODUCTION!
```

---

## 📊 Cas d'Usage Avancés

### Migration des Fichiers Originaux

Si vous aviez déjà des fichiers `.encrypted` et voulez revenir à des originaux:

```bash
# Déchiffrer TOUS les fichiers
for file in $(find . -name "*.encrypted" -type f); do
    php bin\encrypt.php decrypt "$file"
done

# Vérifier
ls config/*.encrypted
# aucun résultat = ✓ tous décrypted
```

### Synchronisation Entre Dev et Prod

```bash
# Dev: avez-vous la dernière version des .encrypted?
$ git pull origin main
$ php bin/encrypt.php status

# Prod: mettre à jour les fichiers
$ cd /var/www/ctr.net-fardc
$ git pull origin main
# L'app redémarre automatiquement
# Les .encrypted sont déchiffrés correctement
# ✓ Sync complète
```

---

## ✅ Résumé des Exemples

| Scénario | Commande | Résultat |
|----------|----------|----------|
| **Initialiser** | `encrypt_init.bat` | Clé/. env créés |
| **Chiffrer tout** | `encrypt_all.bat` | 8 fichiers protégés |
| **Vérifier** | `encrypt_status.bat` | Statut affiché |
| **Éditer** | decrypt → edit → encrypt | Fichier modifié et re-sécurisé |
| **Déployer** | Copier .encrypted + configurer KEY | Production active |
| **Ajouter fichier** | encrypt <file> | Nouveau fichier protégé |
| **Rotation** | rotate_encryption_key.ps1 | Nouvelle clé appliquée |
| **Dépanner** | Restaurer+decrypt+encrypt | Récupération réussie |

---

📚 Pour plus d'infos → `ENCRYPTION.md` (guide complet)
