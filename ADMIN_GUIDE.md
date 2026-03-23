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
