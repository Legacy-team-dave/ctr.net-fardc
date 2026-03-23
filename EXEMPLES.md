# Exemples réels - CTR.NET-FARDC

## 1) Protéger une page par profil

```php
<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);
```

## 2) Page réservée admin

```php
<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG']);
```

## 3) Accès saisie contrôle pour les 3 profils

```php
<?php
require_once '../../includes/functions.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR', 'CONTROLEUR']);
```

## 4) Enregistrement d'une mention (schéma)

Mentions effectivement utilisées dans `modules/controles/ajouter.php` :

- `Présent`
- `Favorable`
- `Défavorable`

Exemple de paramètres GET utilisés par le flux :

```text
?action=valider&matricule=...&mention=Présent
?action=valider&matricule=...&mention=Favorable
?action=valider&matricule=...&mention=Défavorable
```

## 5) Vérification de session

```php
<?php
require_once 'includes/functions.php';
require_login();
```
