# CTR.NET-FARDC

Application PHP de contrôle des effectifs militaires.

## Mise à jour Avril 2026

L’écosystème comporte désormais **deux applications mobiles distinctes** :

- `CTR.NET` pour le profil **`CONTROLEUR`** ;
- `ENROL.NET` pour le profil **`ENROLEUR`**.

Les comptes `OPERATEUR`, `CONTROLEUR` et `ENROLEUR` sont créés avec le mot de passe par défaut **`987654321`** et restent **inactifs** tant qu’un `ADMIN_IG` ne les a pas activés.

## Profils

- `ADMIN_IG`
  - Gestion des utilisateurs
  - Gestion des militaires
  - Supervision des contrôles et rapports
- `OPERATEUR`
  - Saisie de contrôles
  - Accès au tableau de bord opérateur
- `CONTROLEUR`
  - Profil réservé à l'application mobile `CTR.NET`
  - Non accessible côté web (connexion bloquée)
- `ENROLEUR`
  - Profil réservé à l'application mobile `ENROL.NET`
  - Non accessible côté web (connexion bloquée)

## Mentions de contrôle (réelles)

Dans `modules/controles/ajouter.php`, les mentions enregistrées sont :

- `Présent` (militaire vivant)
- `Favorable` (bénéficiaire, contexte décédé)
- `Défavorable` (bénéficiaire, contexte décédé)

Le flux utilise aussi les statuts :

- `Vivant`
- `Décédé`

## QR d’enrôlement mobile

Le QR destiné à `ENROL.NET` est généré dans `modules/controles/liste.php` avec les règles suivantes :

- **QR disponible uniquement** pour un contrôle marqué **vivant** (`type_controle = Militaire`) ;
- **aucun QR** pour un contrôle marqué **décédé / bénéficiaire** ;
- le QR reste accessible manuellement dans `modules/controles/liste.php` mais **n’est plus ouvert automatiquement** après chaque enregistrement ;
- le mobile `ENROL.NET` résout ensuite les détails via `api/controles.php?action=qr_lookup` ;
- côté terrain, `ENROL.NET` suit le parcours **capture de la carte → empreintes via capteur → scan QR professionnel → validation** ;
- le projet `ctr-net-mobile` n’est **pas impacté fonctionnellement** par cette règle : il reste dédié au contrôle terrain du profil `CONTROLEUR`.

## Synchronisation locale → central

Pour la synchronisation, l’adresse à saisir doit pointer vers le **serveur central** de réception, par exemple :

- `http://IP-DU-SERVEUR/ctr-net-fardc_active_front_web`

Éviter d’utiliser l’URL locale de la page de synchronisation elle-même comme :

- `http://127.0.0.1/ctr.net-fardc/modules/controles/sync.php`

sauf si le central est réellement hébergé sur cette même machine. La phase affichée à **55%** correspond à l’envoi/réception réseau ; le délai autorisé a été élargi pour les synchronisations plus lourdes.

## Parcours opérateur actuel

- premier accès : `preferences.php` → `modules/equipes/index.php` → `modules/controles/ajouter.php`
- connexions suivantes : ouverture directe de `modules/controles/ajouter.php` si les préférences existent déjà
- déconnexion : retour systématique vers `login.php`
- tableau de bord local : la **carte RDC** reprend désormais le même cadrage et le même comportement que `ctr-net-fardc_active_front_web/index.php`, sur **fond blanc** et sans affichage des pays voisins, avec une taille encore réduite pour plus de lisibilité
- chargement cartographique : le spinner a été retiré au profit d’un message statique `Chargement de la carte...`
- cartes d’activités récentes **`Derniers militaires`** et **`Derniers contrôles`** harmonisées à la même hauteur
- widget **`Derniers militaires`** simplifié à : `Noms`, `Unité`, `Garnison`
- tableaux unifiés : colonnes `QR` masquées, `Observation(s)` abrégé en `OBN` et bordures d’en-tête harmonisées

## Structure réelle

- `index.php`, `login.php`, `logout.php`, `profil.php`, `preferences.php`
- `includes/` : auth, fonctions globales, header/footer
- `modules/` : `administration`, `militaires`, `equipes`, `controles`, `rapports`
- point d’entrée équipe : `modules/equipes/index.php`
- synchronisation locale : `modules/controles/sync.php` pour l’envoi des `equipes` et `controles` vers l’instance centrale
- logique DataTable unifiée : mêmes bases de recherche / pagination / colonnes masquées que `modules/controles/liste.php`
- `ajax/` : endpoints AJAX
- `api/` : API REST pour application mobile (auth, contrôles, profil, polling)
- `config/` : configuration base de données, chiffrement
- `assets/` : css, js, images, polices, données géographiques
- `backups/`

## Lancement

- `START.bat`
- `INSTALLER.bat`
- `launch.bat`
- `launch.ps1`
- `lanceur.ps1`

`INSTALLER.bat` crée un raccourci bureau `.lnk` (avec icône `assets/img/ig_fardc.ico` si disponible), pointant en priorité vers `launch.ps1` (fallback `launch.bat`). Un double-clic sur ce raccourci lance Laragon, attend le démarrage des services (Apache/MySQL), puis ouvre l'application dans le navigateur.

## Sauvegarde automatique consolidée

Les sauvegardes sont effectuées via un job Windows planifié, avec mise à jour d'une archive ZIP consolidée unique et rotation automatique.

Le mode internet est désormais durci par :

- des en-têtes HTTP de sécurité et le blocage direct des fichiers sensibles via `.htaccess` ;
- une limitation anti-bruteforce sur les connexions web et mobiles (verrouillage temporaire après plusieurs échecs depuis la même IP) ;
- des cookies de session `HttpOnly` / `SameSite` et `Secure` dès que l'application passe en HTTPS.

La sauvegarde se déclenche maintenant :

- par la tâche planifiée Windows toutes les 8 heures ;
- automatiquement en arrière-plan lorsqu'un nouveau contrôle est saisi après expiration du délai de 8h.

## 🔐 Système de Chiffrement

**CTR.NET-FARDC inclut un système de chiffrement AES-256-CBC complète** pour protéger le code source contre la lecture en clair.

### Démarrage Rapide

```bash
# 1. Initialiser (générer clé unique)
php bin/encrypt.php init

# 2. Chiffrer fichiers sensibles
php bin/encrypt.php encrypt

# 3. Vérifier le statut
php bin/encrypt.php status
```

### Caractéristiques

- ✅ **Algorithme:** AES-256-CBC (standard militaire NIST FIPS 197)
- ✅ **Transparent:** Déchiffrement automatique au runtime
- ✅ **Zéro impact:** Aucune modification du code applicatif
- ✅ **Interfaces multiples:** GUI (batch), CLI (PHP), PowerShell

### Documentation Chiffrement

Pour une documentation complète et des exemples pratiques:

- **[ENCRYPTION.md](ENCRYPTION.md)** — Guide complet
- **[ENCRYPTION_QUICKSTART.md](ENCRYPTION_QUICKSTART.md)** — 3 étapes
- **[ENCRYPTION_EXAMPLES.md](ENCRYPTION_EXAMPLES.md)** — Cas réels
- **[ENCRYPTION_IMPLEMENTATION_GUIDE.md](ENCRYPTION_IMPLEMENTATION_GUIDE.md)** — Guide IT

### Fichiers Clés

**Configuration & Crypto:**

- `config/encryption.php` — Fonctions AES-256-CBC
- `config/encrypted_loader.php` — Déchiffrement automatique
- `.env` — Clé secrète (à générer une fois)

**Sauvegardes Automatiques :**

- Fréquence : toutes les 8 heures (tâche planifiée Windows)
- Format : CSV (Excel compatible) + XLSX
- Mode : consolidé (ancienne + nouvelles données dans la même archive ZIP)
- Archive principale : `backups/backup_consolide_latest.zip`
- Sources principales : `equipes` et `controles` pour le périmètre actif, avec éventuels exports techniques selon l'environnement local
- Scripts : `setup_backup_task.bat`, `setup_backup_task.ps1`, `run_backup_job.ps1`
- Auto-relance métier : déclenchée par `mark_sync_dirty()` si de nouvelles données de contrôle arrivent après 8h
- Purge auto : suppression des doublons + conservation des 30 dernières archives non identiques
- Purge auto paramétrable : `setup_backup_task.ps1 -MaxKeep 30` (ou `setup_backup_task.bat 30`)
- Envoi e-mail (optionnel) : configurer config/backup_mail.json (adresse expéditeur + destinataire + SMTP)

**🧹 Nettoyage automatique des caches :**

- Exécution automatique : intégré au job planifié (toutes les 8h après backup + purge)
- Cibles : fichiers temporaires XLSX, verrous obsolètes, tokens expirés, anciens logs (> 90 jours)
- Script de nettoyage manuel : `run_cache_cleanup.ps1 -JoursLogs 90` ou `run_cache_cleanup.bat 90`

## URL

- Écran d'accueil : `http://127.0.0.1/ctr.net-fardc/splash_screen.php`
- Connexion directe : `http://127.0.0.1/ctr.net-fardc/login.php`

Avec **Laragon**, il n’est **pas obligatoire** de préciser un port tant qu’Apache utilise les ports par défaut (`80` en HTTP ou `443` en HTTPS). Le suffixe `:port` n’est nécessaire que si vous avez modifié la configuration Apache/Laragon.

## Base de données

Tables principales utilisées :

- `utilisateurs`
- `militaires`
- `controles`
- `logs`
- `equipes`

## Synchronisation active

- périmètre actuel : `equipes` et `controles` uniquement
- test de connectivité : `api/test_sync_connection.php`
- envoi local : `api/sync_controles.php`
- réception centrale : `ctr-net-fardc_active_front_web/api/api_receiver.php`
- regroupement côté central : les cartes et statistiques sont consolidées par libellé d’équipe/source afin d’éviter la séparation entre `equipes` et `controles` lorsqu’un identifiant technique de PC varie

Les anciennes références liées aux litiges ne font plus partie du flux actif de synchronisation.

## Documentation liée

- `SETUP.md`
- `ARCHITECTURE.md`
- `FAQ.md`
- `FONCTIONNEMENT_APPLICATION.md`
- `USER_GUIDE.md`
- `ADMIN_GUIDE.md`
- `OPERATEUR_GUIDE.md`
- `CONTROLEUR_GUIDE.md`
- `TROUBLESHOOTING.md`
- `SYNCHRONISATION.md`
- `PRESENTATION_CTR_NET_FARDC.md`
- `PROMPT_PRESENTATION.md`

## Applications mobiles associées

- `ctr-net-mobile/` → base historique de contrôle terrain `CTR.NET`
- `ctr-net-mobile-control-enrollement/` → APK terrain active **`CTR Opérations`** pour `CONTROLEUR` et `ENROLEUR`
- build APK automatique via GitHub Actions sur le dépôt mobile unifié
- documentation contrôle + enrôlement : `ctr-net-mobile-control-enrollement/README.md`
- guides terrain : `ctr-net-mobile-control-enrollement/CONTROLEUR_GUIDE.md` et `ctr-net-mobile-control-enrollement/ENROLEUR_GUIDE.md`

