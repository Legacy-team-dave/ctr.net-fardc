# Exemples Pratiques - CTR.NET-FARDC

## 📚 Table des Matières
1. [Gestion des Utilisateurs](#gestion-des-utilisateurs)
2. [Gestion des Militaires](#gestion-des-militaires)
3. [Gestion des Contrôles](#gestion-des-contrôles)
4. [Validation de Données](#validation-de-données)
5. [Logging et Audit](#logging-et-audit)
6. [Gestion des Erreurs](#gestion-des-erreurs)

---

## Gestion des Utilisateurs

### Exemple 1 : Créer un nouvel utilisateur

```php
<?php
require_once 'config/bootstrap.php';

// Valider les données
$validator = new Validator($_POST);
$validator->required(['login', 'email', 'password', 'nom_complet', 'profil'])
          ->min('password', 8)
          ->validateEmail('email');

if ($validator->hasErrors()) {
    $errors = $validator->getErrors();
    // Afficher les erreurs au formulaire
    foreach ($errors as $field => $message) {
        echo "Erreur pour $field: $message\n";
    }
} else {
    // Créer l'utilisateur
    $user = new User();
    $user->setLogin($validator->get('login'))
         ->setNom($validator->get('nom_complet'))
         ->setEmail($validator->get('email'))
         ->setProfil($validator->get('profil'))
         ->setActif(true);
    
    $userService = new UserService();
    if ($userService->create($user, $validator->get('password'))) {
        $logger = new Logger();
        $logger->logCreate('utilisateurs', $user->getId(), 'Nouvel utilisateur créé');
        
        redirect_with_flash('utilisateurs.php', 'success', 'Utilisateur créé avec succès');
    } else {
        redirect_with_flash('ajouter_utilisateur.php', 'danger', 'Erreur lors de la création');
    }
}
?>
```

### Exemple 2 : Modifier un utilisateur

```php
<?php
require_once 'config/bootstrap.php';
require_login();
check_profil('ADMIN_IG');

$userId = $_GET['id'] ?? null;
if (!$userId) {
    redirect_with_flash('utilisateurs.php', 'danger', 'ID utilisateur manquant');
}

$userService = new UserService();
$user = $userService->getById($userId);

if (!$user) {
    redirect_with_flash('utilisateurs.php', 'danger', 'Utilisateur non trouvé');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider
    $validator = new Validator($_POST);
    $validator->required(['email', 'nom_complet', 'profil'])
              ->validateEmail('email');
    
    if (!$validator->hasErrors()) {
        // Mettre à jour
        $user->setNom($validator->get('nom_complet'))
             ->setEmail($validator->get('email'))
             ->setProfil($validator->get('profil'));
        
        if ($userService->update($user)) {
            $logger = new Logger();
            $logger->logUpdate('utilisateurs', $user->getId(), 
                json_encode(['email' => $user->getEmail(), 'profil' => $user->getProfil()])
            );
            
            redirect_with_flash('utilisateurs.php', 'success', 'Utilisateur mis à jour');
        }
    } else {
        $errors = $validator->getErrors();
    }
}

// Afficher le formulaire...
?>
```

### Exemple 3 : Récupérer les utilisateurs par profil

```php
<?php
require_once 'config/bootstrap.php';

$userService = new UserService();

// Obtenir tous les administrateurs
$admins = $userService->getByProfil('ADMIN_IG');
foreach ($admins as $admin) {
    echo "Admin: " . $admin->getNom() . " (" . $admin->getEmail() . ")\n";
}

// Obtenir tous les opérateurs
$operateurs = $userService->getByProfil('OPERATEUR');
echo "Total d'opérateurs: " . count($operateurs) . "\n";
?>
```

### Exemple 4 : Vérifier les credentials (Connexion)

```php
<?php
require_once 'config/bootstrap.php';

$login = $_POST['login'] ?? '';
$password = $_POST['password'] ?? '';

$userService = new UserService();
$user = $userService->verifyCredentials($login, $password);

if ($user) {
    // Succès
    $_SESSION['user_id'] = $user->getId();
    $_SESSION['user_nom'] = $user->getNom();
    $_SESSION['user_profil'] = $user->getProfil();
    
    // Logger
    $logger = new Logger();
    $logger->logLogin($user->getId());
    
    // Rediriger
    header('Location: index.php');
} else {
    // Erreur
    $_SESSION['flash'] = [
        'type' => 'danger',
        'message' => 'Identifiants invalides'
    ];
}
?>
```

---

## Gestion des Militaires

### Exemple 5 : Lister tous les militaires actifs

```php
<?php
require_once 'config/bootstrap.php';
require_login();

$militaireService = new MilitaireService();
$militaires = $militaireService->getActifs();
?>

<!-- Vue -->
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Matricule</th>
                <th>Nom Complet</th>
                <th>Grade</th>
                <th>Catégorie</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($militaires as $militaire): ?>
            <tr>
                <td><?= e($militaire->getMatricule()) ?></td>
                <td><?= e($militaire->getNomComplet()) ?></td>
                <td><?= e($militaire->getGrade()) ?></td>
                <td><?= e($militaire->getCategorie()) ?></td>
                <td>
                    <a href="voir.php?id=<?= $militaire->getId() ?>" class="btn btn-sm btn-info">Voir</a>
                    <a href="modifier.php?id=<?= $militaire->getId() ?>" class="btn btn-sm btn-warning">Modifier</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

### Exemple 6 : Rechercher des militaires

```php
<?php
require_once 'config/bootstrap.php';
require_login();

$search = $_GET['q'] ?? '';
$militaires = [];

if (!empty($search)) {
    $militaireService = new MilitaireService();
    $militaires = $militaireService->search($search);
}

// Retourner en JSON pour AJAX
header('Content-Type: application/json');
echo json_encode(array_map(function($m) {
    return [
        'id' => $m->getId(),
        'matricule' => $m->getMatricule(),
        'nom_complet' => $m->getNomComplet(),
        'grade' => $m->getGrade()
    ];
}, $militaires));
?>
```

### Exemple 7 : Ajouter un militaire avec fichier

```php
<?php
require_once 'config/bootstrap.php';
require_login();
check_profil(['ADMIN_IG', 'OPERATEUR']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valider
    $validator = new Validator($_POST);
    $validator->required(['numero_matricule', 'nom', 'prenom', 'grade', 'categorie'])
              ->min('numero_matricule', 3)
              ->max('nom', 100)
              ->max('prenom', 100)
              ->in('categorie', ['ACTIF', 'INTEGRES', 'RETRAITES', 'DCD_AV_BIO', 'DCD_AP_BIO']);
    
    if (!$validator->hasErrors()) {
        $militaire = new Militaire();
        $militaire->setMatricule($validator->get('numero_matricule'))
                  ->setNom($validator->get('nom'))
                  ->setPrenom($validator->get('prenom'))
                  ->setGrade($validator->get('grade'))
                  ->setCategorie($validator->get('categorie'))
                  ->setUnité($validator->get('unite'))
                  ->setDateNaissance($validator->get('date_naissance'));
        
        $militaireService = new MilitaireService();
        if ($militaireService->create($militaire)) {
            $logger = new Logger();
            $logger->logCreate('militaires', $militaire->getId(), 
                'Militaire ' . $militaire->getNomComplet() . ' ajouté'
            );
            
            redirect_with_flash('liste.php', 'success', 'Militaire ajouté avec succès');
        }
    } else {
        $errors = $validator->getErrors();
    }
}

// Afficher le formulaire...
?>
```

### Exemple 8 : Obtenir les statistiques

```php
<?php
require_once 'config/bootstrap.php';
require_login();

$militaireService = new MilitaireService();
$stats = $militaireService->getStatistics();

$total = 0;
$data = [];

foreach ($stats as $stat) {
    $categorie = $stat['categorie'];
    $count = $stat['total'];
    $total += $count;
    
    $data[$categorie] = $count;
}
?>

<!-- Chart.js example -->
<canvas id="chart"></canvas>
<script>
    const ctx = document.getElementById('chart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: <?= json_encode(array_keys($data)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($data)) ?>,
                backgroundColor: ['#28a745', '#dc3545', '#0d6efd', '#6f42c1', '#6c757d']
            }]
        }
    });
</script>
```

---

## Gestion des Contrôles

### Exemple 9 : Créer un contrôle avec coordonnées GPS

```php
<?php
require_once 'config/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator($_POST);
    $validator->required(['id_militaire', 'localisation', 'type_controle', 'resultat'])
              ->numeric('id_militaire')
              ->numeric('latitude')
              ->numeric('longitude')
              ->in('type_controle', ['routine', 'alerte', 'vérification'])
              ->in('resultat', ['conforme', 'non_conforme', 'à_vérifier']);
    
    if (!$validator->hasErrors()) {
        $controle = new Controle();
        $controle->setIdMilitaire($validator->get('id_militaire'))
                 ->setIdOperateur($_SESSION['user_id'])
                 ->setDateControle(date('Y-m-d H:i:s'))
                 ->setLocalisation($validator->get('localisation'))
                 ->setCoordonnees($validator->get('latitude'), $validator->get('longitude'))
                 ->setTypeControle($validator->get('type_controle'))
                 ->setResultat($validator->get('resultat'))
                 ->setObservations($validator->get('observations'));
        
        $controleService = new ControleService();
        if ($controleService->create($controle)) {
            $logger = new Logger();
            $logger->logCreate('controles', $controle->getId());
            
            redirect_with_flash('liste.php', 'success', 'Contrôle enregistré');
        }
    }
}

// Afficher le formulaire avec carte Leaflet...
?>
```

### Exemple 10 : Récupérer les contrôles d'une période

```php
<?php
require_once 'config/bootstrap.php';
require_login();

$dateDebut = $_GET['debut'] ?? date('Y-m-01');
$dateFin = $_GET['fin'] ?? date('Y-m-d');

$controleService = new ControleService();
$controles = $controleService->getByDateRange($dateDebut, $dateFin);

// Grouper par opérateur
$parOperateur = [];
foreach ($controles as $controle) {
    $idOp = $controle->getIdOperateur();
    if (!isset($parOperateur[$idOp])) {
        $parOperateur[$idOp] = [];
    }
    $parOperateur[$idOp][] = $controle;
}

echo "Contrôles du $dateDebut au $dateFin :\n";
foreach ($parOperateur as $idOp => $controlesOp) {
    echo "  Opérateur $idOp: " . count($controlesOp) . " contrôles\n";
}
?>
```


## Validation de Données

### Exemple 13 : Validation complexe

```php
<?php
require_once 'config/bootstrap.php';

$data = [
    'email' => 'user@example.com',
    'password' => 'MyPass123',
    'password_confirm' => 'MyPass123',
    'date_naissance' => '1990-01-15',
    'url_website' => 'https://example.com'
];

$validator = new Validator($data);

// Validation en chaîne
$validator->required(['email', 'password', 'date_naissance'])
          ->validateEmail('email')
          ->min('password', 8)
          ->match('password', 'password_confirm', 'Les mots de passe ne correspondent pas')
          ->date('date_naissance', 'Y-m-d')
          ->url('url_website')
          ->regex('password', '/[A-Z]/', 'Le mot de passe doit contenir une majuscule');

if ($validator->hasErrors()) {
    foreach ($validator->getErrors() as $field => $message) {
        echo "❌ $field: $message\n";
    }
} else {
    echo "✅ Toutes les validations sont passées\n";
    
    // Utiliser les données
    $email = $validator->get('email');
    $password = $validator->get('password');
}
?>
```

---

## Logging et Audit

### Exemple 14 : Logger toutes les opérations

```php
<?php
require_once 'config/bootstrap.php';

$logger = new Logger();

// Créer un militaire
$militaire = new Militaire();
// ... remplir les données
$militaireService = new MilitaireService();
$success = $militaireService->create($militaire);

if ($success) {
    $logger->logCreate('militaires', $militaire->getId(), 
        json_encode([
            'matricule' => $militaire->getMatricule(),
            'nom' => $militaire->getNomComplet(),
            'grade' => $militaire->getGrade()
        ])
    );
}

// Modifier
$logger->logUpdate('militaires', $militaire->getId(), 
    json_encode(['avant' => $ancien, 'apres' => $nouveau])
);

// Supprimer
$logger->logDelete('militaires', $militaire->getId());

// Obtenir les logs
$userLogs = $logger->getLogsByUser($_SESSION['user_id']);
foreach ($userLogs as $log) {
    echo "Action: " . $log['action'] . " | Table: " . $log['table_concernee'] . "\n";
}

// Nettoyer les vieux logs (>90 jours)
$logger->purgeLogs(90);
?>
```

### Exemple 15 : Afficher l'historique d'un enregistrement

```php
<?php
require_once 'config/bootstrap.php';
require_login();

$table = 'militaires';
$idRecord = $_GET['id'] ?? null;

$logger = new Logger();
$logs = $logger->getLogsByTable($table);

// Filtrer pour cet enregistrement
$relevantLogs = array_filter($logs, function($log) use ($idRecord) {
    return $log['id_enregistrement'] == $idRecord;
});
?>

<div class="timeline">
    <?php foreach (array_reverse($relevantLogs) as $log): ?>
    <div class="timeline-item">
        <time><?= format_date($log['date_action']) ?></time>
        <h4><?= e($log['action']) ?></h4>
        <p><?= e($log['details'] ?? '') ?></p>
        <small class="text-muted">Par: <?= e($log['user_name'] ?? 'Utilisateur supprimé') ?></small>
    </div>
    <?php endforeach; ?>
</div>
```

---

## Gestion des Erreurs

### Exemple 16 : Try-catch avec Logger

```php
<?php
require_once 'config/bootstrap.php';

try {
    // Opération qui pourrait échouer
    $userService = new UserService();
    $user = $userService->getById($userId);
    
    if (!$user) {
        throw new Exception("Utilisateur non trouvé avec l'ID: $userId");
    }
    
    // Traiter l'utilisateur
    $user->setEmail('new@example.com');
    if (!$userService->update($user)) {
        throw new Exception("Erreur lors de la mise à jour");
    }
    
} catch (PDOException $e) {
    // Erreur base de données
    error_log("Erreur DB: " . $e->getMessage());
    redirect_with_flash('index.php', 'danger', 'Erreur de base de données');
    
} catch (Exception $e) {
    // Autre erreur
    error_log("Erreur: " . $e->getMessage());
    redirect_with_flash('index.php', 'danger', 'Une erreur est survenue: ' . $e->getMessage());
}
?>
```

---

## 🎓 Conclusion

Ces exemples couvrent les cas d'usage les plus courants. Pour plus d'informations :
- Voir [ARCHITECTURE.md](./ARCHITECTURE.md) pour la structure complète
- Voir [MIGRATION.md](./MIGRATION.md) pour les guidelines de migration
