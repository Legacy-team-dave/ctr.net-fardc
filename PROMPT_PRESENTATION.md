# Prompt de présentation - CTR.NET-FARDC

Copiez-collez ce prompt dans votre assistant IA pour générer une présentation claire et professionnelle de l'application.

---

## Prompt prêt à utiliser

Tu es un expert en dévéloppement de solutions métier et en systèmes d'information publics.
Je dois présenter l'application CTR.NET-FARDC devant [type de public: direction / opérateurs / équipe technique / partenaires].

Objectif de la présentation:
- Expliquer à quoi sert l'application
- Montrer comment elle fonctionne réellement
- Mettre en évidence les profils utilisateurs, les flux clés et les bénéfices
- Donner les points de vigilance et les recommandations

Contexte fonctionnel réel à respecter:
- Profils: ADMIN_IG, OPERATEUR, CONTROLEUR, ENROLEUR
- Mentions de contrôle: Présent, Favorable, Défavorable
- Flux d'accès: ADMIN_IG vers dashboard, OPERATEUR selon préférences, CONTROLEUR via `CTR.OPS Contrôle`.
- Modules principaux: Administration, Militaires, Contrôles, Synchronisation, Tableau de bord
- Sécurité: authentification, contrôle d'accès, journalisation des actions
- 🔐 Chiffrement: Fichiers sensibles protégés par AES-256-CBC (v1.1.0+) - transparence applicative totale
- Données: utilisateurs, militaires, contrôles, logs

Consignes de production:
1. Génère un plan de présentation en 10 à 14 slides maximum.
2. Pour chaque slide, fournis:
   - Titre
   - Message clé
   - 3 à 5 points à dire à l'oral
3. Ajoute une section "Démonstration en direct" avec un scénario pas à pas:
   - Connexion
   - Navigation selon profil
   - Saisie d'un contrôle
   - Consultation d'une liste (contrôles ou équipes synchronisées)
   - Exemple de log/audit
4. Ajoute une section "Questions/Réponses" avec 10 questions probables et réponses courtes.
5. Le ton doit être institutionnel, clair, sans jargon inutile.
6. Produis le résultat en français.
7. Termine par une conclusion orientée décision (prochaines actions recommandées).

Contraintes importantes:
- N'invente pas de fonctionnalités non présentes.
- Respecte strictement les profils et mentions réels.
- Sois concret et orienté usage terrain.

Format de sortie attendu:
- Section 1: "Résumé exécutif"
- Section 2: "Plan des slides"
- Section 3: "Script de démonstration"
- Section 4: "Q&R anticipées"
- Section 5: "Conclusion et recommandations"

---

## Variante courte (30 secondes)

Fais-moi un pitch de 30 secondes de CTR.NET-FARDC pour un décideur, en mettant l'accent sur:
- la fiabilité des contrôles,
- la traçabilité des actions,
- la simplicité d'usage par profil,
- et le gain opérationnel.

## Variante moyenne (2 minutes)

Fais-moi un pitch de 2 minutes de CTR.NET-FARDC pour une réunion de pilotage, avec:
- problème initial,
- solution apportée,
- fonctionnement global,
- impacts métier,
- prochaines étapes.
