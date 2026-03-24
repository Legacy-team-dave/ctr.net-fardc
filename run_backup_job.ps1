# ================================================================================
# Exécution du job de sauvegarde incrémentale CTR.NET-FARDC
# ================================================================================
param(
    [int]$MaxKeep = 30
)

$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$cronScript = Join-Path $projectRoot 'includes\backup_cron.php'

if (-not (Test-Path $cronScript)) {
    Write-Error "Script introuvable: $cronScript"
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

if ($MaxKeep -le 0) {
    $MaxKeep = 30
}

& $phpExe $cronScript $MaxKeep
exit $LASTEXITCODE
