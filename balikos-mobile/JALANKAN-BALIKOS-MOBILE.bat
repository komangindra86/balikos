@echo off
setlocal EnableExtensions

title BALIKOS Mobile Expo

set "PROJECT_DIR=C:\laragon\www\balikos-mobile"
set "API_DIR=C:\laragon\www\balikos"
set "PHP_EXE=C:\laragon\bin\php\php-8.2.27-Win32-vs16-x64\php.exe"
set "API_PORT=8010"
set "ANDROID_SDK=C:\Users\Indra\AppData\Local\Android\Sdk"
set "ADB=%ANDROID_SDK%\platform-tools\adb.exe"
set "EMULATOR=%ANDROID_SDK%\emulator\emulator.exe"
set "AVD_NAME=Pixel_3a_API_33_x86_64"

echo.
echo ========================================
echo   BALIKOS Mobile - Local Android
echo ========================================
echo.

if not exist "%PROJECT_DIR%\package.json" (
    echo ERROR: Folder mobile tidak ditemukan:
    echo %PROJECT_DIR%
    pause
    exit /b 1
)

if not exist "%API_DIR%\artisan" (
    echo ERROR: Backend BALIKOS tidak ditemukan:
    echo %API_DIR%
    pause
    exit /b 1
)

if not exist "%PHP_EXE%" (
    echo ERROR: PHP 8.2 Laragon tidak ditemukan:
    echo %PHP_EXE%
    pause
    exit /b 1
)

if not exist "%ADB%" (
    echo ERROR: adb.exe tidak ditemukan:
    echo %ADB%
    pause
    exit /b 1
)

if not exist "%EMULATOR%" (
    echo ERROR: emulator.exe tidak ditemukan:
    echo %EMULATOR%
    pause
    exit /b 1
)

cd /d "%PROJECT_DIR%"

echo Folder aplikasi:
echo %PROJECT_DIR%
echo.

where node >nul 2>nul
if errorlevel 1 (
    echo ERROR: Node.js tidak ditemukan di PATH.
    pause
    exit /b 1
)

where npm >nul 2>nul
if errorlevel 1 (
    echo ERROR: npm tidak ditemukan di PATH.
    pause
    exit /b 1
)

if not exist node_modules (
    echo Menginstall dependency Expo. Ini bisa beberapa menit...
    call npm install
    if errorlevel 1 (
        echo ERROR: npm install gagal.
        pause
        exit /b 1
    )
)

echo Mengecek BALIKOS API lokal...
powershell -NoProfile -Command "exit ((Test-NetConnection -ComputerName 127.0.0.1 -Port %API_PORT% -InformationLevel Quiet) -eq $false)" >nul 2>nul
if errorlevel 1 (
    echo Menjalankan Laravel API di http://127.0.0.1:%API_PORT%
    start "BALIKOS API" /min "%PHP_EXE%" "%API_DIR%\artisan" serve --host=0.0.0.0 --port=%API_PORT%
    echo Menunggu API siap...
    for /L %%i in (1,1,30) do (
        powershell -NoProfile -Command "exit ((Test-NetConnection -ComputerName 127.0.0.1 -Port %API_PORT% -InformationLevel Quiet) -eq $false)" >nul 2>nul
        if not errorlevel 1 goto API_READY
        timeout /t 1 /nobreak >nul
        <nul set /p="."
    )
    echo.
    echo WARNING: API belum terdeteksi. Pastikan MySQL/Laragon database aktif.
) else (
    echo BALIKOS API sudah aktif di port %API_PORT%.
)

:API_READY
echo.
echo API mobile: http://10.0.2.2:%API_PORT%/api/balikos

echo Mengecek Android device/emulator...
"%ADB%" devices | findstr /R /C:"device$" >nul
if errorlevel 1 (
    echo Tidak ada device aktif. Menjalankan emulator: %AVD_NAME%
    echo Mode stabil: cold boot, tanpa snapshot, renderer software.
    start "BALIKOS Emulator" "%EMULATOR%" -avd "%AVD_NAME%" -no-snapshot-load -no-snapshot-save -gpu swiftshader_indirect
    echo Menunggu emulator terdeteksi...
    for /L %%i in (1,1,45) do (
        "%ADB%" devices | findstr /R /C:"device$" >nul
        if not errorlevel 1 goto DEVICE_READY
        timeout /t 2 /nobreak >nul
        <nul set /p="."
    )
    echo.
    echo WARNING: Emulator belum terdeteksi setelah menunggu.
    echo Expo tetap akan dijalankan. Jika emulator belum terbuka, tunggu sampai selesai boot lalu tekan "a" di jendela Expo.
    goto START_EXPO
)

:DEVICE_READY
echo.
echo Android device/emulator siap.
echo Membersihkan koneksi Metro lama...
"%ADB%" shell am force-stop host.exp.exponent >nul 2>nul
"%ADB%" shell am force-stop id.balisantih.balikos >nul 2>nul
"%ADB%" reverse --remove-all >nul 2>nul
"%ADB%" reverse tcp:8081 tcp:8081 >nul 2>nul

:START_EXPO
echo.
echo Menjalankan BALIKOS Mobile...
echo Jika diminta, tekan "a" untuk membuka di Android.
echo Jika sebelumnya macet di Bundling 100%%, tutup aplikasi BALIKOS/Expo di emulator lalu jalankan lagi.
echo Mode koneksi: LAN. Jika Windows Firewall bertanya, pilih Allow.
echo.
call npm run android

echo.
echo Proses Expo selesai/berhenti.
pause
