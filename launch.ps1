# ================================================================================
# PowerShell Launch Script - CTR.NET-FARDC
# ================================================================================
# Script to start CTR.NET-FARDC
# Usage: .\launch.ps1 [-NoWait]

param(
    [switch]$NoWait = $false
)

$AppRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppName = "CTR.NET-FARDC"
$AppUrl = "http://127.0.0.1/ctr.net-fardc/splash_screen.php"
$LoginUrl = "http://127.0.0.1/ctr.net-fardc/login.php"
$LaragonExe = "C:\laragon\laragon.exe"
$ApacheRoot = "C:\laragon\bin\apache\httpd-2.4.62-240904-win64-VS17"
$ApacheExe = Join-Path $ApacheRoot "bin\httpd.exe"

function Test-PortListening {
    param([int]$Port)

    $listener = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue
    return [bool]$listener
}

function Test-ServicesReady {
    $httpReady = Test-PortListening -Port 80
    $mysqlReady = Test-PortListening -Port 3306
    return ($httpReady -and $mysqlReady)
}

function Wait-ServicesReady {
    param([int]$Seconds = 15)

    for ($attempt = 0; $attempt -lt $Seconds; $attempt++) {
        if (Test-ServicesReady) {
            return $true
        }
        Start-Sleep -Seconds 1
    }

    return $false
}

function Start-ApacheFallback {
    if (-not (Test-Path $ApacheExe)) {
        Write-Host "ERROR: Apache not found at $ApacheExe" -ForegroundColor Red
        return $false
    }

    Write-Host "Laragon did not start Apache automatically. Starting Apache manually..." -ForegroundColor Yellow
    Start-Process -FilePath $ApacheExe -ArgumentList '-d', $ApacheRoot -WorkingDirectory (Join-Path $ApacheRoot 'bin')

    return (Test-PortListening -Port 80)
}

function Get-MySqlExe {
    $mysqlCandidates = @(
        "C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe",
        "C:\laragon\bin\mysql\mysql-8.0.35-winx64\bin\mysqld.exe",
        "C:\laragon\bin\mysql\mysql-8.4.0-winx64\bin\mysqld.exe"
    )

    foreach ($candidate in $mysqlCandidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    $discovered = Get-ChildItem "C:\laragon\bin\mysql" -Recurse -Filter "mysqld.exe" -ErrorAction SilentlyContinue |
        Select-Object -First 1 -ExpandProperty FullName

    return $discovered
}

function Start-MySqlFallback {
    $mySqlExe = Get-MySqlExe
    if (-not $mySqlExe) {
        Write-Host "WARNING: MySQL executable not found in Laragon." -ForegroundColor Yellow
        return $false
    }

    $mysqlBin = Split-Path -Parent $mySqlExe
    Write-Host "Laragon did not start MySQL automatically. Starting MySQL manually..." -ForegroundColor Yellow
    Start-Process -FilePath $mySqlExe -WorkingDirectory $mysqlBin

    for ($i = 0; $i -lt 10; $i++) {
        if (Test-PortListening -Port 3306) {
            return $true
        }
        Start-Sleep -Seconds 1
    }

    return $false
}

Write-Host ""
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host "Starting $AppName" -ForegroundColor Cyan
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host ""

if (-not (Test-Path $LaragonExe)) {
    Write-Host "ERROR: Laragon not found at $LaragonExe" -ForegroundColor Red
    if (-not $NoWait) {
        Read-Host "Press Enter to exit"
    }
    exit 1
}

if (-not (Test-Path $AppRoot -PathType Container)) {
    Write-Host "ERROR: Application folder not found: $AppRoot" -ForegroundColor Red
    if (-not $NoWait) {
        Read-Host "Press Enter to exit"
    }
    exit 1
}

Write-Host "Starting Laragon..." -ForegroundColor Yellow
Start-Process -FilePath $LaragonExe

Write-Host "Waiting for Laragon services (Apache + MySQL)..." -ForegroundColor Yellow
$servicesReady = Wait-ServicesReady -Seconds 15

if (-not $servicesReady) {
    $apacheReady = Test-PortListening -Port 80
    if (-not $apacheReady) {
        $apacheReady = Start-ApacheFallback
    }

    $mysqlReady = Test-PortListening -Port 3306
    if (-not $mysqlReady) {
        $mysqlReady = Start-MySqlFallback
    }

    $servicesReady = ($apacheReady -and $mysqlReady)
}

if (-not $servicesReady) {
    Write-Host "ERROR: Laragon services did not start completely." -ForegroundColor Red
    Write-Host "Check Laragon, Apache and MySQL, then retry." -ForegroundColor Yellow
    if (-not $NoWait) {
        Read-Host "Press Enter to exit"
    }
    exit 1
}

Write-Host "Opening browser..." -ForegroundColor Yellow
Start-Process $AppUrl

Write-Host ""
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host "Application started" -ForegroundColor Green
Write-Host "URL: $AppUrl" -ForegroundColor Cyan
Write-Host "Login: $LoginUrl" -ForegroundColor Cyan
Write-Host "================================================================================" -ForegroundColor Cyan
Write-Host ""

if (-not $NoWait) {
    Read-Host "Press Enter to close this window"
}
