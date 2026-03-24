@echo off
REM Afficher l'état du chiffrement
cd /d "%~dp0"
php bin\encrypt.php status
pause
