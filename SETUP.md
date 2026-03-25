# Setup - CTR.NET-FARDC

## Prérequis

- Windows
- Laragon installé (`C:\laragon\`)
- PHP + MySQL actifs dans Laragon

## Installation rapide

1. Copier le projet dans `C:\laragon\www\ctr.net-fardc`
2. Lancer Laragon
3. Ouvrir : `http://localhost/ctr.net-fardc/login.php`

## Configuration du Chiffrement (v1.1.0+)

### Initialisation

Le chiffrement AES-256-CBC est **automatisé**. Effectuez ceci **une seule fois** après installation :

**Option 1: GUI (Recommandé pour débutants)**
1. Double-cliquer `encrypt_init.bat` (à la racine du projet)
2. Suivre les prompts
3. Vérifier que `.env` contient `ENCRYPTION_KEY=...`

**Option 2: CLI (Recommandé pour IT/DevOps)**
```bash
php bin/encrypt.php init
```

**Option 3: PowerShell**
```powershell
./encrypt_sources.ps1 init
```

### Vérification

```bash
php bin/encrypt.php status
```

Tous les fichiers connaissables doivent montrer `[✓ Encrypté]`.

### Activation sur Production

Chiffrer tous les fichiers sensibles :

**Option 1: GUI**
- Double-cliquer `encrypt_all.bat`

**Option 2: CLI**
```bash
php bin/encrypt.php encrypt
```

### Documentation Complète

Consulter [ENCRYPTION.md](ENCRYPTION.md) pour :
- Toutes les commandes disponibles
- Configuration avancée
- Rotation de clés
- Dépannage

---

## Lancement via scripts

- `START.bat`
- `INSTALLER.bat`
- `launch.bat`
- `launch.ps1`
- `lanceur.ps1`

`INSTALLER.bat` crée directement le raccourci bureau `.lnk` pointant en priorité vers `launch.ps1` (fallback `launch.bat`, icône personnalisée si disponible). Ce raccourci permet de démarrer Laragon, d'attendre Apache/MySQL, puis d'ouvrir l'application dans le navigateur.

## Profils applicatifs

- `ADMIN_IG`
- `OPERATEUR`
- `CONTROLEUR` (mobile uniquement — non accessible côté web)

## Vérification minimale après setup

- Connexion utilisateur possible
- Accès à `index.php` pour `ADMIN_IG` / `OPERATEUR`
- Connexion `CONTROLEUR` côté web bloquée (profil réservé à l'app mobile)
- Enregistrement d'un contrôle fonctionnel

## Mentions à valider en test

Dans `modules/controles/ajouter.php` :

- `Présent`
- `Favorable`
- `Défavorable`

## Dépannage rapide

- Si page inaccessible : vérifier Apache/MySQL dans Laragon
- Si erreur BD : vérifier `config/database.php`
- Si problème de droits : vérifier le profil dans la table `utilisateurs`

## Nettoyage des caches (v1.5.0+)

Le nettoyage des caches s’exécute automatiquement toutes les 8 heures (intégré au job de sauvegarde planifié). Pour un nettoyage manuel :

```powershell
# PowerShell
./run_cache_cleanup.ps1 -JoursLogs 90

# Batch
run_cache_cleanup.bat 90
```

## Sauvegarde consolidée et purge automatique

- Installer/mettre à jour la tâche planifiée (8h) :
	- PowerShell : `./setup_backup_task.ps1 -MaxKeep 30`
	- CMD : `setup_backup_task.bat 30`
- Exécuter immédiatement un job de sauvegarde + purge :
	- `./run_backup_job.ps1 -MaxKeep 30`
- Purge manuelle uniquement :
	- PowerShell : `./run_backup_purge.ps1 -MaxKeep 30`
	- CMD : `run_backup_purge.bat 30`

Comportement de sauvegarde : mise à jour de l'archive consolidée `backups/backup_consolide_latest.zip` avec les données complètes (`controles`, `litiges`, `non_vus`).

Comportement de purge : suppression des archives ZIP identiques, puis conservation des 30 dernières archives non identiques (valeur ajustable via `MaxKeep`).

### Envoi e-mail des sauvegardes (optionnel)

Le script `run_backup_job.ps1` peut envoyer automatiquement l'archive consolidée par e-mail après chaque exécution.

Configurer `config/backup_mail.json` :

- `from` : adresse expéditeur
- `to` : adresse destinataire (ou plusieurs adresses séparées par `;`)
- `smtpHost`, `smtpPort`, `smtpUser`, `smtpPassword`, `useSsl`
- `enabled: true` pour activer l'envoi
