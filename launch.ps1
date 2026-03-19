# ================================================================================
# PowerShell Launch Script - CTR.NET-FARDC
# ================================================================================
# Script pour démarrer l'application CTR.NET-FARDC
# Utilisation: .\launch.ps1

param(
    [switch]$NoWait = $false
)

$AppRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppName = "CTR.NET-FARDC"
$AppUrl = "http://localhost:8080/ctr.net-fardc"
$LarafonUrl = "C:\laragon\laragon.exe"

Write-Host ""
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host "Lancement de $AppName" -ForegroundColor Cyan
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host ""

# Vérifier si Laragon est installé
if (-not (Test-Path $LarafonUrl)) {
    Write-Host "ERREUR : Laragon n'est pas trouvé à $LarafonUrl" -ForegroundColor Red
    Write-Host ""
    Write-Host "Veuillez installer Laragon depuis: https://laragon.org/" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Appuyez sur Entrée pour quitter"
    exit 1
}

Write-Host "✓ Laragon détecté" -ForegroundColor Green

# Vérifier si le dossier de l'app existe
if (-not (Test-Path $AppRoot -PathType Container)) {
    Write-Host "ERREUR : Dossier de l'application non trouvé: $AppRoot" -ForegroundColor Red
    exit 1
}

Write-Host "✓ Dossier de l'application trouvé: $AppRoot" -ForegroundColor Green

# Lancer Laragon
Write-Host ""
Write-Host "Démarrage de Laragon..." -ForegroundColor Yellow
& $LarafonUrl

# Attendre que Laragon soit lancé
Write-Host "Attente de 3 secondes..." -ForegroundColor Yellow
Start-Sleep -Seconds 3

# Ouvrir le navigateur
Write-Host "Ouverture du navigateur..." -ForegroundColor Yellow
Start-Process $AppUrl

Write-Host ""
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host "Application lancée !" -ForegroundColor Green
Write-Host "URL: $AppUrl" -ForegroundColor Cyan
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host ""

if (-not $NoWait) {
    Write-Host "Appuyez sur Entrée pour fermer cette fenêtre..." -ForegroundColor Gray
    Read-Host
}
