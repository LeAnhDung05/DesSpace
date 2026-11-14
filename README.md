# DeSpace — Nền tảng Thương mại Điện tử Nội thất 3D & AR

Hệ thống thương mại điện tử chuyên biệt cho nội thất cao cấp với trải nghiệm trực quan hóa không gian thực tế tăng cường 3D/AR. Dự án được chuyển đổi công nghệ (re-platforming) toàn diện từ mã nguồn **BTTTS_5** (Furniva / AXGNUD) sang kiến trúc chuẩn hóa **WordPress + WooCommerce + PHP 8.3 + MySQL 8.4**.

---

## 1. MỤC TIÊU VÀ NGUYÊN TẮC CHUYỂN ĐỔI

- **Bảo toàn 100% dữ liệu & sản phẩm:** Giữ nguyên 40 sản phẩm nội thất chính thức (SKU `AXG-STR-001` đến `AXG-STR-040`), 64 tệp nhị phân 3D (GLB + USDZ), 353 tệp media hình ảnh và 1,682 bản ghi kiểm toán kho.
- **Bảo tồn toàn vẹn thiết kế & UX:** Giữ trọn ngôn ngữ thiết kế tối giản Apple × Muji, typography Plus Jakarta Sans & Newsreader serif, kịch bản trang chủ 8 Acts, và Canvas tương tác AR Room Visualizer.
- **Chuẩn hóa kiến trúc:**
  - Tách bạch giao diện vào Child Theme: `wordpress/wp-content/themes/despace-child`.
  - Đóng gói toàn bộ nghiệp vụ mở rộng vào Core Plugin: `wordpress/wp-content/plugins/despace-core` theo kiến trúc PSR-4.
  - Tận dụng chuẩn WooCommerce High-Performance Order Storage (HPOS) với bảng đơn hàng thuần `wp_wc_orders`.
  - Phân quyền nghiệp vụ an toàn (RBAC) với vai trò riêng `despace_staff`.

---

## 2. KIẾN TRÚC MÃ NGUỒN (DIRECTORY STRUCTURE)

```text
replatform_bttts5_despace/
├── backup/
│   └── bttts5_live_backup_20261114.sql            # Bản sao lưu CSDL nguồn gốc
├── docs/
│   ├── 01-source-audit.md                         # Báo cáo kiểm toán toàn bộ BTTTS_5
│   ├── 02-feature-inventory.md                    # Kiểm kê chi tiết 29 tính năng và module
│   ├── 03-ui-migration.md                         # Báo cáo chuyển đổi giao diện và CSS Tokens
│   ├── 04-database-migration.md                   # Báo cáo đối soát CSDL, SKU và tệp 3D
│   └── 05-testing-report.md                       # Báo cáo kiểm thử tự động và nghiệm thu
├── wordpress/
│   └── wp-content/
│       ├── mu-plugins/
│       │   ├── furniva-store-admin.php            # Tối ưu hóa bảng điều khiển quản trị
│       │   ├── dynamic-tunnel.php                 # Hỗ trợ tự động nhận diện host tunnel/proxy
│       │   └── despace-smtp.php                   # Định tuyến gửi email an toàn qua Mailpit
│       ├── themes/
│       │   └── despace-child/                     # Giao diện chính của DeSpace
│       │       ├── style.css                      # CSS Design system (9,977 dòng, --despace-* tokens)
│       │       ├── front-page.php                 # Trang chủ kịch bản 8 Acts
│       │       ├── page-ar-visualizer.php         # Trực quan hóa không gian thực tế ảo 3D
│       │       ├── page-wishlist.php              # Danh sách sản phẩm yêu thích
│       │       ├── page-cart.php                  # Giỏ hàng tùy biến cao cấp
│       │       ├── woocommerce/                   # Template override WooCommerce chuẩn
│       │       └── inc/                           # Helpers, i18n, 3D AJAX, AR Integration
│       └── plugins/
│           └── despace-core/                      # Core Plugin nghiệp vụ riêng DeSpace
│               ├── despace-core.php               # Điểm khởi chạy & PSR-4 autoloader
│               └── includes/
│                   ├── Product-3D/                # Quản lý mô hình 3D, <model-viewer> v4.4.0
│                   ├── Inventory/                 # Hệ thống kiểm toán và khấu trừ kho tự động
│                   ├── Checkout-Orders/           # Xử lý giỏ hàng, địa chỉ 63 tỉnh VN, BACS/COD
│                   ├── REST-API/                  # Endpoints REST: 3D models, lọc sản phẩm
│                   └── Security/                  # Quản lý vai trò RBAC (despace_staff)
└── README.md
```

---

## 3. KHỞI CHẠY VÀ VẬN HÀNH THỬ NGHIỆM (LOCAL DOCKER)

Hệ thống đã được đóng gói và cấu hình chạy trên Docker:

### 3.1. Các dịch vụ đang chạy
- **Frontend / CMS:** `http://localhost:8090/` (Container `despace-wordpress-1`, PHP 8.3 Apache).
- **Cơ sở dữ liệu:** MySQL 8.4 Community Edition (`despace-db-1`, port 3306 nội bộ).
- **Mailbox thử nghiệm:** `http://localhost:8026/` (Mailpit).

### 3.2. Tài khoản kiểm thử
| Vai trò | Tên đăng nhập | Mật khẩu | Quyền hạn chính |
|---|---|---|---|
| **Administrator** | `admin` | *(Cấu hình trong .env)* | Toàn quyền quản trị hệ thống, plugins, cài đặt |
| **DeSpace Staff** | `staff` | `StaffPass123!` | Quản lý mô hình 3D, kiểm toán kho, xem báo cáo |
| **Customer** | `customer` | `CustomerPass123!` | Đặt hàng, theo dõi đơn, lưu danh sách yêu thích |

---

## 4. BỘ ĐỊNH TUYẾN CHÍNH (ENDPOINTS MATRIX)

| Tuyến đường | Mô tả chức năng | Mã trạng thái |
|---|---|:---:|
| `http://localhost:8090/` | Trang chủ 8 Acts Storytelling | **200 OK** |
| `http://localhost:8090/shop/` | Danh mục & Bộ lọc sản phẩm | **200 OK** |
| `http://localhost:8090/product/tu-giay-3-canh-axgnud-metro-van-go-linear/` | Chi tiết sản phẩm tích hợp mô hình 3D tương tác | **200 OK** |
| `http://localhost:8090/ar-room-visualizer/` | Không gian trải nghiệm AR Room WebGL | **200 OK** |
| `http://localhost:8090/cart/` | Giỏ hàng & Mini-cart Drawer | **200 OK** |
| `http://localhost:8090/checkout/` | Thanh toán (Hỗ trợ COD & Vietcombank BACS) | **200 OK** |
| `http://localhost:8090/wishlist/` | Danh sách sản phẩm yêu thích | **200 OK** |
| `http://localhost:8090/blog/` | Tạp chí cẩm nang nội thất DeSpace Journal | **200 OK** |
| `http://localhost:8090/wp-json/despace/v1/3d-models/46` | REST API lấy siêu dữ liệu & URL mô hình 3D | **200 OK** |
| `http://localhost:8090/wp-json/despace/v1/products/filter?min_price=1000000` | REST API lọc sản phẩm theo khoảng giá | **200 OK** |

---

## 5. TÀI LIỆU CHUYỂN ĐỔI KỸ THUẬT (MIGRATION DOCUMENTATION)

1. [docs/01-source-audit.md](docs/01-source-audit.md) — Báo cáo kiểm toán hiện trạng BTTTS_5.
2. [docs/02-feature-inventory.md](docs/02-feature-inventory.md) — Danh mục kiểm kê 29 chức năng và phương án chuyển đổi.
3. [docs/03-ui-migration.md](docs/03-ui-migration.md) — Báo cáo bảo tồn giao diện, responsive và hệ thống tokens.
4. [docs/04-database-migration.md](docs/04-database-migration.md) — Báo cáo đối soát cơ sở dữ liệu, SKU và tài nguyên 3D.
5. [docs/05-testing-report.md](docs/05-testing-report.md) — Báo cáo kết quả kiểm thử tự động và biên bản nghiệm thu.
