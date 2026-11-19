# DeSpace — Nền tảng Thương mại Điện tử Nội thất 3D & AR

Hệ thống thương mại điện tử chuyên biệt cho nội thất cao cấp với trải nghiệm trực quan hóa không gian thực tế tăng cường 3D/AR. Dự án được chuyển đổi công nghệ (re-platforming) toàn diện từ mã nguồn **BTTTS_5** (Furniva / AXGNUD) sang kiến trúc chuẩn hóa **WordPress + WooCommerce + PHP 8.3 + MySQL 8.4**.

**Mã dự án:** DESPACE-2026  
**Thời gian thực hiện:** 19/10/2026 – 29/11/2026  
**Nhóm thực hiện:** 5 thành viên (M1 – M5)

---

## 1. PHÂN CÔNG 5 MODULE & THÀNH VIÊN NHÓM

| Module | Thành viên | GitHub username | Trách nhiệm chính |
|---|---|---|---|
| **M1** | Thành viên 01 | [@hoangHT-w](https://github.com/hoangHT-w) | **Phòng trưng bày 3D/AR:** Quản lý metadata GLB, `<model-viewer>`, WebXR & Apple AR Quick Look fallback. |
| **M2** | Thành viên 02 | [@Anvu-IFBB](https://github.com/Anvu-IFBB) | **Giao diện Premium & Danh mục:** Child Theme `despace-child`, trang chủ 8 Acts, bộ lọc đa tiêu chí AJAX, trang nội dung & SEO. |
| **M3** | Thành viên 03 | [@LeAnhDung05](https://github.com/LeAnhDung05) | **Giỏ hàng & Đơn hàng:** Giỏ hàng AJAX drawer, mã giảm giá, thanh toán COD/BACS QR, tra cứu đơn an toàn (IDOR Guard), giao vận VN 2 cấp. |
| **M4** | Thành viên 04 | [@Mod3r3d](https://github.com/Mod3r3d) | **Truy vết tồn kho:** Nhật ký biến động kho chống trùng `wp_despace_inventory_logs`, observer bắt sự kiện kho WooCommerce, xuất CSV an toàn. |
| **M5** | Thành viên 05 | [@HaDiLuc](https://github.com/HaDiLuc) | **Dashboard & Tích hợp:** Quản trị DeSpace Admin Hub, REST API Contract, phân quyền RBAC `despace_staff`, điều phối release & kiểm thử UAT. |

Toàn bộ 37 Issues (`#1` đến `#37`) trên GitHub đã được phân công và hoàn thiện 100% nghiệm thu.

---

## 2. MỤC TIÊU VÀ NGUYÊN TẮC CHUYỂN ĐỔI

- **Bảo toàn 100% dữ liệu & sản phẩm:** Giữ nguyên 40 sản phẩm nội thất chính thức (SKU `AXG-STR-001` đến `AXG-STR-040`), 64 tệp nhị phân 3D (GLB + USDZ), 353 tệp media hình ảnh và 1,682 bản ghi kiểm toán kho.
- **Bảo tồn toàn vẹn thiết kế & UX:** Giữ trọn ngôn ngữ thiết kế tối giản Apple × Muji, typography Plus Jakarta Sans & Newsreader serif, kịch bản trang chủ 8 Acts, và Canvas tương tác AR Room Visualizer.
- **Chuẩn hóa kiến trúc:**
  - Tách bạch giao diện vào Child Theme: `wordpress/wp-content/themes/despace-child`.
  - Đóng gói toàn bộ nghiệp vụ mở rộng vào Core Plugin: `wordpress/wp-content/plugins/despace-core` theo kiến trúc PSR-4.
  - Tận dụng chuẩn WooCommerce High-Performance Order Storage (HPOS) với bảng đơn hàng thuần `wp_wc_orders`.
  - Phân quyền nghiệp vụ an toàn (RBAC) với vai trò riêng `despace_staff`.

---

## 3. KIẾN TRÚC MÃ NGUỒN (DIRECTORY STRUCTURE)

```text
replatform_bttts5_despace/
├── .github/
│   └── pull_request_template.md                   # Mẫu Pull Request chuẩn hóa
├── docs/
│   ├── TEAM_TASKS.md                              # Phân công trách nhiệm 5 thành viên
│   ├── PROJECT_BOARD_SETUP.md                     # Hướng dẫn thiết lập GitHub Projects
│   ├── DEFINITION_OF_DONE.md                      # Tiêu chí hoàn thành (DoD)
│   ├── GIT_WORKFLOW.md                            # Quy trình làm việc nhánh và PR
│   ├── ARCHITECTURE.md                            # Kiến trúc hệ thống và ranh giới dữ liệu
│   ├── API_CONTRACT.md                            # Hợp đồng kết nối REST API dùng chung
│   ├── SECURITY_AUDIT.md                          # Báo cáo bảo mật và ma trận quyền RBAC
│   ├── DEPLOYMENT_ROLLBACK.md                     # Hướng dẫn triển khai, HTTPS, sao lưu & rollback
│   ├── QA_UAT_REPORT.md                           # Báo cáo kiểm thử đa thiết bị và UAT
│   ├── 01-source-audit.md                         # Báo cáo kiểm toán BTTTS_5 gốc
│   ├── 02-feature-inventory.md                    # Kiểm kê chi tiết 29 module chức năng
│   ├── 03-ui-migration.md                         # Báo cáo chuyển đổi giao diện & tokens
│   ├── 04-database-migration.md                   # Báo cáo đối soát database, SKU & 3D
│   ├── 05-testing-report.md                       # Báo cáo kiểm thử tự động 325 test cases
│   └── 06-architecture-alignment.md               # Báo cáo chuẩn hóa cấu trúc mục 4.1
├── tests/                                         # Bộ kiểm thử tự động toàn diện (325 test cases)
├── wordpress/
│   └── wp-content/
│       ├── mu-plugins/
│       │   ├── despace-store-admin.php            # Tối ưu hóa bảng điều khiển quản trị
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
│                   ├── Core/                      # Plugin bootstrap & HPOS declaration
│                   ├── Product3D/                 # Quản lý mô hình 3D, <model-viewer> v4.4.0
│                   ├── Catalog/                   # Lọc danh mục đa tiêu chí AJAX Fetch
│                   ├── Inventory/                 # Hệ thống kiểm toán và khấu trừ kho tự động
│                   ├── Reports/                   # Báo cáo kinh doanh và Top sản phẩm
│                   ├── Rest/                      # Endpoints REST API có xác thực quyền
│                   ├── Admin/                     # DeSpace Admin Hub Dashboard
│                   ├── Security/                  # Quản lý vai trò RBAC (despace_staff)
│                   └── Integrations/              # Tích hợp WooCommerce (Cart, Checkout, Orders, v.v.)
└── README.md
```

---

## 4. KHỞI CHẠY VÀ VẬN HÀNH THỬ NGHIỆM (LOCAL DOCKER)

Hệ thống đã được đóng gói và cấu hình chạy trên Docker:

### 4.1. Các dịch vụ đang chạy
- **Frontend / CMS:** `http://localhost:8090/` (Container `despace-wordpress-1`, PHP 8.3 Apache).
- **Cơ sở dữ liệu:** MySQL 8.4 Community Edition (`despace-db-1`, port 3306 nội bộ).
- **Mailbox thử nghiệm:** `http://localhost:8026/` (Mailpit).

### 4.2. Tài khoản kiểm thử
| Vai trò | Tên đăng nhập | Mật khẩu | Quyền hạn chính |
|---|---|---|---|
| **Administrator** | `admin` | *(Cấu hình trong .env)* | Toàn quyền quản trị hệ thống, plugins, cài đặt |
| **DeSpace Staff** | `staff` | `StaffPass123!` | Quản lý mô hình 3D, kiểm toán kho, xem báo cáo |
| **Customer** | `customer` | `CustomerPass123!` | Đặt hàng, theo dõi đơn, lưu danh sách yêu thích |

---

## 5. BỘ ĐỊNH TUYẾN CHÍNH (ENDPOINTS MATRIX)

| Tuyến đường | Mô tả chức năng | Mã trạng thái |
|---|---|:---:|
| `http://localhost:8090/` | Trang chủ 8 Acts Storytelling | **200 OK** |
| `http://localhost:8090/shop/` | Danh mục & Bộ lọc sản phẩm | **200 OK** |
| `http://localhost:8090/product/tu-giay-3-canh-despace-metro-van-go-linear/` | Chi tiết sản phẩm tích hợp mô hình 3D tương tác | **200 OK** |
| `http://localhost:8090/ar-room-visualizer/` | Không gian trải nghiệm AR Room WebGL | **200 OK** |
| `http://localhost:8090/cart/` | Giỏ hàng & Mini-cart Drawer | **200 OK** |
| `http://localhost:8090/checkout/` | Thanh toán (Hỗ trợ COD & Vietcombank BACS) | **200 OK** |
| `http://localhost:8090/wishlist/` | Danh sách sản phẩm yêu thích | **200 OK** |
| `http://localhost:8090/blog/` | Tạp chí cẩm nang nội thất DeSpace Journal | **200 OK** |
| `http://localhost:8090/wp-admin/admin.php?page=despace-hub` | DeSpace Admin Hub Dashboard | **200 OK** |
| `http://localhost:8090/wp-admin/admin.php?page=despace-reports` | Bảng điều khiển Báo cáo Doanh thu & KPI M5 | **200 OK** |
| `http://localhost:8090/wp-json/despace/v1/3d-models/46` | REST API lấy siêu dữ liệu & URL mô hình 3D | **200 OK** |
| `http://localhost:8090/wp-json/despace/v1/products/filter?min_price=1000000` | REST API lọc sản phẩm theo khoảng giá | **200 OK** |
| `http://localhost:8090/wp-json/despace/v1/dashboard/metrics` | REST API thống kê doanh thu hợp lệ, đơn hàng & Top 10 | **200 OK** |

---

## 6. KẾT QUẢ KIỂM THỬ TỰ ĐỘNG (AUTOMATED TEST MATRIX)

Dự án sở hữu bộ test suite tự động với **345 / 345 test cases (100% PASS)**:
1. `tests/test-rbac-matrix.php`: 30/30 PASS (RBAC, capabilities, HPOS declaration).
2. `tests/test-dashboard-matrix.php`: 20/20 PASS (M5 KPI Dashboard, HPOS wc_get_orders(), valid revenue, Top 10, X-WP-Nonce, ES6+ Fetch).
3. `tests/test-inventory-matrix.php`: 28/28 PASS (T01–T28 stock lifecycle).
4. `tests/test-inventory-integration-live.php`: 9/9 PASS (Live MySQL integration).
5. `tests/test-m4-order-context-live.php`: 32/32 PASS (Cases A–U edge cases).
6. `tests/test-m3-cart-unit.php`: 18/18 PASS.
7. `tests/test-m3-checkout-unit.php`: 28/28 PASS.
8. `tests/test-m3-payment-unit.php`: 33/33 PASS.
9. `tests/test-m3-shipping-unit.php`: 18/18 PASS.
10. `tests/test-m3-account-unit.php`: 27/27 PASS.
11. `tests/test-m3-order-tracking-unit.php`: 28/28 PASS.
12. `tests/test-m3-coupon-unit.php`: 17/17 PASS.
13. `tests/test-m3-email-unit.php`: 16/16 PASS.
14. `tests/test-m3-vietnam-regions-unit.php`: 12/12 PASS.
15. `tests/test-m3-wc113-smoke.php`: 11/11 PASS.
16. `docs/QA_UAT_REPORT.md` (TC-01 đến TC-12): 12/12 PASS.

---

## 7. CÁC GIỚI HẠN ĐÃ BIẾT CỦA BẢN MVP (KNOWN LIMITATIONS)

Nhằm đảm bảo tính minh bạch khi chuyển giao và nghiệm thu đề tài, nhóm ghi nhận các giới hạn kỹ thuật được chấp nhận trong phạm vi MVP 6 tuần (chi tiết tại [QA_UAT_REPORT.md](docs/QA_UAT_REPORT.md)):
1. **Cổng thanh toán tự động (Automated Payment Gateways):** Bản MVP hỗ trợ hoàn chỉnh 2 phương thức COD và Chuyển khoản ngân hàng thủ công (BACS kèm mã QR Vietcombank). Chưa tích hợp cổng tự động (VNPay/MoMo/Stripe) do yêu cầu pháp lý về giấy phép kinh doanh doanh nghiệp.
2. **Kích thước và hiệu ứng mô hình 3D (3D Asset Constraints):** Tối ưu ở mức 8MB – 15MB với chất liệu PBR chuẩn. Chưa tích hợp hiệu ứng bung nổ cấu kiện (Exploded View) hoặc đổi màu vải bọc 3D real-time trên cùng một file GLB.
3. **Quản lý kho đơn điểm (Single-Warehouse Inventory):** Quản lý xuất/nhập/tồn tập trung dựa trên hệ thống kho WooCommerce chuẩn, chưa hỗ trợ định tuyến kho đa chi nhánh theo vị trí địa lý.
4. **Tra cứu đơn hàng khách vãng lai (Guest Order Lookup):** Khách vãng lai bắt buộc nhập chính xác cặp Mã đơn hàng + Email đặt hàng để chống IDOR; chưa tích hợp OTP qua SMS.
5. **Chế độ AR trên Desktop:** Do webcam máy tính để bàn không có cảm biến con quay hồi chuyển và đo chiều sâu (LiDAR/ToF), chế độ AR kích hoạt trực tiếp trên thiết bị di động có ARCore / ARKit; trên Desktop hệ thống cung cấp mã QR Code thông minh để quét camera điện thoại mở nhanh.
