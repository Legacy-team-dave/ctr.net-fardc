@echo off
REM ================================================================================
REM INSTALLER.bat - Point d'entrée installation CTR.NET-FARDC
REM ================================================================================

cd /d "%~dp0"
call "%~dp0INSTALL.bat"
exit /b %errorlevel%
