@echo off
setlocal

set "SCRIPT=%~dp0run_backup_purge.ps1"
set "MAXKEEP=%~1"

if "%MAXKEEP%"=="" set "MAXKEEP=30"

if not exist "%SCRIPT%" (
  echo ERREUR: script introuvable: %SCRIPT%
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT%" -MaxKeep %MAXKEEP%
if errorlevel 1 (
  echo ECHEC: purge des sauvegardes.
  exit /b 1
)

echo SUCCES: purge terminee (MaxKeep=%MAXKEEP%).
endlocal
