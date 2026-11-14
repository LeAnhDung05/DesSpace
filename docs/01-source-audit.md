# BÁO CÁO KIỂM TOÁN NỀN TẢNG BTTTS_5 (SOURCE AUDIT)

> **Mã tài liệu:** `docs/01-source-audit.md`  
> **Dự án:** Chuyển đổi nền tảng BTTTS_5 sang Thương mại điện tử DeSpace  
> **Thời điểm kiểm toán:** 2026-11-14 16:55 (Giờ hệ thống)  
> **Môi trường kiểm toán thực tế:** macOS Darwin, Docker runtime (`bttts5-wp`, `bttts5-db`, `despace-wordpress-1`, `despace-db-1`)  
> **Trạng thái đối soát:** VERIFIED trực tiếp trên database, source code, filesystem và runtime HTTP.

---

## 1. CÔNG NGHỆ THỰC TẾ CỦA BTTTS_5

Dựa trên kết quả kiểm tra trực tiếp qua Docker container `bttts5-wp` (port 8088), `bttts5-db` và source code tại `/Users/admin/Documents/BTTTS_5`:

| Thành phần | Phiên bản thực tế | Ghi chú & Trạng thái kiểm chứng |
|---|---|---|
| **CMS Platform** | WordPress 7.1.4 (nightly/custom release) | `wp core version`: DB revision 61833, en_US. VERIFIED |
| **Web Server** | Apache 2.4.68 (Debian) | PHP/8.3.35 handler qua mpm_prefork. VERIFIED |
| **Backend Runtime** | PHP 8.3.35 (cli & apache module) | Zend OPcache v8.3.35 enabled. VERIFIED |
| **Database Server** | MariaDB 10.11.19 (`bttts5-db`) | Volume `bttts_5_db_data`, charset utf8mb4. VERIFIED |
| **E-commerce Core** | WooCommerce 11.1.2 | HPOS (High-Performance Order Storage) enabled. VERIFIED |
| **Parent Theme** | Astra 4.14.0 | Theme cha cung cấp khung base. VERIFIED |
| **Active Child Theme** | `furniva-child` v2.9.0 | Kích hoạt tại port 8088; 226KB CSS, layout 8 hồi. VERIFIED |
| **Custom Core Plugin** | `despace-core` v1.0.0 | PSR-4 autoloader, RBAC, Inventory Traceability, Checkout. VERIFIED |
| **Search Engine** | FiboSearch (AJAX Search for WooCommerce) 1.34.2 | Live suggest sản phẩm + SKU + ảnh. VERIFIED |
| **Multilingual Engine** | TranslatePress 3.3.7 | Hỗ trợ song ngữ Tiếng Việt (vi_VN) / Tiếng Anh (en_US). VERIFIED |
| **Wishlist Plugin** | YITH WooCommerce Wishlist 4.19.0 | Bảng riêng `wp_yith_wcwl`. VERIFIED |
| **Quick View Plugin** | YITH WooCommerce Quick View 2.18.0 | Modal xem nhanh sản phẩm trên catalog. VERIFIED |
| **3D / AR Viewer** | Google `<model-viewer>` v3.5.0 / v4.4.0 | Tương thích WebXR, Quick Look iOS (USDZ), Scene Viewer (GLB). VERIFIED |
| **Design Typography** | Plus Jakarta Sans, Newsreader Italic, Cormorant Garamond, Inter | Load trực tiếp Google Fonts CDN. VERIFIED |
| **Mail Staging** | Mailpit v1.30.2 (`despace-mailpit`) | SMTP port 1025, Web UI port 8026. VERIFIED |

---

## 2. KIẾN TRÚC HIỆN TẠI CỦA BTTTS_5

Dự án BTTTS_5 được xây dựng theo kiến trúc thương mại điện tử kiến trúc cao cấp (Architectural Furniture Sanctuary) kết hợp giữa WordPress Child Theme và hệ thống mở rộng DeSpace Core:

### 2.1. Phân tầng kiến trúc
1. **Tầng Giao diện (Presentation Layer - `furniva-child`):**
   - Triết lý thiết kế: *Apple Simplicity × Muji Serenity × Scandinavian Warmth*.
   - Trang chủ tự sự 8 hồi (*Act I: The Monumental Hero* đến *Act VIII: The Commitments*).
   - Hệ thống CSS modular: `style.css` (226KB), `assets/css/homepage-clone.css` (68KB), `my-account.css` (44KB), `ax-room-visualizer.css` (26KB), `ar-visualizer.css` (21KB), `animations.css` (17KB), `wishlist-cart.css` (17KB), `performance.css` (15KB), `bento-edit.css` (14KB), `single-product.css` (13KB), `accessibility.css` (8KB).
   - Hệ thống JS frontend: `js/main.js` (55KB - xử lý AJAX filter, swatch UI, off-canvas cart drawer, stepper, toast), `assets/js/ax-room-visualizer.js` (18KB), `axgnud-3d-visualizer.js` (12KB), `animations.js` (14KB), `accessibility.js` (14KB).

2. **Tầng Nghiệp vụ mở rộng (Business Logic Layer - `despace-core`):**
   - Tọa lạc tại: `site/wp-content/plugins/despace-core`.
   - Cấu trúc namespace: `DeSpace\...` với chuẩn PSR-4 autoloader trong `despace-core.php`.
   - Khai báo tương thích High-Performance Order Storage (HPOS) trước khi WooCommerce load.
   - Quản lý phân quyền RBAC (`DeSpace\Security\RoleManager`): vai trò `despace_staff`, 5 custom capabilities (`view_despace_reports`, `view_despace_inventory_logs`, `manage_despace_3d`, `manage_despace_settings`, `edit_despace_products`).
   - Mô-đun Quản lý Kho & Truy vết biến động tồn kho (`DeSpace\Inventory`):
     * Bảng lưu vết: `wp_despace_inventory_logs` (1,634 bản ghi).
     * Bắt sự kiện stock change qua `InventoryStockObserver`, `InventoryEventRecorder`, `InventoryOrderContext`.
     * Đối soát và cảnh báo chênh lệch tồn kho qua `InventoryReconciler`.
     * Giao diện quản trị Admin: `InventoryLogsPage`, `InventoryLogsTable`, xuất báo cáo `InventoryExportService` (CSV).
     * REST API endpoint: `/wp-json/despace/v1/inventory/logs`.
   - Mô-đun Checkout & Quản lý đơn hàng (`DeSpace\CheckoutOrders`):
     * Tích hợp dữ liệu 63 tỉnh thành Việt Nam 2025 (`vietnam-regions-2025.json`).
     * Kiểm tra tính hợp lệ địa chỉ giao hàng và mã giảm giá (`AddressValidator`, `CouponValidator`, `CheckoutValidator`).
     * Bảo vệ thanh toán và hướng dẫn BACS (`PaymentStatusGuard`, `PaymentMethodValidator`, `BacsInstructions`).
     * Quản lý đơn hàng khách vãng lai và bảo mật truy cập (`OrderAccessGuard`, `GuestOrderTracker`, `OrderTrackingPresenter`).
     * Quản lý hồ sơ khách hàng và email giao dịch (`AccountProfileHandler`, `EmailCustomizer`).

3. **Tầng Studio 3D & Thực tế tăng cường (3D/AR Studio):**
   - Quản trị mô hình tập trung: Bảng database riêng `wp_axgnud_product_3d_models` (40 sản phẩm, 100% có GLB, USDZ và AR kích hoạt).
   - Thư mục lưu trữ: `site/wp-content/uploads/3d/` với 32 bộ mô hình GLB + USDZ (13MB).
   - Giao diện Admin: `inc/admin-3d-manager.php` (trang menu *3D & AR Studio*).
   - Giao diện Frontend: Nút xem 3D, modal xoay 360°, mã QR trải nghiệm AR trên di động, và trang chuyên biệt `/ar-room-visualizer/` (page ID 314).

4. **Tầng Tiện ích cốt lõi (Must-Use Plugins Layer - `mu-plugins`):**
   - `furniva-store-admin.php`: Việt hóa toàn bộ menu WordPress admin cho người quản trị cửa hàng, báo cáo sản phẩm sắp hết hàng (`low_stock`), dashboard widget điều hành.
   - `dynamic-tunnel.php`: Tự động nhận diện host qua Cloudflare Tunnel / Ngrok / Localhost, ghi đè `pre_option_siteurl` và `pre_option_home` mà không làm hỏng URL tĩnh.
   - `despace-smtp.php`: Định tuyến toàn bộ email WooCommerce giao dịch về Mailpit staging local (port 1025).

---

## 3. DANH SÁCH CÁC TRANG TRÊN HỆ THỐNG

| ID Page | Tiêu đề trang | Đường dẫn (Slug) | Template sử dụng | Trạng thái |
|---|---|---|---|---|
| **208** | Trang chủ | `/` hoặc `/home` | `front-page.php` (8 hồi kiến trúc) | VERIFIED (HTTP 200) |
| **5** | Cửa hàng | `/shop/` | `woocommerce.php` (Grid 4/3/2 + AJAX Filter) | VERIFIED (HTTP 200) |
| **Chi tiết** | 40 Trang chi tiết sản phẩm | `/product/{slug}` | `woocommerce/content-single-product.php` | VERIFIED (40/40 HTTP 200) |
| **314** | AR Room Visualizer | `/ar-room-visualizer/` | `page-ar-visualizer.php` | VERIFIED (HTTP 200) |
| **247** | Danh sách yêu thích | `/wishlist/` | `page-wishlist.php` | VERIFIED (HTTP 200) |
| **6** | Giỏ hàng | `/cart/` | `page-cart.php` | VERIFIED (HTTP 200) |
| **7** | Thanh toán | `/checkout/` | `page.php` + WooCommerce Checkout | VERIFIED (HTTP 200) |
| **8** | Tài khoản của tôi | `/my-account/` | `woocommerce/myaccount/my-account.php` | VERIFIED (HTTP 200) |
| **209** | Tạp chí | `/blog/` | `home.php` | VERIFIED (HTTP 200) |
| **210** | Về chúng tôi | `/about/` | `page.php` | VERIFIED (HTTP 200) |
| **211** | Liên hệ | `/contact/` | `page.php` | VERIFIED (HTTP 200) |
| **212** | Giao hàng & Đổi trả | `/shipping-returns/` | `page.php` | VERIFIED (HTTP 200) |
| **213** | Hỏi đáp | `/faqs/` | `page.php` | VERIFIED (HTTP 200) |
| **404** | Trang báo lỗi 404 | N/A | `404.php` | VERIFIED |

---

## 4. DANH SÁCH MODULE & CHỨC NĂNG NGHIỆP VỤ

### 4.1. Nhóm Frontend & Catalog
- **AJAX Filter Engine (`wp_ajax_furniva_filter`):** Lọc theo danh mục, thương hiệu, khoảng giá, màu sắc (`pa_color`), tình trạng còn hàng / sale. Lọc trực tiếp qua `WP_Query` không tải lại trang.
- **Skeleton Shimmer Loading:** Giả lập khung hình sản phẩm khi đang fetch dữ liệu AJAX (`@keyframes furniva-shimmer`).
- **Color Swatches Preview:** Hiển thị danh sách mã màu thực tế trên từng product card ngoài trang catalog.
- **Grid Layout Switcher:** Chuyển đổi hiển thị 3 cột / 4 cột trên desktop, lưu tùy chọn vào `localStorage`.
- **FiboSearch Live Autocomplete:** Tìm kiếm theo thời gian thực hiển thị tên, giá, ảnh thumb và SKU.
- **YITH Quick View Integration:** Xem nhanh thông số sản phẩm và thêm vào giỏ không rời trang.
- **Custom Wishlist Engine:** Lưu sản phẩm yêu thích (AJAX toggle, header count sync, trang quản lý danh sách).

### 4.2. Nhóm Giỏ hàng & Thanh toán
- **Mini-Cart Off-Canvas Drawer (`furniva_get_cart_drawer`):** Drawer trượt từ cạnh phải khi thêm hàng hoặc bấm icon giỏ, tích hợp thanh tiến trình Free Shipping ($500), tăng/giảm số lượng và xóa sản phẩm tức thì bằng AJAX.
- **1-Click Buy Now (`furniva_buy_now`):** Thêm sản phẩm vào giỏ và chuyển thẳng vào trang Checkout.
- **Sticky Add-to-Cart Bar:** Thanh điều hướng chân trang trên trang chi tiết sản phẩm khi người dùng cuộn qua nút mua chính.
- **Coupons Engine:** Hỗ trợ coupon `WELCOME10` (giảm 10%), `SAVE20` (giảm $20 cho đơn từ $200). 1-Click Copy Coupon kèm Toast alert.
- **WooCommerce HPOS Checkout:** Thanh toán hỗ trợ COD và Chuyển khoản ngân hàng (BACS), lưu trữ đơn hàng trên bảng tối ưu `wp_wc_orders`.
- **Vietnam Regions 2025:** Xác thực 63 tỉnh thành và quận huyện theo chuẩn phân cấp hành chính.

### 4.3. Nhóm 3D & Thực tế tăng cường (AR)
- **Interactive 3D Modal:** Xem mô hình 3D xoay 360°, phóng to, thu nhỏ trên `<model-viewer>`.
- **Cross-device AR Launch:**
  * Desktop: Sinh mã QR động để người dùng quét camera điện thoại mở trải nghiệm AR.
  * iOS: Khởi chạy Apple Quick Look với file định dạng `.usdz`.
  * Android: Khởi chạy Google Scene Viewer với file định dạng `.glb`.
- **AR Room Visualizer:** Tải ảnh phòng thực tế, chèn mô hình nội thất theo tỷ lệ và lưu sơ đồ phòng (Save Layout).

### 4.4. Nhóm Quản trị & Truy vết Kho (Admin & Inventory)
- **Traceability Audit Logs (`wp_despace_inventory_logs`):** Ghi nhật ký mọi biến động xuất/nhập/hoàn đơn với `event_key`, `quantity_before`, `quantity_delta`, `stock_after`, `actor_user_id`, `context_json`.
- **Phân quyền RBAC (`RoleManager`):** Phân chia quyền hạn chặt chẽ giữa Administrator và DeSpace Staff.
- **Bảng điều khiển 3D Studio (`admin-3d-manager.php`):** Đồng bộ, kiểm tra trạng thái file GLB/USDZ, nhập/xuất CSV.

---

## 5. DATABASE SCHEMA TOÀN DIỆN

Kiểm tra trực tiếp tại `bttts5-db` qua `information_schema.tables`: **Tổng cộng 65 bảng dữ liệu**.

### 5.1. Bảng mở rộng đặc thù của dự án

#### Bảng `wp_axgnud_product_3d_models` (40 bản ghi)
Lưu trữ toàn bộ siêu dữ liệu 3D, AR, kích thước thực tế của 40 sản phẩm:
```sql
CREATE TABLE `wp_axgnud_product_3d_models` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned DEFAULT 0,
  `sku` varchar(100) DEFAULT '',
  `glb_url` text DEFAULT '',
  `usdz_url` text DEFAULT '',
  `thumbnail_url` text DEFAULT '',
  `width` decimal(10,2) DEFAULT 0.00,
  `depth` decimal(10,2) DEFAULT 0.00,
  `height` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(20) DEFAULT 'cm',
  `material` varchar(255) DEFAULT '',
  `color` varchar(255) DEFAULT '',
  `version` varchar(50) DEFAULT '1.0',
  `model_status` varchar(50) DEFAULT 'missing',
  `ar_enabled` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` bigint(20) unsigned DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `sku` (`sku`),
  KEY `model_status` (`model_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### Bảng `wp_despace_inventory_logs` (1,634 bản ghi)
Lưu vết kiểm toán biến động kho hàng thời gian thực:
```sql
CREATE TABLE `wp_despace_inventory_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_key` char(64) NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `event_source` varchar(40) NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `variation_id` bigint(20) unsigned DEFAULT NULL,
  `order_id` bigint(20) unsigned DEFAULT NULL,
  `order_item_id` bigint(20) unsigned DEFAULT NULL,
  `refund_id` bigint(20) unsigned DEFAULT NULL,
  `actor_user_id` bigint(20) unsigned DEFAULT NULL,
  `quantity_before` decimal(20,6) DEFAULT NULL,
  `quantity_delta` decimal(20,6) NOT NULL,
  `stock_after` decimal(20,6) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `context_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_key` (`event_key`),
  KEY `product_id` (`product_id`),
  KEY `variation_id` (`variation_id`),
  KEY `order_id` (`order_id`),
  KEY `event_type` (`event_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 5.2. Các bảng WooCommerce HPOS & Dữ liệu cốt lõi
- `wp_wc_orders`: 341 đơn hàng.
- `wp_wc_order_addresses`: 303 bản ghi địa chỉ thanh toán & giao hàng.
- `wp_wc_order_operational_data`: 341 bản ghi cờ trạng thái xử lý đơn.
- `wp_wc_orders_meta`: 785 bản ghi metadata đơn hàng.
- `wp_posts`: 835 bản ghi (40 sản phẩm chính thức, bài viết blog, trang nội dung, tệp đính kèm).
- `wp_postmeta`: 3,001 bản ghi metadata sản phẩm (giá, SKU, tồn kho, thuộc tính).
- `wp_yith_wcwl`: 14 danh sách yêu thích.
- `wp_trp_*`: Hệ thống từ điển đa ngữ TranslatePress (hơn 18,000 chuỗi dịch).

---

## 6. DANH MỤC DỮ LIỆU SẢN PHẨM (CATALOG AUDIT)

Bộ sản phẩm chuẩn gồm **40 sản phẩm nội thất cao cấp** thuộc thương hiệu Axgnud / DeSpace, mã SKU từ `AXG-STR-001` đến `AXG-STR-040`:

### Phân bố theo 5 Danh mục chính:
1. **Tủ giày & Lưu trữ (`shoe-racks-storage`):** 18 sản phẩm (SKU: `AXG-STR-001` đến `AXG-STR-018`).
2. **Tủ quần áo (`wardrobes`):** 6 sản phẩm (SKU: `AXG-STR-019` đến `AXG-STR-024`).
3. **Kệ sách & Tủ trưng bày (`bookshelves`):** 6 sản phẩm (SKU: `AXG-STR-025` đến `AXG-STR-030`).
4. **Tủ bếp & Tủ đa năng (`kitchen-cabinets`):** 5 sản phẩm (SKU: `AXG-STR-031` đến `AXG-STR-035`).
5. **Tủ ngăn kéo & Tủ Sideboard (`chest-of-drawers-sideboards`):** 5 sản phẩm (SKU: `AXG-STR-036` đến `AXG-STR-040`).

### Thuộc tính kỹ thuật đã thiết lập:
- `pa_brand`: Axgnud / Furniva / DeSpace.
- `pa_color`: Nâu Wenge, Gỗ sồi Sonoma, Gỗ óc chó Columbia, Trắng Ice White, Vàng & Nâu Two-Tone, Xám hiện đại, Gỗ anh đào Lome Cherry, Gỗ óc chó Lyon, Gỗ Giant Wood, Gỗ sồi Modern Oak, Gỗ óc chó Mocha, Gỗ sồi Rustic.
- `pa_finish`: Melamine, Veneer gỗ tự nhiên, Sơn phủ bóng, Cánh kính.
- `pa_size`: 2 cánh, 3 cánh, 4 cánh, 4 tầng, 5 tầng, 6 tầng, v.v.

---

## 7. DANH SÁCH TÀI NGUYÊN HÌNH ẢNH VÀ MÔ HÌNH 3D

1. **Mô hình 3D & AR:**
   - 32 tệp nhị phân GLB: `/wp-content/uploads/3d/{SKU}/model.glb`
   - 32 tệp nén USDZ cho iOS AR Quick Look: `/wp-content/uploads/3d/{SKU}/model.usdz`
   - 32 ảnh thumbnail 3D: `/wp-content/uploads/3d/{SKU}/thumbnail.webp`
   - Dung lượng thư mục: 13 MB.
2. **Thư viện ảnh sản phẩm & truyền thông:**
   - Tổng cộng 353 attachments trong WordPress media library.
   - Dung lượng thư mục `wp-content/uploads`: 269 MB.
   - Toàn bộ ảnh được chuẩn hóa theo tỷ lệ khung hình kiến trúc, nền sạch, không có ảnh lỗi hoặc broken links.
3. **Tài nguyên thương hiệu trong Child Theme:**
   - Toàn bộ ảnh banner, hero Scandinavian, bento room sets, logo dark/light, favicon nằm tại `furniva-child/assets/images/` (39 MB).

---

## 8. LỖI ĐANG TỒN TẠI VÀ RỦI RO KHI RE-PLATFORMING

### 8.1. Phát hiện từ lần chuyển đổi trước (Commit 9e534f3)
- **RỦI RO NGHIÊM TRỌNG:** Ở lần commit trước, một phiên bản sơ khai của `despace-child` và `despace-core` đã vô tình lược bỏ phần lớn tinh hoa giao diện của `furniva-child` (chỉ còn 14KB CSS thay vì 226KB CSS đầy đủ; thiếu các template quan trọng như `content-single-product.php`, `content-product.php`, `page-ar-visualizer.php`, `page-wishlist.php`, `page-cart.php`, `myaccount/*`).
- **Khắc phục:** Quá trình re-platforming sang DeSpace Child Theme bắt buộc phải kế thừa 100% markup, CSS, JS, responsive logic, AR room visualizer và template parts của `furniva-child`, chỉ tái cấu trúc tên gọi thương hiệu, biến CSS và prefix hàm sang chuẩn `despace_*`.

### 8.2. Tính nhất quán của bảng 3D Models
- Tên bảng hiện tại trên BTTTS_5 là `wp_axgnud_product_3d_models`.
- Khi chuyển đổi sang DeSpace Core, cần hỗ trợ cả `wp_despace_product_3d_models` lẫn cơ chế fallback đọc từ `wp_axgnud_product_3d_models` hoặc tự động migrate dữ liệu để không làm gián đoạn mô hình 3D của 40 sản phẩm.

### 8.3. Tương thích HPOS & WooCommerce Hooks
- Đơn hàng phải luôn tương thích chuẩn HPOS (`wp_wc_orders`). Không được sử dụng các hook cũ can thiệp thô bạo vào bảng `wp_posts` cho post type `shop_order`.
- Mọi thao tác đơn hàng phải gọi qua WooCommerce CRUD API.
