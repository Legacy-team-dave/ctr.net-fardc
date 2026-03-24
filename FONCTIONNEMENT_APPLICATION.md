# Fonctionnement détaillé de l'application CTR.NET-FARDC

Ce document décrit le comportement réel de l'application selon le code en place.

## 1. Vue d'ensemble

CTR.NET-FARDC est une application web PHP (PDO/MySQL) orientée :

- authentification des utilisateurs,
- gestion des militaires,
- saisie et suivi des contrôles,
- gestion des litiges,
- administration et audit.

Les pages principales sont protégées par session (`$_SESSION`) et contrôles de rôle.

## 2. Profils et rôles

Profils effectivement utilisés :

- `ADMIN_IG`
- `OPERATEUR`
- `CONTROLEUR`

### Droits observés

- `ADMIN_IG`
  - Accès au dashboard (`index.php`)
  - Accès au module administration (utilisateurs, logs)
  - Accès aux rapports
  - Accès au module militaires
  - Accès aux contrôles et litiges

- `OPERATEUR`
  - Accès au dashboard (`index.php`)
  - Accès aux contrôles et litiges
  - Passage par `preferences.php` si filtres non définis

- `CONTROLEUR`
  - Redirigé vers la saisie de contrôle (`modules/controles/ajouter.php`)
  - Interface top-nav spécifique dans le header

## 3. Authentification et session

## 3.1 Connexion (`login.php`)

- Identifiant accepté : `login` ou `nom_complet` ou `email`
- Vérification : `actif = true`
- Mot de passe : `password_verify`
- Session initialisée avec : id, login, nom, email, profil, avatar
- `dernier_acces` est mis à jour à la connexion
- Audit : `CONNEXION` (succès), `ECHEC_CONNEXION` (échec)

### Redirections post-login

- `ADMIN_IG` -> `index.php`
- `OPERATEUR` -> `preferences.php` si pas de préférences → `equipes.php` (enregistrement équipe) → `index.php`, sinon `modules/controles/ajouter.php`
- `CONTROLEUR` -> `modules/controles/ajouter.php`

## 3.2 Se souvenir de moi

- Génère un `remember_token` stocké en base (expiration 30 jours)
- Cookie `remember_token` côté client
- Reconnexion automatique si token valide

## 3.3 Déconnexion (`logout.php`)

- Invalidation du `remember_token` en base
- Suppression du cookie
- Audit `DECONNEXION`
- Destruction complète de session

## 3.4 Réinitialisation de mot de passe (`reset_password.php`)

Flux en 2 étapes :

1. `step=request`
   - génération token + expiration (`+1 hour`)
   - stockage dans `utilisateurs.reset_token` / `reset_expires`
   - audit `DEMANDE_RESET`

2. `step=reset&token=...`
   - vérification token non expiré
   - définition nouveau mot de passe (min 4 caractères)
   - purge token
   - audit `RESET_MDP`

## 4. Contrôle d'accès

Fonctions principales :

- `require_login()` : impose une session active
- `check_profil([...])` : vérifie le profil autorisé
- `verifier_acces([...])` (dans header) : vérifie et redirige avec message flash

Important : le menu (visibilité UI) et la protection serveur ne sont pas strictement identiques sur toutes les pages. Certaines pages reposent surtout sur `require_login()`.

## 5. Préférences utilisateur (`preferences.php`)

## 5.1 Objectif

Permet de définir les filtres personnels :

- garnisons sélectionnées,
- catégories sélectionnées.

## 5.2 Persistance

- Sauvegarde JSON dans `utilisateurs.preferences`
- Chargement en session dans `$_SESSION['filtres']`
- Audit `PREFERENCES`

## 5.3 Impact métier

Les préférences servent au filtrage des données du dashboard.

## 5.4 Équipe de contrôle (`equipes.php`)

Après la validation des préférences, l'opérateur est redirigé vers `equipes.php` pour enregistrer les membres de son équipe de contrôle.

- Table standalone `equipes` (id, noms, grade, role) — aucune relation avec les autres tables
- Formulaire d'ajout : noms, grade, rôle
- Liste des membres enregistrés avec suppression possible
- Bouton « Continuer » pour accéder au dashboard (`index.php`)
- Page autonome (design identique à `preferences.php`, sans header/footer)

## 6. Module Contrôles

## 6.1 Saisie d'un contrôle (`modules/controles/ajouter.php`)

Accès prévu : `ADMIN_IG`, `OPERATEUR`, `CONTROLEUR`.

### Recherche militaire

- Endpoint AJAX : `?ajax=search&q=...`
- Minimum 2 caractères
- Retour max 10 résultats

### Mentions disponibles (réelles)

- `Présent`
- `Favorable`
- `Défavorable`

### Règles de validation

- Un militaire ne peut être contrôlé qu'une seule fois (unicité fonctionnelle sur matricule)
- Le lien de parenté est obligatoire
- Si lien = `Militaire lui-même` : pas de bénéficiaire
- Sinon : bénéficiaire existant ou nouveau obligatoire

### Transformation du lien

Normalisation vers `lien_parente` :

- `Epoux` -> `Veuf`
- `Epouse` -> `Veuve`
- `Fils` / `Fille` -> `Orphelin`
- `Père` / `Mère` / `Frère` / `Sœur` -> `Tuteur`

### Cas spécifique DCD_AP_BIO

Si catégorie `DCD_AP_BIO` et statut "vivant" coché (sans "décédé"), l'observation est enrichie avec `Mort vivant`.

### Enregistrement

Insertion dans `controles` avec date/heure (`NOW()`), puis audit `AJOUT`.

## 6.2 Liste des contrôles (`modules/controles/liste.php`)

- Jointure `controles` + `militaires`
- Calcul de statistiques (présents, favorables, défavorables)
- Filtres et exports
- Journalisation des exports (`EXPORT`)

## 7. Module Litiges

## 7.1 Ajout (`modules/litige/ajouter.php`)

- Formulaire de saisie de litige
- Session requise

## 7.2 Liste (`modules/litige/liste.php`)

- Récupération des litiges triés
- Statistiques (zones de défense, provinces, garnisons)
- Exports disponibles

## 7.3 ZDEF

Le calcul de zone de défense est basé sur la province via `getZdefValue()` (1ZDEF / 2ZDEF / 3ZDEF / AUTRE).

## 8. Dashboard (`index.php`)

Accessible à `ADMIN_IG` et `OPERATEUR`.

Fonctions principales :

- statistiques globales,
- statistiques filtrées selon préférences,
- exploitation des catégories militaires,
- exports (dont non-vus).

## 9. Administration

## 9.1 Utilisateurs (`modules/administration/liste.php`)

- Contrôle de rôle `ADMIN_IG`
- Affichage comptes, profils, statut actif/inactif
- Exports et audit export

## 9.2 Ajout utilisateur (`modules/administration/ajouter_utilisateur.php`)

- Profils proposés : `ADMIN_IG`, `OPERATEUR`, `CONTROLEUR`
- Mot de passe minimum : 6 caractères
- Protection CSRF
- Audit d'ajout

## 9.3 Profil personnel (`profil.php`)

- Mise à jour nom, email, avatar
- Changement de mot de passe avec vérification de l'ancien
- Audit `MODIFICATION_PROFIL`

## 10. Audit et logs

Mécanisme principal :

- `audit_action()` : ne journalise que pour profils attendus
- `log_action()` : insertion dans `logs`, fallback `logs_actions`

Actions courantes tracées :

- `CONNEXION`
- `ECHEC_CONNEXION`
- `DECONNEXION`
- `AJOUT`
- `EXPORT`
- `MODIFICATION_PROFIL`
- `DEMANDE_RESET`
- `RESET_MDP`
- `PREFERENCES`

## 11. Sauvegardes automatiques

Fonctions de sauvegarde dans `includes/functions.php` :

- `maybe_create_backup()` : calcule et crée une sauvegarde incrémentale
- `create_incremental_backup_archive()` : fabrique l'archive ZIP avec CSV + XLSX

Comportement réel :

- exécution autonome par tâche planifiée Windows toutes les 8 heures
- fichiers produits dans `backups/`
- contenu : nouveaux éléments uniquement (`controles`, `litiges`, `non_vus`)
- formats : CSV compatible Excel et XLSX
- état persistant : `backups/backup_state.json` (curseurs et snapshot non-vus)
- purge automatique : suppression des archives identiques, puis conservation des 30 dernières archives non identiques
- script manuel de purge : `run_backup_purge.ps1 -MaxKeep 30`

## 12. Schéma simplifié du cycle utilisateur

1. Connexion
2. Vérification rôle
3. Redirection selon profil
4. Configuration équipe (`equipes.php`) — si premier accès
5. Saisie/consultation (contrôles, litiges, dashboard)
6. Journalisation des actions
7. Sauvegardes automatiques en arrière-plan

## 13. Points d'attention opérationnels

- Vérifier régulièrement l'espace disque du dossier `backups/`.
- Maintenir une politique de rotation/archivage des sauvegardes.
- Contrôler la cohérence des profils affectés aux comptes.
- Uniformiser les mentions utilisées dans les procédures métier (`Présent`, `Favorable`, `Défavorable`).
- Revoir périodiquement les protections de pages pour aligner menu et contrôle serveur.

## 14. 🔐 Chiffrement des fichiers sensibles (v1.1.0+)

### 14.1 Vue d'ensemble

Depuis v1.1.0, les fichiers critiques (configuration, authentification, logique) sont protégés par chiffrement AES-256-CBC NIST.

### 14.2 Architecture

**Couche de chiffrement** :

- Classeur : `/config/encryption.php`
  - Fonctions `encrypt_string()` et `decrypt_string()`
  - Gestion de clé aléatoire 256-bit
  - Encoding Base64 + IV aléatoire par chiffrage

- **Autoloader de déchiffrement** : `/config/encrypted_loader.php`
  - Interception transparente des fichiers `.encrypted`
  - Décryptage en mémoire au moment du chargement
  - Aucun stockage en clair sur disque

### 14.3 Clé secrète

- **Emplacement** : variabilité d'environnement `ENCRYPTION_KEY` (fichier `.env`)
- **Génération** : `php bin/encrypt.php init` (une seule fois)
- **Sauvegarde** : **CRITIQUE** - à conserver hors ligne et sécurisée
- **Rotation** : via `./rotate_encryption_key.ps1` (réchiffre tous les fichiers avec nouvelle clé)

### 14.4 Fichiers chiffrables par défault

```
config/database.php              (identifiants BD)
config/load_config.php           (config)
includes/auth.php                (logique auth)
includes/functions.php           (fonctions métier)
includes/header.php              (dynamique pages)
login.php                        (authentification)
logout.php                       (déconnexion)
index.php                        (page d'accès)
```

### 14.5 Processus d'activation

1. **Initialiser la clé** (une fois) :
   ```bash
   php bin/encrypt.php init
   ```
   → Crée `.env` avec `ENCRYPTION_KEY`

2. **Chiffrer les fichiers** :
   ```bash
   php bin/encrypt.php encrypt
   ```
   → Crée versions `.encrypted` des fichiers, les originaux restent actifs

3. **Vérifier l'état** :
   ```bash
   php bin/encrypt.php status
   ```
   → Affiche [✓ Encrypté] ou [✓ Non chiffré] pour chaque fichier

### 14.6 Transparence applicative

- Aucune modification au code métier requise
- Les fichiers `.encrypted` sont déchiffrés au runtime automatiquement
- Performance : < 5ms de surcharge/démarrage (déchiffrement en mémoire)
- Sessions/caches : Pas affectés (opèrent sur données déchiffrées)

### 14.7 Gestion avancée

**Déchiffrer un fichier** (si on souhaite revenir en clair) :
```bash
php bin/encrypt.php decrypt config/database.php
```

**Lister les fichiers chiffrables** :
```bash
php bin/encrypt.php list
```

**Rotation de clé complète** (si compromission soupçonnée) :
```powershell
./rotate_encryption_key.ps1
```
Flux : sauvegarde ancienne clé → nouvelle clé → déchiffrage avec ancienne → re-chiffrage avec nouvelle

### 14.8 Points d'attention opérationnels

- ⚠️ **Perte de `.env`** = perte d'accès aux données (sans recovery)
- Intégrer `.env` dans le plan de sauvegarde (location sécurisée)
- Ne jamais commiter `.env` dans Git (règle `.gitignore.encryption`)
- Rotation annuelle recommandée pour conformité sécurité
- Si migration vers nouveau serveur : copier `.env` également

### 14.9 Interfaces de gestion

| Interface | Usage | Profil |
|-----------|-------|--------|
| CLI (`php bin/encrypt.php`) | Scripts, DevOps | IT/Admin technique |
| GUI Batch (`encrypt_*.bat`) | Point-and-click | IT/Admin non-tech |
| PowerShell (`.ps1` scripts) | Orchestration, tâches planifiées | DevOps/Admin avancé |

## 15. Fichiers clés à connaître

- `login.php` / `logout.php`
- `index.php`
- `preferences.php`
- `profil.php`
- `reset_password.php`
- `includes/functions.php`
- `includes/header.php`
- `modules/controles/ajouter.php`
- `modules/controles/liste.php`
- `modules/litige/ajouter.php`
- `modules/litige/liste.php`
- `modules/administration/liste.php`
- `modules/administration/ajouter_utilisateur.php`
