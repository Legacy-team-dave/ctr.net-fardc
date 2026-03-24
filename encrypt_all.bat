@echo off
REM Chiffrer tous les fichiers sensibles
cd /d "%~dp0"
php bin\encrypt.php encrypt
pause
