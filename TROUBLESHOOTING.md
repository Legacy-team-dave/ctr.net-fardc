# Troubleshooting - CTR.NET-FARDC

## 1) Impossible d'ouvrir l'application

- Vérifier que Laragon est démarré
- Vérifier l'URL : `http://localhost/ctr.net-fardc/login.php`

## 2) Erreur de connexion base de données

- Vérifier `config/database.php`
- Vérifier que MySQL est actif

## 3) Accès refusé à une page

- Vérifier le profil dans `utilisateurs`
- Vérifier la règle `check_profil(...)` dans la page

## 4) Le CONTROLEUR ne voit pas le dashboard

Comportement normal : le profil `CONTROLEUR` est limité à la saisie de contrôles.

## 5) Mentions inattendues

Mentions valides pour la saisie actuelle :

- `Présent`
- `Favorable`
- `Défavorable`

Si une doc ou un écran affiche d'autres mentions, la référence est obsolète.
