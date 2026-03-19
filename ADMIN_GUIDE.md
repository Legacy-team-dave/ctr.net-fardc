# Guide Administrateur FARDC
## Gestion Complète de l'Application

---

## Table des Matières

1. [Introduction Admin](#introduction)
2. [Dashboard Administrateur](#dashboard-administrateur)
3. [Gestion des Utilisateurs](#gestion-complète-des-utilisateurs)
4. [Gestion des Militaires](#gestion-avancée-des-militaires)
5. [Supervision des Contrôles](#supervision-des-contrôles)
6. [Rapports et Analyses](#rapports-et-analyses-avancées)
7. [Maintenance et Configuration](#maintenance-et-configuration)
8. [Sécurité et Audit](#sécurité-et-audit)
9. [Dépannage Admin](#dépannage-administrateur)

---

## Introduction

### Rôle de l'Administrateur

L'administrateur **ADMIN_IG** dispose de **droits complets** sur l'application :

- ✅ Créer, modifier et gérer les utilisateurs
- ✅ Gérer la base de données des militaires
- ✅ Superviser tous les contrôles effectués
- ✅ Générer rapports et statistiques
- ✅ Configurer l'application
- ✅ Consulter les logs et audits

### Responsabilités Principales

1. **Gestion des Comptes** : Créer/modifier/supprimer les utilisateurs
2. **Conformité** : Assurer le respect des procédures
3. **Données** : Intégrité et sauvegarde des données
4. **Sécurité** : Monitoring et logs d'accès
5. **Support** : Assister les utilisateurs en cas de problème

---

## Dashboard Administrateur

### Accès au Dashboard

1. Après connexion, vous êtes redirigé au **Dashboard** (Tableau de bord)
2. Vous avez un **accès complet** à toutes les fonctionnalités

### Vue d'Ensemble du Dashboard

Le dashboard affiche :

```
STATISTIQUES GLOBALES
├── Nombre total d'utilisateurs actifs
├── Nombre de militaires dans le système
├── Contrôles effectués ce mois
└── Tendances et graphiques

ALERTES ET NOTIFICATIONS
├── Comptes expirés ou à renouveler
├── Anomalies détectées
└── Accès déclinés (tentatives non autorisées)

ACTIONS RAPIDES
├── Ajouter un utilisateur
├── Ajouter un militaire
├── Générer un rapport
└── Voir les logs d'accès
```

### Widgets et Personnalisation

- Vous pouvez **réorganiser les widgets** du dashboard
- Cliquez sur **"Épingler"** pour verrouiller une section
- Cliquez sur **"Masquer"** pour retirer une section
- Les préférences sont **sauvegardées par utilisateur**

---

## Gestion Complète des Utilisateurs

### Section "Gestion des Utilisateurs"

Accès : Menu latéral → **"⚙️ Gestion des utilisateurs"**

### Vue d'Ensemble des Utilisateurs

La page liste **tous les utilisateurs**actifs et inactifs :

**Colonnes :**

| Colonne | Description |
|---------|-------------|
| **Login** | Identifiant d'accès |
| **Nom Complet** | Nom officiel |
| **Email** | Adresse e-mail |
| **Profil/Rôle** | ADMIN_IG / OPERATEUR / CONTROLEUR |
| **Statut** | Actif / Inactif |
| **Dernier accès** | Date/heure de la dernière connexion |
| **Actions** | Modifier, Désactiver, Supprimer, etc. |

### Créer un Nouvel Utilisateur

#### Formulaire de Création

1. Cliquez sur **"+ Ajouter un utilisateur"**
2. Remplissez les champs obligatoires :

```
┌─────────────────────────────────────────┐
│ CRÉER UN NOUVEL UTILISATEUR             │
├─────────────────────────────────────────┤
│                                         │
│ Nom complet *          [___________]    │
│ Login *                [___________]    │
│ Email *                [___________]    │
│ Profil *               [ADMIN_IG ▼]     │
│ Actif *                [☑] Oui          │
│                                         │
│ [Créer]  [Annuler]                     │
└─────────────────────────────────────────┘
```

#### Directives pour Chaque Champ

**Nom complet** :
- Format : Nom Prénom ou Prénom Nom
- Exemple : David AKWAS
- Caractères autorisés : A-Z, a-z, espaces, traits d'union

**Login** :
- Identifiant **unique** (pas de doublons)
- Format : Lettres minuscules + chiffres + point/tiret
- Exemple : `jdurand`, `j.durand2024`, `jdurand-001`
- ⚠️ Ne peut pas être changé après création

**Email** :
- Format valide : `nom@domaine.com`
- Sera utilisé pour les notifications
- L'utilisateur recevra son mot de passe par email

**Profil/Rôle** :
- **ADMIN_IG** : Accès complet (gestion des utilisateurs)
- **OPERATEUR** : Gestion militaires et contrôles
- **CONTROLEUR** : Effectuer contrôles uniquement

**Statut Actif** :
- ☑ Actif : L'utilisateur peut se connecter
- ☐ Inactif : L'utilisateur **ne peut pas se connecter**

#### Confirmation et Mot de Passe

Après création :
- Un **mot de passe temporaire** est généré automatiquement
- L'utilisateur reçoit un **e-mail de notification**
- Il **doit changer** son mot de passe à la première connexion

### Modifier un Utilisateur Existant

1. Dans la liste, cliquez sur **"✏️ Modifier"** (ligne de l'utilisateur)
2. Vous pouvez modifier :
   - ✅ Nom complet
   - ✅ Email
   - ✅ Profil/Rôle
   - ✅ Statut (Actif/Inactif)
   - ❌ Login (impossible à modifier)

3. Cliquez sur **"Enregistrer"** ou **"Annuler"**

### Actions sur les Utilisateurs

#### Réinitialiser le Mot de Passe

```
🔄 RÉINITIALISER LE MOT DE PASSE
│
├─ Génère un nouveau mot de passe temporaire
├─ Envoie un e-mail à l'utilisateur
└─ L'utilisateur doit changer au prochain accès
```

1. Cliquez sur **"🔄 Réinitialiser"**
2. Confirmez l'action
3. Un nouvel e-mail est envoyé immédiatement

#### Désactiver un Utilisateur

```
🔓 DÉSACTIVER
│
├─ L'utilisateur NE PEUT PLUS se connecter
├─ Son compte reste en base (archivage)
├─ Ses données sont conservées
└─ Peut être réactivé ultérieurement
```

1. Cliquez sur **"🔓 Désactiver"**
2. Confirmez
3. L'utilisateur reçoit une notification

#### Réactiver un Utilisateur

- Cliquez sur **"🔒 Activer"** pour réactiver
- L'utilisateur peut à nouveau se connecter

#### Supprimer un Utilisateur

```
⚠️ ATTENTION : ACTION IRRÉVERSIBLE
│
├─ Supprime complètement le compte
├─ Les données de l'utilisateur sont supprimées
├─ Les données qu'il a créées restent
└─ NE PEUT PAS être annulée
```

1. Cliquez sur **"❌ Supprimer"**
2. Un **formulaire de confirmation** apparaît
3. Vous devez taper le **login de l'utilisateur** pour confirmer
4. Cliquez sur **"Confirmer la suppression"**

### Filtrer et Rechercher les Utilisateurs

**Barre de recherche** (en haut du tableau) :
```
Rechercher : [jean      ]
│
└─ Cherche dans : Login, Nom, Email
```

**Filtres disponibles** :
- **Par profil** : ADMIN_IG, OPERATEUR, CONTROLEUR
- **Par statut** : Actif, Inactif
- **Par date de création** : Plage de dates

**Tri** :
- Cliquez sur les en-têtes de colonnes
- Direction ascendante/descendante

### Export des Utilisateurs

1. Cliquez sur **"📥 Exporter"**
2. Choisissez le format :
   - 📊 CSV (pour Excel)
   - 📋 JSON (pour applications)
   - 📄 PDF (pour impression)

3. Le fichier est téléchargé sur votre ordinateur

---

## Gestion Avancée des Militaires

### Accès à la Gestion des Militaires

Menu → **"👥 Militaires"**

### Vues Disponibles

#### 1. Liste Complète des Militaires
```
Affiche TOUS les militaires (actifs & inactifs)
```

#### 2. Militaires Actifs
```
Affiche uniquement les militaires avec statut "Actif"
- Utilisé pour les opérations courantes
```

#### 3. Militaires Inactifs
```
Affiche uniquement les militaires avec statut "Inactif"
- Pour archivage et historique
```

### Gestion Avancée des Données Militaires

#### Importer une Liste de Militaires

1. Cliquez sur **"📥 Importer des militaires"**
2. Choisissez un fichier Excel/CSV :

```
FICHIER ATTENDU :
┌──────────────────────────────────────┐
│ NOM    | PRENOM | GRADE | MATRICULE │
├──────────────────────────────────────┤
│ Dupont | Jean   | Cpl   | 123456    │
│ Martin | Paul   | Sgt   | 123457    │
│ ...                                  │
└──────────────────────────────────────┘
```

3. Vérifiez le **mapping des colonnes** (correspondance)
4. Cliquez sur **"Importer"**
5. Les militaires sont **ajoutés ou mis à jour**

#### Double-Vérification Avant Import

L'application affiche:
- ✅ **Nombre de nouveaux militaires**
- ⚠️ **Doublons détectés** (même matricule)
- ❌ **Erreurs ou données manquantes**

#### Exporter les Données des Militaires

1. Cliquez sur **"📥 Exporter"**
2. Sélectionnez le format :
   - **Excel** (.xlsx) : Données + formatage
   - **CSV** : Format texte simple
   - **PDF** : Rapport d'impression

3. Sélectionnez les colonnes à exporter (optionnel)
4. Cliquez sur **"Télécharger"**

#### Fusion de Doublon (Militaire Enregistré Deux Fois)

Rarement utilisée mais critère :

1. Sélectionnez les 2 militaires (checkboxes)
2. Cliquez sur **"🔄 Fusionner"**
3. Choisissez les données **dominantes** (de quel militaire garder certains champs)
4. Les contrôles sont **tous fusionnés** vers un seul militaire
5. L'ancien profil est **supprimé**

#### Archivage Massif

Si beaucoup de militaires sont inactifs/à retirer :

1. **Filtrez** par unité ou statut
2. Sélectionnez **plusieurs militaires** (checkbox en haut du tableau = tous)
3. Cliquez sur **"📦 Archiver"**
4. Confirmez
5. Les militaires deviennent **inactifs**

### Analyse des Données Militaires

#### Rapport de Couverture

Menu → **"📈 Rapports"** :

```
RAPPORT DE COUVERTURE
├─ Nombre total de militaires : 1500
├─ Militaires avec au moins 1 contrôle : 1234 (82%)
├─ Militaires sans contrôle : 266 (18%)
│
└─ RECOMMANDATION : Augmenter la fréquence des contrôles
```

#### Détection d'Anomalies

L'application alerte automatiquement si :
- ❌ Militaire avec **matricule dupliqué**
- ⚠️ Données **incomplètes** (grade absent, unité inconnue, etc.)
- 🔔 **Pas de contrôle depuis plus de X mois**

---

## Contrôles Effectués - Supervision

### Tableau de Bord des Contrôles

Menu → **"✅ Contrôles"** → **"📋 Liste des contrôlés"**

### Affichage et Filtres

**Colonnes du Tableau :**
- Militaire (nom + grade)
- Date du contrôle
- Type de contrôle
- Observations
- Utilisateur responsable
- Statut

**Filtres avancés :**

```
Filtrer par :
├─ Plage de dates      [01/01/2024] → [31/01/2024]
├─ Type de contrôle    [Visite médi ▼]
├─ Statut              [Terminé ▼]
├─ Utilisateur         [Jean Dupont ▼]
│
└─ [Appliquer les filtres]
```

### Audit des Contrôles

#### Voir Qui a Effectué le Contrôle

Pour chaque contrôle :
- **Utilisateur** : Qui a enregistré le contrôle
- **Date/Heure** : Quand exactement
- **Localisation GPS** : Si renseignée
- **Observations** : Notes écrites

#### Vérifier la Conformité

L'application peut alerter si :
- ⚠️ Contrôle effectué par quelqu'un d'une autre unité
- ⚠️ Gap temporel anormal entre contrôles
- ⚠️ Observations manquantes ou suspectes

### Modification Après le Fait (Correction)

En cas d'**erreur dans un contrôle enregistré** :

1. Trouvez le contrôle dans la liste
2. Cliquez sur **"✏️ Modifier"**
3. Modifiez les données
4. Cliquez sur **"Enregistrer"**

⚠️ **Note** : Un **log d'audit** enregistre qui a modifié quoi et quand

### Suppression de Contrôles

En cas d'erreur grave (contrôle enregistré par erreur) :

1. Cliquez sur **"❌ Supprimer"**
2. Confirmez
3. Le contrôle est **supprimé** et **enregistré en log**

---

## Rapports et Analyses Avancées

### Accès aux Rapports

Menu → **"📈 Rapports"**

### Rapports Pré-Générés

L'application propose des reportage automatisées :

#### 1. **Bilan Grand Public** (Vue d'ensemble)

```
STATISTIQUES GLOBALES
├─ Total militaires enregistrés
├─ Militaires actifs vs inactifs
├─ Nombre de contrôles par mois
├─ Taux de couverture (% contrôlés)
└─ Tendances sur 12 mois
```

#### 2. **Rapport par Unité**

```
RAPPORT PAR UNITÉ
├─ Unité 1
│  ├─ Nombre de militaires : 150
│  ├─ Nombre de contrôles : 120
│  └─ Taux de couverture : 80%
│
├─ Unité 2
│  └─ ...
```

#### 3. **Rapport par Profil d'Utilisateur**

```
ACTIVITÉ DES UTILISATEURS
├─ Admin Jean (160 contrôles)
├─ Opérateur Michel (95 contrôles)
├─ Contrôleur Paul (42 contrôles)
└─ Utilisateur Inactif (0 contrôles)
```

#### 4. **Problèmes de Conformité**

```
⚠️ ALERTES CONFORMITÉ
├─ Militaires sans contrôle : 115
├─ Contrôles incomplets : 8
├─ Utilisateurs n'ayant pas changé mot de passe : 3
└─ Tentatives d'accès refusées : 12
```

### Générer un Rapport Personnalisé

1. Cliquez sur **"🔧 Personnaliser"** (en haut)
2. Configurez :

```
PARAMÈTRES DU RAPPORT
├─ Type de données
│  ├─ ☑ Militaires
│  ├─ ☑ Contrôles
│  ├─ ☑ Utilisateurs
│  └─ ☐ Accès/Sécurité
│
├─ Période
│  ├─ De : [01/01/2024]
│  └─ À : [31/12/2024]
│
├─ Filtres additionnels
│  ├─ Unité : [Toutes ▼]
│  ├─ Profil : [Tous ▼]
│  └─ Statut : [Tous ▼]
│
├─ Format de sortie
│  ├─ ☑ Tableau HTML
│  ├─ ☑ PDF imprimable
│  ├─ ☑ Excel/CSV
│  └─ ☑ JSON (API)
│
└─ [Générer le rapport]
```

3. Cliquez sur **"Générer le rapport"**
4. Le rapport s'affiche et peut être **téléchargé**

### Graphiques et Visualisations

Le système génère automatiquement :

- **Graphiques en barres** : Comparaison par catégories
- **Courbes temporelles** : Tendances sur le temps
- **Camemberts** : Répartition (actif/inactif, par grade, etc.)
- **Heatmaps** : Densité d'activité (Qui? Quand? Où?)

Tous sont **interactifs** :
- Survolez pour les détails
- Cliquez pour zoomer/filtrer
- Téléchargez comme image

---

## Maintenance et Configuration

### Paramétrages de l'Application

Accès : Menu → **"⚙️ Configuration"** (si disponible pour admin)

#### 1. **Paramètres de Sécurité**

```
SÉCURITÉ
├─ Expiration des sessions : [30] minutes
├─ Nombre de tentatives de connexion avant blocage : [5]
├─ Durée du blocage : [15] minutes
├─ Récaptcha : [☑] Activé
└─ Authentification 2-facteurs : [☐] Désactivée
```

#### 2. **Paramètres de Base de Données**

```
SAUVEGARDE
├─ Sauvegarde automatique : [☑] Toutes les nuits à 02:00
├─ Emplacement : [/backup/]
├─ Rétention : [30] jours
└─ [▶ Lancer une sauvegarde maintenant]
```

#### 3. **Paramètres de Notification**

```
NOTIFICATIONS E-MAIL
├─ Serveur SMTP : [smtp.gmail.com]
├─ Port : [587]
├─ Nom d'utilisateur : [fardc@example.com]
├─ Notifications automatiques : [☑] Activé
│
└─ Notifier pour :
   ├─ [☑] Nouvel utilisateur créé
   ├─ [☑] Modification de données
   ├─ [☑] Tentatives d'accès non autorisé
   └─ [☑] Réinitialisation mot de passe
```

### Maintenance du Système

#### Nettoyage des Logs Anciens

```
Menu → "🧹 Maintenance" → "Nettoyer les logs"
│
├─ Garder les logs des [30] derniers jours
├─ Logs plus anciens : [Archiver ▼]
│
└─ [Nettoyer maintenant]
```

#### Récréer les Index de Base de Données

En cas de **ralentissements** :

```
Menu → "🧹 Maintenance" → "Optimiser la BD"
│
├─ Statut : [▓▓▓▓▓▓░░░░] 60%
├─ Temps écoulé : 2m 34s
│
└─ [Attendre la fin]
```

#### Diagnostic du Système

1. Menu → **"🧹 Maintenance"** → **"Diagnostic"**
2. Vérifiez :
   - ✅ Connexion base de données
   - ✅ Espace disque disponible
   - ✅ Permissions des fichiers
   - ✅ Versions des dépendances PHP
   - ✅ Intégrité des fichiers

---

## Sécurité et Audit

### Logs d'Accès et d'Audit

Menu → **"🔐 Sécurité"** → **"📊 Logs"**

### Visualisation des Logs

**Tableau d'audit :**

```
DATE/HEURE | UTILISATEUR | ACTION | RESSOURCE | STATUT | IP
────────────────────────────────────────────────────────────
01/15 10:30| jdurand    | LOGIN  | --        | ✅     | 192.168.1.100
01/15 10:35| jdurand    | READ   | utilisateurs.php | ✅ | 192.168.1.100
01/15 10:45| jdurand    | UPDATE | User #25  | ✅     | 192.168.1.100
01/15 11:00| mmartin    | LOGIN  | --        | ✅     | 192.168.1.101
01/15 11:15| mmartin    | LOGIN  | --        | ❌ (Wrong pwd) | 192.168.1.102
```

### Types d'Événements Enregistrés

| Action | Description |
|--------|-------------|
| **LOGIN** | Tentative de connexion |
| **READ** | Consultation de données |
| **CREATE** | Création d'un élément |
| **UPDATE** | Modification d'un élément |
| **DELETE** | Suppression d'un élément |
| **EXPORT** | Téléchargement de données |
| **PASSWORD_CHANGE** | Changement de mot de passe |
| **PERMISSION_DENIED** | Accès refusé |
| **LOGOUT** | Déconnexion |

### Alertes de Sécurité

L'application alerte automatiquement si :

**🔴 CRITIQUE :**
- ❌ **Tentatives de connexion répétées échouées** (attaque brute-force)
- ❌ **Accès refusé à une page sensible** (hack attempt)
- ❌ **Modification suspecte** de droits d'utilisateur
- ❌ **Suppression en masse** de données

**🟡 AVERTISSEMENT :**
- ⚠️ Connexion à une heure inhabituelle
- ⚠️ Même utilisateur connecté depuis 2 IP différentes
- ⚠️ Nombreuses opérations en peu de temps

### Export des Logs d'Audit

1. Sélectionnez une **plage de dates**
2. Cliquez sur **"📥 Exporter"**
3. Format : CSV, JSON ou PDF

**Exemple d'utilisation :**
```
En cas d'enquête :
- Cherchez qui a supprimé une donnée
- À quelle heure
- Depuis quelle adresse IP
- Et confirmez l'identité
```

---

## Dépannage Administrateur

### Problèmes Courants

#### 1. **Un utilisateur ne peut pas se connecter**

**Vérification :**
- Statut du compte est-il "Actif" ?
- Mot de passe temporaire a-t-il expiré ?
- Y a-t-il trop de tentatives échouées (compte bloqué) ?

**Solution :**
1. Accédez à "Gestion des utilisateurs"
2. Trouvez l'utilisateur
3. Cliquez sur **"🔄 Réinitialiser"** (genère nouveau mot de passe)
4. Dites à l'utilisateur de vérifier son email

#### 2. **Les données semblent incohérentes**

**Cause possible :** Importation incorrecte ou corruption

**Solution :**
1. Menu → "🧹 Maintenance"
2. Cliquez sur **"Vérifier l'intégrité"**
3. Laisse le système réparer les anomalies
4. Vérifiez les données ensuite

#### 3. **L'application répond lentement**

**Causes possibles :**
- Base de données non optimisée
- Trop de logs non nettoyés
- Serveur surchargé

**Solutions :**
```
1. Optimiser la BD
   → Menu → "Maintenance" → "Optimiser"

2. Nettoyer les logs
   → Menu → "Maintenance" → "Nettoyer les logs"

3. Vérifier la charge serveur
   → Menu → "Diagnostic" → "Ressources"
```

#### 4. **Email de notification ne part pas**

**Vérification :**
1. Configuration SMTP est-elle correcte ?
   - Menu → "⚙️ Configuration" → "Notifications"
2. Serveur SMTP fonctionne-t-il ?
   - Cliquez sur **"Tester"**

**Solution :**
- Vérifiez les **identifiants SMTP**
- Vérifiez que le **port (587) est ouvert**
- Activez les **mots de passe d'application** (si Gmail)

---


## Ressources Additionnelles

- [Guide Utilisateur Complet](USER_GUIDE.md)
- [Guide Opérateur](OPERATEUR_GUIDE.md)
- [FAQ et Troubleshooting](TROUBLESHOOTING.md)

---

**Dernière mise à jour:** Janvier 2024  
**Support:** support@fardc.org

---

## Profils

Les profils disponibles dans cette version sont : **ADMIN_IG** et **OPERATEUR**.

Remarque : le profil **CONTROLEUR** n'est plus utilisé dans cette version. Ses fonctionnalités de saisie simple de contrôles sont désormais assurées par le profil **OPERATEUR**.

