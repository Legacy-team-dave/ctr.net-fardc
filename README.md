# CTR.NET-FARDC - Application de Gestion Militaire

**Version** : 1.0  
**Statut** : Stable ✓  
**Architecture** : MVC avec Middleware de Rôles  
**Dernière mise à jour** : 02 Mars 2026

---

## 🚀 Démarrage Rapide

### Installation & Lancement (< 2 minutes)

```bash
# 1. Télécharger et extraire le projet
# 2. Double-cliquer sur: INSTALL.bat

# OU manually:
.\launch.ps1
```

### Accès à l'Application
```
URL: http://localhost:8080/ctr.net-fardc
```

---


## 👥 Comptes de Test

| Profil | Login | Mot de Passe | Accès |
|--------|-------|--------------|-------|
| **Administrateur** | admin | admin123 | Gestion complète |
| **Opérateur** | operateur | operateur123 | Gestion militaires & contrôles |
| **Contrôleur** | controleur | controleur123 | Saisie de contrôles (mobile) |

> ⚠️ **IMPORTANT**: Changez immédiatement ces mots de passe en production !

---

## 📁 Structure du Projet

```
ctr.net-fardc/
├── 📁 app/
│   ├── classes/              # Entités (User, Militaire, Controle)
│   ├── services/             # Services métier
│   ├── controllers/          # AdminController, UserController
│   └── utils/                # DB, Logger, Validator, RoleMiddleware
├── 📁 config/                # Bootstrap, Constants, Database
├── 📁 includes/              # Fonctions globales, Header, Footer, Auth
├── 📁 modules/               # Pages métier
│   ├── administration/       # Gestion utilisateurs (Admin uniquement)
│   ├── militaires/           # Gestion militaires
│   ├── controles/            # Enregistrement contrôles
│   └── rapports/             # Rapports et statistiques
├── 📁 assets/                # CSS (Barlow), JS, Images
├── 📁 uploads/               # Fichiers uploadés
├── 📄 index.php              # Dashboard
├── 📄 login.php              # Authentification
└── 📄 logout.php             # Déconnexion
```

---

## 🎯 Fonctionnalités

### Pour Administrateur (ADMIN_IG)
✅ Gestion complète des utilisateurs  
✅ Création de comptes avec assig nation de rôles  
✅ Modification et suppression d'utilisateurs  
✅ Dashboard avec statistiques  
✅ Gestion des militaires (lecture/écriture)  
✅ Logs d'audit complets  
✅ Gestion des préférences système  

### Pour Opérateurs (OPERATEUR)
✅ Gestion des militaires  
✅ Enregistrement des contrôles  
✅ Dashboard avec filtres personnalisés  
✅ Mise à jour de profil  
✅ Historique d'actions personnel  

### Pour Contrôleurs (CONTROLEUR)
✅ Enregistrement de contrôles de terrain (interface mobile optimisée)  
✅ Localisation GPS du militaire contrôlé  
✅ Saisie des mentions et observations  
✅ Mise à jour de profil  

---

## 🔐 Sécurité & Contrôle d'Accès

### Middleware RoleMiddleware
```php
// Vérifier une connexion
require_login();

// Vérifier un rôle admin
require_admin();

// Vérifier des rôles spécifiques
// Autoriser OPERATEUR et CONTROLEUR
check_profil(['OPERATEUR', 'CONTROLEUR']);

// Vérifier un rôle (fonction legacy support)
check_profil('ADMIN_IG');
```

### Authentification
- ✓ Hachage bcrypt des mots de passe
- ✓ Session sécurisée (regenerate_id)
- ✓ Tokens de réinitialisation
- ✓ IP logging et User-Agent tracking
- ✓ Logs d'audit complets

---

## 🎨 Typographie & Styles

- **Font Principale** : Barlow (Google Fonts)
- **Poids Disponibles** : 300, 400, 500, 600, 700
- **Framework CSS** : Bootstrap 5.1
- **Icons** : Font Awesome 6.0
- **Thème Couleur** : Vert Kaki Militaire
  - Primary: #5C7A4D
  - Dark: #3F5A2E

### Import dans CSS
```html
<link rel="stylesheet" href="assets/css/fonts.css">
<style>body { font-family: 'Barlow', sans-serif; }</style>
```

---

## 📊 Architecture et Séparation des Rôles

### Séparation Admin vs Users (Logique)

#### ✅ Approche Middleware
La classe **`RoleMiddleware`** gère:
- ✓ Vérifications de profil
- ✓ Redirections sécurisées
- ✓ Gestion de permissions granulaires

#### ✅ Controllers Séparés
- **`AdminController`** : Logique administrateur
- **`UserController`** : Logique utilisateur standard

#### ✅ Accès Conditionnel
```php
// Page admin uniquement
RoleMiddleware::requireAdmin('index.php');

// Utilisateurs standard
RoleMiddleware::requireRegularUser('index.php');

// Rôles mixtes
RoleMiddleware::requireRole(['ADMIN_IG', 'OPERATEUR'], 'login.php');
```

---

## 🛠️ Utilisation des Services

### Service Utilisateurs
```php
require_once 'config/bootstrap.php';

$userService = new UserService();

// Récupérer utilisateur
$user = $userService->getById($id);

// Récupérer par profil
$admins = $userService->getByProfil('ADMIN_IG');

// Vérifier credentials
$user = $userService->verifyCredentials($login, $password);
```

### Service Militaires
```php
$militaireService = new MilitaireService();

// Récupérer tous
$militaires = $militaireService->getAll();

// Filtrer par catégorie
$actifs = $militaireService->getByCategorie('ACTIF');

// Rechercher
$results = $militaireService->search('Martin');
```

### Service Contrôles
```php
$controleService = new ControleService();

// Créer contrôle
$controleService->create($controle);

// Par période
$controles = $controleService->getByDateRange($debut, $fin);

// Par opérateur
$mesControles = $controleService->getByOperateur($_SESSION['user_id']);
```

---

## 📚 Documentation Complète

- **[ARCHITECTURE.md](ARCHITECTURE.md)** - Vue générale de l'architecture
- **[MIGRATION.md](MIGRATION.md)** - Guide de migration du code legacy
- **[EXEMPLES.md](EXEMPLES.md)** - Exemples pratiques pour développeurs
- **[SETUP.md](SETUP.md)** - Guide détaillé d'installation
- **[RESUME.md](RESUME.md)** - Résumé technique complet

---

## 🚀 Lancement de l'Application

### Option 1 : Script Batch (Windows)
```batch
launch.bat
```

### Option 2 : Script PowerShell
```powershell
.\launch.ps1
```

### Option 3 : Manuel
1. Lancer `C:\laragon\laragon.exe`
2. Attendre le démarrage de MySQL/Apache
3. Ouvrir `http://localhost:8080/ctr.net-fardc`

---

## 🔄 Base de Données

### Tables Principales
- `utilisateurs` - Comptes utilisateurs
- `militaires` - Liste des militaires
- `controles` - Enregistrements de contrôle
- `logs` - Audit et traçabilité

### Connexion
- **Host** : localhost
- **User** : root (Laragon default)
- **Pass** : (vide)
- **DB** : ctr_net_fardc (auto-créée)

---

## 📞 Support & Aide

### Dépannage Courant

**Le navigateur ne s'ouvre pas**
```
-> Ouvrir manuellement http://localhost:8080/ctr.net-fardc
```

**Laragon ne démarre pas**
```
-> Cliquer droit sur INSTALL.bat > Exécuter en tant qu'administrateur
-> Ou lancer manuellement C:\laragon\laragon.exe
```

**Erreur de connexion BDD**
```
-> S'assurer que MySQL est actif dans Laragon
-> Cliquer "Start All" dans l'interface Laragon
```

**Port 8080 en utilisation**
```
-> Changer le port dans Laragon (Document Root settings)
-> Puis mettre à jour l'URL dans les scripts
```

---

## 👨‍💻 Pour les Développeurs

### Compiling en .exe (Optionnel)

Pour compiler `launch.ps1` en `.exe` :
```powershell
# Utiliser PS2EXE (gratuit)
# https://github.com/MScholtes/PS2EXE

PS2EXE.ps1 -inputFile launch.ps1 -outputFile launch.exe
```

### Personnaliser les Styles
```css
/* assets/css/custom.css */
/* Ajouter vos règles personnalisées ici */
```

### Ajouter un Nouveau Module
1. Créer `modules/monmodule/` 
2. Créer `modules/monmodule/index.php`
3. Ajouter au menu dans `includes/header.php`
4. Sécuriser avec `check_profil()` ou `RoleMiddleware`

---

## ✅ Checklist de Production

- [ ] Mots de passe par défaut changés
- [ ] Base de données sauvegardée
- [ ] Certificat HTTPS installé
- [ ] Logs archivés régulièrement
- [ ] Backups programmés
- [ ] Monitoring activé (New Relic, etc.)
- [ ] Rate limiting configuré
- [ ] CORS sécurisé

---

## 📦 Dépendances

- **PHP** 7.4+ (Inclus dans Laragon)
- **MySQL** 5.7+ (Inclus dans Laragon)
- **Bootstrap** 5.1 (CDN)
- **Font Awesome** 6.0 (CDN)
- **Barlow Font** (Google Fonts CDN)
- **Leaflet.js** (Cartes - CDN)
- **DataTables** (Tableaux - CDN)

---

## 📄 Licence

Copyright © 2026 CTR.NET-FARDC  
Tous droits réservés.

---

## 🎉 Merci!

Merci d'utiliser CTR.NET-FARDC !  
Pour des questions ou bugs, contactez le support.

**Happy Coding! 🚀**
