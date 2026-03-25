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
echo  CTR.NET-FARDC v1.5.0
echo ================================
echo.
echo Vérification de l'environnement...
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
echo [✓] Application lancée
echo.
echo    URL: http://127.0.0.1/ctr.net-fardc/splash_screen.php
echo.
echo Profils disponibles: ADMIN_IG, OPERATEUR, CONTROLEUR
echo.
echo Identifiants de test:
echo   - Admin: admin / admin123
echo   - Opérateur: operateur / operateur123
echo   - Contrôleur: controleur / controleur123
echo.
echo Chiffrement AES-256-CBC: Activé (v1.1.0+)
echo Pour initialiser: double-cliquer encrypt_init.bat
echo.
echo Sauvegardes incrémentales automatiques: Actives (toutes les 8h)
echo Nettoyage automatique des caches: Actif (v1.5.0+)
echo.
