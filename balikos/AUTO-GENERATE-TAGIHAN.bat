@echo off
setlocal

title BALIKOS Auto Generate Tagihan

set "PROJECT_DIR=C:\laragon\www\balikos"
set "PHP82=C:\laragon\bin\php\php-8.2.27-Win32-vs16-x64\php.exe"

cd /d "%PROJECT_DIR%"

if not exist "%PHP82%" (
    echo PHP 8.2 Laragon tidak ditemukan. Mencoba php dari PATH...
    set "PHP82=php"
)

echo Menjalankan auto-generate tagihan BALIKOS...
echo Tagihan dibuat saat tanggal jatuh tempo penghuni berada 7 hari dari hari ini.
echo.

"%PHP82%" artisan balikos:auto-generate-tagihan --days=7

echo.
echo Selesai.
pause
