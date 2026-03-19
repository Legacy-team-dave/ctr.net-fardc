@echo off
REM ================================================================================
REM Installeur CTR.NET-FARDC
REM ================================================================================
REM Ce script configure et installe l'application CTR.NET-FARDC
REM Version gérant les caractères spéciaux dans les chemins

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
set "SPLASH_SCREEN=%~dp0assets\img\splash_screen.png"
if exist "%SPLASH_SCREEN%" (
    echo Affichage de l'écran de bienvenue...
    start "" "%SPLASH_SCREEN%"
) else (
    echo Fichier d'écran de bienvenue introuvable : %SPLASH_SCREEN%
)

REM Icône de l'application
set "APP_ICON=%~dp0assets\img\ig_fardc.ico"
if exist "%APP_ICON%" (
    echo Icône de l'application disponible : %APP_ICON%
) else (
    echo Icône de l'application introuvable : %APP_ICON%
)

REM Définir l'URL de démarrage (splash screen PHP)
set "SPLASH_URL=http://127.0.0.1/ctr.net-fardc/splash_screen.php"

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
REM Le profil CONTROLEUR a été retiré ; utiliser OPERATEUR pour les contrôles
echo.

REM ================================================================================
REM Création d'un vrai raccourci (.lnk) sur le bureau qui lance Laragon + l'application
REM ================================================================================
echo Création du raccourci sur le bureau...
set "desktop_path=%USERPROFILE%\Desktop"
set "shortcut_name=CTL EFF MIL_IG FARDC.lnk"
set "shortcut_file=%desktop_path%\%shortcut_name%"
set "app_icon=%~dp0assets\img\ig_fardc.ico"
set "target_url=http://127.0.0.1/ctr.net-fardc/splash_screen.php"

REM Passage des chemins via des variables d'environnement (gère les caractères spéciaux)
set "SC=%shortcut_file%"
set "ICON=%app_icon%"
set "URL=%target_url%"

if exist "%app_icon%" (
    powershell -Command "$WS = New-Object -ComObject WScript.Shell; $SC = $WS.CreateShortcut($env:SC); $SC.TargetPath = 'cmd.exe'; $SC.Arguments = '/c start C:\laragon\laragon.exe && start ' + $env:URL; $SC.IconLocation = $env:ICON; $SC.Save()"
    echo OK - Raccourci créé avec l'icône personnalisée
) else (
    powershell -Command "$WS = New-Object -ComObject WScript.Shell; $SC = $WS.CreateShortcut($env:SC); $SC.TargetPath = 'cmd.exe'; $SC.Arguments = '/c start C:\laragon\laragon.exe && start ' + $env:URL; $SC.Save()"
    echo ATTENTION: Icône introuvable, raccourci créé sans icône personnalisée
)

REM ================================================================================
REM Attente silencieuse puis arrêt de Laragon (comportement identique au lanceur)
REM ================================================================================
echo.
pause > nul

echo Arrêt de Laragon...
taskkill /f /im laragon.exe > nul 2>&1
echo Laragon a été fermé.
echo.