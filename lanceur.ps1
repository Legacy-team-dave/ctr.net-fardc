# ================================================================================
# Lanceur de l'application CTR.NET-FARDC avec arrêt automatique à la fermeture
# ================================================================================

$laragonExe    = "C:\laragon\laragon.exe"
$laragonWww    = "C:\laragon\www"
$appRoot       = $PSScriptRoot                     # répertoire du script
$appName       = "ctr.net-fardc"
$appUrl        = "http://127.0.0.1/ctr.net-fardc/login.php"

# Vérifier que Laragon est installé
if (-not (Test-Path $laragonExe)) {
    Write-Host ""
    Write-Host "================================================================================"
    Write-Host "ERREUR: Laragon n'est pas installé à l'emplacement attendu."
    Write-Host "================================================================================"
    Write-Host ""
    Write-Host "Veuillez installer Laragon depuis: https://laragon.org/"
    Write-Host ""
    pause
    exit 1
}

Write-Host ""
Write-Host "================================================================================"
Write-Host "Lancement de CTR.NET-FARDC avec Laragon..."
Write-Host "================================================================================"
Write-Host ""

# Créer le lien symbolique vers le projet dans le www de Laragon si nécessaire
Set-Location $laragonWww
if (-not (Test-Path $appName)) {
    Write-Host "Création du lien symbolique vers $appRoot ..."
    # Pour créer un lien symbolique, il faut les droits administrateur
    try {
        New-Item -ItemType SymbolicLink -Path $appName -Target $appRoot -ErrorAction Stop
    } catch {
        Write-Host "Erreur : impossible de créer le lien symbolique. Vérifiez que vous exécutez en tant qu'administrateur."
        pause
        exit 1
    }
}

# Démarrer Laragon (fenêtre minimisée)
Write-Host "Démarrage de Laragon..."
Start-Process -FilePath $laragonExe -WindowStyle Minimized

# Attendre que les services soient prêts
Start-Sleep -Seconds 3

# Ouvrir le navigateur sur l'application
Write-Host "Ouverture du navigateur..."
Start-Process $appUrl

Write-Host ""
Write-Host "================================================================================"
Write-Host "Serveur démarré. Fermez cette fenêtre pour arrêter Laragon et ses services."
Write-Host "================================================================================"
Write-Host ""

# Boucle infinie pour garder le script actif et détecter la fermeture
try {
    while ($true) {
        Start-Sleep -Seconds 1
    }
}
finally {
    # Ce bloc s'exécute même si la console est fermée (Ctrl+C, fermeture de la fenêtre...)
    Write-Host "Arrêt de Laragon et des services associés..."
    
    # Tuer les processus principaux de Laragon
    Stop-Process -Name "laragon" -Force -ErrorAction SilentlyContinue
    Stop-Process -Name "httpd"   -Force -ErrorAction SilentlyContinue   # Apache
    Stop-Process -Name "mysqld"  -Force -ErrorAction SilentlyContinue   # MySQL
    Stop-Process -Name "nginx"   -Force -ErrorAction SilentlyContinue   # Nginx (si utilisé)
    
    Write-Host "Arrêt terminé."
}