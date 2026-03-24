# Résumé - CTR.NET-FARDC

## État réel du projet

Application PHP opérationnelle basée sur des modules métier et des fonctions globales dans `includes/`.

**Version 1.1.0** : Incluent chiffrement AES-256-CBC des fichiers sensibles.

## Points clés

- 3 profils actifs : `ADMIN_IG`, `OPERATEUR`, `CONTROLEUR`
- Saisie de contrôles dans `modules/controles/ajouter.php`
- Contrôle d'accès via `check_profil(...)`
- Endpoints AJAX dans `ajax/`
- 🔐 **Chiffrement** : Fichiers sensibles protégés par AES-256-CBC (transparent)

## Mentions réellement implémentées

- `Présent`
- `Favorable`
- `Défavorable`

## Statuts de contexte

- `Vivant`
- `Décédé`
