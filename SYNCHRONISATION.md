# Synchronisation locale → serveur central

## Portée retenue

La synchronisation a été simplifiée pour ne transporter que :

- `equipes`
- `controles`

> Toute la logique liée aux `litiges` et à l’ancienne synchronisation par mapping a été retirée.

## Architecture

- `APP_MODE=local` : instance terrain qui envoie les données.
- `APP_MODE=central` : instance serveur qui reçoit les données.
- Page locale de lancement : `modules/controles/sync.php`
- Endpoint serveur de réception : `api/api_receiver.php`
- Test de connectivité : `api/test_sync_connection.php`

## Structure SQL attendue

### Table `equipes`
Elle doit désormais contenir les métadonnées de synchronisation suivantes :

- `id_source`
- `db_source`
- `sync_status`
- `sync_date`
- `sync_version`

### Table `controles`
Elle conserve les champs de suivi :

- `id_source`
- `db_source`
- `sync_status`
- `sync_date`
- `sync_version`

### Table `synchronisation`
Un journal simple enregistre les envois avec :

- `controle_ids`
- `equipe_ids`
- `nb_controles`
- `nb_equipes`
- `statut`
- `details`
- `cree_le`

## Configuration `.env`

### Côté local

```env
APP_MODE=local
SYNC_INSTANCE_ID=site-local-01
SYNC_CENTRAL_URL=
SYNC_SHARED_TOKEN=le_meme_token_que_le_central
SYNC_TIMEOUT=30
```

### Côté central

```env
APP_MODE=central
SYNC_SHARED_TOKEN=un_token_fort_et_unique
SYNC_REQUIRE_HTTPS=true
```

## Utilisation

1. Ouvrir `Contrôles > Synchronisation`.
2. Saisir l’IP ou l’URL de la machine centrale.
   - Avec **Laragon** en configuration standard, l’IP seule suffit (`10.x.x.x`) sans `:port`.
   - Ajouter `:port` uniquement si Apache a été déplacé hors de `80` / `443`.
3. Cliquer sur **Tester la connexion IP**.
4. Cliquer sur **Synchroniser maintenant**.

Le système envoie automatiquement :

- le roster complet de `equipes`
- les lignes de `controles` encore non synchronisées

## Dashboard central attendu

Après synchronisation, `ctr-net-fardc_active_front_web/index.php` doit afficher :

- une section de synthèse avec `Militaires (total)`, `Total Contrôlés`, `Non-vus` et `Équipes synchronisées`
- une deuxième section avec une carte par équipe/source qui a transmis des données, regroupée par **libellé d’équipe** si l’identifiant technique du PC varie
- la `Carte interactive de la RDC par province`

## Ancienne logique supprimée

Les éléments suivants ne font plus partie du flux actif :

- `api/synchronisation.php` (remplacé par `api/api_receiver.php`)
- `sync_batches`
- `sync_record_map`
- l’ancienne synchronisation élargie hors `equipes` / `controles`
- les anciennes pages de comparaison/archives/conflits

## Sécurité

- Utiliser le même `SYNC_SHARED_TOKEN` des deux côtés.
- Préférer HTTPS en production.
- Ne jamais laisser le token par défaut.

