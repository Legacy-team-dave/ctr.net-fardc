#!/usr/bin/env powershell
<#
.SYNOPSIS
    Rotation de Clé de Chiffrement - Script Automatisé
    
.DESCRIPTION
    Effectue une rotation de clé de chiffrement complète:
    1. Sauvegarde la clé actuelle
    2. Génère une nouvelle clé
    3. Déchiffre tous les fichiers avec l'ancienne clé
    4. Re-chiffre tout avec la nouvelle clé
    
.PARAMETER SourcePath
    Chemin vers le répertoire racine du projet
    
.PARAMETER Backup
    Créer une sauvegarde des fichiers avant rotation
    
.EXAMPLE
    .\rotate_encryption_key.ps1
    .\rotate_encryption_key.ps1 -SourcePath C:\laragon\www\ctr.net-fardc -Backup
#>

param(
    [string]$SourcePath = (Get-Location).Path,
    [switch]$Backup = $false
)

$colors = @{
    Success = "Green"
    Error   = "Red"
    Warning = "Yellow"
    Info    = "Cyan"
}

function Write-Status {
    param(
        [string]$Message,
        [string]$Type = "Info"
    )
    Write-Host $Message -ForegroundColor $colors[$Type]
}

function Execute-PhpCommand {
    param(
        [string]$Command,
        [string]$SourcePath
    )
    
    $phpBinary = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpBinary) {
        Write-Status "ERREUR: PHP non trouvé" -Type "Error"
        exit 1
    }
    
    Push-Location $SourcePath
    try {
        & php bin\encrypt.php $Command
        $success = $LASTEXITCODE -eq 0
        Pop-Location
        return $success
    } catch {
        Pop-Location
        Write-Status "Erreur lors de l'exécution: $_" -Type "Error"
        return $false
    }
}

Write-Status "===== Rotation de Clé de Chiffrement =====" -Type "Info"
Write-Status "" -Type "Info"

# Étape 1: Vérifier .env
Write-Status "[1/5] Vérification de la clé actuelle..." -Type "Info"
$envPath = "$SourcePath\.env"

if (-not (Test-Path $envPath)) {
    Write-Status "ERREUR: Fichier .env introuvable" -Type "Error"
    Write-Status "Exécutez d'abord: php bin/encrypt.php init" -Type "Warning"
    exit 1
}

$currentKey = Select-String -Path $envPath -Pattern "^ENCRYPTION_KEY=(.+)$" -AllMatches
if ($currentKey.Matches.Count -eq 0) {
    Write-Status "ERREUR: Clé de chiffrement non trouvée dans .env" -Type "Error"
    exit 1
}

$oldKey = $currentKey.Matches[0].Groups[1].Value
Write-Status "✓ Clé actuelle détectée: $($oldKey.Substring(0,16))..." -Type "Success"

# Étape 2: Sauvegarder la clé actuelle
Write-Status "[2/5] Sauvegarde de la clé actuelle..." -Type "Info"
$backupDir = "$SourcePath\.encryption_backups"
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
}

$timestamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
$backupFile = "$backupDir\encryption_key_$timestamp.bak"
$oldKey | Set-Content -Path $backupFile
Write-Status "✓ Clé sauvegardée: $backupFile" -Type "Success"

# Étape 3: Générer la nouvelle clé
Write-Status "[3/5] Génération de la nouvelle clé..." -Type "Info"
if (-not (Execute-PhpCommand "init" $SourcePath)) {
    Write-Status "ERREUR: Impossible de générer la nouvelle clé" -Type "Error"
    exit 1
}
Write-Status "✓ Nouvelle clé générée et sauvegardée" -Type "Success"

# Étape 4: Restaurer les clés autour de la transition
Write-Status "[4/5] Déchiffrement avec l'ancienne clé..." -Type "Info"
$env:ENCRYPTION_KEY = $oldKey

# Obtenir la liste des fichiers chiffrés
Push-Location $SourcePath
$encryptedFiles = Get-ChildItem -Recurse -Include "*.encrypted" -File
Pop-Location

if ($encryptedFiles.Count -eq 0) {
    Write-Status "⊘ Aucun fichier chiffré trouvé" -Type "Warning"
} else {
    Write-Status "  Fichiers à re-chiffrer: $($encryptedFiles.Count)" -Type "Info"
    
    foreach ($file in $encryptedFiles) {
        $relPath = $file.FullName.Replace($SourcePath + "\", "")
        if (-not (Execute-PhpCommand "decrypt $relPath" $SourcePath)) {
            Write-Status "  ✗ Erreur déchiffrement: $relPath" -Type "Error"
        } else {
            Write-Status "  ✓ Déchiffré: $relPath" -Type "Success"
        }
    }
}

# Étape 5: Re-chiffrer avec la nouvelle clé
Write-Status "[5/5] Re-chiffrement avec la nouvelle clé..." -Type "Info"

# Charger la nouvelle clé depuis .env
$newKeyMatch = Select-String -Path $envPath -Pattern "^ENCRYPTION_KEY=(.+)$" -AllMatches
$newKey = $newKeyMatch.Matches[0].Groups[1].Value
$env:ENCRYPTION_KEY = $newKey

if (-not (Execute-PhpCommand "encrypt" $SourcePath)) {
    Write-Status "ERREUR: Erreur lors du re-chiffrement" -Type "Error"
    Write-Status "Restaurer manuellement:  cp .encryption_backups\encryption_key_$timestamp.bak .env" -Type "Warning"
    exit 1
}

Write-Status "✓ Tous les fichiers re-chiffrés avec la nouvelle clé" -Type "Success"

# Résumé final
Write-Status "" -Type "Info"
Write-Status "===== Rotation de Clé Complètée =====" -Type "Info"
Write-Status "" -Type "Info"
Write-Status "Résumé:" -Type "Info"
Write-Status "  Ancienne clé: $($oldKey.Substring(0,16))..." -Type "Warning"
Write-Status "  Nouvelle clé: $($newKey.Substring(0,16))..." -Type "Success"
Write-Status "  Sauvegarde: $backupFile" -Type "Info"
Write-Status "" -Type "Info"
Write-Status "Prochaines étapes:" -Type "Info"
Write-Status "  1. Vérifier l'application en dev" -Type "Info"
Write-Status "  2. Tester le déchiffrement automatique" -Type "Info"
Write-Status "  3. Mettre à jour la clé en production" -Type "Info"
Write-Status "  4. A partir d'ici, l'ancienne cle n'est plus necessaire" -Type "Info"
Write-Status "" -Type "Info"
