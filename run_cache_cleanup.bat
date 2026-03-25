@echo off
setlocal

set "SCRIPT=%~dp0run_cache_cleanup.ps1"
set "JOURS=%~1"

if "%JOURS%"=="" set "JOURS=90"

if not exist "%SCRIPT%" (
  echo ERREUR: script introuvable: %SCRIPT%
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT%" -JoursLogs %JOURS%
if errorlevel 1 (
  echo ECHEC: nettoyage des caches.
  exit /b 1
)

echo SUCCES: nettoyage des caches termine (JoursLogs=%JOURS%).
