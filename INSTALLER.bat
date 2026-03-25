@echo off
REM ================================================================================
REM Installeur CTR.NET-FARDC
REM ================================================================================
REM Ce script configure et installe l'application CTR.NET-FARDC

setlocal enabledelayedexpansion

cls
color 0A
title Installeur CTR.NET-FARDC

echo.
echo ================================================================================
echo                      INSTALLATION CTR.NET-FARDC
echo ================================================================================
echo.

REM Vérifier Laragon
echo [1/5] Vérification de Laragon...
if not exist "C:\laragon\laragon.exe" (
    echo.
    echo ERREUR: Laragon n'est pas installé
    echo Veuillez installer Laragon depuis: https://laragon.org/
    echo.
    pause
    exit /b 1
)
echo OK - Laragon trouvé

REM Vérifier dossier application
echo [2/5] Vérification du dossier application...
if not exist "%~dp0config\database.php" (
    echo.
    echo ERREUR: Fichiers d'application manquants
    echo Assurez-vous que le dossier contient tous les fichiers CTR.NET-FARDC
    echo.
    pause
    exit /b 1
)
echo OK - Fichiers détectés

REM Créer lien symbolique si nécessaire
echo [3/5] Configuration des répertoires...
cd /d "C:\laragon\www"
if not exist "ctr.net-fardc" (
    echo Création du lien symbolique...
    mklink /d ctr.net-fardc "%~dp0" >nul 2>&1
    if !errorlevel! equ 0 (
        echo OK - Lien créé
    ) else (
        echo ATTENTION: Lien non créé (nécessite une exécution administrateur)
        echo Vous devrez copier manuellement le dossier
    )
) else (
    echo OK - Dossier détecté
)

REM Lancer Laragon
echo [4/5] Démarrage de Laragon...
start C:\laragon\laragon.exe
timeout /t 3 /nobreak

REM Afficher l'écran de bienvenue (image locale)
set SPLASH_SCREEN=%~dp0assets\img\splash_screen.png
if exist "%SPLASH_SCREEN%" (
    echo Affichage de l'écran de bienvenue...
    start "" "%SPLASH_SCREEN%"
) else (
    echo Fichier d'écran de bienvenue introuvable : %SPLASH_SCREEN%
)

REM Icône de l'application
set APP_ICON=%~dp0assets\img\ig_fardc.ico
if exist "%APP_ICON%" (
    echo Icône de l'application disponible : %APP_ICON%
) else (
    echo Icône de l'application introuvable : %APP_ICON%
)

REM Définir l'URL de démarrage (splash screen PHP)
set SPLASH_URL=http://127.0.0.1/ctr.net-fardc/splash_screen.php

REM Ouvrir navigateur sur la page de bienvenue
echo [5/5] Ouverture de l'application...
start %SPLASH_URL%

echo.
echo ================================================================================
echo                       INSTALLATION TERMINEE
echo ================================================================================
echo.
echo L'application démarre dans votre navigateur...
echo Identifiants de test:
echo   - Admin: admin / admin123
echo   - Opérateur: operateur / operateur123
echo   - Contrôleur: controleur / controleur123
echo.
echo Chiffrement AES-256-CBC: Activé (v1.1.0+)
echo Sauvegardes incrémentales automatiques: Actives (toutes les 8h)
echo Nettoyage automatique des caches: Actif (v1.5.0+)
echo.

REM ================================================================================
REM Création du raccourci sur le bureau
REM ================================================================================
echo Création du raccourci sur le bureau...
set "desktop_path=%USERPROFILE%\Desktop"
set "shortcut_name=CTL EFF MIL_IG FARDC.lnk"
set "shortcut_file=%desktop_path%\%shortcut_name%"
set "launch_target=%~dp0launch.bat"
set "url_fallback=%desktop_path%\CTL EFF MIL_IG FARDC.url"

if not exist "%desktop_path%" (
    echo ATTENTION: Bureau introuvable, tentative de création...
    mkdir "%desktop_path%" >nul 2>&1
)

if not exist "%launch_target%" (
    echo ATTENTION: launch.bat introuvable, création d'un raccourci web de secours
    > "%url_fallback%" (
        echo [InternetShortcut]
        echo URL=%SPLASH_URL%
        if exist "%APP_ICON%" echo IconFile=%APP_ICON%
        if exist "%APP_ICON%" echo IconIndex=0
    )
    echo OK - Raccourci web de secours créé: %url_fallback%
) else (
    powershell -NoProfile -ExecutionPolicy Bypass -Command ^
      "$ErrorActionPreference = 'Stop';" ^
      "try {" ^
      "  $WshShell = New-Object -ComObject WScript.Shell;" ^
      "  $Shortcut = $WshShell.CreateShortcut('%shortcut_file%');" ^
      "  $Shortcut.TargetPath = '%launch_target%';" ^
      "  $Shortcut.WorkingDirectory = '%~dp0';" ^
      "  if (Test-Path '%APP_ICON%') { $Shortcut.IconLocation = '%APP_ICON%,0' };" ^
      "  $Shortcut.Save();" ^
      "  if (Test-Path '%shortcut_file%') { exit 0 } else { exit 2 }" ^
      "} catch {" ^
      "  exit 1" ^
      "}"

    if %errorlevel% equ 0 (
        echo OK - Raccourci .lnk créé: %shortcut_file%
        echo Ce raccourci lance Laragon, attend les services, puis ouvre l'application.
        if exist "%APP_ICON%" (
            echo OK - Icône personnalisée appliquée
        ) else (
            echo ATTENTION: Icône personnalisée introuvable (icône par défaut)
        )
    ) else (
        echo ATTENTION: Echec de création du .lnk, création d'un raccourci web de secours
        > "%url_fallback%" (
            echo [InternetShortcut]
            echo URL=%SPLASH_URL%
            if exist "%APP_ICON%" echo IconFile=%APP_ICON%
            if exist "%APP_ICON%" echo IconIndex=0
        )
        echo OK - Raccourci web de secours créé: %url_fallback%
    )
)

REM ================================================================================
REM Fin d'installation
REM ================================================================================
echo.
pause > nul

echo Installation finalisée. Laragon reste actif.
echo Pour relancer ensuite l'application automatiquement,
echo double-cliquez sur l'icône "CTL EFF MIL_IG FARDC" du Bureau.
if exist "%shortcut_file%" echo Raccourci principal: %shortcut_file%
if not exist "%shortcut_file%" if exist "%url_fallback%" echo Raccourci de secours: %url_fallback%
echo Cette icône démarre Laragon, attend Apache/MySQL,
echo puis ouvre l'application dans votre navigateur.
echo.

REM Fin du script – pas de pause finale
