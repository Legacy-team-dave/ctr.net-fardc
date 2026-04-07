@echo off
REM ================================================================================
REM CTR.NET-FARDC - Application Launcher Executable
REM ================================================================================
REM Ce script peut être compilé en .exe avec PS2EXE

title CTR.NET-FARDC Launcher
cls

set "LAUNCH_PS1=%~dp0launch.ps1"

echo.
echo ================================
echo  CTR.NET-FARDC - Poste local
echo ================================
echo.
echo Verification de l'environnement...
echo.

REM Vérifier Laragon 
if not exist "C:\laragon\laragon.exe" (
    echo [!] ERREUR: Laragon non détecté
    echo.
    echo Veuillez installer Laragon:
    echo https://laragon.org/download
    echo.
    pause
    exit /b 1
)
echo [OK] Laragon détecté

REM Vérifier MySQL
timeout /t 1 /nobreak > nul
echo.
echo Démarrage de l'application...
echo.

if exist "%LAUNCH_PS1%" (
    powershell -NoProfile -ExecutionPolicy Bypass -File "%LAUNCH_PS1%" -NoWait
    exit /b %errorlevel%
)

REM Lancer Laragon
start C:\laragon\laragon.exe

REM Attendre
timeout /t 4 /nobreak > nul

REM Ouvrir navigateur
start http://127.0.0.1/ctr.net-fardc/splash_screen.php

echo.
echo [✓] Application lancee
echo.
echo    URL: http://127.0.0.1/ctr.net-fardc/splash_screen.php
echo    Login direct: http://127.0.0.1/ctr.net-fardc/login.php
echo.
echo Profils web actifs:
echo   - ADMIN_IG : supervision et administration
echo   - OPERATEUR : preferences ^> equipe ^> controle
echo   - CONTROLEUR / ENROLEUR : acces mobile uniquement
echo.
echo Acces initial:
echo   - mot de passe par defaut des comptes crees : 987654321
echo   - activation initiale requise par ADMIN_IG
echo.
echo Chiffrement AES-256-CBC : actif
echo Tables unifiees : QR masque ; Observation(s) abrege en OBN
echo Sauvegarde consolidee automatique : active (toutes les 8h)
echo Nettoyage automatique des caches : actif
echo.
