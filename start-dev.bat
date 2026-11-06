@echo off
setlocal

set "ROOT=%~dp0"
set "PHP="

where php >nul 2>nul
if not errorlevel 1 set "PHP=php"
if not defined PHP if exist "C:\xampp2\php\php.exe" set "PHP=C:\xampp2\php\php.exe"
if not defined PHP if exist "C:\xamppb\php\php.exe" set "PHP=C:\xamppb\php\php.exe"
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"

if not defined PHP (
    echo PHP was not found. Install PHP or add php.exe to PATH.
    pause
    exit /b 1
)

start "MAU HRMS PHP API" "%PHP%" -S 127.0.0.1:8000 -t "%ROOT%backend"
echo Ensure Apache and MySQL are running in XAMPP and that
echo MySQL is running. The PHP API starts on 127.0.0.1:8000.
cd /d "%ROOT%frontend"
call npm run dev
