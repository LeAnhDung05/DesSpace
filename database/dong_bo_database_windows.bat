@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul
title DeSpace - Dong Bo Du Lieu Database (LocalWP / Windows)
color 0B

echo ==============================================================================
echo                DESPACE - CONG CU DONG BO DATABASE TU DONG
echo ==============================================================================
echo.
echo Cong cu nay se nap toan bo 40 san pham, 5 danh muc, mau sac van go,
echo danh gia khach hang va mo hinh 3D vao website LocalWP tren may cua ban.
echo.

where wp >nul 2>nul
if errorlevel 1 (
    color 0C
    echo [CANH BAO] Khong tim thay lenh 'wp' (WP-CLI^)!
    echo.
    echo HUONG DAN DUNG DUNG CACH:
    echo 1. Mo ung dung Local (LocalWP^) tren may tinh cua ban.
    echo 2. Nhan chuot phai vao ten website.
    echo 3. Chon nut "Open Site Shell".
    echo 4. Trong cua so den vua hien ra, go lenh:
    echo    database\dong_bo_database_windows.bat
    echo.
    pause
    exit /b 1
)

echo Tim thay WP-CLI!
echo.
set "PORT="
set /p PORT="Nhap so cong LocalWP cua ban (Nhan Enter de dung mac dinh 10016): "
if "!PORT!"=="" set "PORT=10016"

:: Strictly validate PORT is digits only and in range 1..65535 (prevents command injection)
echo(!PORT!| findstr /r "^[0-9][0-9]*$" >nul
if errorlevel 1 (
    color 0C
    echo [LOI] So cong khong hop le. Chi nhap so nguyen tu 1 den 65535.
    pause
    exit /b 1
)
if !PORT! LSS 1 (
    color 0C
    echo [LOI] So cong phai tu 1 den 65535.
    pause
    exit /b 1
)
if !PORT! GTR 65535 (
    color 0C
    echo [LOI] So cong phai tu 1 den 65535.
    pause
    exit /b 1
)

set "SCRIPT_DIR=%~dp0"
set "SEED_SQL=%SCRIPT_DIR%despace_seed_data.sql"

echo.
echo ------------------------------------------------------------------------------
echo [1/3] Dang nap file despace_seed_data.sql vao MySQL...
echo ------------------------------------------------------------------------------

if not exist "!SEED_SQL!" (
    color 0C
    echo [LOI] Khong tim thay file !SEED_SQL!
    pause
    exit /b 1
)

call wp db import "!SEED_SQL!"
if errorlevel 1 (
    color 0C
    echo [LOI] Nap database that bai!
    pause
    exit /b 1
)

echo.
echo ------------------------------------------------------------------------------
echo [2/3] Dang chuyen doi toan bo URL sang http://localhost:!PORT! ...
echo ------------------------------------------------------------------------------
call wp search-replace "http://localhost:8090" "http://localhost:!PORT!" --all-tables
if errorlevel 1 (
    color 0C
    echo [LOI] Cap nhat URL that bai!
    pause
    exit /b 1
)

echo.
echo ------------------------------------------------------------------------------
echo [3/3] Dang tat che do Coming Soon va lam moi Rewrite Rules...
echo ------------------------------------------------------------------------------
call wp option update woocommerce_coming_soon "no"
if errorlevel 1 (
    color 0C
    echo [LOI] Cap nhat option that bai!
    pause
    exit /b 1
)
call wp rewrite flush
if errorlevel 1 (
    color 0C
    echo [LOI] Lam moi Rewrite Rules that bai!
    pause
    exit /b 1
)

color 0A
echo.
echo ==============================================================================
echo                      DONG BO HOAN TAT 100%%!
echo ==============================================================================
echo.
echo Bay gio ban co the mo trinh duyet va truy cap:
echo http://localhost:!PORT!/shop/
echo.
pause
endlocal
