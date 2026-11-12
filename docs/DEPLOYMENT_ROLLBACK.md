# DeSpace — Quy Trình Triển Khai, Vận Hành, Sao Lưu & Rollback

**Mã tài liệu:** DESPACE-DOC-OPS-01  
**Phiên bản:** 1.0.0 (Hoàn thiện theo Issue #36)  
**Mục tiêu:** Hướng dẫn triển khai môi trường Staging/Production chuẩn HTTPS/SSL, cấu hình gửi email giao dịch qua SMTP, quy trình sao lưu định kỳ, kịch bản thử nghiệm khôi phục (Restore) và các bước Rollback khẩn cấp.

---

## 1. Kiến Trúc Môi Trường & Luồng Triển Khai

Quy trình phát triển DeSpace tuân thủ mô hình 3 tầng:

```
[Local Development] ──► [Pull Request] ──► [Shared Staging] ──► [Production Demo]
 (5 Máy cá nhân)          (develop)          (Kiểm thử UAT)        (main branch)
```

- **Môi trường cục bộ (Local):** Mỗi thành viên chạy WordPress + WooCommerce riêng, chia sẻ chung mã nguồn qua Git branch `feature/<module>`.
- **Môi trường Staging:** Máy chủ dùng chung để tích hợp 5 module (M1–M5), kiểm thử đa thiết bị và chạy kịch bản UAT.
- **Môi trường Production Demo:** Phiên bản ổn định cao nhất, sẵn sàng phục vụ báo cáo và nghiệm thu đề tài.

### Yêu cầu cấu hình máy chủ tối thiểu:
- **Hệ điều hành:** Ubuntu 22.04 LTS / Debian 12.
- **Web Server:** Nginx 1.20+ (hoặc Apache 2.4+ có module `mod_rewrite`, `mod_ssl`, `mod_headers`).
- **PHP:** Phiên bản 8.0 đến 8.2 (Bắt buộc các extension: `mysqli`, `pdo_mysql`, `gd`, `mbstring`, `xml`, `curl`, `zip`).
- **Cơ sở dữ liệu:** MySQL Community Edition 8.0+ hoặc MariaDB 10.6+.
- **WordPress:** 6.4+, **WooCommerce:** 8.0+ (Bật sẵn tính năng High-Performance Order Storage - HPOS).

---

## 2. Cấu Hình HTTPS / SSL (Bắt Buộc Cho WebXR & WebAR)

> [!IMPORTANT]
> **Tại sao HTTPS là bắt buộc?**
> Chuẩn W3C WebXR Device API (`navigator.xr`), Google Scene Viewer và Apple Quick Look trên thiết bị di động **chỉ hoạt động trong ngữ cảnh an toàn (Secure Context)** với giao thức `https://`. Nếu website chạy HTTP thường, tính năng thực tế tăng cường (AR) trên camera điện thoại sẽ bị trình duyệt chặn hoàn toàn.

### 2.1. Cài đặt chứng chỉ SSL Let's Encrypt miễn phí
Sử dụng Certbot tự động cấp phát và gia hạn chứng chỉ SSL trên Ubuntu Nginx:

```bash
# 1. Cài đặt certbot
sudo apt update
sudo apt install -y certbot python3-certbot-nginx

# 2. Cấp phát chứng chỉ SSL cho domain
sudo certbot --nginx -d despace.vn -d www.despace.vn

# 3. Kiểm tra tự động gia hạn
sudo systemctl status certbot.timer
```

### 2.2. Cấu hình Nginx tối ưu cho DeSpace và 3D GLB
Tệp tin `/etc/nginx/sites-available/despace`:

```nginx
server {
    listen 80;
    server_name despace.vn www.despace.vn;
    return 301 https://$host$request_uri; # Ép buộc chuyển hướng toàn bộ sang HTTPS
}

server {
    listen 443 ssl http2;
    server_name despace.vn www.despace.vn;

    root /var/www/despace/wordpress;
    index index.php index.html;

    # Cấu hình SSL an toàn
    ssl_certificate /etc/letsencrypt/live/despace.vn/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/despace.vn/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Bảo mật HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;

    # Giới hạn kích thước tải lên (phù hợp với file 3D GLB tối đa 20MB)
    client_max_body_size 30M;

    # Cấu hình CORS và Cache cho file 3D .glb và ảnh .webp
    location ~* \.(glb|gltf|bin)$ {
        add_header Access-Control-Allow-Origin *; # Cần thiết cho model-viewer tải 3D
        add_header Cache-Control "public, max-age=31536000, immutable";
        try_files $uri =404;
    }

    # Định tuyến WordPress chuẩn
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Chặn truy cập file ẩn và file cấu hình
    location ~ /\. {
        deny all;
    }
}
```

---

## 3. Cấu Hình SMTP Gửi Email Giao Dịch

Tuyệt đối không sử dụng hàm `mail()` mặc định của máy chủ Linux vì tỷ lệ rơi vào hòm thư rác (Spam) hoặc bị chặn rất cao.

### 3.1. Hướng dẫn cài đặt SMTP
1. Cài đặt plugin chuẩn: **WP Mail SMTP** (hoặc **FluentSMTP**).
2. Chọn nhà cung cấp: **Gmail API**, **SendGrid**, **Brevo** hoặc máy chủ **SMTP tùy chỉnh**.
3. Cấu hình thông số kết nối:
   - **SMTP Host:** `smtp.gmail.com` (hoặc máy chủ SMTP đã đăng ký).
   - **Encryption:** TLS (Khuyến nghị).
   - **SMTP Port:** `587` (hoặc `465` nếu chọn SSL).
   - **Authentication:** Yes (Bật xác thực).
   - **Username:** Địa chỉ email hệ thống (VD: `no-reply@despace.vn`).
   - **Password:** App Password (Mật khẩu ứng dụng 16 ký tự, không dùng mật khẩu chính).

### 3.2. Ranh giới dữ liệu email giao dịch
- **Đơn hàng Chuyển khoản ngân hàng (BACS):** Trạng thái đơn là `on-hold`. Nội dung email gửi khách hàng hiển thị thông báo: *"Đơn hàng đang chờ đối soát chuyển khoản ngân hàng"*, kèm thông tin tài khoản và mã đơn hàng. **Tuyệt đối không gửi email thông báo đã nhận thanh toán.**
- **Kiểm thử gửi mail:** Sử dụng công cụ *Send Test Email* trong trang quản trị WP Mail SMTP để xác thực trước khi chạy thử nghiệm UAT.

---

## 4. Quy Trình Sao Lưu Định Kỳ (Backup Procedures)

### 4.1. Phạm vi dữ liệu sao lưu
1. **Cơ sở dữ liệu MySQL:**
   - Toàn bộ các bảng WooCommerce (`wp_wc_orders`, `wp_wc_order_items`, `wp_posts`, v.v.).
   - Bảng truy vết kho: `wp_despace_inventory_logs`.
   - Bảng cấu hình tùy chọn: `wp_options`.
2. **Thư mục Media & Uploads:** `wordpress/wp-content/uploads/` (Lưu ảnh sản phẩm và toàn bộ file mô hình 3D `.glb`).
3. **Mã nguồn Plugin & Theme:** `wordpress/wp-content/plugins/despace-core/` và `wordpress/wp-content/themes/despace-child/`.

### 4.2. Kịch bản sao lưu tự động qua Bash Script
Tệp tin `/usr/local/bin/despace-backup.sh`:

```bash
#!/bin/bash
# ====================================================================
# DeSpace Automatic Daily Backup Script
# ====================================================================

BACKUP_DIR="/var/backups/despace"
DATE=$(date +"%Y%m%d_%H%M%S")
DB_NAME="despace_db"
DB_USER="despace_user"
DB_PASS="StrongSecretPasswordHere"
WP_PATH="/var/www/despace/wordpress"

mkdir -p "$BACKUP_DIR/$DATE"

echo "[1/3] Đang sao lưu Database MySQL..."
mysqldump --user=$DB_USER --password=$DB_PASS --single-transaction --quick "$DB_NAME" | gzip > "$BACKUP_DIR/$DATE/db_$DATE.sql.gz"

echo "[2/3] Đang sao lưu thư mục uploads (Ảnh & File 3D GLB)..."
tar -czf "$BACKUP_DIR/$DATE/uploads_$DATE.tar.gz" -C "$WP_PATH/wp-content" uploads

echo "[3/3] Đang sao lưu mã nguồn DeSpace Core Plugin & Child Theme..."
tar -czf "$BACKUP_DIR/$DATE/source_$DATE.tar.gz" -C "$WP_PATH/wp-content" plugins/despace-core themes/despace-child

# Xóa các bản sao lưu cũ hơn 7 ngày để tiết kiệm dung lượng ổ cứng
find $BACKUP_DIR -maxdepth 1 -type d -mtime +7 -exec rm -rf {} +

echo "Sao lưu thành công hoàn tất vào thư mục: $BACKUP_DIR/$DATE"
```

Cài đặt lịch Cron job chạy tự động vào **02:00 sáng mỗi ngày**:
```bash
sudo crontab -e
# Thêm dòng sau:
0 2 * * * /usr/local/bin/despace-backup.sh >> /var/log/despace_backup.log 2>&1
```

---

## 5. Kịch Bản Thử Nghiệm Khôi Phục (Restore Testing)

Để đáp ứng tiêu chí nghiệm thu của Issue #36, quy trình Restore đã được thực nghiệm thành công trên môi trường kiểm thử:

### Các bước khôi phục từ bản sao lưu:
1. **Bước 1: Bật chế độ bảo trì WordPress:**
   ```bash
   cd /var/www/despace/wordpress
   wp maintenance-mode activate
   ```
2. **Bước 2: Giải nén và nạp lại cơ sở dữ liệu:**
   ```bash
   gunzip -c /var/backups/despace/YYYYMMDD_HHMMSS/db_YYYYMMDD_HHMMSS.sql.gz | mysql -u despace_user -p despace_db
   ```
3. **Bước 3: Khôi phục thư mục Uploads & File 3D GLB:**
   ```bash
   tar -xzf /var/backups/despace/YYYYMMDD_HHMMSS/uploads_YYYYMMDD_HHMMSS.tar.gz -C /var/www/despace/wordpress/wp-content/
   chown -R www-data:www-data /var/www/despace/wordpress/wp-content/uploads
   ```
4. **Bước 4: Thay thế URL (nếu khôi phục sang domain staging mới):**
   ```bash
   wp search-replace 'https://despace.vn' 'https://staging.despace.vn' --all-tables
   ```
5. **Bước 5: Xóa bộ nhớ đệm và tắt chế độ bảo trì:**
   ```bash
   wp cache flush
   wp maintenance-mode deactivate
   ```
6. **Bước 6: Kiểm tra khôi phục (Smoke Test Verification):**
   - Đăng nhập tài khoản quản trị viên.
   - Kiểm tra mở trang chi tiết sản phẩm 3D -> Mô hình GLB tải bình thường.
   - Kiểm tra trang Nhật ký kho `despace-inventory-logs` -> Đầy đủ dữ liệu lịch sử.
   - Kiểm tra Dashboard báo cáo `despace-reports` -> Số liệu doanh thu nguyên vẹn.

---

## 6. Hướng Dẫn Rollback Khẩn Cấp (Emergency Rollback)

Khi triển khai bản release mới lên Staging hoặc Production phát sinh lỗi blocker:

### 6.1. Rollback mã nguồn qua Git
```bash
# 1. Chuyển về tag ổn định gần nhất
git fetch --tags
git checkout tags/v1.0.0-stable

# 2. Xóa bộ nhớ đệm OPcache của PHP
sudo systemctl reload php8.1-fpm
```

### 6.2. Rollback Plugin DeSpace Core
Trong trường hợp mã nguồn phiên bản mới gây lỗi 500 (Fatal Error), người quản trị có thể vô hiệu hóa plugin ngay từ dòng lệnh mà không làm hỏng dữ liệu đơn hàng hay sản phẩm của WooCommerce:
```bash
wp plugin deactivate despace-core
```
Sau khi sửa lỗi xong trên nhánh `hotfix`, kích hoạt lại:
```bash
wp plugin activate despace-core
```

### 6.3. Rollback Database Schema
Nếu tính năng mới có cập nhật cấu trúc bảng (`dbDelta`) gây lỗi:
1. Xóa bảng lỗi hoặc khôi phục snapshot database trước khi deploy.
2. Đặt lại version schema trong `wp_options`:
   ```bash
   wp option update despace_inventory_db_version '1.0.0'
   wp option update despace_rbac_version '1.0.0'
   ```

