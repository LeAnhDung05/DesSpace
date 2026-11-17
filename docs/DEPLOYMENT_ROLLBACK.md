# Quy Trình Triển Khai & Khôi Phục Thảm Họa (Deployment & Rollback Guide)

**Dự án:** DeSpace — Nền Tảng Thương Mại Điện Tử Nội Thất 3D & WebXR AR  
**Môi trường hỗ trợ:** Docker Compose (PHP 8.3 + MySQL 8.4 / MariaDB + Mailpit) & LocalWP

---

## 1. Quy Trình Triển Khai Chuẩn (Standard Deployment)

### Bước 1: Kiểm tra mã nguồn & Chạy bộ kiểm thử tự động (Pre-flight Checks)
Trước khi triển khai hoặc hợp nhất nhánh vào `main`, chạy các lệnh kiểm tra cú pháp và Unit Tests:
```bash
# 1. Kiểm tra cú pháp toàn bộ file PHP
find wordpress/wp-content/plugins/despace-core wordpress/wp-content/themes/despace-child wordpress/wp-content/mu-plugins tests -name '*.php' -print0 | xargs -0 -n1 php -l

# 2. Chạy bộ kiểm thử RBAC, Inventory, M3 và Desktop 3D Sync
php tests/test-rbac-matrix.php
php tests/test-inventory-matrix.php
php tests/test-desktop-3d-sync.php
for f in tests/test-*-unit.php; do php "$f"; done
```

### Bước 2: Đồng bộ Cơ sở dữ liệu & Tài nguyên 3D / Hình ảnh
1. **Qua kịch bản tự động (Mac/Linux/Windows):**
   - Mac/Linux: `./database/dong_bo_database.sh`
   - Windows: `database\dong_bo_database_windows.bat`
2. **Qua DeSpace Hub (1-Click trong WP Admin):**
   - Truy cập `WP Admin -> DeSpace Hub -> Khôi Phục Ảnh & 3D`.
   - Nhấn **Đồng Bộ Ngay (40 Sản Phẩm + 3D)** và **Sửa Lỗi Đường Dẫn Ảnh Tự Động**.

---

## 2. Quy Trình Khôi Phục Khi Sự Cố (Rollback Procedure)

### 2.1. Khôi phục Mã nguồn (Code Rollback)
Nếu bản cập nhật plugin `despace-core` hoặc theme `despace-child` phát sinh lỗi trên môi trường chạy:
```bash
git checkout main
git revert HEAD --no-edit
```

### 2.2. Khôi phục Cơ sở dữ liệu (Database Rollback)
Bản sao lưu chuẩn luôn được lưu trữ tại `database/despace_seed_data.sql` (và bản nén `database/despace_seed_data.sql.gz`). Để khôi phục tức thì trên Docker:
```bash
docker exec -i despace-db-1 mysql -u root -pdespace-local-root-password despace < database/despace_seed_data.sql
```

### 2.3. Kiểm tra Sức khỏe Hệ thống sau Khôi phục (Post-Rollback Health Check)
```bash
curl -s http://localhost:8090/wp-json/despace/v1/desktop-3d/health
curl -s http://localhost:8090/wp-json/despace/v1/catalog?per_page=1
```
Đảm bảo cả hai endpoint trả về HTTP `200 OK` và `"status": "online"`.
