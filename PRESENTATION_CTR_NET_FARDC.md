# Présentation - CTR.NET-FARDC

## Section 1: Résumé exécutif

CTR.NET-FARDC est une application de contrôle des effectifs qui structure le travail de terrain autour de quatre profils (`ADMIN_IG`, `OPERATEUR`, `CONTROLEUR`, `ENROLEUR`), de règles de saisie claires et d’une traçabilité systématique des actions sensibles. La solution centralise la gestion des militaires, la saisie des contrôles, la synchronisation des équipes et la supervision via tableaux de bord, avec un fonctionnement adapté aux responsabilités de chaque profil. L’enjeu principal est d’améliorer la fiabilité des données, d’accélérer les opérations de contrôle et de sécuriser la gouvernance grâce aux journaux d’audit et aux sauvegardes automatiques.

---

## Section 2: Plan des slides

## Slide 1 — Titre et objectif de la séance

**Message clé**
Aligner tous les acteurs sur le fonctionnement réel de CTR.NET-FARDC et sur sa valeur opérationnelle.

**Points à dire à l’oral**
- CTR.NET-FARDC est une solution de contrôle opérationnel des effectifs.
- La présentation couvre le fonctionnement réel, pas un scénario théorique.
- Nous allons montrer les profils, les flux, les contrôles et la traçabilité.
- L’objectif final est une décision claire sur la généralisation et les priorités.

## Slide 2 — Problème métier adressé

**Message clé**
Le dispositif répond au besoin de fiabilité des contrôles, de rapidité d’exécution et de gouvernance documentaire.

**Points à dire à l’oral**
- Les opérations de contrôle exigent cohérence, rapidité et preuve d’exécution.
- Sans outillage, les risques sont: erreurs, doublons, faible traçabilité.
- Le besoin n’est pas seulement de collecter, mais de gouverner la donnée.
- CTR.NET-FARDC formalise les flux de bout en bout.

## Slide 3 — Périmètre fonctionnel

**Message clé**
L’application couvre les fonctions clés: administration, militaires, contrôles, synchronisation et supervision centrale.

**Points à dire à l’oral**
- Module Administration: utilisateurs et journaux.
- Module Militaires: référentiel de base des personnes contrôlées.
- Module Contrôles: saisie et consultation des contrôles.
- Module Synchronisation: envoi sécurisé des `equipes` et `controles` vers le central.
- Module Tableau de bord: visibilité consolidée par équipe synchronisée.

## Slide 4 — Profils et responsabilités

**Message clé**
Quatre profils structurent l’accès et les responsabilités opérationnelles.

**Points à dire à l’oral**
- `ADMIN_IG`: pilotage, supervision, administration complète.
- `OPERATEUR`: exécution opérationnelle avec tableau de bord et contrôles.
- `CONTROLEUR`: saisie ciblée des contrôles via l'application mobile `CTR.NET`.
- `ENROLEUR`: enrôlement terrain des militaires vivants via l'application mobile `ENROL.NET`.
- Cette séparation réduit les risques d’accès non pertinent.

## Slide 5 — Flux d’accès réel (connexion/redirection)

**Message clé**
Après connexion, chaque profil est guidé vers son point d’entrée utile.

**Points à dire à l’oral**
- `ADMIN_IG` est orienté vers le dashboard (`index.php`).
- `OPERATEUR` passe par `preferences.php` si ses préférences sont absentes.
- `CONTROLEUR` et `ENROLEUR` n'accèdent plus au web ; ils utilisent `CTR.NET` et `ENROL.NET`.
- Cette logique réduit les étapes inutiles et accélère l’action.

## Slide 6 — Saisie d’un contrôle: règles métier

**Message clé**
La saisie de contrôle est encadrée par des règles qui sécurisent la qualité des données.

**Points à dire à l’oral**
- Mentions réelles utilisées: `Présent`, `Favorable`, `Défavorable`.
- Recherche militaire assistée (AJAX) avec retour rapide.
- Vérification de doublon sur matricule: un militaire déjà contrôlé est signalé dans les résultats et n'est plus sélectionnable.
- Règles de bénéficiaire et lien de parenté appliquées avant enregistrement.
- Horodatage précis à l’insertion (`NOW()`).

## Slide 7 — Synchronisation terrain → central

**Message clé**
La synchronisation a été simplifiée pour envoyer uniquement les `equipes` et les `controles` vers le serveur central.

**Points à dire à l’oral**
- Chaque site renseigne l’IP ou l’URL du serveur central avant l’envoi.
- Le flux transporte le roster de `equipes` et les `controles` non encore synchronisés.
- Le tableau de bord central crée une carte par équipe source avec les effectifs reçus.
- Cette approche réduit les erreurs et facilite le suivi multi-sites.

## Slide 8 — Sécurité et contrôle d’accès

**Message clé**
La sécurité s'appuie sur l'authentification, les sessions, les contrôles de rôle, et la protection des données sensibles.

**Points à dire à l'oral**
- Vérification d'authentification avant l'accès aux pages sensibles.
- Fonctions dédiées au contrôle de profil (`check_profil`, `verifier_acces`).
- Gestion de session avec option "Se souvenir de moi".
- Réinitialisation de mot de passe par token à durée limitée.
- 🔐 **Chiffrement AES-256-CBC** (v1.1.0+): protection des fichiers sensibles (base de données, authentification, configuration) contre la lecture claire.
- Clé secrète gérée automatiquement et sauvegardée de manière sécurisée.

## Slide 9 — Traçabilité et audit

**Message clé**
Les actions critiques sont journalisées pour renforcer redevabilité et auditabilité.

**Points à dire à l’oral**
- Connexion, échec de connexion, déconnexion, ajouts et exports sont tracés.
- Les logs alimentent les contrôles internes et la reconstitution d’événements.
- Le dispositif réduit les zones d’ombre opérationnelles.
- L’audit favorise la qualité et la discipline de saisie.

## Slide 10 — Sauvegardes et résilience opérationnelle

**Message clé**
La continuité est renforcée par un mécanisme de sauvegarde automatique.

**Points à dire à l’oral**
- Génération d’archives ZIP contenant les données exportées clés.
- Sauvegardes automatiques déclenchées à intervalle régulier.
- Le dossier `backups/` sert de point de récupération rapide.
- Une politique de rotation est recommandée pour maîtriser le volume.

## Slide 11 — Bénéfices observables

**Message clé**
CTR.NET-FARDC améliore simultanément qualité, vitesse et gouvernance.

**Points à dire à l’oral**
- Réduction des erreurs de saisie via règles métier explicites.
- Meilleure vitesse d’exécution grâce aux flux orientés par profil.
- Traçabilité renforcée pour le contrôle interne.
- Vision consolidée pour le pilotage et la décision.

## Slide 12 — Conclusion et décision attendue

**Message clé**
Le système est opérationnel et prêt à être consolidé selon un plan d’amélioration maîtrisé.

**Points à dire à l’oral**
- Les fondamentaux fonctionnels sont en place et cohérents.
- La valeur est immédiate sur la fiabilité et la supervision.
- Les prochaines actions portent sur standardisation et gouvernance.
- Décision attendue: confirmer le déploiement et valider la feuille de route.

---

## Section 3: Script de démonstration

## Scénario de démo en direct (8 à 12 minutes)

### Étape 1 — Connexion
- Ouvrir `login.php`.
- Se connecter avec un compte de démonstration.
- Expliquer que l’identifiant peut être login/nom/email.

### Étape 2 — Navigation selon profil
- Montrer la redirection de session selon le profil connecté.
- Cas `ADMIN_IG`: accès dashboard.
- Cas `CONTROLEUR`: connexion web refusée ; la démonstration se fait depuis l'application mobile.

### Étape 3 — Saisie d’un contrôle
- Ouvrir `modules/controles/ajouter.php`.
- Rechercher un militaire (saisie de quelques caractères).
- Choisir une mention valide (`Présent`, `Favorable`, `Défavorable`).
- Enregistrer et commenter les validations (doublon, bénéficiaire, lien).

### Étape 4 — Consultation d’une liste
- Ouvrir la liste des contrôles.
- Appliquer un filtre simple.
- Montrer un export (CSV/Excel/PDF selon disponibilité UI).

### Étape 5 — Exemple de log/audit
- Ouvrir les logs (module administration).
- Montrer la trace d’une action récente (connexion, ajout, export).
- Expliquer la valeur de preuve et de suivi.

### Clôture de la démo
- Résumer le cycle complet: connexion -> action -> traçabilité.
- Revenir aux bénéfices opérationnels concrets.

---

## Section 4: Q&R anticipées

1. **Pourquoi plusieurs profils et deux applications mobiles distinctes ?**
   Pour séparer clairement les responsabilités et réduire le risque d’erreurs ou d’accès inadaptés.

2. **Le CONTROLEUR peut-il accéder au dashboard ?**
   Non. Le profil CONTROLEUR n'accède plus au web et doit utiliser l'application mobile.

3. **Quelles mentions sont autorisées ?**
   `Présent`, `Favorable`, `Défavorable`.

4. **Comment éviter les doublons de contrôle ?**
   L’application vérifie l’existence d’un contrôle par matricule avant insertion.

5. **Que se passe-t-il en cas d’oubli de mot de passe ?**
   Un flux de réinitialisation par token temporaire permet de définir un nouveau mot de passe.

6. **Les actions sont-elles tracées ?**
   Oui, les événements majeurs sont journalisés pour audit et supervision.

7. **Peut-on exporter les données ?**
   Oui, les listes principales proposent des exports selon les pages.

8. **Les données sont-elles sauvegardées automatiquement ?**
   Oui, un mécanisme de sauvegarde automatique est prévu.

9. **Comment garantir la qualité des données saisies ?**
   Par les validations métier (mentions, doublons, champs obligatoires, logique bénéficiaire).

10. **Quelle est la prochaine priorité après stabilisation ?**
   Renforcer la gouvernance (rotation sauvegardes, standardisation des procédures, suivi qualité).

---

## Section 5: Conclusion et recommandations

CTR.NET-FARDC apporte une base robuste pour piloter les contrôles avec discipline opérationnelle et visibilité de gestion. Le dispositif est cohérent sur ses fonctions essentielles: rôles, flux de saisie, audit et continuité. La décision recommandée est de confirmer l’exploitation, de formaliser les procédures d’usage par profil et d’inscrire un plan court d’amélioration continue.

### Prochaines actions recommandées
- Valider un protocole de démonstration standard pour les équipes.
- Formaliser une charte de saisie (mentions, règles bénéficiaire, qualité des champs).
- Mettre en place une revue périodique des logs et des sauvegardes.
- Suivre des indicateurs simples: taux d’erreur, délai de saisie, volume traité.
