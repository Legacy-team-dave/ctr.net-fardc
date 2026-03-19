# FAQ - Questions Fréquemment Posées
## Application de Gestion des Effectifs FARDC

---

## Table des Matières

1. [Questions Générales](#questions-générales)
2. [Connexion et Comptes](#connexion-et-comptes)
3. [Gestion des Militaires](#gestion-des-militaires)
4. [Gestion des Contrôles](#gestion-des-contrôles)
5. [Sécurité et Mot de Passe](#sécurité-et-mot-de-passe)
6. [Données et Export](#données-et-export)
7. [Techniques et Erreurs](#techniques-et-erreurs)

---

## Questions Générales

### Q1: Qu'est-ce que cette application?
**R:** C'est un système centralisé de **gestion des effectifs militaires FARDC**. Elle vous permet d'enregistrer les militaires, documenter les contrôles effectués et générer des rapports.

### Q2: Quels navigateurs sont supportés?
**R:** Les navigateurs modernes :
- ✅ Google Chrome (v80+)
- ✅ Mozilla Firefox (v75+)
- ✅ Microsoft Edge (v80+)
- ✅ Safari (v13+)

**Évitez :** Internet Explorer (trop ancien)

⚠️ Activez JavaScript (requis pour le fonctionnement)

### Q3: Qui peut accéder à l'application?
**R:** Seules les personnes avec un **compte actif** créé par l'administrateur. Dans cette version il y a 2 profils :
- **ADMIN_IG** : Accès complet
- **OPERATEUR** : Gestion des militaires et contrôles

> Remarque : le profil **CONTROLEUR** a été retiré ; ses fonctions sont prises en charge par **OPERATEUR**.

### Q4: Comment savoir quel profil j'ai?
**R:** Après connexion, regardez en **haut à droite**. Votre nom et profil sont affichés. Ou allez à **"Mon Profil"**.

### Q5: Où trouver la documentation complète?
**R:** Selon votre profil :
- **Tous** : [USER_GUIDE.md](USER_GUIDE.md)
- **ADMIN_IG** : [ADMIN_GUIDE.md](ADMIN_GUIDE.md)
- **OPERATEUR** : [OPERATEUR_GUIDE.md](OPERATEUR_GUIDE.md)

Le document [CONTROLEUR_GUIDE.md](CONTROLEUR_GUIDE.md) est conservé à titre informatif mais le profil **CONTROLEUR** n'est plus utilisé.

---

## Connexion et Comptes

### Q6: Je n'ai pas reçu mes identifiants de connexion
**R:** Vérifiez d'abord votre **dossier "Spam"** et **"Courrier indésirable"**.

Si toujours rien :
1. Contactez votre **administrateur**
2. Demandez-lui de vérifier que votre **email est correct**
3. Demandez un **renvoi d'email**
4. Vérifiez que vous *acceptez aussi les emails* de l'application

### Q7: Comment me connecter pour la première fois?
**R:** 
1. Allez à `http://localhost/ctr.net-fardc/login.php`
2. Entrez votre **login** fourni par email
3. Entrez votre **mot de passe temporaire** (depuis l'email)
4. Cliquez sur **"Se connecter"**
5. Vous devez **changer votre mot de passe** immédiatement

### Q8: Puis-je utiliser l'appli depuis mon téléphone?
**R:** Techniquement oui, mais :
- ✅ L'appli est **responsive** (s'adapte aux petits écrans)
- ⚠️ Quelques **fonctionnalités limitées** sur mobile
- ⚠️ Le **GPS** fonctionne mieux sur téléphone
- 👍 Utilisez **Chrome ou Firefox** sur le téléphone

**Conseil :** Mieux sur **ordinateur/tablette** pour une meilleure expérience

### Q9: Ma session s'expire. Pourquoi?
**R:** Pour **raisons de sécurité**, les sessions expirent après **30 minutes d'inactivité**.

El que faire :
- Simplement **vous reconnecter**
- Vos données non enregistrées seront **perdues**
- **Astuce** : Enregistrez avant de faire une pause!

### Q10: Puis-je avoir deux comptes?
**R:** Non, **un login = un compte unique**. Si vous avez besoin de plus de droits, contactez votre admin pour **changer votre profil**.

---

## Gestion des Militaires

### Q11: Combien de militaires peut contenir le système?
**R:** Techniquement **illimité** (milliers). Mais les performances dégradent après ~50 000 militaires sans optimisation.

La FARDC dispose généralement de :
- 📊 1 000 - 10 000 militaires max par unité
- Le système est **dans les normes** pour cette charge

### Q12: Comment ajouter un militaire rapidement?
**R:** 3 façons :

**1. Manuel unitaire** (1 à la fois) :
   - Menu → Accueil ou → Militaires → "➕ Ajouter"
   - Remplissez et enregistrez
   - ⏱️ ~2 minutes par militaire

**2. Par import Excel/CSV** (200+ d'un coup) :
   - Préparez fichier Excel avec colonnes : NOM, PRENOM, GRADE, MATRICULE, UNITE
   - Menu → Militaires → "📥 Importer"
   - Chargez fichier
   - Vérifiez et confirmez
   - ⏱️ ~5 minutes pour 200 militaires

**3. Par API/script** (si développeur) :
   - Contactez votre admin technique

### Q13: Je dois changer le matricule d'un militaire
**R:** **Impossible**. Le matricule est **immuable** pour des raisons d'audit.

**Solutions :**
- Si erreur grave : Supprimez et recréez (Attention : perds les contrôles)
- Si juste changement de personnel : Contactez votre admin

### Q14: Puis-je supprimer un militaire?
**R:** Oui **MAIS** :
- ⚠️ Cela supprime aussi **tous ses contrôles**
- 👍 Mieux : Le passer en **"Inactif"** (conserve données)
- 🔒 Seul OPERATEUR et ADMIN_IG peuvent supprimer

**Processus** :
1. Liste militaires → Trouvez le militaire
2. Cliquez "❌ Supprimer"
3. Confirmez (tapez le nom pour confirmer)

### Q15: Un militaire a changé d'unité
**R:** Modifiez simplement :
1. Liste militaires → Cliquez "✏️ Modifier"
2. Changez l'**unité**
3. Cliquez "💾 Enregistrer"
4. Les anciens contrôles restent liés à ce militaire

### Q16: Qu'est-ce que le statut "Inactif"?
**R:** Un militaire inactif :
- 🔒 **N'apparaît pas** dans les listes par défaut
- 📦 **Ses données restent** (archivé)
- ✅ Peut être **réactivé** plus tard
- 💾 **Simule une retraite/départ** sans suppression

C'est le **meilleur choix** pour les départs!

---

## Gestion des Contrôles

### Q17: À quelle fréquence dois-je contrôler chaque militaire?
**R:** Selon les procédures FARDC :
- **Minimum officiel** : 1 contrôle par **trimestre** (90 jours)
- **Recommandé** : 1 par **mois** pour opérations régulières
- **En opération** : Plus fréquent (selon ordre)

L'application peut vous **alerter** si un militaire n'a pas eu de contrôle depuis X jours.

### Q18: Je peux effectuer un contrôle rétroactif (date passée)?
**R:** **Oui mais...**
- ✅ Techniquement possible (entrez date antérieure)
- ⚠️ À utiliser **exceptionnel** seulement
- 📝 **Notez bien** dans les observations : **"[Contrôle rétroactif du JJ/MM]"**
- ✅ Informez votre hiérarchie

### Q19: Un contrôle a une erreur. Comment corriger?
**R:** Selon le cas :

**Erreur mineure (avant de fermer l'appli)** :
- Avant d'enregistrer : Cliquez "🔄 Réinitialiser" et recommencez

**Erreur après enregistrement** :
- Allez à "Contrôles" → "Liste" → Trouvez le contrôle
- Cliquez "✏️ Modifier"
- Corrigez (généralement observations)
- Cliquez "💾 Enregistrer"
- Un log enregistre qui a modifié quoi/quand

### Q20: Puis-je supprimer un contrôle?
**R:** 
- **OPERATEUR** : Peut-être (selon configuration)
- **ADMIN_IG** : Oui, toujours

**Processus** (si autorisé) :
1. Contrôles → Liste → Trouvez le contrôle
2. Cliquez "❌ Supprimer"
3. Confirmez
4. Un log d'audit enregistre la suppression

⚠️ **Attention** : C'est qui demande la suppression est noté

### Q21: Que signifient les statuts "En cours" vs "Terminé"?
**R:** 

| Statut | Signification | Quand l'utiliser |
|--------|---------------|-----------------|
| **Terminé** | Contrôle complètement fait | Observations compètes, prêt à valider |
| **En cours** | Contrôle commencé mais incomplet | À être fin plus tard |
| **Suspendu** | Reporté/arrêté temporairement | Militaire absent, à revoir demain |

**Utilité** : Pour le suivi. Un **"En cours"** depuis 3 mois = **alerte** ⚠️

### Q22: Puis-je mettre une photo dans les observations?
**R:** **Actuellement non**. Mais vous pouvez :
- Décrire précisément dans les _observations_
- Stocker les photos séparément avec le numéro de contrôle ou matricule

**Souhaits futurs** : L'application pourrait intégrer des pièces jointes

### Q23: Le GPS du contrôle, c'est obligatoire?
**R:** **Non, c'est optionnel**. Utile pour :
- ✅ Opérations au terrain
- ✅ Contrôles mobiles
- ❌ Logique pour contrôles en infirmerie ou bureau

Laissez vide si non pertinent.

---

## Sécurité et Mot de Passe

### Q24: Quel est un bon mot de passe?
**R:** Un mot de passe **fort** doit avoir :

| Critère | Exemple BON | Exemple MAUVAIS |
|---------|-------------|-----------------|
| **Longueur** | 12+ caractères | "abc" |
| **Majuscules** | A-Z | abc123 |
| **Minuscules** | a-z | ABC123! |
| **Chiffres** | 0-9 | NoNumber! |
| **Symboles** | !@#$%^&* | No$ymbol |
| **Pas nom/login** | RandomStr!89 | jdupont2024 |
| **Pas commun** | C$cL@Ck987! | password123 |

**Exemples:**
- ❌ `password123` - Trop commun
- ❌ `jdupon2024` - Contient votre nom
- ✅ `Ctrl@2024FARDC!` - Bon
- ✅ `F4RdC#SecurePass99` - Excellent

### Q25: J'ai perdu mon mot de passe. Comment le récupérer?
**R:** Sur la page de **connexion** :

1. Cliquez sur **"Mot de passe oublié ?"**
2. Entrez votre **email**
3. Vérifiez votre email (Spam aussi!)
4. Cliquez sur le **lien de réinitialisation**
5. Créez un **nouveau mot de passe fort**
6. Reconnectez-vous

⏱️ Le lien expire après **24 heures** pour raison de sécurité

### Q26: Dois-je changer mon mot de passe régulièrement?
**R:** 
- **Obligatoire** : Tous les **90 jours** (3 mois)
- **Systématiquement** : Si vous soupçonnez une fuite
- **Recommandé** : À chaque changement de traitement/groupe

**Comment** :
- Menu → Mon Profil → "🔐 Changer le mot de passe"

### Q27: Mon compte a été "hacké". Que faire?
**R:** Immédiatement :

1. **Déconnectez-vous** de partout
2. **Changez votre mot de passe** (utilisez une autre machine)
3. **Contactez votre admin**
4. Admin peut **réinitialiser** votre compte
5. Attendez que admin vérifie les **logs **d'audit**
6. See ce qui a été accédé/modifié illégalement

---

## Données et Export

### Q28: Puis-je télécharger une copie de toutes mes données?
**R:** Selon votre profil :

**Pour vous-même** :
- Allez à "Mon Profil"
- Cliquez "📥 Télécharger mes données"
- Format : JSON/CSV

**Pour votre unité** :
- Demandez à votre **OPERATEUR** ou **ADMIN_IG**
- Ils peuvent exporter via "📥 Export"

### Q29: Quels formats d'export sont supportés?
**R:** 

| Format | Usage | Ouvert avec |
|--------|-------|-----------|
| **Excel** (.xlsx) | Analyse, édition | Excel, LibreOffice |
| **CSV** | Import/Export entre systèmes | Excel, Notepad |
| **PDF** | Impression, rapport officiel | Lecteur PDF |
| **JSON** | Intégration API méveloppement | Notepad, Code |

Généralement : **Excel** pour 99% des usage!

### Q30: Mes données sont-elles sauvegardées?
**R:** **Oui, automatiquement** :
- ✅ **Chaque nuit** à 02:00 (sauvegarde serveur)
- ✅ À chaque **clic sur enregistrer** (BD)
- ✅ **30 jours** d'historique conservés

Vous pouvez aussi :
- Manuel : Cliquez "📥 Exporter" n'importe quand
- Demandez à admin une sauvegarde manuel

### Q31: Combien de temps les données sont conservées?
**R:** 
- **Données actives** : Indéfiniment (taant qu'actives)
- **Données archivées** : Minimum **5 ans** (légalement)
- **Logs d'audit** : **2 ans** minimum
- **Contrôles** : Jamais supprimés (sauf suppression manuelle)

---

## Techniques et Erreurs

### Q32: Je vois une erreur "Base de données indisponible"
**R:** Le serveur est **hors ligne**. Essayez :

1. **Rafraîchir** (F5) dans 5 minutes
2. **Vérifier connexion** internet
3. **Contacter IT** si persiste

C'est généralement une **panne serveur temporaire**.

### Q33: "Erreur 404 - Page non trouvée"
**R:** Le lien/URL est **cassé ou incorrect**. 

**Solutions** :
1. Vérifier l'URL dans la barre adresse
2. Aller au "🏠 Accueil" et naviguer depuis le menu
3. Vérifier que vous avez les **droits d'accès**
4. Nettoyer le cache (Ctrl+Maj+Supp)

### Q34: J'ai un message "Vous n'avez pas le droit d'accéder"
**R:** Vous n'avez **pas la permission** pour cette page :

**Raisons :**
- ❌ Votre profil a des droits limités
- ❌ Votre profil a changé récemment
- ❌ Un admin a révoqué les droits

**Solutions** :
1. Vérifiez votre profil (Menu → Mon Profil)
2. Contactez admin pour **demander les droits**
3. Demandez admin à vérifier votre **statut du compte**

### Q35: L'appli est très lente
**R:** Plusieurs causes possibles :

**Côté vous :**
- 🔌 **Connexion internet lente** → V testez internet.speedtest.net
- 💻 **Ordinateur surchargé** → Fermez d'autres applications
- 🌐 **Trop d'onglets ouverts** → Fermez les inutiles
- 🗑️ **Cache plein** → Nettoyez (Ctrl+Maj+Supp)

**Côté serveur :**
- 📊 **Base de données surchargée** → Contacter IT/admin
- 🔧 **Serveur en maintenance** → Attendez
- 📥 **Import massif en cours** → Attendez

### Q36: Certaines données ne s'enregistrent pas
**R:** Vérifiez :
1. **Connexion internet** stable
2. **Les champs obligatoires** (avec *)
3. **Format des données** (dates, etc.)
4. **Pas de caractères spéciaux** bizarres
5. Relisez les **messages d'erreur**

Si persistant → Contactez support

### Q37: J'ai perdu ma session / déconnexion surprise
**R:** Raisons les plus courantes :

- **Inactivité 30min+** → Reconnectez-vous
- **Autre connexion** du même compte → Allez sur un autre appareil
- **Serveur redémarré** → Pas vos données enregistrées
- **Admin a fermé** votre session → Contactez

**Conseil** : Enregistrez souvent!

### Q38: Est-ce que mes contrôles offline (sans internet) seront synchro?
**R:** **Non**. L'application **nécessite une connexion permanente**.

**Solutions** :
- Utilisez l'appli **uniquement en bureau** (fiable internet)
- Ou utilisez une **connexion mobile 4G** (si disponible)
- **Envisagez une version offline** future (à demander)

---

## Support et Contact

**Avez-vous d'autres questions?**

📧 Email : support@fardc.org  
☎️ Tél : +243 XX XXX XXXX  
🕐 Heures : Lun-Ven 8h-17h

**Ressources :**
- [Guide Complet](USER_GUIDE.md)
- [Admin Guide](ADMIN_GUIDE.md)
- [Dépannage Avancé](TROUBLESHOOTING.md)
- [Wiki Interne](https://wiki.fardc.org)

---

**Dernière mise à jour:** Janvier 2024  
**Version:** 1.0

