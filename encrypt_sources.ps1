#!/usr/bin/env powershell
<#
.SYNOPSIS
    Script de Chiffrement des Fichiers Sources PHP
    
.DESCRIPTION
    Automatise le chiffrement des fichiers PHP sensibles (includes, config, modules).
    Les fichiers originaux restent intacts, les versions chiffrées sont créées en .encrypted
    
.PARAMETER Action
    init     : Générer une nouvelle clé de chiffrement et sauvegarder en .env
    encrypt  : Chiffrer tous les fichiers listés
    decrypt  : Déchiffrer les fichiers (restaurer les originaux)
    status   : Afficher l'état du chiffrement
    
.PARAMETER SourcePath
    Chemin vers le répertoire racine du projet (défaut: répertoire courant)
    
.EXAMPLE
    .\encrypt_sources.ps1 -Action init
    .\encrypt_sources.ps1 -Action encrypt
    .\encrypt_sources.ps1 -Action status
#>

param(
    [ValidateSet("init", "encrypt", "decrypt", "status")]
    [string]$Action = "status",
    
    [string]$SourcePath = (Get-Location).Path
)

# Couleurs
$colors = @{
    Success = "Green"
    Error   = "Red"
    Warning = "Yellow"
    Info    = "Cyan"
}

function Write-Status {
    param([string]$Message, [string]$Type = "Info")
    Write-Host $Message -ForegroundColor $colors[$Type]
}

function Get-PhpBinaryPath {
    # Chercher php dans Laragon
    $possiblePaths = @(
        "C:\laragon\bin\php\php-7.4.34\php.exe",
        "C:\laragon\bin\php\php-8.0.30\php.exe",
        "C:\laragon\bin\php\php-8.1.30\php.exe",
        "C:\laragon\bin\php\php-8.2.0\php.exe",
        "C:\Program Files\PHP\php.exe",
        "$SourcePath\php.exe"
    )
    
    foreach ($path in $possiblePaths) {
        if (Test-Path $path) {
            return $path
        }
    }
    
    # Sinon, essayer d'utiliser php en PATH
    try {
        $php = Get-Command php -ErrorAction Stop
        return $php.Source
    } catch {
        return $null
    }
}

function Invoke-PhpAction {
    param(
        [string]$Action,
        [string]$SourcePath
    )
    
    $phpPath = Get-PhpBinaryPath
    if (-not $phpPath) {
        Write-Status "ERREUR: PHP non trouvé dans Laragon ou PATH" -Type "Error"
        exit 1
    }
    
    $scriptPath = "$SourcePath\bin\encrypt_runner.php"
    
    # Créer le script PHP intermédiaire
    $phpScript = @'
<?php
$sourcePath = $argv[1] ?? getcwd();
$action = $argv[2] ?? 'status';

require_once $sourcePath . '/config/encryption.php';

try {
    switch ($action) {
        case 'init':
            $newKey = generate_encryption_key();
            save_encryption_key_to_env($newKey);
            echo "✓ Nouvelle clé générée et sauvegardée en .env\n";
            echo "  Clé: " . substr($newKey, 0, 16) . "...\n";
            break;
            
        case 'encrypt':
            $files = glob($sourcePath . '/{includes,config,modules}/*.php', GLOB_BRACE | GLOB_NOSORT);
            $count = 0;
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') continue;
                if (strpos($file, '.encrypted') !== false) continue;
                
                try {
                    encrypt_file($file);
                    echo "✓ Chiffré: " . basename($file) . "\n";
                    $count++;
                } catch (Exception $e) {
                    echo "✗ Erreur: " . $e->getMessage() . "\n";
                }
            }
            echo "\nRésumé: $count fichiers chiffrés\n";
            break;
            
        case 'status':
            $allFiles = glob($sourcePath . '/{includes,config,modules}/*.php', GLOB_BRACE | GLOB_NOSORT);
            $encrypted = glob($sourcePath . '/{includes,config,modules}/*.encrypted', GLOB_BRACE | GLOB_NOSORT);
            echo "État du chiffrement:\n";
            echo "  Fichiers PHP: " . count($allFiles) . "\n";
            echo "  Fichiers chiffrés: " . count($encrypted) . "\n";
            
            if (count($encrypted) > 0) {
                echo "\nFichiers chiffrés:\n";
                foreach ($encrypted as $file) {
                    echo "  - " . basename($file) . "\n";
                }
            }
            break;
            
        default:
            echo "Action inconnue: $action\n";
            exit(1);
    }
} catch (Exception $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
'@
    
    # Écrire le script temporaire
    $tempScript = "$SourcePath\bin\encrypt_temp.php"
    Set-Content -Path $tempScript -Value $phpScript -Encoding UTF8
    
    # Exécuter
    & $phpPath $tempScript $SourcePath $Action
    
    # Nettoyer
    Remove-Item -Path $tempScript -Force -ErrorAction SilentlyContinue
}

# Créer le répertoire bin si nécessaire
$binPath = "$SourcePath\bin"
if (-not (Test-Path $binPath)) {
    New-Item -ItemType Directory -Path $binPath | Out-Null
}

Write-Status "==== Encryption des Sources CTR.NET-FARDC ====" -Type Info
Write-Status "Action: $Action" -Type Info
Write-Status "Chemin: $SourcePath" -Type Info

Invoke-PhpAction -Action $Action -SourcePath $SourcePath

Write-Status "Operation completed." -Type "Success"
