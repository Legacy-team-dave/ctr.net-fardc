@echo off
REM Initialiser le chiffrement - Générer une clé
cd /d "%~dp0"
php bin\encrypt.php init
pause
