# Guide Complet d'Utilisation
## Application de Gestion des Effectifs Militaires FARDC

---

## Table des Matières

1. [Introduction](#introduction)
2. [Connexion à l'Application](#connexion-à-lapplication)
3. [Interface Générale](#interface-générale)
4. [Gestion des Comptes Utilisateurs](#gestion-des-comptes-utilisateurs)
5. [Gestion des Militaires](#gestion-des-militaires)
6. [Gestion des Contrôles](#gestion-des-contrôles)
7. [Rapports et Statistiques](#rapports-et-statistiques)
8. [Profil Utilisateur](#profil-utilisateur)
9. [Sécurité et Bonnes Pratiques](#sécurité-et-bonnes-pratiques)

---

## Introduction

Bienvenue dans l'application de gestion des effectifs militaires de la FARDC. Cette application permet de :

- **Gérer les utilisateurs** (pour les administrateurs)
- **Enregistrer et suivre les militaires** de votre unité
- **Documenter les contrôles** effectués sur les militaires
- **Générer des rapports** et statistiques de gestion

### Types de Profils

L'application dispose de **2 profils d'utilisateurs** dans cette version :

| Profil | Accès | Fonctionnalités |
|--------|-------|-----------------|
| **ADMIN_IG** | Complet | Gestion des utilisateurs, militaires, contrôles, rapports |
| **OPERATEUR** | Limité | Gestion des militaires et contrôles |

> Remarque : le profil **CONTROLEUR** a été retiré — ses fonctions de saisie de contrôles sont prises en charge par **OPERATEUR**.

---

## Connexion à l'Application

### Accès Initial

1. Ouvrez votre navigateur web (Chrome, Firefox, Edge)
2. Accédez à l'adresse : `http://localhost/ctr.net-fardc/login.php`
3. Entrez votre **Nom d'utilisateur** (login)
4. Entrez votre **Mot de passe**
5. Cliquez sur **"Se connecter"**

### Premier Accès

- Votre compte doit être créé par un **administrateur (ADMIN_IG)**
- Vous recevrez votre login et mot de passe temporaire
- **Changez votre mot de passe** en vous connectant pour la première fois (voir section Profil utilisateur)

### En Cas d'Oubli du Mot de Passe

1. Sur la page de connexion, cliquez sur **"Mot de passe oublié ?"**
2. Entrez votre adresse e-mail
3. Suivez les instructions reçues par e-mail
4. Créez un nouveau mot de passe sécurisé

---

## Interface Générale

### Dispositions de l'Écran

Après connexion, vous verrez :

```
┌─────────────────────────────────────────────────┐
│              BARRE DE NAVIGATION (Haut)         │
│   Logo  │ Tableau de bord │ [Autres menus]      │
├─────────────────────────────────────────────────┤
│         │                                       │
│ SIDEBAR │        CONTENU PRINCIPAL              │
│ (Menu)  │                                       │
│         │                                       │
├─────────────────────────────────────────────────┤
│                    PIED DE PAGE (Bas)           │
└─────────────────────────────────────────────────┘
```

### Menu de Navigation (Sidebar)

Le menu latéral gauche **change selon votre profil** :

#### Pour ADMIN_IG :
- 📊 **Tableau de bord** - Vue d'ensemble des statistiques
- 👥 **Militaires** - Gestion de la base de données militaires
- ✅ **Contrôles** - Suivi des contrôles effectués
- ⚙️ **Gestion des utilisateurs** - Créer/modifier des comptes
- 📈 **Rapports** - Statistiques et analyses

#### Pour OPERATEUR :
- ✅ **Contrôles** - Lister et effectuer des contrôles

#### Pour CONTROLEUR :
- ✅ **Contrôles** - Effectuer des contrôles (accès direct)

### Barre de Profil (En Haut à Droite)

- **Votre nom** et **profil** sont affichés
- Accédez à **"Mon Profil"** pour modifier vos paramètres
- Cliquez sur **"Déconnexion"** pour quitter l'application

---

## Gestion des Comptes Utilisateurs

### ⚠️ Accessible uniquement pour ADMIN_IG

#### Accès à la Gestion des Utilisateurs

1. Dans le menu latéral, cliquez sur **"⚙️ Gestion des utilisateurs"**
2. Vous verrez la **liste de tous les utilisateurs** actuels

#### Créer un Nouvel Utilisateur

1. Cliquez sur le bouton **"+ Ajouter un utilisateur"** (en haut à droite)
2. Remplissez le formulaire :

| Champ | Description | Exemple |
|-------|-------------|---------|
| **Nom complet** | Le nom officiel de la personne | Jean Dupont |
| **Login** | Identifiant de connexion (unique) | jdupont |
| **Email** | Adresse e-mail | jean.dupont@fardc.org |
| **Profil** | Rôle dans l'application | OPERATEUR |
| **Mot de passe temporaire** | Généré automatiquement | Généralement complexe |

3. Cliquez sur **"Créer le compte"**
4. L'utilisateur recevra un e-mail avec ses identifiants

#### Modifier un Utilisateur

1. Dans la liste des utilisateurs, trouvez l'utilisateur
2. Cliquez sur le bouton **"✏️ Modifier"** (colonne "Actions")
3. Modifiez les informations souhaitées
4. Cliquez sur **"Enregistrer"**

#### Désactiver un Utilisateur

1. Trouvez l'utilisateur dans la liste
2. Cliquez sur **"🔓 Désactiver"** (l'utilisateur ne pourra plus se connecter)
3. Pour réactiver : cliquez sur **"🔒 Activer"**

#### Réinitialiser le Mot de Passe d'un Utilisateur

1. Dans la liste, cliquez sur **"🔄 Réinitialiser"**
2. Un nouveau mot de passe temporaire sera généré
3. L'utilisateur recevra le nouveau mot de passe par e-mail

---

## Gestion des Militaires

### ⚠️ Accessible pour ADMIN_IG et OPERATEUR

Depuis le menu **"👥 Militaires"**

#### Voir la Liste des Militaires

1. Cliquez sur **"📋 Liste des militaires"**
2. Vous verrez un **tableau avec tous les militaires** enregistrés

**Colonnes disponibles :**
- Nom et Prénom
- Grade
- Matricule
- Statut (Actif/Inactif)
- Date d'enregistrement

#### Filtrer la Liste

- **Par statut** : Cliquez sur l'onglet "Actifs" ou "Inactifs"
- **Par texte** : Utilisez la barre de recherche en haut du tableau
- **Par grade** : Filtres disponibles (le cas échéant)

#### Ajouter un Nouveau Militaire

1. Cliquez sur **"+ Ajouter un militaire"** (page "Liste des militaires")
2. Remplissez le formulaire :

| Champ | Description |
|-------|-------------|
| **Nom complet** | Jean-Paul Durand |
| **Prénom** | Paul |
| **Grade** | Caporal, Sergent, etc. |
| **Matricule** | 123456 (identifiant unique) |
| **Unité** | Unité d'appartenance |
| **Fonction** | Poste occupé |
| **Date d'enregistrement** | Aujourd'hui (auto) |

3. Cliquez sur **"Enregistrer"**

#### Modifier les Données d'un Militaire

1. Dans la liste, trouvez le militaire
2. Cliquez sur **"✏️ Modifier"** (colonne "Actions")
3. Modifiez les informations
4. Cliquez sur **"Enregistrer"**

#### Désactiver/Supprimer un Militaire

1. Cliquez sur **"❌ Supprimer"** pour retirer un militaire
2. **Confirmation** : Un message de confirmation apparaît
3. Confirmez pour finaliser la suppression

⚠️ **Attention** : Cette action supprime tous les contrôles associés au militaire

---


### Gestion des Contrôles

### Accessible pour ADMIN_IG et OPERATEUR

#### Voir la Liste des Contrôles

1. Dans le menu, cliquez sur **"✅ Contrôles"**
2. Sélectionnez **"📋 Liste des contrôlés"**
3. Vous verrez tous les contrôles effectués

**Informations affichées :**
- Militaire contrôlé
- Date du contrôle
- Type de contrôle
- Observations
- Utilisateur qui a effectué le contrôle

#### Effectuer un Contrôle

1. Cliquez sur **"✅ Contrôles"** → **"+ Effectuer un contrôle"**
2. Remplissez le formulaire :

| Champ | Description | Exemple |
|-------|-------------|---------|
| **Militaire** | Sélectionnez le militaire | Jean Dupont |
| **Date du contrôle** | Quand a lieu le contrôle | 15/01/2024 |
| **Type de contrôle** | Visite médicale, Inspection, etc. | Inspection |
| **Localisation GPS** (optionnel) | Lieu du contrôle (si applicable) | -4.3, 15.3 |
| **Observations** | Remarques noter | Bon état, remarques... |
| **Statut** | Terminé, En cours, etc. | Terminé |

3. Cliquez sur **"Enregistrer le contrôle"**

#### Voir le Détail d'un Contrôle

1. Dans la liste des contrôles, cliquez sur le **🔍 "Voir"** ou directement sur la ligne
2. Vous verrez tous les détails du contrôle
3. Cliquez sur **"✏️ Modifier"** pour éditer
4. Cliquez sur **"❌ Supprimer"** pour supprimer

#### Filtrer les Contrôles

- **Par date** : Sélectionnez une plage de dates
- **Par militaire** : Recherchez le nom
- **Par type** : Filtrez par type de contrôle
- **Par statut** : Filtrez par état (Terminé, En cours, etc.)

---

## Rapports et Statistiques

### ⚠️ Accessible uniquement pour ADMIN_IG

#### Accès aux Rapports

1. Dans le menu, cliquez sur **"📈 Rapports"**
2. Vous verrez les **statistiques de gestion** :

**Disponible :**
- **Nombre total de militaires**
- **Nombre de militaires actifs/inactifs**
- **Nombre total de contrôles**
- **Contrôles par type**
- **Contrôles par période**
- **Utilisateurs actifs**

#### Générer un Rapport Personnalisé

1. Sur la page des rapports, cliquez sur **"📊 Générer un rapport"**
2. Sélectionnez les critères :
   - Période (date de début à date de fin)
   - Type de données (Militaires, Contrôles, etc.)
   - Format de sortie (Tableau, PDF, Excel)
3. Cliquez sur **"Générer"**
4. Le rapport sera téléchargé ou affiché à l'écran

#### Interpréter les Statistiques

- **Taux d'activité** = (Militaires actifs / Total) × 100%
- **Fréquence des contrôles** = Nombre de contrôles / Nombre de militaires
- **Couverture** = % de militaires ayant au moins un contrôle

---

## Profil Utilisateur

### Accéder à Votre Profil

1. En haut à droite, cliquez sur **"👤 Mon profil"**
2. Vous verrez vos informations personnelles

### Modifier Vos Informations

1. Cliquez sur **"✏️ Modifier mes informations"**
2. Modifiez :
   - Nom complet
   - Email
   - Numéro de téléphone (si applicable)
   - Avatar (photo de profil)
3. Cliquez sur **"Enregistrer"**

### Changer Votre Mot de Passe

1. Sur la page profil, cliquez sur **"🔐 Changer mon mot de passe"**
2. Entrez :
   - **Ancien mot de passe** (actuel)
   - **Nouveau mot de passe** (au moins 8 caractères)
   - **Confirmer le nouveau mot de passe**
3. Cliquez sur **"Mettre à jour"**

### Paramètres et Préférences

1. Cliquez sur **"⚙️ Préférences"**
2. Configurez :
   - **Thème** (clair/sombre)
   - **Langue** (Français/Anglais si disponible)
   - **Notifications e-mail**
   - **Format d'affichage** des données
3. Cliquez sur **"Enregistrer les préférences"**

---

## Sécurité et Bonnes Pratiques

### ✅ Recommandations de Sécurité

#### 1. **Mot de Passe Fort**

Un bon mot de passe doit :
- ✅ Contenir au moins **8 caractères**
- ✅ Mélanger **majuscules et minuscules**
- ✅ Inclure des **chiffres** et **symboles** (!@#$%^&*)
- ✅ Ne pas contenir votre **nom ou login**
- ❌ Ne pas utiliser des mots courants ou faciles

**Exemple faible :** `password123` ❌  
**Exemple fort :** `P@ssw0rd!Secure$2024` ✅

#### 2. **Confidentialité**

- ⚠️ Ne **partagez jamais votre mot de passe**
- ⚠️ Ne laissez **pas votre ordinateur déverrouillé**
- ⚠️ Utilisez des **réseaux Wi-Fi sécurisés** (VPN recommandé)
- ⚠️ **Déconnectez-vous** quand vous quittez le poste


#### 3. **Cession du Compte**

Si un utilisateur quitte l'organisation :
1. Un **adminiastrateur** doit **désactiver son compte**
2. Tous ses **données restent** (pour archivage)
3. Ses **accès sont supprimés** immédiatement

### ✅ Bonnes Pratiques d'Utilisation

1. **Relisez avant de valider**
   - Vérifiez les données saisies
   - Confirmez les suppressions importantes

2. **Respectez les catégories**
   - Utilisez les profils corrects
   - Ne contournez pas les restrictions d'accès

3. **Mettez à jour régulièrement**
   - Actualisez les données des militaires
   - Documentez rapidement après les actions

4. **Sauvegardez les données importantes**
   - Exportez les rapports régulièrement
   - Gardez des copies locales des données critiques

### 🚨 En Cas de Problème

Si vous rencontrez un problème :
1. Consultez la [section FAQ](FAQ.md)
2. Consultez le [Guide de Dépannage](TROUBLESHOOTING.md)
3. Contactez votre **administrateur système**
4. Signalez les **erreurs ou anomalies** immédiatement

---

## Support et Assistance

### Contacter le Support

- **E-mail support** : support@fardc.org
- **Téléphone** : +243 XX XXX XXXX
- **Horaires** : Lundi à vendredi, 8h00-17h00

### Ressources Additionnelles

- [Guide Spécifique Administrateur](ADMIN_GUIDE.md)
- [Guide Spécifique Opérateur](OPERATEUR_GUIDE.md)
- [Guide Spécifique Contrôleur](CONTROLEUR_GUIDE.md)
- [Questions Fréquentes](FAQ.md)
- [Troubleshooting](TROUBLESHOOTING.md)

---

## Historique des Versions

| Version | Date | Modifications |
|---------|------|---------------|
| 1.0 | Jan 2024 | Guide initial complet |

---

**Dernière mise à jour:** Janvier 2024  
**Auteur:** Support FARDC  
**Langue:** Français

