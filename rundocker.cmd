@echo off
REM ===========================================================================
REM  Bring up the Docker Compose stack (Windows).
REM
REM  Usage:
REM    rundocker dev          local development (+ phpMyAdmin)
REM    rundocker stg          staging
REM    rundocker prod         production
REM
REM    rundocker dev down     stop ^& remove the dev stack
REM    rundocker prod logs    tail logs for the prod stack
REM
REM  An explicit environment is REQUIRED (no default) so nobody accidentally
REM  launches prod when they meant dev.
REM ===========================================================================
setlocal
cd /d "%~dp0"

set "ENV=%~1"
set "ACTION=%~2"
if "%ACTION%"=="" set "ACTION=up"

if "%ENV%"=="" (
    echo Usage: %~nx0 ^<dev^|stg^|prod^> [up^|down^|logs^|ps]
    exit /b 1
)

if /I not "%ENV%"=="dev" if /I not "%ENV%"=="stg" if /I not "%ENV%"=="prod" (
    echo Unknown environment "%ENV%" ^(expected: dev ^| stg ^| prod^).
    exit /b 1
)

if not exist "docker-compose.%ENV%.yml" (
    echo Override file "docker-compose.%ENV%.yml" not found.
    exit /b 1
)

if not exist ".env" (
    echo Missing .env file. Copy .env.example to .env and fill in the values first:
    echo     copy .env.example .env
    exit /b 1
)

set "COMPOSE=docker compose -f docker-compose.yml -f docker-compose.%ENV%.yml"

if /I "%ACTION%"=="up" (
    echo Starting "%ENV%" stack...
    %COMPOSE% up -d
) else if /I "%ACTION%"=="down" (
    echo Stopping "%ENV%" stack...
    %COMPOSE% down
) else if /I "%ACTION%"=="logs" (
    %COMPOSE% logs -f --tail=100
) else if /I "%ACTION%"=="ps" (
    %COMPOSE% ps
) else (
    echo Unknown action "%ACTION%" ^(expected: up ^| down ^| logs ^| ps^).
    exit /b 1
)

endlocal
