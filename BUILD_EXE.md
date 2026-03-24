-# 🔧 Guide de Compilation .EXE - CTR.NET-FARDC

## ⚠️ Important (v1.1.0+)

Si le chiffrement est activé, vous **DEVEZ** inclure le fichier `.env` dans le package de distribution!

```bash
# Vérifier le statut du chiffrement
php bin/encrypt.php status

# Si des fichiers sont [✓ Encrypté], le .env est CRITIQUE
```

---

## 📦 Préparation

### Prérequis
- PowerShell 5.0+
- .NET Framework 4.5+ (inclus dans Windows 10+)
- PS2EXE (outil de compilation)

---

## ✅ Méthode 1 : Utiliser PS2EXE (Recommandé)

### Étape 1 : Télécharger PS2EXE
```powershell
# Lancer PowerShell en tant qu'Administrateur
# Créer un dossier
mkdir C:\PS2EXE
cd C:\PS2EXE

# Télécharger PS2EXE
Invoke-WebRequest -Uri "https://github.com/MScholtes/PS2EXE/raw/master/ps2exe.ps1" -OutFile ps2exe.ps1
```

### Étape 2 : Compiler launch.ps1 en .exe
```powershell
# Se placer dans le répertoire du projet
cd "C:\laragon\www\ctr.net-fardc"

# Compiler
C:\PS2EXE\ps2exe.ps1 -InputFile launch.ps1 -OutputFile ctr-launcher.exe

# Optionnel : Ajouter une icône personnalisée
C:\PS2EXE\ps2exe.ps1 -InputFile launch.ps1 -OutputFile ctr-launcher.exe -IconFile "assets\img\icon.ico"
```

### Résultat
```
✓ ctr-launcher.exe (créé dans le dossier du projet)
```

---

## ✅ Méthode 2 : Utiliser Advanced Installer (Pro)

### Alternative Commercial
- **Outil** : Advanced Installer (https://www.advancedinstaller.com/)
- **Avantages** :
  -Interface graphique
  - Génère un vrai installeur MSI
  - Création de raccourcis automatique
  - Gestion des dépendances

### Étapes
1. Créer nouveau projet "App Modeler"
2. Pointer vers `launch.ps1`
3. Générer `.exe` via "Build"

---

## ✅ Méthode 3 : Script Batch Compilé (Plus Simple)

### Utiliser BatchToExe (Gratuit)
```
Télécharger: https://www.f2ko.de/en/b2e.php
Sélectionner: launch.bat
Compiler: → launch.exe
```

**C'est la méthode la plus simple!**

---

## 🎯 Après Compilation

### Tester le .exe
```batch
# Double-cliquer sur ctr-launcher.exe
# L'application doit démarrer normalement
```

### Personnaliser
```batch
# Pour ajouter une icône personnalisée (avant compilation):
# 1. Préparer l'icône: assets/img/icon.ico
# 2. Passer à ps2exe -IconFile "assets\img\icon.ico"
```

### Distribuer
```
✓ Copier le .exe n'importe où
✓ Les utilisateurs n'ont besoin que du .exe
✓ (Laragon doit être installé sur la machine cible)
```

---

## 📦 Distribution Complète (Alternative)

### Package Portable avec NSIS
```batch
# 1. Installer NSIS: https://nsis.sourceforge.io/
# 2. Créer script d'installation: installer.nsi
# 3. Compiler: makensis.exe installer.nsi
# 4. Résultat: CTR-Setup.exe
```

### Contenu du Package
```
ctr-launcher.exe                (lanceur)
ctr-setup.exe                   (installeur)
README.md, QUICKSTART.txt       (docs)
```

---

## 🚀 Déploiement Utilisateur

### Version Simple Distribuée
```
1. User télécharge: ctr-launcher.exe
2. User double-clique
3. Application démarre ✓
```

**Prérequis unique** : Laragon installé (`C:\laragon\`)

### Version Complète
```
1. User execute: CTR-Setup.exe
   - Installe tout
   - Crée raccourci Windows
2. Clique sur raccourci de bureau
3. Application démarre ✓
```

---

## 🔧 Dépannage Compilation

### Erreur : "PS2EXE not found"
```powershell
# Vérifier le chemin
Get-Item "C:\PS2EXE\ps2exe.ps1"

# Si absent, télécharger directement depuis GitHub
```

### Erreur : "Cannot find path"
```powershell
# Vérifier le chemin vers launch.ps1
Get-Item "C:\laragon\www\ctr.net-fardc\launch.ps1"

# Utiliser des chemins absolus
```

### Erreur : "Execution policy"
```powershell
# Exécuter en tant qu'Admin, puis:
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope LocalMachine
C:\PS2EXE\ps2exe.ps1 -InputFile launch.ps1 -OutputFile ctr-launcher.exe
```

---

## 📊 Comparaison des Méthodes

| Méthode | Complexité | Résultat | Temps |
|---------|-----------|----------|-------|
| **Batch2Exe** | ⭐ Très facile | .exe simple | 2 min |
| **PS2EXE** | ⭐⭐ Facile | .exe PowerShell | 5 min |
| **Advanced Installer** | ⭐⭐⭐⭐ Complexe | MSI pro | 20 min |
| **NSIS** | ⭐⭐⭐ Moyen | Installeur | 15 min |

**Recommandé** : Batch2Exe ou PS2EXE

---

## ✨ Version Finale Distribuée

### Fichiers Min imum Requis
```
ctr-launcher.exe          (lanceur - compilé)
README.md                 (documentation)
QUICKSTART.txt            (guide rapide)
```

### Fichiers Optionnels
```
LICENSE.txt               (licence)
MODIFICATIONS.md          (changelog)
setup-guide.pdf           (PDF guide)
```

---

## 📝 Instructions Utilisateur

### "Comment lancer l'application ?"

```
AVANT: (Codes complexes)
  • Installer Laragon
  • Ouvrir PowerShell
  • .\launch.ps1

APRÈS: (Simple!)
  • Double-cliquer: ctr-launcher.exe
  • L'app démarre ✓
```

---

## 🎉 Résultat

```
✓ Fichier unique distributable : ctr-launcher.exe
✓ Installation nulle pour l'utilisateur  
✓ Lancement simple (double-clic)
✓ Prérequis unique : Laragon
✓ Professionnel et moderne
```

---

**Compilation Terminée! 🎊**

Pour les utilisateurs finaux, fournir uniquement:
- `ctr-launcher.exe`
- `README.md` ou `QUICKSTART.txt`

---

*Dernière mise à jour : 02 Mars 2026*
