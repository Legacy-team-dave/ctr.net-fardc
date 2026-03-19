# Guide Opérateur FARDC
## Gestion des Militaires et Contrôles

---

## Table des Matières

1. [Introduction Opérateur](#introduction-opérateur)
2. [Accès et Interface](#accès-et-interface)
3. [Gestion des Militaires](#gestion-des-militaires)
4. [Enregistrement des Contrôles](#enregistrement-des-contrôles)
5. [Consultation des Données](#consultation-des-données)
6. [Profil Utilisateur Opérateur](#profil-utilisateur-opérateur)
7. [FAQ Opérateur](#faq-opérateur)

---

## Introduction Opérateur

### Rôle de l'Opérateur

En tant qu'**OPERATEUR**, vous pouvez :

- ✅ **Consulter** la liste des militaires
- ✅ **Ajouter** des militaires à la base de données
- ✅ **Modifier** les informations des militaires
- ✅ **Effectuer et enregistrer** les contrôles
- ✅ **Consulter** l'historique des contrôles
- ❌ **Gérer les utilisateurs** (réservé à l'admin)

### Responsabilités

1. **Maintien de la base militaires à jour**
2. **Enregistrement précis des contrôles**
3. **Traçabilité complète** des opérations
4. **Respect des procédures** d'enregistrement

---

## Accès et Interface

### Connexion

1. Ouvrez : `http://localhost/ctr.net-fardc/login.php`
2. Entrez votre **login** et **mot de passe**
3. Cliquez sur **"Se connecter"**

### Tableau de Bord Opérateur

Après connexion, vous accédez à un **dashboard personnalisé** :

```
VOTRE TABLEAU DE BORD
├─ Bienvenue [Votre Nom]
├─ Votre profil : OPERATEUR
│
├─ STATISTIQUES RAPIDES
│  ├─ Total militaires : 1200
│  ├─ Militaires actifs : 1050
│  ├─ Contrôles ce mois : 85
│  └─ Contrôles effectués par vous : 32
│
├─ ACTIONS RAPIDES
│  ├─ 📝 Ajouter un militaire
│  ├─ ✅ Effectuer un contrôle
│  └─ 📋 Voir mes contrôles récents
│
└─ ALERTES
   ├─ 3 militaires sans contrôle depuis 30 jours
   └─ Vous n'avez pas terminé 2 contrôles en cours
```

### Menu de Navigation

Le menu latéral affiche **uniquement** vos options autorisées :

```
MENU OPERATEUR
│
├─ ✅ Contrôles
│  ├─ 📋 Liste des contrôlés
│  └─ ➕ Effectuer un contrôle
│
└─ 👤 Profil
   ├─ Mon profil
   └─ Déconnexion
```

⚠️ **Note** : Vous ne verrez **pas** les menus "Gestion des utilisateurs", "Rapport" ou "Militaires" (réservés à l'admin)

---

## Gestion des Militaires

### Consulter la Liste des Militaires

#### Accès

1. Dans le menu principal, cherchez **"Militaires"** ou allez à l'accueil
2. Cliquez sur **"📋 Liste des militaires"**
3. Vous verrez tous les militaires enregistrés

#### Tableau des Militaires

**Colonnes affichées :**

| Colonne | Information |
|---------|-----------|
| **Nom** | Nom complet du militaire |
| **Prénom** | Prénom |
| **Grade** | Soldat, Caporal, Sergent, etc. |
| **Matricule** | Identifiant unique |
| **Unité** | Unité d'appartenance |
| **Statut** | Actif / Inactif |
| **Actions** | 👁️ Voir, ✏️ Modifier |

#### Rechercher un Militaire

**Utiliser la barre de recherche (en haut du tableau) :**

```
Chercher : [jean dupont    ]
│
└─ Résultats en temps réel
   ├─ Dupont Jean - Cpl - Unité A
   ├─ Dupont Jean-Paul - Sgt - Unité B
   └─ (2 résultats trouvés)
```

**Cherchez par :**
- Nom ou prénom
- Matricule
- Unité

#### Filtrer la Liste

**Boutons de filtrage (en haut) :**

```
[📋 Tous] [✅ Actifs] [⏸️ Inactifs]
```

Cliquez sur le filtre désiré pour **afficher uniquement** les militaires correspondants

#### Voir le Détail d'un Militaire

1. Cliquez sur le **nom** du militaire ou sur **"👁️ Voir"**
2. Vous verrez :
   - Informations personnelles
   - Historique des contrôles
   - Coordonnées et contacts
   - Autres données pertinentes

### Ajouter un Nouveau Militaire

#### Formulaire d'Enregistrement

1. Cliquez sur **"➕ Ajouter un militaire"** (bouton bleu en haut)
2. Remplissez le formulaire :

```
ENREGISTREMENT D'UN MILITAIRE
├─────────────────────────────────────
│ INFORMATIONS GÉNÉRALES
│
│ Nom complet *           [________________]
│ Prénom * (si séparé)    [________________]
│ Grade *                 [Soldat ▼]
│ Matricule *             [________________]
│ Numéro de telephone     [________________]
│ Email                   [________________]
│
│ AFFECTATION
│
│ Unité *                 [Unité A ▼]
│ Fonction                [Soldat de 1e classe ▼]
│ Date d'incorporation    [01/01/2024]
│
│ STATUT
│
│ Statut *                [☑ Actif] [☐ Inactif]
│
├─────────────────────────────────────
│ [🔄 Réinitialiser]  [✅ Enregistrer]
└─────────────────────────────────────
```

#### Guide de Remplissage

**Champs obligatoires** (avec *) :

| Champ | Format | Exemple | Notes |
|-------|--------|---------|-------|
| **Nom complet** | Texte | DUPONT Jean | Doit être complet |
| **Grade** | Sélection | Caporal | À choisir dans la liste |
| **Matricule** | 6 chiffres | 123456 | DOIT être unique |
| **Unité** | Sélection | Unité Spéciale | À choisir |

**Champs optionnels** :
- Téléphone (format : +243 XX XXX XXXX)
- Email (exemple@fardc.org)
- Date d'incorporation

⚠️ **Attention** : Le **matricule ne peut pas être dupliqué**. Si vous voyez une erreur "Matricule déjà enregistré", vérifiez que le numéro est correct.

#### Validation et Confirmation

Après avoir rempli le formulaire :

1. Vérifiez les informations saisies
2. Cliquez sur **"✅ Enregistrer"**
3. Le système affiche un **message de confirmation**
4. Vous êtes redirigé vers **la fiche du militaire**

### Modifier les Données d'un Militaire

#### Accès à la Modification

1. Dans la liste, cliquez sur **"✏️ Modifier"** (colonne Actions)
2. Ou cliquez sur le militaire → **"✏️ Modifier"** (en haut)

#### Champs Modifiables

Vous pouvez modifier :
- ✅ Nom complet
- ✅ Prénom
- ✅ Grade
- ✅ Téléphone
- ✅ Email
- ✅ Unité
- ✅ Fonction
- ✅ Statut (Actif/Inactif)

Vous **ne pouvez pas** modifier :
- ❌ Matricule (immuable)
- ❌ Date d'incorporation (donnée historique)

#### Processus de Modification

```
1. Modifiez les champs nécessaires
2. Vérifiez les changements
3. Cliquez sur "💾 Enregistrer"
4. Message de confirmation
5. Les modifications sont sauvegardées
6. Un LOG d'audit enregistre qui a modifié quoi
```

---

## Enregistrement des Contrôles

### Effectuer un Contrôle

**Le cœur de votre travail d'opérateur**

#### Accéder au Formulaire de Contrôle

1. Menu → **"✅ Contrôles"**
2. Cliquez sur **"➕ Effectuer un contrôle"**

#### Formulaire de Contrôle Complet

```
EFFECTUER UN CONTRÔLE
├─────────────────────────────────────
│ IDENTIFICATION
│
│ Militaire * [Chercher ________] [🔍]
│            └─ DUPONT Jean - Cpl
│
│ Date du contrôle * [01/15/2024]
│
│ DÉTAILS DU CONTRÔLE
│
│ Type de contrôle * [Inspection ▼]
│
│ Localisation GPS (optionnel)
│ Latitude  [________] Longitude [______]
│ ou [📍 Utiliser ma position GPS]
│
│ OBSERVATIONS
│
│ Observations [_____________________
│              |_____________________
│              |___________________]
│
│ Statut * [☑ Terminé] [☐ En cours] [☐ Suspendu]
│
├─────────────────────────────────────
│ [🔄 Réinitialiser]  [✅ Enregistrer]
└─────────────────────────────────────
```

#### Guide Détaillé

**Militaire** (obligatoire) :
- Commencez à taper le **nom**
- Une liste de suggestions apparaît
- Sélectionnez le militaire correct

**Date du Contrôle** (obligatoire) :
- Format : JJ/MM/AAAA
- Par défaut : Aujourd'hui
- Vous pouvez entrer une **date antérieure** si nécessaire

**Type de Contrôle** (obligatoire) :

Les types disponibles :
```
[Visite médicale] - Examen de santé
[Visite dentaire] - Examen des dents
[Inspection] - Contrôle général
[Inspection d'équipement] - Vérification du matériel
[Visite psychologique] - Évaluation mental
[Entraînement] - Exercice opérationnel
[GPS] - Localisation/contrôle spatial
[Autre] - À spécifier dans les observations
```

**Localisation GPS** (optionnel) :
- **Manuel** : Entrez Latitude et Longitude
  - Latitude : -4.3375 (format décimal)
  - Longitude : 15.3136 (format décimal)
- **Automatique** : Cliquez sur "📍 Utiliser ma position GPS"

⚠️ Nécessite que votre navigateur **permette l'accès au GPS**

**Observations** (très important) :
- Décrivez ce que vous avez observé
- État physique du militaire
- Présence de blessures ou maladies
- Conformité à l'uniforme et équipement
- Problèmes particuliers à signaler
- Toute note pertinente

**Exemple d'observations :**
```
"Contrôle de routine effectué. Le soldat Dupont présente
un bon état général. Poids : 82 kg. Pas de blessures 
visibles. Équipement complet et en bon état. À revoir
dans 30 jours."
```

**Statut du Contrôle** (obligatoire) :

```
☑ Terminé  - Contrôle complètement effectué
☐ En cours - Contrôle en cours (à finaliser plus tard)
☐ Suspendu - Reporté à une autre date
```

### Types de Contrôles Courants

#### 1. **Visite Médicale**
```
Données à noter :
✓ État général (bon/moyen/mauvais)
✓ Température si prise
✓ Symptômes signalés
✓ Recommandations (repos, traitement, etc.)
✓ Date du prochain contrôle
```

#### 2. **Inspection d'Équipement**
```
À vérifier :
✓ Uniform présent et propre
✓ Equipement de base (fusil, gilet pare-balle, etc.)
✓ État du matériel (endommagé? manquant?)
✓ Conformité des équipement
✓ Necessite de remplacement
```

#### 3. **Entraînement**
```
À documente :
✓ Type d'entraînement effectué
✓ Durée de l'exercice
✓ Performances du militaire
✓ Points forts/faibles
✓ Recommandations
```

### Soumettre et Sauvegarder

1. **Avant de cliquer "Enregistrer"**, vérifiez :
   - ✓ Le bon militaire sélectionné
   - ✓ La bonne date
   - ✓ Toutes les observations saisies
   - ✓ Le statut vérifié

2. Cliquez sur **"✅ Enregistrer"**

3. Le système affiche :
   ```
   ✅ Contrôle enregistré avec succès
   
   Référence : CTRL-2024-01-15-001
   Militaire : DUPONT Jean
   Opérateur : Vous
   Date : 15/01/2024 10:35:22
   ```

4. Vous êtes redirigé vers **la liste des contrôles**

### Corriger un Contrôle Enregistré

Si vous avez commis une erreur après l'enregistrement :

1. Allez à **"✅ Contrôles"** → **"📋 Liste des contrôlés"**
2. Trouvez le contrôle à corriger
3. Cliquez sur **"✏️ Modifier"**
4. Corrigez les informations
5. Cliquez sur **"💾 Enregistrer"**

⚠️ **Note** : Un log d'audit enregistre qui a modifié quoi et quand

### Contrôles en Attente

Si vous avez commencé un contrôle mais n'l'avez pas terminé :

1. Sauvegardez avec le statut **"☐ En cours"**
2. Plus tard, retrouvez-le dans **"📋 Mes contrôles en cours"**
3. Cliquez sur **"✏️ Continuer"**
4. Complétez le contrôle
5. Changez le statut à **"☑ Terminé"**
6. Enregistrez

---

## Consultation des Données

### Voir la Liste de Vos Contrôles

1. Menu → **"✅ Contrôles"** → **"📋 Liste des contrôlés"**
2. Vous verrez **tous les contrôles** de tout le système
3. Pour filtrer vos contrôles :
   - Cliquez sur **"Mes contrôles"** (si cette optionest disponible)
   - Ou utilisez le filtre **"Opérateur"** = Votre nom

### Statistiques Personnelles

Sur le dashboard :
- **Nombre de contrôles effectués** ce mois
- **Nombre de militaires** suivis
- **Taux de conformité** (% de contrôles terminés vs en cours)
- **Heures travaillées** (optionnel)

### Exporter Vos Données

Pour garder une copie locale :

1. **Liste des militaires** :
   - Allez à "Militaires" → "Liste"
   - Cliquez sur **"📥 Exporter en Excel"**
   - Fichier téléchargé : `militaires_[date].xlsx`

2. **Historique de vos contrôles** :
   - Allez à "Contrôles" → "Liste"
   - Filtrez par **"Mon nom"**
   - Cliquez sur **"📥 Exporter CSV"**
   - Fichier téléchargé : `mes_controles_[date].csv`

---

## Profil Utilisateur Opérateur

### Accéder à Mon Profil

En haut à droite, cliquez sur votre **nom** puis **"👤 Profil"**

### Informations de Profil

Vous verrez :
- ✅ Votre nom complet
- ✅ Votre login
- ✅ Votre email
- ✅ Votre profil (OPERATEUR)
- ✅ Votre avatar (photo)

### Modifier Votre Email

1. Cliquez sur **"✏️ Modifier"**
2. Changez votre **email**
3. Cliquez sur **"💾 Enregistrer"**

Les notifications futures seront envoyées au nouvel email

### Changer Votre Mot de Passe

1. Cliquez sur **"🔐 Changer mon mot de passe"**
2. Entrez :
   - **Ancien mot de passe** (pour vérification)
   - **Nouveau mot de passe** (min 8 caractères)
   - **Confirmation** du nouveau

3. Cliquez sur **"✅ Mettre à jour"**

⚠️ **Important** :
- Utilisez un mot de passe **fort** (maj + min + chiffres + symboles)
- Ne réutilisez pas un ancien mot de passe
- Mémorisez-le bien (non partageable)

### Préférences

1. Cliquez sur **"⚙️ Préférences"**
2. Configurez :
   - **Thème** : Clair ou Sombre
   - **Fuseau horaire** : Pour les dates/heures exact
   - **Notifications** : Activer/désactiver les alertes par email

3. Cliquez sur **"💾 Enregistrer"**

### Déconnexion

En haut à droite, cliquez sur **"🚪 Déconnexion"**

Une confirmation apparaît. Cliquez sur **"Oui, déconnecter"**

---

## FAQ Opérateur

### Q: J'ai oublié mon mot de passe ?
**R:** Sur la page de connexion, cliquez sur "Mot de passe oublié?" et suivez les instructions

### Q: Pourquoi ne peux-je pas créer un utilisateur ?
**R:** Seuls les administrateurs (ADMIN_IG) peuvent gérer les utilisateurs. Contactez votre admin.

### Q: Le militaire que je cherche n'existe pas
**R:** Il n'a pas encore été enregistré. Cliquez sur "➕ Ajouter un militaire" pour l'ajouter à la base.

### Q: Je vois une erreur "Matricule déjà enregistré"
**R:** Ce matricule existe déjà. Vérifiez :
   1. Le numéro saisi est-il correct?
   2. Le militaire n'est-il pas enregistré sous un autre nom?
   3. Demandez confirmation à votre supérieur

### Q: Puis-je effectuer un contrôle rétroactif (une date antérieure)?
**R:** Oui, entrez simplement la date passée. MAIS notez bien dans le titre ou chez un supérieur!

### Q: Que faire si un militaire est décédé ou quitté l'armée?
**R:** Cliquez sur **"⏸️ Désactiver"** pour passer le statut à "Inactif". Les données sont conservées (pas suppression).

### Q: Je n'ai pas reçu mon email de nouveau compte
**R:** Vérifiez votre dossier **"Spam"** ou **"Courrier indésirable"**. Contactez l'admin si toujours rien.

### Q: Puis-je supprimer mes propres contrôles?
**R:** Non, seul un admin peut supprimer. Contactez votre admin si erreur grave.

### Q: Puis-je voir les contrôles effectués par d'autres opérateurs?
**R:** Oui, vous avez accès à **toute la liste** des contrôles du système (pour cohérence).

### Q: Est-ce que mes données sont sauvegardées automatiquement?
**R:** Oui, l'application sauvegarde **automatiquement chaque nuit**. Mais vous pouvez aussi exporter manuellement.

### Q: Quelle est la fréquence recommandée des contrôles?
**R:** Selon la procédure FARDC, un contrôle au **minimum par trimestre** (tous les 90 jours). Plus fréquent si nécessaire.

---

## Ressources Additionnelles

- [Guide Complet Utilisateur](USER_GUIDE.md)
- [Guide Administrateur](ADMIN_GUIDE.md)
- [Guide Contrôleur](CONTROLEUR_GUIDE.md)
- [FAQ et Dépannage](FAQ.md)
- [Troubles Techniques](TROUBLESHOOTING.md)

---

**Dernière mise à jour:** Janvier 2024  
**Support:** support@fardc.org  
**Qu'avez-vous besoin?** Contactez votre administrateur

