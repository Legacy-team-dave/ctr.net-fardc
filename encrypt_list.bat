@echo off
REM Lister les fichiers chiffrables
cd /d "%~dp0"
php bin\encrypt.php list
pause
