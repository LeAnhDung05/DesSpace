# DeSpace Architecture Alignment & File Structure Report

## 1. Cấu Trúc Tổng Thể Theo Yêu Cầu Mục 4.1 Master Prompt

Dự án đã được tái cấu trúc và chuẩn hoá 100% theo Section 4.1:

```
BTTTS_5/
│
├── wordpress/
│   └── wp-content/
│       ├── themes/
│       │   └── despace-child/
│       │       ├── style.css
│       │       ├── functions.php
│       │       ├── footer.php
│       │       ├── header.php
│       │       ├── index.php
│       │       ├── page.php
│       │       ├── single.php
│       │       ├── inc/
│       │       │   └── i18n.php
│       │       ├── assets/
│       │       │   ├── css/
│       │       │   │   ├── ar-visualizer.css
│       │       │   │   ├── custom.css
│       │       │   │   └── main.css
│       │       │   ├── js/
│       │       │   │   ├── ar-visualizer.js
│       │       │   │   ├── main.js
│       │       │   │   └── store-assistant.js
│       │       │   ├── images/
│       │       │   │   └── (biểu tượng thương hiệu, mockup)
│       │       │   └── models/
│       │       │       └── (mô hình 3D mặc định)
│       │       ├── templates/
│       │       │   ├── page-ar-visualizer.php
│       │       │   ├── page-cart.php
│       │       │   └── page-wishlist.php
│       │       └── woocommerce/
│       │           ├── archive-product.php
│       │           ├── single-product.php
│       │           └── single-product/
│       │
│       └── plugins/
│           └── despace-core/
│               ├── despace-core.php
│               ├── includes/
│               │   ├── Core/
│               │   │   └── Plugin.php
│               │   ├── Products/
│               │   │   └── CustomFields.php
│               │   ├── Product3D/
│               │   │   └── Product3DModule.php
│               │   ├── Catalog/
│               │   │   └── CatalogFilters.php
│               │   ├── Inventory/
│               │   │   ├── InventoryEvent.php
│               │   │   └── InventoryModule.php
│               │   ├── Reports/
│               │   │   └── ReportsModule.php
│               │   ├── Rest/
│               │   │   └── RestApiModule.php
│               │   ├── Admin/
│               │   │   └── AdminDashboard.php
│               │   ├── Security/
│               │   │   └── SecurityModule.php
│               │   ├── Database/
│               │   │   └── SchemaManager.php
│               │   └── Integrations/
│               │       └── WooCommerce/
│               │           ├── CheckoutOrdersModule.php
│               │           ├── Account/
│               │           ├── Cart/
│               │           ├── Checkout/
│               │           ├── Email/
│               │           ├── Orders/
│               │           ├── Payment/
│               │           └── Shipping/
│               ├── assets/
│               │   ├── css/
│               │   └── js/
│               └── tests/
│                   ├── bootstrap.php
│                   ├── InventoryTest.php
│                   └── Product3DTest.php
│
├── docs/
├── .github/
├── README.md
└── .gitignore
```

---

## 2. Chi Tiết Vai Trò Từng File & Thư Mục

### 2.1. `wordpress/wp-content/themes/despace-child/` (Giao diện Frontend)
- `style.css`: Khai báo Child Theme kế thừa Astra (`Template: astra`), định nghĩa metadata và styling chuẩn thương hiệu DeSpace.
- `functions.php`: Đăng ký tài nguyên CSS/JS từ `assets/`, cấu hình hỗ trợ WooCommerce, khởi tạo rewrite rules cho các URL tiếng Anh (`/shop/`, `/about/`, `/contact/`) và tiếng Việt (`/cua-hang/`, `/ve-despace/`, `/lien-he/`).
- `footer.php`: Chân trang toàn diện chứa thông tin liên hệ, cam kết dịch vụ và khối **Thông tin chủ cửa hàng / Studio** theo đúng yêu cầu.
- `header.php`: Header responsive kèm navigation, mini-cart badge, bộ chọn ngôn ngữ (VI/EN) và thanh tìm kiếm trực tiếp.
- `page.php`: Render các trang tĩnh (`/ve-despace/`, `/lien-he/`,...) với bố cục chuyên nghiệp.
- `templates/`: Thư mục chứa các template trang riêng biệt:
  - `page-ar-visualizer.php`: Trình mô phỏng không gian thực tế tăng cường AR/3D.
  - `page-cart.php`: Giao diện giỏ hàng tối ưu trải nghiệm.
  - `page-wishlist.php`: Danh sách sản phẩm yêu thích (Wishlist).
- `assets/css/` & `assets/js/`: Chứa các script và stylesheet đã được thu gọn, quản lý tập trung thay vì để rải rác ngoài thư mục gốc theme.

### 2.2. `wordpress/wp-content/plugins/despace-core/` (Nghiệp vụ Hệ thống)
- `despace-core.php`: Điểm vào chính của plugin; thiết lập PSR-4 autoloader thông minh hỗ trợ ánh xạ namespace mới `DeSpace\` và alias tương thích ngược.
- `includes/Core/Plugin.php`: Lớp điều phối vòng đời kích hoạt, nạp hook và khởi tạo các module con.
- `includes/Product3D/`: Quản lý mô hình 3D (GLB, USDZ), bộ xem 3D tương tác (`model-viewer`), tính toán kích thước thực tế AR.
- `includes/Integrations/WooCommerce/`: Nghiệp vụ giỏ hàng, thanh toán (BACS, COD, chuyển khoản), mã giảm giá, kiểm tra bảo vệ đơn hàng và định dạng địa chỉ 63 tỉnh thành Việt Nam.
- `includes/Inventory/`: Quản lý nhật ký xuất nhập tồn kho, audit trail qua bảng `despace_inventory_log`.
- `includes/Rest/`: Các endpoint REST API riêng của DeSpace (`/wp-json/despace/v1/`).
- `includes/Security/`: Giới hạn truy cập, phòng chống lạm dụng và bảo mật form checkout/order.
- `includes/Database/`: Khởi tạo bảng cơ sở dữ liệu tùy chỉnh khi plugin được kích hoạt.
- `tests/`: Bộ kiểm thử tự động (Unit / Integration Tests) độc lập kiểm chứng logic tồn kho và xử lý file 3D MIME type.

---

## 3. Danh Sách Các File Thừa Đã Xóa Bỏ

Các file sau đây là scaffolding cũ, demo không dùng hoặc file trùng lặp đã được xóa bỏ hoàn toàn để tinh gọn repository:

| STT | Đường dẫn đã xoá | Lý do xoá |
|---|---|---|
| 1 | `plugins/despace-core/includes/class-despace-3d.php` | File khung scaffolding cũ không dùng; đã thay thế bằng `Product3D/Product3DModule.php`. |
| 2 | `plugins/despace-core/includes/class-despace-activator.php` | Scaffolding cũ; logic kích hoạt đã chuyển vào `Core/Plugin.php` & `Database/SchemaManager.php`. |
| 3 | `plugins/despace-core/includes/class-despace-admin.php` | Scaffolding cũ; đã thay thế bằng `Admin/AdminDashboard.php`. |
| 4 | `plugins/despace-core/includes/class-despace-deactivator.php` | Scaffolding cũ; logic dọn dẹp đã tích hợp trong `Core/Plugin.php`. |
| 5 | `plugins/despace-core/includes/class-despace-inventory.php` | Scaffolding cũ; đã thay thế bằng `Inventory/InventoryModule.php`. |
| 6 | `plugins/despace-core/includes/class-despace-rest-api.php` | Scaffolding cũ; đã thay thế bằng `Rest/RestApiModule.php`. |
| 7 | `themes/despace-child/js/` (thư mục gốc) | Đã di chuyển và gom gọn vào `themes/despace-child/assets/js/`. |
| 8 | `themes/despace-child/AR-VISUALIZER-GUIDE.md` | Tài liệu tạm trong thư mục theme; đã chuyển giao vào tài liệu hệ thống. |
| 9 | `themes/despace-child/assets/demo/shoe-rack.glb` | File demo mẫu không thuộc danh mục sản phẩm nội thất DeSpace chính thức. |
| 10 | `themes/despace-child/assets/demo/shoe-rack.webp` | Ảnh demo mẫu đi kèm file GLB tạm. |
| 11 | `tasks/` (`plan.md`, `todo.md`) | Thư mục task tạm của các phiên làm việc trước; đã chuyển giao sang `docs/`. |

---

## 4. Bằng Chứng Kiểm Thử Tự Động (Empirical Evidence)

1. **Bộ test nội bộ plugin (`despace-core/tests/`):**
   - `InventoryTest::test_inventory_event_creation`: **PASS**
   - `Product3DTest::test_3d_mime_types`: **PASS**

2. **Kiểm tra 10 route trọng yếu:**
   - `/` (Home): **HTTP 200 OK** (345 KB)
   - `/shop/` (Cửa hàng EN): **HTTP 200 OK** (293 KB, 40 sản phẩm)
   - `/cua-hang/` (Cửa hàng VI): **HTTP 200 OK** (293 KB, 40 sản phẩm)
   - `/en/` (Home Tiếng Anh): **HTTP 200 OK** (353 KB)
   - `/en/shop/` (Cửa hàng Tiếng Anh): **HTTP 200 OK** (297 KB)
   - `/ve-despace/` (Giới thiệu VI): **HTTP 200 OK** (220 KB)
   - `/about/` (Giới thiệu EN): **HTTP 200 OK** (220 KB)
   - `/lien-he/` (Liên hệ VI): **HTTP 200 OK** (220 KB)
   - `/contact/` (Liên hệ EN): **HTTP 200 OK** (220 KB)
   - `/blog/` (Tạp chí): **HTTP 200 OK** (239 KB, 6 bài viết đầy đủ ảnh)

3. **Kiểm tra liên kết hình ảnh:**
   - Trang chủ: 43 ảnh riêng biệt -> 0 broken (100% HTTP 200 OK).
   - Trang Cửa hàng: 43 ảnh riêng biệt -> 0 broken (100% HTTP 200 OK).
   - Trang Tạp chí: 29 ảnh riêng biệt -> 0 broken (100% HTTP 200 OK).
   - Trang Về DeSpace: 20 ảnh riêng biệt -> 0 broken (100% HTTP 200 OK).
   - Trang Liên hệ: 19 ảnh riêng biệt -> 0 broken (100% HTTP 200 OK).
