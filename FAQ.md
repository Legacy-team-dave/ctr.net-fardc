# FAQ - CTR.NET-FARDC

FAQ opérationnelle basée sur le comportement réel du code.

## 1) Profils et accès

### Quels profils existent dans l'application ?
Trois profils sont utilisés : `ADMIN_IG`, `OPERATEUR`, `CONTROLEUR` (mobile uniquement).

### Quel profil voit le tableau de bord (`index.php`) ?
`ADMIN_IG` et `OPERATEUR` uniquement.

### Pourquoi un `CONTROLEUR` ne peut pas se connecter sur le web ?
Le profil `CONTROLEUR` est désormais réservé exclusivement à l'application mobile CTR.NET Mobile. La connexion web est bloquée.

### Où va un `OPERATEUR` juste après connexion ?
- vers `preferences.php` → `equipes.php` → `index.php` si aucune préférence n'est enregistrée,
- vers `modules/controles/ajouter.php` si les préférences existent.

### Où va un `ADMIN_IG` juste après connexion ?
Vers `index.php`.

### Un utilisateur inactif peut-il se connecter ?
Non. La connexion vérifie `actif = true`.

### L'application bloque-t-elle les pages non connectées ?
Oui, via `require_login()` ou des vérifications directes de session.

### Quelle différence entre `check_profil()` et `verifier_acces()` ?
- `check_profil()` est une protection PHP côté serveur.
- `verifier_acces()` est aussi côté serveur et redirige avec message flash.

### Tous les menus visibles garantissent-ils le même niveau de protection serveur ?
Non. Les menus masquent certaines entrées, mais certaines pages utilisent seulement `require_login()` sans contrôle de profil strict.

### Exemple concret de différence menu/protection ?
Le menu « Litige » est prévu pour `ADMIN_IG` et `OPERATEUR`, mais les pages litige lues exigent surtout la connexion.

## 2) Connexion et session

### Quels identifiants de connexion sont acceptés ?
Le login peut être : `login`, `nom_complet` ou `email`.

### Le mot de passe est-il vérifié de façon sécurisée ?
Oui, avec `password_verify()` sur un hash (`password_hash`).

### À quoi sert « Se souvenir de moi » ?
À créer un `remember_token` valable 30 jours (cookie + base de données).

### Que se passe-t-il si « Se souvenir de moi » n'est pas coché ?
Le token persistant est supprimé (base + cookie).

### Que se passe-t-il à la déconnexion ?
Le token « remember me » est invalidé, la déconnexion est journalisée, puis la session est détruite.

### Y a-t-il une expiration d'inactivité de session ?
Une fonction existe (`check_session_timeout`, 1 heure), mais elle doit être appelée pour s'appliquer.

### Les tentatives de connexion ratées sont-elles journalisées ?
Oui, action `ECHEC_CONNEXION`.

### Les connexions réussies sont-elles journalisées ?
Oui, action `CONNEXION`.

## 3) Préférences utilisateur

### Que contient `preferences.php` ?
La sélection des garnisons et catégories servant de filtres personnalisés.

### Pourquoi l'opérateur est parfois bloqué sur `preferences.php` ?
Ce n'est pas un blocage : c'est l'étape initiale obligatoire quand aucune préférence n'est enregistrée.

### Où sont stockées les préférences ?
Dans la colonne `utilisateurs.preferences` (JSON).

### Peut-on modifier les préférences plus tard ?
Oui, en revenant sur la page des préférences.

## 4) Contrôles

### Quelles mentions existent réellement lors d'un contrôle ?
`Présent`, `Favorable`, `Défavorable`.

### Peut-on enregistrer deux contrôles pour le même matricule ?
Non. Un contrôle existant sur le matricule bloque un nouvel enregistrement.

### La recherche de militaire dans le formulaire de contrôle est-elle dynamique ?
Oui, via un endpoint AJAX (`?ajax=search&q=...`) avec minimum 2 caractères.

### Combien de résultats la recherche renvoie-t-elle au maximum ?
10 résultats.

### Que se passe-t-il si aucun lien de parenté n'est choisi ?
L'enregistrement est refusé.

### Que signifie le lien « Militaire lui-même » ?
Le contrôle est traité comme un contrôle du militaire, sans bénéficiaire.

### Quand un bénéficiaire est-il obligatoire ?
Quand le lien n'est pas « Militaire lui-même ».

### Peut-on saisir un nouveau bénéficiaire sans ancien bénéficiaire ?
Oui.

### Comment est calculé `lien_parente` enregistré ?
Le lien saisi est normalisé (ex. `Epouse` → `Veuve`, `Fils`/`Fille` → `Orphelin`, etc.).

### Qu'arrive-t-il pour `DCD_AP_BIO` avec statut « vivant » ?
Le texte `Mort vivant` est injecté dans les observations (ou préfixé à la saisie).

### La date de contrôle inclut-elle l'heure ?
Oui (`NOW()`).

### Les contrôles ajoutés sont-ils journalisés ?
Oui, action `AJOUT` sur la table `controles`.

### Peut-on exporter la liste des contrôles ?
Oui, plusieurs formats sont proposés depuis la liste.

### Les exports sont-ils tracés ?
Oui, un appel AJAX enregistre une action `EXPORT`.

## 5) Litiges

### Peut-on créer des litiges dans l'application ?
Oui, via le module `modules/litige`.

### Peut-on lister et exporter les litiges ?
Oui, la liste prévoit filtres, statistiques et exports.

### Les litiges sont-ils triés comment ?
Par `id` descendant, puis `date_controle` et `cree_le`.

### L'application calcule-t-elle une ZDEF pour les litiges ?
Oui, à partir de la province via une fonction de mapping.

## 6) Utilisateurs et profil

### Qui peut gérer les utilisateurs ?
Le module administration est conçu pour `ADMIN_IG`.

### Peut-on créer un compte `CONTROLEUR` ?
Oui, le formulaire d'ajout utilisateur propose ce profil. Il est utilisé exclusivement via l'application mobile CTR.NET Mobile.

### Le mot de passe minimal à la création d'un utilisateur est de combien ?
6 caractères.

### Peut-on désactiver un utilisateur ?
Oui, l'état actif/inactif est géré dans la table des utilisateurs.

### Que peut-on modifier dans `profil.php` ?
Nom complet, email, avatar (fichier ou URL), et mot de passe.

### Le changement de mot de passe dans le profil exige l'ancien mot de passe ?
Oui.

### Taille maximale d'avatar dans `profil.php` ?
5 Mo.

### Formats d'avatar acceptés ?
JPEG, PNG, GIF, WEBP.

### Le profil utilisateur modifié est-il journalisé ?
Oui, action `MODIFICATION_PROFIL`.

## 7) Réinitialisation de mot de passe

### Comment se déroule la réinitialisation ?
Deux étapes : demande de token puis définition du nouveau mot de passe via le lien tokenisé.

### Durée de validité du token de reset ?
1 heure.

### Longueur minimale du nouveau mot de passe via reset ?
4 caractères.

### Le reset est-il journalisé ?
Oui : `DEMANDE_RESET` puis `RESET_MDP`.

### Faut-il être connecté pour utiliser le lien de reset (`step=reset`) ?
Non, l'étape de reset par token est accessible sans session active.

## 8) Logs, audit et traçabilité

### Quelle table de logs est utilisée ?
Principalement `logs`.

### Et si la table `logs` n'existe pas ?
Elle est créée automatiquement, avec compatibilité vers `logs_actions`.

### Les anciens logs peuvent-ils être nettoyés ?
Oui, une fonction de nettoyage existe (par défaut 90 jours), à déclencher selon votre politique.

### Quelles actions sont typiquement tracées ?
Connexion, échec connexion, déconnexion, ajout, export, mise à jour profil, reset mot de passe, préférences, etc.

## 9) Sauvegardes

### L'application fait-elle des sauvegardes automatiques ?
Oui.

### À quelle fréquence réelle ?
Toutes les 8 heures via une tâche planifiée Windows.

### Que contient une sauvegarde ZIP ?
Exports incrémentaux (nouveaux éléments uniquement) de `controles`, `litiges` et `non_vus` en CSV et XLSX.

### La sauvegarde se lance-t-elle sans visiter les pages ?
Oui. Elle est exécutée par tâche planifiée (`run_backup_job.ps1` via `setup_backup_task.ps1/.bat`).

### Est-ce que chaque sauvegarde contient toutes les données historiques ?
Non. Chaque archive contient uniquement les nouveaux éléments détectés depuis la dernière sauvegarde réussie.

### La purge des anciennes sauvegardes est-elle automatique ?
Oui. Le job applique une purge intelligente : suppression des archives identiques puis conservation des 30 dernières archives non identiques.

### Peut-on lancer une purge manuelle ?
Oui, via `run_backup_purge.ps1 -MaxKeep 30` ou `run_backup_purge.bat 30` (valeur ajustable).

### Où sont stockées les sauvegardes ?
Dans le dossier `backups/`.

## 10) Base de données et configuration

### Où est la connexion base de données ?
`config/database.php`.

### L'application ajoute-t-elle automatiquement certaines colonnes manquantes ?
Oui, notamment `preferences`, `remember_token`, `remember_token_expires` (si absentes).

### Quels objets majeurs sont manipulés ?
`utilisateurs`, `militaires`, `controles`, `litiges`, `logs`.

## 11) Erreurs fréquentes

### « Accès refusé » malgré connexion valide : pourquoi ?
Le profil courant n'est pas autorisé pour la page visée.

### « Ce militaire a déjà été contrôlé » : que faire ?
Le matricule existe déjà dans `controles`. Il faut corriger les données plutôt que ressaisir un doublon.

### Pourquoi la recherche ne renvoie rien ?
Vérifier la longueur de saisie (au moins 2 caractères en AJAX) et l'existence réelle du militaire.

### Pourquoi je reviens toujours sur `preferences.php` ?
Vos préférences ne sont pas enregistrées ou non lisibles en base.

### Pourquoi l'avatar n'est pas pris en compte ?
Type de fichier non supporté, taille dépassée, ou problème d'écriture dossier.

## 12) 🔐 Chiffrement (v1.1.0+)

### Qu'est-ce que le chiffrement dans l'application ?
Depuis v1.1.0, les fichiers sensibles (base de données, authentification, etc.) sont protégés par AES-256-CBC, l'algorithme de chiffrement standard NIST.

### Est-ce transparent pour l'utilisateur final ?
Oui, 100% transparent. Les fichiers chiffrés sont déchiffrés automatiquement au runtime (< 5ms/startup).

### Comment initialiser le chiffrement ?
Une seule fois après l'installation :
```bash
php bin/encrypt.php init
```
Cela génère une clé aléatoire sauvegardée dans `.env`.

### Quels fichiers peuvent être chiffrés ?
Par défaut, 8 fichiers sensibles :
- `config/database.php` (identifiants)
- `includes/auth.php` (logique authentification)
- `includes/functions.php` (logique métier)
- Et 5 autres fichiers critiques

### Comment activer le chiffrement sur les fichiers ?
```bash
php bin/encrypt.php encrypt
```
Cela crée des versions `.encrypted` des fichiers.

### Que se passe-t-il si je déchiffre un fichier ?
```bash
php bin/encrypt.php decrypt config/database.php
```
Cela restaure la version en clair et supprime la version `.encrypted`.

### Puis-je vérifier quels fichiers sont chiffrés ?
Oui :
```bash
php bin/encrypt.php status
```
Affiche l'état de chiffrement de tous les fichiers.

### Que faire si j'ai perdu la clé secrète (.env) ?
C'est critique! Vous ne pouvez pas déchiffrer les fichiers sans la clé. Toujours sauvegarder `.env` en lieu sûr!

### Comment sauvegarder la clé secrète de manière sécurisée ?
Copier `.env` dans un dossier sécurisé (pas en ligne, protection d'accès) :
```powershell
Copy-Item .env "C:\Backups\encryption_key_$(Get-Date -Format yyyyMMdd).env"
```

### Peut-on changer la clé secrète ?
Oui, via rotation de clé (rotation automatique des fichiers) :
```powershell
./rotate_encryption_key.ps1
```

### Quelle est la durée du processus de rotation ?
Quelques secondes (dépend du nombre de fichiers). L'app reste accessible.

### La clé est-elle stockée dans Git ?
Non! Le fichier `.env` est dans `.gitignore.encryption`, donc la clé n'est jamais commitée accidentellement.

### Quelle interface utiliser pour gérer le chiffrement ?
Trois options :
1. **CLI** (technique) : `php bin/encrypt.php [command]`
2. **GUI Batch** (utilisateur) : Double-cliquer `encrypt_init.bat`, `encrypt_all.bat`, etc.
3. **PowerShell** (automatisation) : `./encrypt_sources.ps1`

### Quel est l'impact sur les performances ?
Minimal : < 5ms par démarrage. Le déchiffrement se fait en mémoire (pas I/O disque supplémentaire).

### Comment dépanner un problème de chiffrement ?
Voir [TROUBLESHOOTING.md](TROUBLESHOOTING.md) section "Problèmes de Chiffrement".

## 13) Bonnes pratiques d'exploitation

### Recommandation pour les profils
Réserver `ADMIN_IG` à l'administration; utiliser `OPERATEUR` pour la production web quotidienne et `CONTROLEUR` pour l'app mobile.

### Recommandation pour les sauvegardes
Contrôler régulièrement le volume du dossier `backups/` et définir une rotation.

### Recommandation pour la sécurité
Activer HTTPS en production et ajuster les attributs de cookie (`secure`, etc.).

### Recommandation pour la cohérence métier
Toujours utiliser les mêmes mentions (`Présent`, `Favorable`, `Défavorable`) dans les procédures internes.
