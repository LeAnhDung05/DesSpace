# Chạy DeSpace cục bộ bằng Docker Desktop (Windows)

Hướng dẫn này chạy mã nguồn trong repo bằng WordPress 7.1.4, PHP 8.3 và MySQL 8.4.13. Database mẫu được nạp vào volume Docker mới ở lần khởi động đầu tiên; không dùng chung database với LocalWP.

## Yêu cầu

- Docker Desktop đã cài và đang chạy ở chế độ Linux containers.
- Repo được clone tại máy và đang ở nhánh làm việc của thành viên.
- Cổng 8090 không bị ứng dụng khác sử dụng. Nếu đang chạy LocalWP, hãy Stop site trong Local trước.

## Khởi chạy lần đầu

Mở PowerShell tại thư mục repo, ví dụ `C:\Users\Vu Cong Minh\DesSpace`, rồi chạy:

```powershell
git status -sb
Copy-Item .env.example .env
```

Mở `.env`, thay `MYSQL_PASSWORD` và `MYSQL_ROOT_PASSWORD` bằng mật khẩu chỉ dùng cho môi trường phát triển này. Không đưa `.env` lên Git. Các giá trị này là thông tin kết nối WordPress với MySQL; chúng không tự đặt lại mật khẩu tài khoản WordPress đã có trong database mẫu.

Sau khi Docker Desktop báo Engine đang chạy, thực hiện:

```powershell
docker info
docker compose up -d
docker compose ps
```

Lần đầu Docker tải image và MySQL tạo database, có thể mất vài phút. Mở `http://localhost:8090/` hoặc `http://localhost:8090/wp-admin/` khi hai dịch vụ ở trạng thái running/healthy.

## Sau khi website lên

1. Đăng nhập bằng tài khoản WordPress có trong database mẫu. Nếu chưa có thông tin đăng nhập, xin tài khoản thử nghiệm từ leader; biến `WORDPRESS_ADMIN_*` trong `.env` không ghi đè user đã được import.
2. Repo không lưu plugin WooCommerce. Tài liệu kiểm kê nguồn ghi nhận phiên bản 11.1.2; cài bản này bằng WP-CLI (image chính thức được Compose tải khi chạy lệnh):

   ```powershell
   docker compose run --rm wpcli plugin install woocommerce --version=11.1.2 --activate --path=/var/www/html
   ```

   Nếu leader xác nhận phiên bản khác, hãy dùng phiên bản nhóm thống nhất thay cho `11.1.2`.
3. Repo không lưu theme cha **Astra** (được `despace-child/style.css` khai báo `Template: astra`, bản kiểm kê `4.14.0`). Cài theme cha vào volume Docker bằng WP-CLI (không cần `--activate` vì `despace-child` đã được kích hoạt):

   ```powershell
   docker compose run --rm wpcli theme install astra --version=4.14.0 --path=/var/www/html
   ```

4. Repo không lưu plugin **YITH WooCommerce Wishlist** (bản kiểm kê nguồn `4.19.0`, dùng cho tính năng yêu thích trên catalog, trang chi tiết và `/wishlist/`) và **TranslatePress - Multilingual** (bản kiểm kê nguồn `3.3.7`, dùng cho route `/en/`). Cài đúng phiên bản từ WordPress.org bằng WP-CLI (không commit mã plugin vào Git):

   ```powershell
   docker compose run --rm wpcli plugin install yith-woocommerce-wishlist --version=4.19.0 --activate --path=/var/www/html
   docker compose run --rm wpcli plugin install translatepress-multilingual --version=3.3.7 --activate --path=/var/www/html
   ```

5. Thư mục ảnh sản phẩm và 94 mô hình 3D (`wordpress/wp-content/uploads`) đã được bind-mount trực tiếp từ repo vào container WordPress (`compose.yaml`), đồng thời PHP đã được cấu hình sẵn `memory_limit=512M`, `upload_max_filesize=64M`, `WP_ENVIRONMENT_TYPE='local'` và hộp thư thử nghiệm Mailpit tại `http://localhost:8026/`.
6. Theme, plugin DeSpace, MU plugins và `uploads` đều được bind-mount từ repo nên thay đổi mã nguồn hoặc tài nguyên 3D sẽ xuất hiện trong container ngay. Sau khi cài plugin, chạy thêm lệnh làm mới permalink:

   ```powershell
   docker compose run --rm wpcli plugin activate despace-core --path=/var/www/html
   docker compose run --rm wpcli rewrite flush --hard --path=/var/www/html
   ```

## Dừng và mở lại

```powershell
docker compose stop
docker compose start
```

Hoặc `docker compose down` để dừng và xóa container. Lệnh này giữ database và nội dung trong named volumes. **Không chạy `docker compose down -v`** khi chưa chủ động muốn xóa dữ liệu Docker.

## Nếu có lỗi

```powershell
docker compose ps
docker compose logs --tail 100 db
docker compose logs --tail 100 wordpress
```

- `port is already allocated`: dừng LocalWP hoặc ứng dụng đang dùng cổng 8090.
- `localhost refused to connect`: kiểm tra Docker Desktop/Engine và `docker compose ps`.
- Lỗi kết nối database: xem log `db`; đợi healthcheck thành `healthy` rồi kiểm tra log `wordpress`.
- Thay đổi `.env` không cập nhật một database đã khởi tạo trước đó. Không xóa volume để thử lại nếu chưa sao lưu dữ liệu.

## Ghi chú dữ liệu

File `database/despace_seed_data.sql` có các lệnh `DROP TABLE` và chỉ được mount làm script khởi tạo MySQL. MySQL image chỉ chạy script trong `/docker-entrypoint-initdb.d` khi data directory còn trống. Không nhập file này vào database Local hoặc database Docker đang có dữ liệu cần giữ.
