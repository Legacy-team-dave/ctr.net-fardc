# ================================================================================
# Exécution manuelle de la purge des archives de sauvegarde
# ================================================================================
param(
    [int]$MaxKeep = 30
)

$ErrorActionPreference = 'Stop'

$projectRoot = $PSScriptRoot
$purgeScript = Join-Path $projectRoot 'includes\backup_purge.php'

if (-not (Test-Path $purgeScript)) {
    Write-Error "Script introuvable: $purgeScript"
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

& $phpExe $purgeScript $MaxKeep
exit $LASTEXITCODE
