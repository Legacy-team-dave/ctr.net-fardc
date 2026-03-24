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

## 4) Le CONTROLEUR ne peut pas se connecter au web

Comportement normal : le profil `CONTROLEUR` est désormais réservé à l'application mobile CTR.NET Mobile. La connexion web est bloquée.

## 5) Mentions inattendues

Mentions valides pour la saisie actuelle :

- `Présent`
- `Favorable`
- `Défavorable`

Si une doc ou un écran affiche d'autres mentions, la référence est obsolète.

## 6) 🔐 Problèmes de Chiffrement (v1.1.0+)

### 6.1) Erreur : "Clé de chiffrement non trouvée"

**Cause** : `.env` manquant ou `ENCRYPTION_KEY` non défini.

**Solution** :

```bash
php bin/encrypt.php init
```

Cela génère une nouvelle clé dans `.env`.

### 6.2) Erreur : "Impossible de déchiffrer le fichier"

**Cause** : Clé changée après chiffrement, ou fichier corrompu.

**Solutions** :

1. Vérifier que `.env` contient la bonne `ENCRYPTION_KEY`
2. Déchiffrer manuellement le fichier affecté :

   ```bash
   php bin/encrypt.php decrypt config/database.php
   ```

3. Vérifier l'intégrité du fichier `.encrypted`

### 6.3) Fichiers partiellement chiffrés

**Cause** : L'encryption a échoué à mi-chemin (interruption réseau, etc.).

**Solution** :

```bash
# Vérifier l'état
php bin/encrypt.php status

# Re-chiffrer tous les fichiers
php bin/encrypt.php encrypt
```

### 6.4) Performance dégradée

**Cause** : < 5ms/startup attendu. Problème sinon.

**Solutions** :

- Vérifier que Laragon a assez de RAM
- Vérifier les logs PHP pour les erreurs de déchiffrement
- Consulter `logs/` pour les traces d'erreur

### 6.5) Clé oubliée / perdue

**Cas critique** : Sans clé, impossible de déchiffrer.

**Options** :

1. **Récupérer depuis backup** : si vous aviez sauvegardé `.env`
2. **Rotation de clé** : Ne marche QUE si l'ancienne clé est disponible
3. **Reconstruction** :
   - Restaurer les fichiers `.encrypted` depuis Git
   - Restaurer `.env` depuis backup
   - Redémarrer l'app

> ⚠️ **CRITIQUE** : Sauvegarder `.env` régulièrement!

### 6.6) Comment verifier le chiffrement est actif?

```bash
php bin/encrypt.php status
```

Résultat attendu :

```text
[✓ Encrypté]   config/database.php
[✓ Encrypté]   includes/auth.php
...
```

### 6.7) Rotation de clé - Étapes

Si vous suspectez une compromission :

```powershell
./rotate_encryption_key.ps1
```

Cela :

1. Sauvegarde ancienne clé → `.encryption_backups/`
2. Génère nouvelle clé → `.env`
3. Déchiffre tous les fichiers avec ancienne
4. Re-chiffre avec nouvelle

---

Pour plus de détails : [ENCRYPTION.md](ENCRYPTION.md)
