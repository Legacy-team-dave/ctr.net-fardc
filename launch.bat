@echo off
chcp 65001 > nul
REM ================================================================================
REM Lanceur de l'application CTR.NET-FARDC (IG-FARDC)
REM ================================================================================

REM Déterminer le répertoire racine de l'application
set APP_ROOT=%~dp0
set APP_NAME=CTR.NET-FARDC
set SPLASH_URL=http://127.0.0.1/ctr.net-fardc/splash_screen.php
set LAUNCH_PS1=%~dp0launch.ps1
set LAUNCHED_VIA_PS1=0

REM Set the path to the desktop shortcut file
set "desktop_shortcut_path=%USERPROFILE%\Desktop\CTL EFF MIL_IG FARDC.lnk"
REM Set the path to the icon file
set "icon_path=C:\laragon\www\ctr.net-fardc\assets\img\ig_fardc.ico"

REM Vérifier si Laragon est installé
if exist "C:\laragon\laragon.exe" (
    echo.
    echo ================================================================================
    echo Lancement de %APP_NAME% avec Laragon...
    echo ================================================================================
    echo.
    
    REM Afficher les informations de l'application
    echo ============================================
    echo PROFILS WEB ACTIFS
    echo ============================================
    echo - ADMIN_IG (gestion complete)
    echo - OPERATEUR (preferences ^> equipe ^> controle)
    echo - CONTROLEUR / ENROLEUR (acces mobile uniquement)
    echo.
    echo ============================================
    echo PARCOURS ET FONCTIONNALITES ACTUELS
    echo ============================================
    echo [✓] Deconnexion renvoyee vers login.php
    echo [✓] Flux initial : preferences ^> modules/equipes/index.php ^> modules/controles/ajouter.php
    echo [✓] Tables harmonisees : QR masque, OBN, en-tetes uniformes
    echo [✓] Sauvegarde consolidee automatique + nettoyage des caches
    echo.

    if exist "%LAUNCH_PS1%" (
        powershell -NoProfile -ExecutionPolicy Bypass -File "%LAUNCH_PS1%" -NoWait
        if "%ERRORLEVEL%"=="0" (
            set LAUNCHED_VIA_PS1=1
        ) else (
            echo Le script PowerShell a échoué. Fallback BAT en cours...
            set LAUNCHED_VIA_PS1=0
        )
    )

    if "%LAUNCHED_VIA_PS1%"=="0" (
        REM Se placer dans le dossier www de Laragon
        cd /d "C:\laragon\www"

        REM Créer un lien symbolique vers le projet s'il n'existe pas déjà
        if not exist "ctr.net-fardc" (
            echo Création du lien symbolique...
            mklink /d ctr.net-fardc "%APP_ROOT%"
        ) else (
            echo Le lien symbolique existe déjà.
        )

        REM Lancer Laragon (à chaque exécution)
        echo Démarrage de Laragon...
        start C:\laragon\laragon.exe

        REM Laisser le temps à Laragon de démarrer complètement
        echo Attente du démarrage des services...
        timeout /t 5 /nobreak

        REM Ouvrir le navigateur sur l'écran de bienvenue
        echo Ouverture de l'application dans le navigateur...
        start %SPLASH_URL%
    )
    
    echo.
    echo L'application a été lancée avec succès.
    echo.
    pause > nul
    
    REM Laragon reste actif après fermeture de cette fenêtre
    
) else (
    echo.
    echo ================================================================================
    echo ERREUR: Laragon n'est pas installé
    echo ================================================================================
    echo.
    echo Veuillez installer Laragon depuis: https://laragon.org/
    echo.
    pause
    exit /b 1
)

exit /b 0