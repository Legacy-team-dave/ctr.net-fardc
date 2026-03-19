# Guide de Migration - CTR.NET-FARDC

## 📋 Vue d'ensemble

Ce document guide la migration progressive du projet vers la nouvelle architecture basée sur les services.

## 🔄 Approche de Migration

### Phase 1 : Préparation (✅ Complétée)
- [x] Créer la structure de répertoires
- [x] Créer les classes métier
- [x] Créer les services
- [x] Créer les utilitaires
- [x] Créer le fichier bootstrap
- [x] Créer le fichier de compatibilité

### Phase 2 : Migration Progressive (🚀 À commencer)
- [ ] Mettre à jour login.php
- [ ] Mettre à jour index.php (dashboard)
- [ ] Mettre à jour les modules (militaires, contrôles)
- [ ] Mettre à jour les pages d'administration
- [ ] Mettre à jour les fichiers AJAX

### Phase 3 : Optimisation (⏳ À faire)
- [ ] Ajouter les contrôleurs
- [ ] Ajouter les routes avancées
- [ ] Ajouter les tests unitaires
- [ ] Documenter l'API

## 📝 Étapes de Migration par Fichier

### 1. login.php

**Avant :**
```php
<?php
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE (login = ? OR nom_complet = ? OR email = ?) AND actif = true");
    $stmt->execute([$login, $login, $login]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id_utilisateur'];
        // ... autres assignations
    }
}
?>
```

**Après :**
```php
<?php
require_once 'config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $userService = new UserService();
    $user = $userService->verifyCredentials($login, $password);

    if ($user) {
        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_login'] = $user->getLogin();
        $_SESSION['user_nom'] = $user->getNom();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['user_profil'] = $user->getProfil();
        $_SESSION['user_avatar'] = $user->getAvatar();
        
        // Logger la connexion
        $logger = new Logger();
        $logger->logLogin($user->getId());
    }
}
?>
```

### 2. Modules/Militaires/liste.php

**Avant :**
```php
<?php
require_once '../../includes/functions.php';
require_login();

$militaires = $pdo->query("SELECT * FROM militaires ORDER BY nom ASC")->fetchAll();
?>
```

**Après :**
```php
<?php
require_once '../../config/bootstrap.php';
require_login();

$militaireService = new MilitaireService();
$militaires = $militaireService->getAll();

// Convertir en tableaux pour la vue
$militairesData = array_map(function($m) {
    return $m->toArray();
}, $militaires);
?>
```

### 3. Modules/Militaires/ajouter.php

**Avant :**
```php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        INSERT INTO militaires 
        (numero_matricule, nom, prenom, grade, categorie) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$_POST['matricule'], $_POST['nom'], $_POST['prenom'], $_POST['grade'], $_POST['categorie']])) {
        log_action('CREATE', 'militaires', $pdo->lastInsertId());
    }
}
?>
```

**Après :**
```php
<?php
require_once '../../config/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator($_POST);
    $validator->required(['numero_matricule', 'nom', 'prenom', 'grade', 'categorie'])
              ->min('numero_matricule', 3)
              ->max('nom', 100);

    if (!$validator->hasErrors()) {
        $militaire = new Militaire();
        $militaire->setMatricule($_POST['numero_matricule'])
                  ->setNom($_POST['nom'])
                  ->setPrenom($_POST['prenom'])
                  ->setGrade($_POST['grade'])
                  ->setCategorie($_POST['categorie']);
        
        $militaireService = new MilitaireService();
        if ($militaireService->create($militaire)) {
            $logger = new Logger();
            $logger->logCreate('militaires', $militaire->getId());
            
            redirect_with_flash('liste.php', 'success', 'Militaire ajouté avec succès');
        }
    } else {
        $errors = $validator->getErrors();
    }
}
?>
```

### 4. Fichiers AJAX

**Avant :**
```php
<?php
// ajax/get_categories.php
require_once '../includes/functions.php';

$militaire_id = $_GET['id'] ?? null;
$stmt = $pdo->prepare("SELECT categorie FROM militaires WHERE id_militaire = ?");
$stmt->execute([$militaire_id]);
$militaire = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode($militaire);
?>
```

**Après :**
```php
<?php
// api/militaires.php
require_once __DIR__ . '/../config/bootstrap.php';

$action = $_GET['action'] ?? null;
$militaireService = new MilitaireService();

header('Content-Type: application/json');

if ($action === 'get' && isset($_GET['id'])) {
    $militaire = $militaireService->getById($_GET['id']);
    echo json_encode($militaire ? $militaire->toArray() : ['error' => 'Non trouvé']);
} elseif ($action === 'search' && isset($_GET['q'])) {
    $results = $militaireService->search($_GET['q']);
    echo json_encode(array_map(function($m) { return $m->toArray(); }, $results));
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Action non valide']);
}
?>
```

## 🔍 Checklist de Migration par Fichier

### Fichiers à migrer (priorité haute)

- [ ] **login.php** - Point d'entrée, utiliser UserService
- [ ] **index.php** - Dashboard, utiliser tous les services
- [ ] **modules/militaires/liste.php** - Utiliser MilitaireService
- [ ] **modules/militaires/ajouter.php** - Créer militaire
- [ ] **modules/militaires/modifier.php** - Modifier militaire
- [ ] **modules/controles/ajouter.php** - Créer contrôle
- [ ] **modules/controles/liste.php** - Lister contrôles


### Fichiers à migrer (priorité moyenne)

- [ ] **modules/administration/utilisateurs.php** - Gérer utilisateurs
- [ ] **modules/administration/ajouter_utilisateur.php** - Créer utilisateur
- [ ] **modules/administration/modifier_utilisateur.php** - Modifier utilisateur
- [ ] **modules/rapports/** - Utiliser services pour les rapports
- [ ] **ajax/*.php** - Créer des endpoints API

### Fichiers à conserver/adapter

- [ ] **includes/header.php** - Garder pour la vue HTML
- [ ] **includes/footer.php** - Garder pour la vue HTML
- [ ] **includes/functions.php** - Garder les fonctions utilitaires globales
- [ ] **includes/auth.php** - Adapter pour utiliser les services

## 🎯 Bonnes Pratiques Pendant la Migration

### 1. Tests après chaque migration
```bash
# Vérifier que la page fonctionne
- Accéder à la page dans le navigateur
- Tester les formulaires
- Vérifier les erreurs dans la console du navigateur
```

### 2. Garder la compatibilité
```php
// Si une fonction existe déjà, l'utiliser via le wrapper
function get_user_by_id($id) {
    return get_user_by_id_new($id); // Wrapper vers nouveau service
}
```

### 3. Gestion des erreurs
```php
try {
    $service = new MilitaireService();
    $militaire = $service->getById($id);
    if (!$militaire) {
        throw new Exception("Militaire non trouvé");
    }
} catch (Exception $e) {
    redirect_with_flash('liste.php', 'danger', $e->getMessage());
}
```

### 4. Validation des données
```php
// TOUJOURS valider les données en entrée
$validator = new Validator($_POST);
$validator->required(['champ1', 'champ2'])
          ->validateEmail('email')
          ->min('password', 8);

if ($validator->hasErrors()) {
    // Afficher les erreurs
}
```

### 5. Logging des actions
```php
// Logger TOUTES les modifications
$logger = new Logger();
$logger->logCreate('table', $id, json_encode($data));
$logger->logUpdate('table', $id, json_encode(['avant' => $avant, 'apres' => $apres]));
$logger->logDelete('table', $id);
```

## 📊 État de la Migration

| Fichier | Status | Notes |
|---------|--------|-------|
| login.php | ⏳ À faire | Haute priorité |
| index.php | ⏳ À faire | Haute priorité |
| logout.php | ⏳ À faire | Moyenne priorité |
| modules/militaires/* | ⏳ À faire | Haute priorité |
| modules/controles/* | ⏳ À faire | Haute priorité |
| modules/documents/* | ⏳ À faire | Moyenne priorité |
| modules/administration/* | ⏳ À faire | Moyenne priorité |
| modules/rapports/* | ⏳ À faire | Basse priorité |
| ajax/*.php | ⏳ À faire | À remplacer par api/* |

## 📚 Ressources

- Architecture complète : [ARCHITECTURE.md](./ARCHITECTURE.md)
- Classes métier : `app/classes/`
- Services : `app/services/`
- Utilitaires : `app/utils/`
- Bootstrap : `config/bootstrap.php`

## ⚠️ Points d'Attention

1. **Sessions** : Les noms des clés de session restent les mêmes pour la compatibilité
2. **Erreurs PDO** : Les exceptions doivent être gérées proprement
3. **Dates** : Utiliser le format UTC internement, formater à l'affichage
4. **Sécurité** : TOUJOURS valider et nettoyer les données utilisateur
5. **Logs** : Logger TOUTES les modifications pour l'audit

---

**À commencer par :** login.php et index.php (les pages critiques)
