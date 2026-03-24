# ================================================================================
# Installation de la tâche planifiée de sauvegarde incrémentale (toutes les 8h)
# ================================================================================
param(
    [string]$TaskName = 'CTR.NET-FARDC-Backup-8H',
    [int]$MaxKeep = 30
)

$ErrorActionPreference = 'Stop'

$scriptPath = Join-Path $PSScriptRoot 'run_backup_job.ps1'
if (-not (Test-Path $scriptPath)) {
    Write-Error "Script introuvable: $scriptPath"
    exit 1
}

if ($MaxKeep -le 0) {
    $MaxKeep = 30
}

$startTime = (Get-Date).AddMinutes(5).ToString('HH:mm')
$taskCommand = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "' + $scriptPath + '" -MaxKeep ' + $MaxKeep

schtasks /Delete /TN $TaskName /F | Out-Null 2>&1

schtasks /Create /TN $TaskName /SC HOURLY /MO 8 /ST $startTime /TR $taskCommand /F | Out-Null
if ($LASTEXITCODE -ne 0) {
    Write-Error "Impossible de créer la tâche planifiée $TaskName."
    exit 1
}

Write-Host "Tâche installée/mise à jour: $TaskName"
Write-Host "Exécution planifiée: toutes les 8 heures (premier démarrage à $startTime)"
Write-Host "Purge automatique: conservation des $MaxKeep dernières archives non identiques"
