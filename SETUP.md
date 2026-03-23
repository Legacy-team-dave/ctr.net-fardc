# Guide de Lancement - CTR.NET-FARDC

## 🚀 Démarrage Rapide

### Méthode 1 : Lanceur Batch (Simple)
```bash
double-cliquez sur: launch.bat
```

### Méthode 2 : PowerShell (Recommandé)
```powershell
# Ouvrir PowerShell (Dans le dossier du projet)
.\launch.ps1
```

### Méthode 3 : Manuel
```powershell
# 1. Lancer Laragon
C:\laragon\laragon.exe

# 2. Attendre le démarrage (5-10 secondes)

# 3. Ouvrir dans le navigateur
http://localhost:8080/ctr.net-fardc
```

---

## ⚙️ Configuration Requise

### Système d'exploitation
- **Windows 7** ou supérieur
- **Windows 10/11** (Recommandé)

### Logiciels Requis
- **Laragon** (gratuit, tout-en-un)
  - Télécharger: https://laragon.org/
  - Installation standard dans `C:\laragon\`

### Si Laragon ne fonctionne pas
- Installer **XAMPP** ou **WAMP** à la place
- Placer le dossier du projet dans `htdocs/` ou `www/`

---

## 🔑 Identifiants par Défaut

### Administrateur
- **Login** : admin
- **Mot de passe** : admin123
- **Profil** : ADMIN_IG

### Opérateur
- **Login** : operateur
- **Mot de passe** : operateur123
- **Profil** : OPERATEUR

### Contrôleur
- **Login** : controleur
- **Mot de passe** : controleur123
- **Profil** : CONTROLEUR

> ⚠️ Changez les mots de passe immédiatement en production !

---

## 📋 Architecture et Structure

### Séparation Admin vs User

#### Administrateur (ADMIN_IG)
- Gestion des utilisateurs (ajout, modification, suppression)
- Dashboard avec statistiques complètes
- Accès à tous les modules
- Les pages admin se trouvent dans `modules/administration/`

#### Utilisateurs Standard (OPERATEUR)
- Gestion des militaires et des contrôles
- Dashboard selon le profil
- Pas accès à la gestion des utilisateurs

#### Contrôleurs (CONTROLEUR)
- Saisie de contrôles uniquement (interface mobile top-nav)
- Redirigé directement vers le formulaire de contrôle après connexion
- Pas accès au tableau de bord, militaires ou rapports

### Architecture MVC
```
ctr.net-fardc/
├── app/
│   ├── classes/          # Entités (User, Militaire, Controle)
│   ├── services/         # Services métier
│   ├── controllers/      # Logique métier (AdminController, UserController)
│   └── utils/            # Utilitaires (DB, Logger, RoleMiddleware)
├── config/               # Configuration
├── includes/             # Fonctions et templating
├── modules/              # Pages métier
├── assets/               # CSS, JS, images
└── api/                  # Endpoints AJAX
```

---

## 🎨 Styles et Typographie

- **Font-Family Principal** : Barlow (Google Fonts)
- **Thème Couleurs** : Vert Kaki Militaire (#5C7A4D - #3F5A2E)
- **Framework CSS** : Bootstrap 5.1
- **Icons** : Font Awesome 6.0

---

## 🔒 Sécurité

### Middleware de Contrôle d'Accès
La classe `RoleMiddleware` gère :
- Vérification de session
- Contrôle des rôles
- Redirections sécurisées

### Utilisation dans les pages
```php
<?php
require_once 'config/bootstrap.php';

// Vérifier que l'utilisateur est connecté
require_login();

// Vérifier un rôle spécifique
require_admin();

// OU
require_role(['ADMIN_IG', 'OPERATEUR']);

// Code de la page...
?>
```

---

## 📱 Fonctionnalités Principales

### Pour Administrateur
- [x] Gestion complète des utilisateurs
- [x] Dashboard statistique
- [x] Gestion des rôles et permissions
- [x] Logs d'audit
- [x] Rapports

### Pour Opérateurs/Contrôleurs
- [x] Gestion des militaires
- [x] Enregistrement des contrôles
- [x] Mise à jour des préférences
- [x] Dashboard personnalisé
- [x] Historique d'actions

---

## 🐛 Dépannage

### Le navigateur ne s'ouvre pas
```
Solution: Ouvrir manuellement http://localhost:8080/ctr.net-fardc
```

### Laragon n'est pas installé
```
Solution: Télécharger et installer depuis https://laragon.org/
```

### Page blanche ou erreur PDO
```
Solution: Vérifier que MySQL est actif dans Laragon
         Cliquer sur "Start All" dans Laragon
```

### Erreur "Permission Denied"
```
Solution: Cliquer avec le bouton droit > "Exécuter en tant qu'administrateur"
```

---

## 📞 Support et Développement

### Fichiers Importants
- `login.php` - Page de connexion
- `index.php` - Dashboard
- `modules/administration/utilisateurs.php` - Gestion des users
- `config/database.php` - Configuration BDD

### Pour Développeurs
Consulter :
- `ARCHITECTURE.md` - Architecture générale
- `MIGRATION.md` - Guide de migration du code
- `EXEMPLES.md` - Exemples pratiques

---

## ✅ Checklist Installation

- [ ] Laragon installé et lancé
- [ ] Projet dans `C:\laragon\www\ctr.net-fardc`
- [ ] MySQL actif
- [ ] Base de données créée
- [ ] Identifiants de connexion vérifiés
- [ ] Font Barlow chargée (vérifier dans F12)
- [ ] Tous les modules accessibles

---

**Version** : 1.0  
**Dernière mise à jour** : 02 Mars 2026  
**État** : Stable ✓
