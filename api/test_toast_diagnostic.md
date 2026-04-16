# Test manuel de l'API toast.php

## 1. Tester l'envoi manuel d'un toast

Utilisez curl ou Postman pour envoyer une requête POST :

```
curl -X POST http://localhost/ctr.net-fardc/api/toast.php \
  -H "Content-Type: application/json" \
  -d '{"message": "Test toast manuel", "type": "success"}'
```

- Après cette commande, le fichier `toast_message.json` doit apparaître dans le dossier `api/`.

## 2. Vérifier la présence du fichier toast_message.json

- Regardez dans `ctr.net-fardc/api/` si le fichier `toast_message.json` existe juste après le POST.
- Son contenu doit ressembler à :

```json
{"message":"Test toast manuel","type":"success","time":1680000000}
```

## 3. Vérifier la récupération et suppression du toast

- Rechargez la page web (liste.php). Le toast doit s'afficher.
- Après affichage, le fichier `toast_message.json` doit être supprimé automatiquement.

## 4. Vérifier le flux mobile

- Effectuez un contrôle mobile qui devrait déclencher un toast.
- Vérifiez si le fichier `toast_message.json` est créé.
- Si non, le problème vient de l'envoi mobile.

## 5. Vérifier la console navigateur

- Ouvrez la console (F12) sur la page web.
- Cherchez des erreurs JS ou des requêtes réseau échouées vers `/api/toast.php`.

## 6. Vérifier les logs serveur Apache/PHP

- Regardez les logs d'erreur pour tout message lié à toast.php ou aux permissions fichiers.

---

Si le test manuel fonctionne mais pas le flux mobile, le problème est côté mobile. Si rien ne marche, il peut s'agir d'un souci de droits d'écriture sur le serveur ou d'un bug JS.
