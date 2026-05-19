@echo off
setlocal enabledelayedexpansion

set STACK_NAME=ma_gestion
set IMAGE_NAME=mon-app-php
set NGINX_IMAGE=rembourse-nginx

echo ========================================
echo ROLLBACK - RembourseMaroc
echo ========================================

if "%1"=="previous" (
    echo Recuperation de la version precedente...
    for /f "delims=" %%i in ('git describe --abbrev^=0 --tags ^(git rev-list --tags --skip=1 --max-count=1^) 2^>nul') do set VERSION=%%i
    if "!VERSION!"=="" (
        echo Aucune version precedente trouvee
        exit /b 1
    )
) else (
    set VERSION=%1
)

if "!VERSION!"=="" (
    echo Usage: rollback.bat previous
    exit /b 1
)

echo Rollback vers !VERSION!...

docker image inspect %IMAGE_NAME%:!VERSION! >nul 2>&1
if errorlevel 1 (
    echo Image introuvable, construction...
    git checkout tags/!VERSION!
    docker build -t %IMAGE_NAME%:!VERSION! .
    docker build -t %NGINX_IMAGE%:!VERSION! -f Dockerfile.nginx .
    git checkout main
)

docker service update --image %IMAGE_NAME%:!VERSION! --force %STACK_NAME%_app_rembourse_1 >nul 2>&1
docker service update --image %IMAGE_NAME%:!VERSION! --force %STACK_NAME%_app_rembourse_2 >nul 2>&1
docker service update --image %NGINX_IMAGE%:!VERSION! --force %STACK_NAME%_nginx_lb >nul 2>&1

timeout /t 5 /nobreak >nul

curl -f http://localhost:8081/health >nul 2>&1
if errorlevel 1 (
    echo ERREUR: Application ne repond pas
) else (
    echo SUCCES: Version !VERSION! en ligne
)

echo ========================================