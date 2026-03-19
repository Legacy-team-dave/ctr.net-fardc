# CTR.NET-FARDC - Architecture Restructurée

## 📁 Structure du Projet

```
ctr.net-fardc/
├── app/                              # Logique métier
│   ├── classes/                      # Classes métier (Entités)
│   │   ├── User.php                  # Classe Utilisateur
│   │   ├── Militaire.php             # Classe Militaire
│   │   ├── Controle.php              # Classe Contrôle
│   │
│   ├── services/                     # Services (Logique métier)
│   │   ├── UserService.php           # Logique utilisateurs
│   │   ├── MilitaireService.php      # Logique militaires
│   │   ├── ControleService.php       # Logique contrôles
│   │
│   ├── controllers/                  # Contrôleurs (non encore implémentés)
│   │   └── (à créer selon les besoins)
│   │
│   └── utils/                        # Utilitaires
│       ├── Database.php              # Connexion PDO (Singleton)
│       ├── Logger.php                # Gestion des logs
│       └── Validator.php             # Validation des données
│
├── api/                              # Endpoints AJAX/API
│   ├── militaires.php
│   ├── categories.php
│   └── ...
│
├── config/                           # Configuration
│   ├── database.php                  # Connexion base de données
│   ├── constants.php                 # Constantes de l'application
│   └── bootstrap.php                 # Fichier d'initialisation
│
├── includes/                         # Fichiers inclus
│   ├── header.php                    # En-tête HTML
│   ├── footer.php                    # Pied de page
│   ├── functions.php                 # Fonctions utilitaires globales
│   ├── auth.php                      # Gestion authentification
│   └── Router.php                    # Gestionnaire de routes
│
├── modules/                          # Pages métier
│   ├── militaires/
│   │   ├── liste.php
│   │   ├── actifs.php
│   │   ├── inactifs.php
│   │   ├── ajouter.php
│   │   ├── modifier.php
│   │   └── voir.php
│   ├── controles/
│   │   ├── ajouter.php
│   │   ├── liste.php
│   │   └── voir.php

│   ├── administration/
│   │   ├── utilisateurs.php
│   │   ├── ajouter_utilisateur.php
│   │   ├── modifier_utilisateur.php
│   │   └── supprimer_utilisateur.php
│   └── rapports/
│       ├── index.php
│       └── statistiques.php
│
├── ajax/                             # Fichiers AJAX (legacy)
│   ├── get_categories.php
│   └── ...
│
├── assets/                           # Ressources statiques
│   ├── css/
│   ├── js/
│   ├── img/
│   └── ...
│
├── uploads/                          # Fichiers uploadés
│   ├── avatars/
│   └── ...
│
├── index.php                         # Tableau de bord
├── login.php                         # Page de connexion
├── logout.php                        # Déconnexion
└── .htaccess                         # Réécriture d'URLs
```

## 🚀 Utilisation des Services

### Classe Database (Singleton)

```php
// Obtenir l'instance (elle sera créée une fois seulement)
$db = Database::getInstance();
$pdo = $db->getConnection();
```

### Service Utilisateurs

```php
require_once 'config/bootstrap.php';

$userService = new UserService();

// Récupérer un utilisateur par ID
$user = $userService->getById(1);

// Récupérer tous les utilisateurs actifs
$users = $userService->getAllActive();

// Vérifier les identifiants
$user = $userService->verifyCredentials('login', 'password');

// Créer un utilisateur
$newUser = new User();
$newUser->setLogin('john_doe')
        ->setNom('Doe')
        ->setEmail('john@example.com')
        ->setProfil('OPERATEUR');

$userService->create($newUser, 'password123');

// Mettre à jour les préférences
$userService->savePreferences($user->getId(), ['theme' => 'dark']);
```

### Service Militaires

```php
$militaireService = new MilitaireService();

// Récupérer tous les militaires
$militaires = $militaireService->getAll();

// Récupérer les militaires actifs
$actifs = $militaireService->getActifs();

// Rechercher des militaires
$results = $militaireService->search('Dupont');

// Créer un militaire
$militaire = new Militaire();
$militaire->setMatricule('MAT001')
          ->setNom('Dupont')
          ->setPrenom('Jean')
          ->setGrade('Colonel')
          ->setCategorie('ACTIF');

$militaireService->create($militaire);

// Obtenir statistiques
$stats = $militaireService->getStatistics();
```

### Service Contrôles

```php
$controleService = new ControleService();

// Créer un contrôle
$controle = new Controle();
$controle->setIdMilitaire(1)
         ->setIdOperateur(5)
         ->setDateControle(date('Y-m-d H:i:s'))
         ->setLocalisation('Kinshasa')
         ->setCoordonnees(-4.3276, 15.3136)
         ->setTypeControle('routine')
         ->setResultat('conforme')
         ->setObservations('Aucune observation');

$controleService->create($controle);

// Récupérer les contrôles d'un militaire
$controles = $controleService->getByMilitaire($id);
```

### Service Validation

```php
$validator = new Validator($_POST);

$validator->required(['email', 'password'])
          ->validateEmail('email')
          ->min('password', 8)
          ->max('nom', 100)
          ->date('date_naissance');

if ($validator->hasErrors()) {
    $errors = $validator->getErrors();
    // Afficher les erreurs
} else {
    // Traiter les données valides
}
```

### Service Logger

```php
$logger = new Logger();

// Enregistrer une action
$logger->log('CREATE', 'militaires', $id, 'Ajout d\'un militaire');

// Méthodes pratiques
$logger->logCreate('militaires', $id);
$logger->logUpdate('militaires', $id);
$logger->logDelete('militaires', $id);
$logger->logLogin($userId);

// Récupérer les logs
$logs = $logger->getLogs(100);
$userLogs = $logger->getLogsByUser($userId);
```

## 🔐 Authentification

```php
// Toujours inclure le bootstrap
require_once 'config/bootstrap.php';

// Vérifier si l'utilisateur est connecté
require_login();

// Obtenir les données de l'utilisateur connecté
$userId = $_SESSION['user_id'];
$userProfil = $_SESSION['user_profil'];

// Vérifier un profil
if ($userProfil === PROFIL_ADMIN) {
    // Accès administrateur
}
```

## 📝 Convention de Codage

### Nommage des Classes

- Les noms de classes doivent être en PascalCase
- Un fichier = une classe
- Les noms doivent être au singulier et descriptifs

Exemple: `User`, `MilitaireService`, `Logger`

### Nommage des Méthodes

- Les getters commencent par `get`
- Les setters commencent par `set`
- Les vérifications commencent par `is` ou `has`
- Utiliser camelCase

Exemple: `getId()`, `getName()`, `setEmail()`, `isAdmin()`

### Nommage des Variables

- Utiliser camelCase
- Être descriptif
- Éviter les variables à une lettre (sauf dans les boucles)

Exemple: `$userId`, `$userName`, `$isAdmin`

### Commentaires

- Utiliser des blocs de documentation PHPDoc
- Documenter les paramètres et les valeurs de retour
- Expliquer le "pourquoi" pas seulement le "quoi"

```php
/**
 * Récupérer un utilisateur par ID
 * 
 * @param int $id L'ID de l'utilisateur
 * @return User|null L'utilisateur trouvé ou null
 */
public function getById($id) {
    // ...
}
```

## 🔄 Migration Graduelle

Pour migrer progressivement les fichiers existants :

1. **Étape 1** : Inclure le bootstrap dans chaque fichier PHP
   ```php
   require_once __DIR__ . '/../../config/bootstrap.php';
   ```

2. **Étape 2** : Remplacer les requêtes SQL par les services
   ```php
   // Ancien
   $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id_utilisateur = ?");
   $stmt->execute([$id]);
   $user = $stmt->fetch();
   
   // Nouveau
   $userService = new UserService();
   $user = $userService->getById($id);
   ```

3. **Étape 3** : Utiliser les classes métier
   ```php
   // Au lieu de travailler avec des tableaux
   $user = new User($data);
   $user->setNom('Nouveau Nom');
   ```

## 📊 Base de Données

La structure de la base de données reste inchangée. Les classes et services interagissent directement avec les tables existantes :

- `utilisateurs` → `User` + `UserService`
- `militaires` → `Militaire` + `MilitaireService`
- `controles` → `Controle` + `ControleService`
- `logs` → Gérée par `Logger`

## 🛠️ Configuration

Les constantes de l'application sont définies dans `config/constants.php` :

- `BASE_URL` : URL de base de l'application
- `PROFIL_ADMIN`, `PROFIL_OPERATEUR` : Profils utilisateurs
- `CATEGORIE_ACTIF`, etc. : Catégories militaires
- `ITEMS_PER_PAGE` : Pagination
- `MAX_UPLOAD_SIZE` : Taille maximale d'upload

## 📦 Dépendances

Aucune dépendance externe requise ! Le projet utilise :
- PHP 7.4+
- PDO (pour la base de données)
- Fonctions PHP natives

## 🎯 Prochaines Étapes

1. Migrer progressivement les fichiers
2. Créer les contrôleurs pour les pages métier
3. Implémenter un système de routes avancé
4. Ajouter des tests unitaires
5. Documenter l'API complète

---

**Dernière mise à jour :** 2 mars 2026
