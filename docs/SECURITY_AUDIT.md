# Báo Cáo Kiểm Toán Bảo Mật & Phân Quyền (Security Audit & RBAC Report)

**Dự án:** DeSpace — Nền Tảng Thương Mại Điện Tử Nội Thất 3D & WebXR AR  
**Phiên bản:** 2.5.0  
**Trạng thái:** Đã kiểm toán & Khắc phục 100% (PASS)

---

## 1. Mô Hình Phân Quyền (RBAC — Role-Based Access Control)

Hệ thống phân quyền của DeSpace được quản lý tập trung qua `DeSpace\Security\RoleManager` (`wordpress/wp-content/plugins/despace-core/includes/Security/RoleManager.php`) và kiểm thử tự động qua 30 kịch bản trong `tests/test-rbac-matrix.php`.

### 1.1. Danh sách Roles & Custom Capabilities
| Role | Capabilities chính | Phạm vi quyền hạn |
|---|---|---|
| `administrator` | Toàn bộ quyền WP + `view_despace_reports`, `view_despace_inventory_logs`, `manage_despace_3d`, `edit_despace_products` | Quản trị toàn diện hệ thống, cấu hình cổng thanh toán, API Key và giải nén tài nguyên. |
| `despace_staff` | `read`, `view_despace_reports`, `view_despace_inventory_logs`, `manage_despace_3d`, `edit_despace_products` | Vận hành sản phẩm, quản lý mô hình 3D/AR, đối soát tồn kho và xem báo cáo vận hành. Nghiêm cấm các quyền `manage_options`, `install_plugins`, `delete_users`. |
| `customer` / `guest` | Quyền mua hàng & tra cứu đơn hàng tiêu chuẩn | Xem sản phẩm 3D/AR, đặt hàng, thanh toán BACS/COD và tra cứu đơn hàng của chính mình. |

---

## 2. Các Hạng Mục Bảo Mật Đã Được Kiểm Toán & Gia Cố

### 2.1. Bảo vệ Chống Tải File Độc Hại & Zip Slip (CWE-22 / CWE-434)
- **Giải nén tài nguyên (`despace_safe_extract_zip_archive`):** Chặn tuyệt đối các entry chứa `../` (Path Traversal / Zip Slip), file ẩn và chỉ cho phép trích xuất các định dạng media/3D trong danh sách trắng (`jpg`, `jpeg`, `png`, `webp`, `gif`, `svg`, `glb`, `gltf`, `usdz`, `json`, `csv`, `txt`, `css`). Nghiêm cấm giải nén `.php`, `.phtml`, `.phar`, `.sh`.
- **Kiểm tra Magic Header của tệp 3D (`.glb`):** Trong `Product3DModule::correct_filetype_ext()` và `RestApiModule::desktop_upload_completed_model()`, hệ thống kiểm tra cả quyền người dùng (`manage_despace_3d` / `manage_woocommerce`) lẫn 4 byte định danh nhị phân đầu tiên (`glTF` / `0x46546C67`) theo chuẩn Khronos glTF 2.0 trước khi lưu tệp.

### 2.2. Chống Giả Mạo Yêu Cầu Liên Trang (CSRF) & Kiểm Soát Truy Cập AJAX / REST
- Toàn bộ các AJAX endpoints thay đổi dữ liệu giỏ hàng, danh sách yêu thích, bố cục phòng AR (`ar_save_layout`, `ar_delete_layout`), đồng bộ 3D (`despace_dispatch_desktop_3d`, `despace_check_job_status`) và tạo mô hình AI (`axgnud_generate_3d_model`) đều bắt buộc xác thực WordPress Nonce và kiểm tra quyền hạn (`current_user_can()`).
- Các REST API nhạy cảm (`/wp-json/despace/v1/inventory/logs`, `/reports/*`, `/products/{id}/3d`, `/desktop-3d/*`) đều có `permission_callback` chặt chẽ, trả về mã `401 Unauthorized` cho khách vãng lai và `403 Forbidden` cho tài khoản không đủ quyền.

### 2.3. Chống Host Header Injection & Bảo Vệ Dữ Liệu Serialized
- `mu-plugins/dynamic-tunnel.php`: Chỉ chấp nhận `$_SERVER['HTTP_HOST']` khớp với danh sách trắng (`localhost`, `127.0.0.1`, hoặc tên miền tunnel hợp lệ `*.trycloudflare.com`, `*.ngrok-free.app`), ngăn chặn tấn công Password Reset Poisoning và XSS qua HTTP Host header.
- Khi đồng bộ hoặc cập nhật tên miền trong DB (`despace_handle_hub_repair_urls`), hệ thống loại trừ các chuỗi PHP Serialized (`NOT REGEXP '^(a|O|s):[0-9]+:'`) khỏi lệnh SQL `REPLACE()` nhằm bảo toàn cấu trúc dữ liệu `wp_postmeta`.

### 2.4. Chống IDOR & Rò Rỉ Dữ Liệu Đơn Hàng (M3 Order Access Guard)
- Khách hàng chỉ xem được đơn hàng thuộc `customer_id` của chính mình; không tự động gán đơn hàng của khách vãng lai (Guest) chỉ dựa vào email trùng khớp.
- Tra cứu đơn hàng của khách vãng lai (`GuestOrderTracker`) yêu cầu khớp đồng thời Mã đơn hàng và Email/Số điện thoại đặt hàng, đồng thời ẩn hoàn toàn `order_key` và ghi chú nội bộ (`private_order_notes`) khỏi dữ liệu trả về.
