@echo off
setlocal

title BALIKOS Local Runner

set "PROJECT_DIR=C:\laragon\www\balikos"
set "PHP82=C:\laragon\bin\php\php-8.2.27-Win32-vs16-x64\php.exe"
set "URL=http://127.0.0.1:8000/login"

cd /d "%PROJECT_DIR%"

if not exist "%PHP82%" (
    echo PHP 8.2 Laragon tidak ditemukan di:
    echo %PHP82%
    echo.
    echo Mencoba memakai php dari PATH...
    set "PHP82=php"
)

if not exist "%PROJECT_DIR%\artisan" (
    echo File artisan tidak ditemukan. Pastikan project ada di %PROJECT_DIR%
    pause
    exit /b 1
)

echo Menjalankan BALIKOS...
echo Folder : %PROJECT_DIR%
echo URL    : %URL%
echo.
echo Pastikan MySQL Laragon sudah aktif.
echo.

start "BALIKOS Laravel Server" cmd /k ""%PHP82%" artisan serve --host=127.0.0.1 --port=8000"

timeout /t 3 /nobreak >nul
start "" "%URL%"

echo Browser sudah dibuka.
echo Jangan tutup window "BALIKOS Laravel Server" selama aplikasi dipakai.
echo.
pause
