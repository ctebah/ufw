@echo off
setlocal
cd /d "%~dp0.."
docker compose run --rm php %*
set CODE=%ERRORLEVEL%
if not "%UFW_DOCKER_KEEP%"=="1" docker compose down
exit /b %CODE%
