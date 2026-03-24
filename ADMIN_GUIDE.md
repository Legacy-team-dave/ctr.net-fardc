# Guide Administrateur - CTR.NET-FARDC

## Périmètre ADMIN_IG

- Gérer les utilisateurs
- Gérer les militaires
- Superviser les contrôles
- Accéder aux rapports et écrans d'administration

## Contrôle d'accès

Le projet utilise les fonctions de contrôle d'accès dans :

- `includes/functions.php`
- `includes/auth.php`

Exemple : `check_profil(['ADMIN_IG'])`.

## Profils à administrer

- `ADMIN_IG`
- `OPERATEUR`
- `CONTROLEUR`

## Mentions de contrôle à connaître

- `Présent`
- `Favorable`
- `Défavorable`

## Points d'attention

- Vérifier les droits lors de la création/modification d'utilisateurs
- Contrôler la cohérence des données militaires
- Vérifier les journaux (`logs`) en cas d'incident
- La table `equipes` est gérée par l'opérateur (standalone, aucune relation)

## 🔐 Gestion du Chiffrement (v1.1.0+)

### Initialisation Unique

Après installation, initialiser le chiffrement une seule fois :

```powershell
php bin/encrypt.php init
```

Cela génère une clé secrète sauvegardée dans `.env`.

### Vérifier l'État du Chiffrement

```bash
php bin/encrypt.php status
```

Affiche quels fichiers sont chiffrés et lesquels ne le sont pas.

### Chiffrer les Fichiers Sensibles

```bash
php bin/encrypt.php encrypt
```

Cela gère :
- `config/database.php` (identifiants BD)
- `includes/auth.php` (logique d'authentification)
- `includes/functions.php` (logique applicative)
- Et 5 autres fichiers critiques

### Rotation de Clé (Annuellement)

Si vous suspectez une compromission de clé :

```powershell
./rotate_encryption_key.ps1
```

Cela :
1. Sauvegarde l'ancienne clé
2. Génère une nouvelle clé
3. Déchiffre tous les fichiers avec l'ancienne
4. Re-chiffre avec la nouvelle

### Sauvegarde de Clé

**CRITIQUE** : Sauvegarder régulièrement `.env` en lieu sûr (ne pas commiter dans Git).

```powershell
# Exemple: Copier manuellement
Copy-Item .env "C:\Backups\encryption_key_$(Get-Date -Format yyyyMMdd).env"
```

### Documentation Complète

Consulter :
- [ENCRYPTION.md](ENCRYPTION.md) — API & API avancée
- [ENCRYPTION_QUICKSTART.md](ENCRYPTION_QUICKSTART.md) — Démarrage rapide
- [ENCRYPTION_COMMANDS.md](ENCRYPTION_COMMANDS.md) — Toutes les commandes
