@echo off
setlocal

set "SCRIPT=%~dp0setup_backup_task.ps1"
set "MAXKEEP=%~1"

if "%MAXKEEP%"=="" set "MAXKEEP=30"

if not exist "%SCRIPT%" (
  echo ERREUR: script introuvable: %SCRIPT%
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT%" -MaxKeep %MAXKEEP%
if errorlevel 1 (
  echo ECHEC: installation de la tache planifiee.
  exit /b 1
)

echo SUCCES: tache planifiee de sauvegarde configuree.
endlocal
