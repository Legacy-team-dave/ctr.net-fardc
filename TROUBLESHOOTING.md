# Guide de Dépannage TROUBLESHOOTING
## Solutions Aux Problèmes Techniques

---

## Table des Matières

1. [Diagnostic Rapide](#diagnostic-rapide)
2. [Problèmes de Connexion](#problèmes-de-connexion)
3. [Problèmes de Navigateur](#problèmes-de-navigateur)
4. [Problèmes de Base de Données](#problèmes-de-base-de-données)
5. [Problèmes de Données](#problèmes-de-données)
6. [Problèmes de Performance](#problèmes-de-performance)
7. [Erreurs Courantes](#erreurs-courantes)
8. [Procédures Avancées](#procédures-avancées)

---

## Diagnostic Rapide

### Arbre de Diagnostic

```
L'application ne respond pas?
│
├─ Peux-tu ping le serveur?
│  ├─ NON → Problème réseau (see "Problèmes de Connexion")
│  └─ OUI → Continue
│
├─ Peux-tu accéder à la page de login?
│  ├─ NON → Serveur web down (see "Codes Erreur HTTP")
│  └─ OUI → Continue
│
├─ Ca login-tu?
│  ├─ NON → Compte/Auth issue (see "Problèmes de Connexion")
│  └─ OUI → Continue
│
├─ L'appli est très lente?
│  ├─ OUI → See "Problèmes de Performance"
│  └─ NON → Continue
│
└─ Des pages donnent des erreurs?
   ├─ OUI → See "Erreurs Courantes" ou "Codes Erreur HTTP"
   └─ NON → Contactez support
```

### Vérification Rapide du Système

Avant de commencer, vérifiez :

```bash
# Depuis votre ordinateur (Cmd ou PowerShell)

# 1. Vérifier la connectivité serveur
ping localhost
# Devrait répondre dans ~1ms

# 2. Tester connexion HTTP
curl http://localhost/ctr.net-fardc/login.php
# Devrait afficher le HTML de la page login

# 3. Vérifier services Laragon
"Laragon running" devrait être visible en bas à droite
```

---

## Problèmes de Connexion

### Problème: "Impossible de se connecter au serveur"

#### Symptômes
- Page blanche
- "Impossible de joindre le serveur" 
- Timeout de connexion
- Pas de réponse après 30+ secondes

#### Causes Possibles et Solutions

**1. Serveur web (Apache) est DOWN**

```
Indication: La page n'apparaît pas du tout

Solution:
1. Ouvrez Laragon (icône en bas à droite du Bureau)
2. Cliquez sur le bouton START (sauf si déjà vert)
3. Attendez 5-10 secondes que tout démarre
4. Actualisez la page du navigateur (F5)
```

**2. URL incorrecte**

```
Indication: Vous avez entré une URL bizarre

Solution:
Vérifiez l'URL exact:
http://localhost/ctr.net-fardc/login.php

Pas de:
- HTTPS (http seulement)
- Chemin mal orthographié
- Port différent (3306, 5432, etc.)

Format correct:
┌────────────────────────────────────────────┐
│ http://localhost/ctr.net-fardc/login.php   │
│ │         │         │   │   │              │
│ │         │         │   │   └─ Fichier    │
│ │         │         │   └────── Dossier   │
│ │         │         └────────── Domaine   │
│ │         └────────────────────── Host     │
│ └──────────────────────────────────── Protocol
└────────────────────────────────────────────┘
```

**3. Pare-feu ou VPN bloque l'accès**

```
Indication: Une fenêtre Windows bloque, ou vous êtes sur VPN externe

Solution:
1. Désactivez VPN (si utilisé)
2. Vérifier que Windows Defender/Firewall n'a pas bloqué Apache
   - Paramètres → Sécurité Windows → Pare-feu
   - Voir si Apache est dans les applications bloquées
   - Cliquez "Autoriser" si necessary
3. Réessayez

Si sur VPN militaire obligatoire:
- Vous devez être en réseau interne FARDC
- Contacts IT si toujours pas d'accès
```

### Problème: "Erreur d'authentification / Login échoue"

#### Symptômes Possibles
- "Login ou mot de passe incorrect"
- "Compte désactivé"
- "Vous n'avez pas les droits nécessaires"

#### Solutions par Type d'Erreur

**Erreur: "Login ou mot de passe incorrect"**

```
Causes:
1. Vous avez mal tapé
2. Caps-Lock est activé (essayez sans)
3. Espace au début/fin (copier-coller)
4. Mot de passe temporaire expiré
5. Compte n'existe pas

Solutions:
1. Vérifiez saisie (pas de Caps-Lock)
2. Vérifiez email avec identifiants (copie exacte)
3. Réessayez 2-3 fois
4. Cliquez "Mot de passe oublié?" pour réinitialiser
5. Contactez admin si compte n'existe pas

Note: Après 5 tentatives échouées, votre compte est bloué 15min
(protection contre brute-force)
```

**Erreur: "Compte désactivé"**

```
Indication: Vous ne pouvez pas vous connecter

Raison: Un admin a **désactivé** votre compte (souvent temporaire)

Solutions:
1. Attendez (peut-être maintenance temporaire)
2. Contactez votre admin pour demander réactivation
3. Donnez votre login et email pour que l'admin vérifie

Admin peut vérifier dans "Gestion des utilisateurs"
```

**Erreur: "Vous n'avez pas les droits accéder à cette application"**

```
Indication: Votre compte existe mais "CONTROLEUR" (trop limité)

Raison: Les CcontrolEURs ne voient QUE le formulaire de contrôle

Solution pour CONTROLEUR:
- C'est normal, c'est le comportement voulu
- Vous pouvez UNIQUEMENT effectuer des contrôles
- Rien d'autre est autorisé

Solution si vous aviez plus:
- Votre profil a été changé récemment
- Contactez admin pour demander changement...
```

### Problème: "Session expirée"

#### Symptômes
- Vous êtes redirigé vers login soudainement
- Message "Votre session a expiré"
- Vous avez perdu vos données non enregistrées

#### Causes et Solutions

**Cause 1: 30 minutes d'inactivité (timeout automatique)**

```
Raison: Pour la sécurité, les sessions expirent après 30min sans
        activité (protection contre accès non autorisé)

Solution:
1. Reconnectez-vous
2. Vos données **enregistrées** sont conservées
3. Vos modifications non-enregistrées sont **perdues**

Prévention:
→ Enregistrez régulièrement (Ctrl+S ou cliquez le bouton)
→ Changez de page après enregistrement
→ Ne laissez pas l'appli inactive longtemps
```

**Cause 2: Vous êtes connecté ailleurs (même account sur deux machines)**

```
Raison: Vous avez commencé une connexion sur un autre ordinateur
        L'une des sessions a été fermée pour sécurité

Solution:
1. Reconnectez-vous ici
2. Ferrez l'autre session (l'ordinateur/navigateur autre)
3. Ne pas utiliser le même account sur 2 machines simultanement
```

**Cause 3: Administrateur a fermé votre session**

```
Raison: L'admin a déconnecté votre session (maintenance, sécurité)

Solution:
1. Reconnectez-vous
2. Contactez admin pour connaître la raison
```

---

## Problèmes de Navigateur

### Problème: "Fonctionnalités ne marchent pas correctement"

#### Causes Courantes et Solutions

**1. JavaScript est désactivé**

```
Indication: Les boutons ne répondent pas, l'appli semble figée

Solution:
Google Chrome:
→ Menu (⋯) → Paramètres → Confidentialité et sécurité
  → Paramètres du site → JavaScript
  → Vérifier que "Autorisé" est sélectionné
  → Ajouter localhost à la liste "Autorisé"

Mozilla Firefox:
→ À propos:config dans l'adresse
→ Chercher "javascript.enabled"
→ Vérifier que la valeur est "true"

Microsoft Edge:
→ Commande + Y → Paramètres → Cookies et autorisations
  → JavaScript → Activer
```

**2. Cookies/Cache obsolète**

```
Indication: L'appli affiche la mauvaise version, erreurs bizarres

Solution:
Nettoyer le cache:

1. Appuyez sur Ctrl+Maj+Supp (Windows) ou Cmd+Maj+Supp (Mac)
2. Sélectionnez:
   - ☑ Cookies et autres données de site
   - ☑ Images et fichiers en cache
   - Plage : "Toute les données"
3. Cliquez "Supprimer les données"
4. Fermez complètement le navigateur
5. Rouvrez et accédez à l'appli

Alternative simple:
- Ctrl+F5 (rechargement forcé, sans cache)
```

**3. Extensions de navigateur qui interfère**

```
Indication: L'appli bugs uniquement avec certaines extensions 
           (adblocker, dark mode, etc.)

Solution:
Mode incognito (pas d'extensions):
1. Appuyez sur Ctrl+Shift+N (Chrome/Edge) ou Ctrl+Maj+P (Firefox)
2. Allez à l'appli
3. Si ça marche → C'est une extension
4. Désinstallez l'extension fautive (probable AdBlock, Tampermonkey)

Ou:
1. Menu → Extensions
2. Désactivez provisoirement les suspects
3. Réessayez
```

**4. Plug-ins manquants (Flash, etc.)**

```
Indication: Vidéos ne marchent pas, certains contenus vides

Solutions:
1. Flash : Complètement déprecated, pas supporté
2. PDF : Devrait être intégré dans le navigateur moderne
3. Autres : Généralement pas nécessaire pour l'appli

La plupart de l'appli fonctionne sans plugins spéciaux
```

### Problème: "Navigateur très lent avec l'appli"

#### Solutions

**1. Mises à jour du navigateur**

```
Chrome:
1. Menu (⋯) → À propos de Google Chrome
2. Chrome se met à jour auto
3. Relancez le navigateur

Firefox:
1. Menu (≡) → ? → À propos de Firefox
2. Firefox se met à jour auto
3. Relancez

Edge:
1. Menu (⋯) → Paramètres → À propos de Microsoft Edge
2. Se met à jour auto
3. Relancez
```

**2. Trop d'onglets ouverts**

```
Symptôme: Tout ralentit, navigateur consomme beaucoup RAM

Solution:
1. Fermez les onglets inutilisés (gardez ~5 max)
2. Fermez les autres applications (YouTube, etc.)
3. Redémarrez le navigateur
```

**3. RAM insuffisante**

```
Vérifiez:
Ctrl+Alt+Supp → Onglet Processus → Colonne Mémoire

Si Chrome/Firefox > 1GB:
→ Trop lourd
→ Fermez le navigateur
→ Fermez autres applications
→ Relancez
```

---

## Problèmes de Base de Données

####Problème: "Erreur de base de données"

##### Symptômes
- "Connection refusée"
- "Erreur de requête SQL"
- "Base de données indisponible"

##### Causes et Solutions

**1. MySQL ne s'est pas lancé**

```
Indication: Messages d'erreur SQL, connexion impossible

Solution via Laragon:
1. Ouvrez Laragon (icône en bas à droite)
2. Cherchez le bouton MySQL (doit être vert)
3. Si rouge → Cliquez pour démarrer
4. Attendez 5-10 secondes
5. Relancez l'appli (F5)
```

**2. Trop de connexions à MySQL**

```
Indication: "Too many connections" 

Raison: Trop de sessions simultanées ou connexions restées ouvertes

Solution rapide:
1. Fermez tous les onglets/machines utilisant l'appli
2. Attendez 5 minutes
3. Redémarrez MySQL (voir ci-dessus)
4. Reconnectez-vous

Solution admin:
→ Augmenter max_connections dans MySQL config
→ Voir section "Procédures Avancées"
```

**3. Permissions de fichiers incorrectes**

```
Indication: Erreurs d'écriture, fichiers uploads ne marchent pas

Solution:
Windows Laragon:
1. Laragon → Menu → PHP → Version List
2. Éditer php.ini
3. Vérifier upload_dir exists et permissions

Mieux: Contactez support/IT
```

---

## Problèmes de Données

### Problème: "Militaire/Données disparus"

#### Causes et Solutions

**1. Données supp expérées**

```
Indication: J'avais des données, elles ont disparu

Causes probables:
- Quelqu'un a supprimé (cliquez  sur suppression)
- Récupération de sauvegarde (rollback)
- Erreur base de données

Solutions:
1. Vérifiez les logs d'audit (Sécurité → Logs)
   → Voir qui a supprimé, quand
2. Demandez à l'admin de restaurer depuis backup (24h avant)
3. Enregistrez une plainte (problème critère)

Note: La plupart des suppression sont tracées dans les logs!
```

**2. Données ne s'enregistrent pas**

```
Indication: J'ai cliqué enregistrer mais pas de save

Solutions:
1. Attendez 10 secondes (requête lente)
2. Vérifiez les messages d'erreur (champs obligatoires?)
3. Vérifiez l'internet (Ctrl+Maj+I → Onglet Réseau)
4. Réessayez

Si persiste:
→ Contactez support avec screenshot de l'erreur
```

### Problème: "Les données affichent bizarrement / encodage"

#### Symptômes
- Caractères "?????", "ã", "é" au lieu de "é"
- Texte en majuscules ou minuscules non expecté
- Données mélangées

#### Solutions

**1. Encodage UTF-8 défaillant**

```
Raison: Probablement issue importer/export

Solution:
1. Navigateur → F12 → Console
2. Vérifiez messages d'erreur
3. Si "charset", demander à l'admin de vérifier database encoding
   (doit être UTF-8)

Admin:
→ MySQL → SELECT CHARACTER_SET_NAME FROM TABLES WHERE TABLE_SCHEMA='db'
→ Vérifier UTF8MB4 ou UTF8
```

**2. Donnée importée corruptued**

```
Raison: Fichier importé avait le mauvais format/encodage

Solution:
1. Exporter les données existantes (sauvegarder)
2. Supprimer les données corruptued
3. Réimporter le fichier (après correction d'encodage)
   Dans Excel: Enregistrer sous → CSV UTF-8 (.csv)
```

---

## Problèmes de Performance

### Problème: "L'application est très lente"

#### Diagnostic

**Où est le problème?**

```
1. Réseau lent?
   - Testez: https://speedtest.net
   - Selon si < 5Mbps → Trop lent

2. Navigateur lent?
   - F12 → Onglet Réseau
   - Actualisez la page
   - Regardez le temps de réponse
   - Si > 5 secondes → Problème serveur ou réseau

3. Ordinateur surchargé?
   - Ctrl+Alt+Supp → Onglet Processus
   - Regardez l'utilisation CPU (% usage)
   - Si > 80% → Fermez d'autres apps
```

#### Solutions par Cause

**Cause: Connexion internet lente**

```
Symptômes:
- Speedtest montre < 5 Mbps
- Pages chargent lentement
- Même sites externes sont lents

Solutions:
1. Utilisez Ethernet (plus rapide que Wi-Fi)
2. Rapprochez-vous du routeur Wi-Fi
3. Fermez autres téléchargements
4. Contactez IT si VPN/réseau lésion
```

**Cause: Base de données lente / requête lente**

```
Symptômes:
- Les pages chargent lentement
- Mais votre internet est rapide
- Ça ralentit surtout quand beaucoup de militaires

Solutions courtes:
1. Attendez (peut-être utilisateur lourd travaille)
2. Essayez plus tard
3. Réduisez le filtrage (moins de données à chercher)

Solutions admin:
1. Vérifier MySQL performance
2. Ajouter indexes sur tables principales
3. Nettoyer les vieux logs
4. Augmenter RAM MySQL
```

**Cause: Trop d'onglets/Applications ouvertes**

```
Symptômes:
- Votre ordinateur ralentit (et la plupart des apps)
- Chrome/Firefox consomme > 1GB RAM

Solutions:
1. Fermez les onglets inutiles (gardez ~5)
2. Fermez autres applications (YouTube, Netflix, etc.)
3. Redémarrez l'ordinateur si vraiment lourd
4. Augmentez la RAM si possible
```

### Problème: "Un rapport ou export très nombreuses données est lent"

#### Solutions

**Pour Exporter Gros Volumes:**

```
Problème: Exporter 10 000+ contrôles prend 5+ minutes

Solutions:
1. Utilisez des filtres (par date, unité) pour en exporter moins
2. Demandez à l'admin une export directe (backend, plus rapide)
3. Attendez il faut être patient...
4. Évitez PDF pour très gros volumes (Excel bêm mieux)
```

---

## Erreurs Courantes

### Codes Erreur HTTP

| Code | Signification | Solution |
|------|---------------|---------| 
| **200** | OK | ✅ Pas d'erreur |
| **301/302** | Redirection | ℹ️ Normal, l'appli redirige |
| **400** | Mauvaise requête | ❌ Données invalides, réessayez |
| **401** | Non authentifié | 🔐 Reconnectez-vous |
| **403** | Accès interdit | 🔒 Vous n'avez pas les droits |
| **404** | Page non trouvée | ❌ URL/lien cassé |
| **500** | Erreur serveur | 🔴 Bug produits, contactez support |
| **502/503** | Serveur unavailable | 🔴 Serveur down, attendez |
| **504** | Timeout | ⏱️ Serveur trop lent, attendez |

### Erreur: "Champ obligatoire manquant"

#### Solution
```
Vérifiez que tous les champs avec * (astérisque) sont remplis:

❌ Nom complet : [______] (VIDE)
❌ Grade : [________] (Choisir dans le menu)
✅ Email : [ok@test.com] (Rempli)

Remplissez tous els champs vides, puis réessayez
```

### Erreur: "Matricule déjà existe"

#### Solution
```
Cause: Vous avez entré le même matricule qu'un autre militaire

Solutions:
1. Vérifiez que le matricule est correct
   - Cherchez dans la liste si le militaire existe déjà
2. Si militaire existe et vous voulez le modifier:
   - Allez à la fiche du militaire existant
   - Cliquez "✏️ Modifier"
   - Changez les données (pas le matricule)
3. Si c'est un nouveau militaire avec bon matricule:
   - Demandez à l'admin de vérifier les doublons
```

### Erreur: "Fichier est trop volumineux"

#### Solutions
```
Quand: Vous essayez d'upload un fichier (import, avatar)

Limites typiques:
- Import Excel/CSV : 10 MB max
- Avatar (photo) : 2 MB max

Solutions:
1. Compressez le fichier avant upload
   - 7-Zip (gratuit)
   - WinRAR
2. Divisez le fichier en plusieurs parties
3. Utilisez une autre méthode (email à l'admin)
```

---

## Procédures Avancées

### Pour Administrateurs

#### Procédure: Réinitialiser la Base de Données

```bash
⚠️ATTENTION: SUPPRIME TOUTES LES DONNÉES

# Via phpMyAdmin:
1. Ouvrez http://localhost/phpmyadmin
2. Sélectionnez la base "ctr_net_fardc" (ou son nom)
3. Cliquez sur "Opérations" → "Supprimer la BD"
4. Confirmez
5. Réimportez le backup initial (fichier .sql)

# Via MySQL commandline:
mysql -u root -p
DROP DATABASE ctr_net_fardc;
CREATE DATABASE ctr_net_fardc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Puis:
mysql -u root -p ctr_net_fardc < /chemin/to/backup.sql
```

#### Procédure: Augmenter max_connections MySQL

```
# Éditquand les connexions drop à "Too many connections"

1. Arrêtez MySQL (via Laragon)
2. Trouvez my.ini ou my.cnf:
   - Windows Laragon: C:\laragon\data\mysql\my.ini
   - Linux: /etc/mysql/mysql.conf.d/mysqld.cnf

3. Cherchez la ligne:
   max_connections = 151

4. Changez en:
   max_connections = 500

5. Sauvegardez et relancez MySQL
```

#### Procédure: Restaurer une Sauvegarde

```bash
# Si données corruption ou suppression accidentelle

1. Trouvez le fichier backup (dossier /backup/)
   Fichier: ctr_net_fardc_YYYY-MM-DD.sql

2. Stopez Laragon (MySQL)

3. Restaurez:
   mysql -u root -p ctr_net_fardc < /chemin/to/backup_20240115.sql

4. Relancez Laragon
5. Vérifiez que les données sont back
```

#### Procédure: Nettoyer les Logs Anciens

```bash
# Si les logs accumulent et ralentissent

1. Connectez-vous à MySQL:
   mysql -u root -p
   USE ctr_net_fardc;

2. Supprimez les logs > 180 jours:
   DELETE FROM logs WHERE date_creation < DATE_SUB(NOW(), INTERVAL 180 DAY);

3. Optimisez la table:
   OPTIMIZE TABLE logs;

4. Vérifiez l'espace gagné:
   SELECT size FROM information_schema.TABLES WHERE TABLE_NAME='logs';
```

---

## Checklist de Dépannage

Avant de contacter le support, vérifiez :

- [ ] Laragon/serveur web redémarré
- [ ] Cache du navigateur vidé (Ctrl+Maj+Supp)
- [ ] Pas de VPN actif (ou VPN FARDC autorisé)
- [ ] JavaScript activé dans navigateur
- [ ] Connexion internet bonne (speedtest.net)
- [ ] Other onglets/applis fermés
- [ ] Navigateur à jour
- [ ] Pas d'extensions bizarres actives
- [ ] MySQL démarre correctement (vert dans Laragon)
- [ ] Base de données accessible (phpMyAdmin)
- [ ] Compte d'utilisateur actif (pas désactivé)
- [ ] Pas de message d'erreur spécifique (notez-le!)

---

## Support Technique

**Si vous n'encontrez toujours pas la solution:**

📧 Email : support@fardc.org  
☎️ Tél : +243 XX XXX XXXX  
🌐 Web : https://support.fardc.org

**Fournirez-  toujours :**
- Description du problème (en français)
- Capture d'écran (screenshot) de l'erreur
- Nom complet et login de l'utilisateur
- Heure exacte du problème
- Navigateur utilisé (Chrome, Firefox, etc.)
- Copies des messages d'erreur
- Déjà tenté quoi comme solutions

---

**Dernière mise à jour:** Janvier 2024  
**Version:** 1.0  
**Maintenance:** Support technique FARDC

