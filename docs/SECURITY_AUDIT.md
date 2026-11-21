# DeSpace — Báo Cáo Kiểm Tra & Thiết Kế Bảo Mật (Security Audit & Architecture)

**Mã tài liệu:** DESPACE-DOC-SEC-01  
**Phiên bản:** 1.0.0 (Hoàn thiện theo Issue #35)  
**Mục tiêu:** Định nghĩa ma trận phân quyền RBAC, kiểm soát an ninh REST API, quy tắc phòng chống tấn công Injection/XSS/CSRF và cơ chế kiểm duyệt tải lên mô hình 3D GLB cho dự án DeSpace.

---

## 1. Nguyên Tắc An Toàn Thông Tin Cốt Lõi

1. **Đặc quyền tối thiểu (Principle of Least Privilege):** Mỗi vai trò (Role) chỉ được cấp đúng và đủ quyền (Capabilities) để thực hiện công việc; không cấp quyền thừa. Nhân viên (`despace_staff`) bị hạn chế hoàn toàn khỏi cấu hình hệ thống (`manage_options`, `manage_woocommerce`).
2. **Phòng thủ đa tầng (Defense in Depth):** Kiểm tra an ninh tại 3 tầng: Tầng Routing/Menu (UI/Admin Gate) -> Tầng REST Controller/AJAX (`permission_callback` & Nonce) -> Tầng Dữ liệu (Sanitize/Prepare/CRUD).
3. **Mặc định từ chối (Default Deny):** Bất kỳ truy vấn hoặc tài nguyên không được cấp phép công khai mặc định sẽ bị chặn và trả về lỗi `401 Unauthorized` hoặc `403 Forbidden`.
4. **Không nhân bản xác thực:** Tận dụng 100% hệ thống xác thực người dùng, cookie phiên làm việc và bảo mật mật khẩu chuẩn của WordPress & WooCommerce.

---

## 2. Ma Trận Phân Quyền RBAC (Role-Based Access Control)

Hệ thống DeSpace Core thiết lập 4 nhóm vai trò chính:
- **Guest:** Khách vãng lai chưa đăng nhập.
- **Customer:** Khách hàng đã đăng ký/đăng nhập tài khoản WordPress/WooCommerce.
- **despace_staff:** Nhân viên quản lý vận hành nội thất (Showroom 3D, Kho hàng, Báo cáo kinh doanh).
- **administrator:** Quản trị viên tối cao của website.

### 2.1. Bảng ma trận quyền chi tiết

| Nghiệp vụ / Thao tác | Capability WordPress | Guest | Customer | `despace_staff` | `administrator` | Ghi chú an ninh |
|---|---|:---:|:---:|:---:|:---:|---|
| **Xem trang chủ, danh mục & bộ lọc** | `None` (Public) | ✅ | ✅ | ✅ | ✅ | Không yêu cầu đăng nhập |
| **Tương tác 3D/AR Model Viewer** | `None` (Public) | ✅ | ✅ | ✅ | ✅ | Dữ liệu mô hình ở chế độ chỉ đọc |
| **Thêm giỏ hàng & Đặt hàng COD/Chuyển khoản** | `None` (WC Checkout) | ✅ | ✅ | ✅ | ✅ | Xác thực giỏ hàng qua WooCommerce |
| **Xem thông tin tài khoản & Đơn hàng của mình** | `read` | ❌ | ✅ | ✅ | ✅ | Customer chỉ truy cập được đơn của mình |
| **Tra cứu đơn hàng khách vãng lai (Guest)** | Nonce + Token/Email | ✅ | ❌ | ❌ | ❌ | Bắt buộc đối soát khớp Order ID + Billing Email |
| **Xem đơn hàng của người khác** | `edit_shop_orders` | ❌ | ❌ | ❌ | ✅ | Ngăn chặn rò rỉ dữ liệu cá nhân (IDOR) |
| **Truy cập wp-admin** | `view_admin_dashboard` | ❌ | ❌ | ✅ | ✅ | Customer bị chặn vào trang quản trị |
| **Xem Dashboard & Báo cáo kinh doanh** | `view_despace_reports` | ❌ | ❌ | ✅ | ✅ | Endpoint `/dashboard/metrics` |
| **Xem Nhật ký biến động tồn kho** | `view_despace_inventory_logs` | ❌ | ❌ | ✅ | ✅ | Endpoint `/inventory/logs` |
| **Upload, lưu & chỉnh sửa Model 3D/AR** | `manage_despace_3d` | ❌ | ❌ | ✅ | ✅ | Endpoint `POST /products/{id}/model-3d` |
| **Sửa metadata sản phẩm nội thất** | `edit_despace_products` | ❌ | ❌ | ✅ | ✅ | Quản lý thông số kỹ thuật 3D |
| **Cấu hình WooCommerce & Cài đặt hệ thống** | `manage_woocommerce`, `manage_options` | ❌ | ❌ | ❌ | ✅ | **Chỉ dành riêng cho Admin** |

### 2.2. Khai báo Capabilities trong mã nguồn (`RoleManager.php`)

```php
// Trích xuất từ includes/Security/RoleManager.php
public static function get_staff_capabilities(): array {
    return array(
        'read'                        => true,
        'view_admin_dashboard'        => true,
        'view_despace_reports'        => true,  // Cốt lõi M5
        'view_despace_inventory_logs' => true,  // Cốt lõi M4
        'manage_despace_3d'           => true,  // Cốt lõi M1
        'edit_despace_products'       => true,
        'manage_options'              => false, // Tuyệt đối cấm
        'manage_woocommerce'          => false, // Tuyệt đối cấm
    );
}
```

---

## 3. Kiểm Soát An Ninh REST API

### 3.1. Cơ chế `permission_callback`
- Tất cả các endpoint quản trị bắt buộc khai báo `permission_callback`:
  - `GET /dashboard/metrics`: `RoleManager::rest_check_reports_permission()`
  - `GET /inventory/logs`: `RoleManager::rest_check_inventory_permission()`
  - `POST /products/{id}/model-3d`: `RoleManager::rest_check_3d_permission()`
- Không sử dụng `__return_true` ngoại trừ các route đọc công khai (`/products/filter`, `GET /products/{id}/model-3d`).

### 3.2. Phòng chống CSRF bằng `X-WP-Nonce`
- Toàn bộ các yêu cầu từ giao diện quản trị hoặc client sử dụng Fetch API đều phải gửi kèm header:
  `X-WP-Nonce: <wp_rest_nonce>`
- Nonce được sinh từ phía server bằng `wp_create_nonce( 'wp_rest' )` và localized vào script.
- **Nguyên tắc phân định:** Nonce **không phải** là phương thức xác thực danh tính (Authentication). Nonce là cơ chế chống giả mạo yêu cầu chéo trang (Cross-Site Request Forgery). Quyền hạn bắt buộc phải được kiểm tra qua `current_user_can()`.

### 3.3. Xử lý phản hồi lỗi an toàn
- Khi chưa đăng nhập: Trả về HTTP `401 Unauthorized` kèm mã `despace_rest_unauthorized`.
- Khi đã đăng nhập nhưng không đủ quyền: Trả về HTTP `403 Forbidden` kèm mã `despace_rest_forbidden`.
- Không làm lộ thông tin nhạy cảm (Stack trace, Database password, Đường dẫn máy chủ tuyệt đối) trong error response.

---

## 4. Quy Tắc Validate, Sanitize, Escape & Chống Injection

### 4.1. Làm sạch dữ liệu đầu vào (Input Sanitization)
- **Chuỗi văn bản:** `sanitize_text_field( wp_unslash( $input ) )`
- **Mã định danh/Slug:** `sanitize_key( $input )`
- **Số nguyên:** `absint( $input )` hoặc `(int) $input`
- **Số thực (Giá/Số lượng tồn):** `(float) $input`
- **Ngày tháng:**
  - Kiểm tra bằng Regular Expression: `preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date )`
  - Kiểm tra tính hợp lệ của lịch Gregory bằng `checkdate( $month, $day, $year )`
  - Kiểm tra ranh giới logic: `from_date <= to_date`.

### 4.2. Thoát dữ liệu đầu ra (Output Escaping)
- **HTML Context:** `esc_html()`, `esc_html_e()`
- **Thuộc tính thẻ HTML (Attributes):** `esc_attr()`, `esc_attr_e()`
- **Đường dẫn liên kết (URLs):** `esc_url()`, `esc_url_raw()`
- **Dữ liệu xuất sang JavaScript:** `wp_json_encode()`

### 4.3. Phòng chống SQL Injection
- Tuyệt đối không ghép chuỗi biến vào câu lệnh SQL trực tiếp.
- Với bảng tự tạo `despace_inventory_logs`:
  - 100% các câu truy vấn động bắt buộc dùng `$wpdb->prepare( $sql, $params )`.
  - Không truyền trực tiếp tên bảng do người dùng chỉ định; luôn lấy từ `InventoryLogInstaller::get_table_name()`.
- Với Đơn hàng, Khách hàng, Sản phẩm:
  - Sử dụng 100% WooCommerce CRUD APIs (`wc_get_orders()`, `wc_get_product()`).
  - Không truy vấn thô vào bảng đơn hàng nhằm đảm bảo an toàn và tương thích High-Performance Order Storage (HPOS).

### 4.4. Phòng chống CSV Formula Injection (CWE-1236)
Khi nhân viên xuất báo cáo kho ra file CSV (`InventoryExportService.php`):
- Nếu nội dung ô bắt đầu bằng các ký tự nguy hiểm (`=`, `+`, `-`, `@`, `\t`, `\r`):
- Hệ thống tự động chèn thêm dấu nháy đơn `'` ở đầu chuỗi nhằm vô hiệu hóa tính năng tự động thực thi công thức hoặc macro của Microsoft Excel / Google Sheets:
  ```php
  if ( preg_match( '/^[=\+\-\@\t\r]/', $field ) ) {
      $field = "'" . $field;
  }
  ```

---

## 5. Kiểm Duyệt Tải Lên Mô Hình 3D (`.glb`)

File 3D (`.glb`) là định dạng nhị phân phức tạp, tiềm ẩn nguy cơ khai thác bộ nhớ (DoS) hoặc chèn mã độc. DeSpace áp dụng quy trình kiểm duyệt 4 lớp:

### 5.1. Bốn lớp kiểm tra tệp tin (4-Tier Validation)
```
[Tải file .glb]
       │
       ▼
[Lớp 1: Kiểm tra Capability] ──────► Không có manage_despace_3d ──► Chặn (403)
       │ (Hợp lệ)
       ▼
[Lớp 2: Kiểm tra Đuôi file] ────────► Không phải đuôi .glb ────────► Chặn (400)
       │ (Hợp lệ)
       ▼
[Lớp 3: Kiểm tra MIME Type] ────────► Không phải model/gltf-binary ─► Chặn (400)
       │ (Hợp lệ)
       ▼
[Lớp 4: Kiểm tra Magic Bytes] ──────► 4 bytes đầu != 0x46546C67 ──► Chặn (400 File giả mạo)
       │ (Hợp lệ: Magic Bytes = "glTF", Version = 2)
       ▼
[Lưu trữ an toàn vào Media Library]
```

### 5.2. Đặc tả kỹ thuật Magic Bytes của Binary glTF (.glb)
1. **Magic Header:** 4 bytes đầu tiên bắt buộc phải là `0x46546C67` (chuỗi ký tự ASCII: `glTF`).
2. **Version:** 4 bytes tiếp theo thể hiện phiên bản glTF, bắt buộc là `0x00000002` (glTF 2.0).
3. **Length:** 4 bytes kế tiếp chứa tổng dung lượng file nhị phân; phải khớp với dung lượng file thực tế ghi nhận trên máy chủ.
4. **Giới hạn kích thước:** File GLB vượt quá `20MB` bị từ chối tự động để bảo vệ tài nguyên bộ nhớ máy chủ.

---

## 6. Kết Quả Kiểm Thử An Ninh & Trạng Thái Blocker

Toàn bộ các tiêu chí an ninh đã được kiểm thử tự động qua 2 bộ test suite:
1. [test-rbac-matrix.php](file:///c:/Users/Hadin/OneDrive/Tài%20liệu/GitHub/DesSpace/tests/test-rbac-matrix.php) (30/30 test case PASS)
2. [test-dashboard-matrix.php](file:///c:/Users/Hadin/OneDrive/Tài%20liệu/GitHub/DesSpace/tests/test-dashboard-matrix.php) (20/20 test case PASS)

**Kết luận đánh giá an ninh:**
- **Lỗ hổng nghiêm trọng (Critical/High):** `0`
- **Trạng thái:** Đạt tiêu chuẩn nghiệm thu của Issue #35 và sẵn sàng cho môi trường Staging / Demo.

