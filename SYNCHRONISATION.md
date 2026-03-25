# Synchronisation locale → serveur central

## Principe

- L'application **centrale** est la même base code que l'application locale.
- La différence se fait via `APP_MODE` dans `.env` :
  - `APP_MODE=local` : instance terrain (avec bouton **Synchroniser**)
  - `APP_MODE=central` : serveur central (accès **ADMIN_IG uniquement**)
- Les données synchronisées portent sur les tables :
  - `militaires`
  - `controles`
  - `litiges`
  - `equipes`

## Déploiement du serveur central

1. Déployer une copie de l'application sur le serveur principal.
2. Configurer la base MySQL centrale avec la **même structure** que les instances locales.
3. Mettre dans `.env` du central :

```env
APP_MODE=central
SYNC_SHARED_TOKEN=un_token_fort_et_unique
SYNC_REQUIRE_HTTPS=true
```

4. Créer/mettre à jour les comptes applicatifs (authentification classique login/mot de passe).
5. Vérifier que seuls les utilisateurs `ADMIN_IG` peuvent se connecter en mode central.

### Initialiser les tables de synchronisation

Exécuter le script SQL sur la base centrale:

```sql
USE `ctr.net-fardc`;
SOURCE sql/sync_init.sql;
```

Script fourni: `sql/sync_init.sql`

### Rollback des tables de synchronisation

En cas de réinitialisation complète des métadonnées de synchronisation:

```sql
USE `ctr.net-fardc`;
SOURCE sql/sync_rollback.sql;
```

Script fourni: `sql/sync_rollback.sql`

## Configuration d'une instance locale

Dans `.env` de chaque site local :

```env
APP_MODE=local
SYNC_INSTANCE_ID=site-local-01
SYNC_CENTRAL_URL=https://votre-serveur-central/ctr.net-fardc
SYNC_SHARED_TOKEN=le_meme_token_que_le_central
SYNC_TIMEOUT=30
```

## Usage

1. Se connecter sur l'instance locale avec un profil `ADMIN_IG` ou `OPERATEUR`.
2. Cliquer sur **Synchroniser** (barre supérieure).
3. Confirmer l'envoi.
4. L'application affiche le résultat (succès/erreur).

## API

Endpoint unique : `api/synchronisation.php`

- **Envoi local vers central**
  - `POST /api/synchronisation.php?action=push`
  - Authentification : session web + CSRF
  - Rôle : `ADMIN_IG` ou `OPERATEUR`

- **Réception côté central**
  - `POST /api/synchronisation.php?action=receive`
  - Authentification : `Authorization: Bearer <SYNC_SHARED_TOKEN>`
  - Sécurité : HTTPS requis si `SYNC_REQUIRE_HTTPS=true`

## Logique de fusion

- Le central n'écrase pas aveuglément par ID local.
- Une table de mapping (`sync_record_map`) lie :
  - `source_instance`
  - `table_name`
  - `source_pk`
  - `target_pk`
- Si un enregistrement est déjà mappé : **mise à jour** de la ligne centrale.
- Sinon : **insertion** d'une nouvelle ligne centrale + création du mapping.
- Les batchs de réception sont journalisés dans `sync_batches`.

## Notes de sécurité

- Toujours utiliser HTTPS pour les échanges inter-sites.
- Ne pas laisser `SYNC_SHARED_TOKEN` par défaut.
- Changer périodiquement le token et le distribuer aux instances autorisées.
