# Guide Contrôleur FARDC
## Saisie de Contrôles sur le Terrain

---

## Table des Matières

1. [Introduction Contrôleur](#introduction-contrôleur)
2. [Connexion et Interface](#connexion-et-interface)
3. [Effectuer un Contrôle](#effectuer-un-contrôle)
4. [Rechercher un Militaire](#rechercher-un-militaire)
5. [Saisir les Mentions et Observations](#saisir-les-mentions-et-observations)
6. [Profil Utilisateur](#profil-utilisateur)
7. [FAQ Contrôleur](#faq-contrôleur)

---

## Introduction Contrôleur

### Rôle du Contrôleur

En tant que **CONTROLEUR**, votre mission est **uniquement** l'enregistrement des contrôles de terrain.

Vos droits :

- ✅ **Effectuer et enregistrer** des contrôles de militaires
- ✅ **Rechercher** un militaire par matricule ou nom
- ✅ **Saisir** les mentions réelles (Présent / Favorable / Défavorable) et observations
- ✅ **Localiser** géographiquement le militaire contrôlé
- ✅ **Mettre à jour** votre profil personnel
- ❌ **Consulter** la liste des militaires (réservé à OPERATEUR et ADMIN_IG)
- ❌ **Gérer les utilisateurs** (réservé à ADMIN_IG)
- ❌ **Accéder aux rapports** (réservé à ADMIN_IG et OPERATEUR)
- ❌ **Accéder au tableau de bord** (réservé à ADMIN_IG et OPERATEUR)

### Interface Mobile Optimisée

L'interface CONTROLEUR est spécialement conçue pour **tablettes et smartphones** :

- **Navigation en haut de l'écran** (barre de navigation horizontale)
- **Boutons agrandis** pour faciliter la saisie tactile
- **Formulaire simplifié** centré sur la tâche unique : saisir un contrôle
- **Polices plus grandes** pour une meilleure lisibilité sur petit écran

---

## Connexion et Interface

### Connexion

1. Ouvrez votre navigateur (Chrome ou Firefox recommandés)
2. Accédez à : `http://localhost/ctr.net-fardc/login.php`
3. Entrez votre **login** et **mot de passe**
4. Cliquez sur **"Se connecter"**

> **Redirection automatique** : après connexion, vous êtes directement redirigé vers le **formulaire de contrôle**. Vous n'avez pas accès au tableau de bord.

### Compte de Test

```
Login :       controleur
Mot de passe: controleur123
Profil :      CONTROLEUR
```

> ⚠️ Changez ce mot de passe avant la mise en production !

### Interface de Navigation

L'interface CONTROLEUR utilise une **barre de navigation en haut** :

```
┌──────────────────────────────────────────────────────┐
│  CTL.NET-FARDC  │  Contrôles  │  Profil  │  Quitter  │
└──────────────────────────────────────────────────────┘
│                                                      │
│              FORMULAIRE DE CONTRÔLE                  │
│                                                      │
└──────────────────────────────────────────────────────┘
```

> **Différence avec OPERATEUR/ADMIN_IG** : ceux-ci ont une barre latérale (sidebar) à gauche avec tous les menus. Le CONTROLEUR n'a qu'une barre horizontale en haut, ce qui libère plus d'espace pour le formulaire.

---

## Effectuer un Contrôle

### Accès au Formulaire

Après connexion, vous arrivez directement sur la page : **"Effectuer un contrôle"**

Le formulaire comprend plusieurs étapes :

```
ÉTAPE 1 : Rechercher le militaire
          ↓
ÉTAPE 2 : Sélectionner la mention
          ↓
ÉTAPE 3 : Saisir les observations (facultatif)
          ↓
ÉTAPE 4 : Valider le contrôle
```

---

## Rechercher un Militaire

### Recherche par Matricule ou Nom

La barre de recherche est en haut du formulaire :

```
┌────────────────────────────────────────────────────┐
│  Rechercher un militaire                           │
│  [Matricule ou nom...              🔍 Rechercher]  │
└────────────────────────────────────────────────────┘
```

**Comment rechercher :**

1. Tapez au minimum **2 caractères** (matricule ou nom)
2. Les résultats apparaissent **en temps réel** dans une liste déroulante
3. Cliquez sur le militaire trouvé pour le **sélectionner**

**Exemple de recherche :**

```
Taper : "1234"
│
└─ Résultats :
   ├─ 12345 - DUPONT Jean - Cpl - Unité Alpha
   ├─ 12346 - MARTIN Paul - Sgt - Unité Beta
   └─ (2 résultats)
```

```
Taper : "dupont"
│
└─ Résultats :
   ├─ 12345 - DUPONT Jean - Cpl - Unité Alpha
   └─ (1 résultat)
```

### Informations du Militaire Sélectionné

Une fois le militaire sélectionné, ses informations apparaissent :

```
┌──────────────────────────────────────────┐
│  MILITAIRE SÉLECTIONNÉ                  │
├──────────────────────────────────────────┤
│  Matricule  : 12345                      │
│  Nom        : DUPONT Jean                │
│  Grade      : Caporal                    │
│  Unité      : Unité Alpha                │
│  Garnison   : Kinshasa                   │
│  Statut     : Actif                      │
│  Âge estimé : 32 ans                     │
└──────────────────────────────────────────┘
```

> **Âge estimé** : calculé automatiquement à partir du matricule (2e et 3e chiffres = année de naissance).

### Militaire Introuvable

Si le militaire ne s'affiche pas dans les résultats :

1. Vérifiez l'orthographe du nom
2. Essayez avec le matricule complet (chiffres uniquement)
3. Essayez les 3-4 premiers chiffres du matricule
4. Contactez l'**OPERATEUR** ou l'**ADMIN_IG** pour vérifier si le militaire est enregistré

---

## Saisir les Mentions et Observations

### Choisir la Mention

Après sélection du militaire, la mention dépend du contexte de contrôle :

```
┌────────────────────────────────────────────────────────────┐
│ Cas 1 : militaire vivant                                  │
│   [✅ PRÉSENT]                                             │
│                                                            │
│ Cas 2 : contrôle bénéficiaire (militaire décédé)          │
│   [👍 FAVORABLE]   [👎 DÉFAVORABLE]                        │
└────────────────────────────────────────────────────────────┘
```

> **Interface agrandie** : les boutons sont plus grands sur l'interface CONTROLEUR pour faciliter la saisie tactile.

**Description des mentions :**

| Mention | Signification |
|---------|---------------|
| **PRÉSENT** | Contrôle du militaire lui-même (statut vivant) |
| **FAVORABLE** | Contrôle bénéficiaire conforme |
| **DÉFAVORABLE** | Contrôle bénéficiaire non conforme |

Le formulaire inclut aussi le choix de **statut** selon la catégorie du militaire :

- `Vivant`
- `Décédé`

### Saisir les Observations (Facultatif)

Le champ **"Observations"** vous permet d'ajouter des précisions :

```
┌──────────────────────────────────────────────────────────┐
│  Observations                                            │
│ ┌──────────────────────────────────────────────────────┐│
│ │ Ex: En mission depuis le 15/03. Retour prévu 20/03.  ││
│ │                                                      ││
│ └──────────────────────────────────────────────────────┘│
└──────────────────────────────────────────────────────────┘
```

**Quand remplir les observations :**
- PRÉSENT → signaler tout élément utile du contrôle
- FAVORABLE / DÉFAVORABLE → indiquer les justificatifs et anomalies constatées

### Bénéficiaires (si applicable)

En mode bénéficiaire (statut décédé), vous pouvez renseigner ou compléter le bénéficiaire :

```
┌──────────────────────────────────────────────────────────┐
│  Bénéficiaires                                           │
│                                                          │
│  Bénéficiaire existant : [Nom trouvé automatiquement]    │
│  Nouveau bénéficiaire : [Nom complet]                    │
│  Lien : Épouse / Époux / Fils / Fille / Père / Mère ... │
└──────────────────────────────────────────────────────────┘
```

### Localisation GPS

Si la **géolocalisation** est disponible sur votre appareil :

1. Cliquez sur **"📍 Ma position"** (si disponible)
2. Autorisez le navigateur à accéder à votre localisation
3. Les coordonnées GPS sont enregistrées avec le contrôle

> Fonctionne mieux sur **smartphone ou tablette** avec GPS intégré.

### Valider le Contrôle

Une fois toutes les informations remplies :

1. Vérifiez les données affichées
2. Cliquez sur le bouton correspondant :
   - **"✅ Présent"** (militaire vivant)
   - **"👍 Favorable"** ou **"👎 Défavorable"** (mode bénéficiaire)
3. Un message de confirmation s'affiche :

```
✅ Contrôle enregistré avec succès !
   Matricule : 12345 - DUPONT Jean
   Mention   : PRÉSENT
   Date      : 23/03/2026 à 10:35
```

4. Le formulaire se réinitialise pour un **nouveau contrôle**

### Si le Militaire a Déjà Été Contrôlé

Si le militaire a déjà un contrôle enregistré pour la session en cours :

```
⚠️ Ce militaire a déjà été contrôlé.
   Mention précédente : PRÉSENT (enregistrée le 23/03/2026)
   
   Souhaitez-vous écraser l'enregistrement ?
   [Oui, écraser]   [Annuler]
```

---

## Profil Utilisateur

### Accéder à Votre Profil

1. Cliquez sur votre **nom** en haut à droite de la barre de navigation
2. Sélectionnez **"Mon Profil"**

### Modifier Votre Profil

Vous pouvez modifier :
- ✅ Votre **nom complet**
- ✅ Votre **adresse e-mail**
- ✅ Votre **avatar** (photo de profil)
- ✅ Votre **mot de passe**
- ❌ Votre **login** (immuable)
- ❌ Votre **profil/rôle** (modifiable uniquement par ADMIN_IG)

### Changer de Mot de Passe

1. Dans "Mon Profil", section **"Changer le mot de passe"**
2. Entrez votre **ancien mot de passe**
3. Entrez un **nouveau mot de passe** (minimum 4 caractères)
4. Confirmez le nouveau mot de passe
5. Cliquez sur **"Enregistrer"**

---

## FAQ Contrôleur

### Q1 : Je ne vois pas le tableau de bord. Est-ce normal ?
**R :** Oui, c'est tout à fait normal. Le profil **CONTROLEUR** n'a accès qu'au formulaire de saisie de contrôles. Le tableau de bord est réservé aux profils ADMIN_IG et OPERATEUR.

### Q2 : Puis-je consulter les contrôles que j'ai déjà effectués ?
**R :** Non, la consultation de l'historique des contrôles est réservée aux profils ADMIN_IG et OPERATEUR. Pour vérifier un contrôle que vous avez fait, contactez un OPERATEUR ou l'ADMIN_IG.

### Q3 : L'application ne trouve pas le militaire que je cherche.
**R :** Plusieurs causes possibles :
1. Le militaire n'est pas encore enregistré dans le système → contactez votre OPERATEUR
2. Le nom est orthographié différemment → essayez avec le matricule
3. Le militaire est marqué inactif → contactez ADMIN_IG

### Q4 : Puis-je corriger un contrôle que j'ai mal saisi ?
**R :** Non, la correction des contrôles est réservée à ADMIN_IG et OPERATEUR. Signalez immédiatement l'erreur à votre OPERATEUR avec le matricule et la date du contrôle.

### Q5 : L'application me déconnecte automatiquement. Pourquoi ?
**R :** Les sessions expirent après **30 minutes d'inactivité** pour des raisons de sécurité. Reconnectez-vous si cela se produit. Pensez à enregistrer chaque contrôle avant de faire une pause.

### Q6 : L'interface s'affiche mal sur mon téléphone.
**R :** Utilisez **Chrome ou Firefox** en version récente. Désactivez le zoom automatique si l'affichage est trop petit. L'application est optimisée pour les résolutions mobiles (360px et plus).

### Q7 : Le GPS ne fonctionne pas.
**R :** Vérifiez que :
1. La **géolocalisation** est activée sur votre appareil
2. Vous avez **autorisé** le navigateur à accéder à votre position
3. Vous êtes dans une zone avec signal GPS suffisant

### Q8 : Comment voir les militaires de mon unité ?
**R :** La consultation des militaires n'est pas accessible avec le profil CONTROLEUR. Demandez à votre OPERATEUR de vous fournir une liste imprimée si nécessaire.

---

## Informations de Sécurité

### Vos Actions Sont Enregistrées

Toutes vos actions dans l'application sont **journalisées** dans le log d'audit :
- Connexion / Déconnexion
- Chaque contrôle enregistré (date, heure, militaire, mention)
- Modifications de profil

### En Cas de Problème

1. **Problème technique** : Contactez votre administrateur (ADMIN_IG)
2. **Compte bloqué** (5 tentatives échouées) : Attendez 15 minutes ou contactez ADMIN_IG
3. **Mot de passe oublié** : Cliquez sur "Mot de passe oublié" sur la page de connexion

---

## Liens Rapides

| Document | Description |
|----------|-------------|
| [USER_GUIDE.md](USER_GUIDE.md) | Guide général (tous profils) |
| [FAQ.md](FAQ.md) | Questions fréquentes |
| [TROUBLESHOOTING.md](TROUBLESHOOTING.md) | Résolution de problèmes |

---

*Guide CONTROLEUR - CTR.NET-FARDC v1.1.0 - Mars 2026*
