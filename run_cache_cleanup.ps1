# ================================================================================
# Exécution manuelle du nettoyage des caches CTR.NET-FARDC
# ================================================================================
param(
    [int]$JoursLogs = 90
)

$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$cleanupScript = Join-Path $projectRoot 'includes\cache_cleanup.php'

if (-not (Test-Path $cleanupScript)) {
    Write-Error "Script introuvable: $cleanupScript"
    exit 1
}

$phpExe = $null
$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($phpCmd) {
    $phpExe = $phpCmd.Source
}

if (-not $phpExe) {
    $candidates = Get-ChildItem -Path 'C:\laragon\bin\php' -Filter 'php.exe' -Recurse -ErrorAction SilentlyContinue |
        Sort-Object FullName -Descending
    if ($candidates -and $candidates.Count -gt 0) {
        $phpExe = $candidates[0].FullName
    }
}

if (-not $phpExe) {
    Write-Error "PHP introuvable. Installez/configurez PHP (Laragon ou PATH)."
    exit 1
}

if ($JoursLogs -le 0) {
    $JoursLogs = 90
}

& $phpExe $cleanupScript $JoursLogs
exit $LASTEXITCODE