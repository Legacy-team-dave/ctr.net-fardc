@echo off
REM Encrypt Sources - Lanceur Windows Batch générique
REM Usage: encrypt.bat init | encrypt | decrypt | status | list

setlocal enabledelayedexpansion

REM Déterminer le répertoire courant
set SCRIPT_DIR=%~dp0
set PHP_BIN=

REM Chercher PHP dans Laragon
if exist "C:\laragon\bin\php\php-8.2.0\php.exe" (
    set PHP_BIN=C:\laragon\bin\php\php-8.2.0\php.exe
) else if exist "C:\laragon\bin\php\php-8.1.30\php.exe" (
    set PHP_BIN=C:\laragon\bin\php\php-8.1.30\php.exe
) else if exist "C:\laragon\bin\php\php-8.0.30\php.exe" (
    set PHP_BIN=C:\laragon\bin\php\php-8.0.30\php.exe
) else if exist "C:\laragon\bin\php\php-7.4.34\php.exe" (
    set PHP_BIN=C:\laragon\bin\php\php-7.4.34\php.exe
) else (
    REM Essayer php dans le PATH
    for /f "tokens=*" %%a in ('where php 2^>nul') do set PHP_BIN=%%a
)

if "!PHP_BIN!"=="" (
    echo ERREUR: PHP non trouvé. Assurez-vous que Laragon est installé ou PHP est dans PATH.
    pause
    exit /b 1
)

echo [Chiffrement des Sources]
echo PHP trouvé: !PHP_BIN!
cd /d "!SCRIPT_DIR!"
"!PHP_BIN!" bin\encrypt.php %*
pause
