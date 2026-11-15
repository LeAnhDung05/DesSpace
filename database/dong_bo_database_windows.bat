@echo off
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

:: Kiem tra xem co lenh wp khong
where wp >nul 2>nul
if %errorlevel% neq 0 (
    color 0C
    echo [CANH BAO] Khong tim thay lenh 'wp' (WP-CLI)!
    echo.
    echo HUONG DAN DUNG DUNG CACH:
    echo 1. Mo ung dung Local (LocalWP) tren may tinh cua ban.
    echo 2. Nhan chuot phai vao ten website (dang chay o port 10016).
    echo 3. Chon nut "Open Site Shell".
    echo 4. Trong cua so den vua hien ra, go lenh:
    echo    database\dong_bo_database_windows.bat
    echo.
    echo HOAC: Ban co the mo trinh duyet vao trang http://localhost:10016/shop/
    echo       Roi bam nut [DONG BO 40 SAN PHAM NGAY (1-CLICK)] o goc phai man hinh!
    echo.
    pause
    exit /b 1
)

:: Neu co wp-cli
echo Tim thay WP-CLI!
echo.
set /p PORT="Nhap so cong LocalWP cua ban (Nhan Enter de dung mac dinh 10016): "
if "%PORT%"=="" set PORT=10016

echo.
echo ------------------------------------------------------------------------------
echo [1/3] Dang nap file despace_seed_data.sql vao MySQL...
echo ------------------------------------------------------------------------------

if exist "database\despace_seed_data.sql" (
    wp db import database\despace_seed_data.sql
) else if exist "..\database\despace_seed_data.sql" (
    wp db import ..\database\despace_seed_data.sql
) else (
    echo Khong tim thay file database\despace_seed_data.sql!
    pause
    exit /b 1
)

echo.
echo ------------------------------------------------------------------------------
echo [2/3] Dang chuyen doi toan bo URL sang http://localhost:%PORT% ...
echo ------------------------------------------------------------------------------
wp search-replace "http://localhost:8090" "http://localhost:%PORT%" --all-tables

echo.
echo ------------------------------------------------------------------------------
echo [3/3] Dang tat che do Coming Soon va lam moi Rewrite Rules...
echo ------------------------------------------------------------------------------
wp option update woocommerce_coming_soon "no"
wp rewrite flush

color 0A
echo.
echo ==============================================================================
echo                      DONG BO HOAN TAT 100%!
echo ==============================================================================
echo.
echo Bay gio ban co the mo trinh duyet va truy cap:
echo 👉 http://localhost:%PORT%/shop/
echo.
echo Nho giai nen file uploads.zip vao thu muc wp-content/uploads de hien thi day du anh!
echo.
pause
