# BẢNG KIỂM KÊ TOÀN BỘ TÍNH NĂNG BTTTS_5 (FEATURE INVENTORY)

> **Mã tài liệu:** `docs/02-feature-inventory.md`  
> **Dự án:** Re-platforming BTTTS_5 → DeSpace  
> **Quy tắc phân loại trạng thái:**  
> - **VERIFIED:** Đã kiểm chứng hoạt động thực tế trên runtime và source code.  
> - **IMPLEMENTED:** Đã lập trình nhưng đang trong quá trình chuyển đổi / đóng gói.  
> - **PARTIAL:** Mới hoàn thành một phần.  
> - **NOT IMPLEMENTED:** Chưa có trong hệ thống gốc.  
> - **BLOCKED:** Bị chặn do thiếu môi trường.

---

## BẢNG KIỂM KÊ CHI TIẾT TÍNH NĂNG

| ID | Trang / Module | Chức năng hiện tại | Vị trí source trong BTTTS_5 | Trạng thái | Phương án chuyển đổi sang DeSpace |
|---|---|---|---|---|---|
| **FE-01** | Header | Logo thương hiệu & biểu tượng (Brand Mark) | `themes/furniva-child/header.php:24-40` | VERIFIED | Chuyển sang DeSpace Child Theme (`themes/despace-child/header.php`), cập nhật logo DeSpace. |
| **FE-02** | Header | Primary Navigation & Mega Menu (Custom Walker) | `themes/furniva-child/header.php:42-80`, `inc/template-tags.php` | VERIFIED | Kế thừa nguyên vẹn logic walker và styling sang DeSpace Child Theme, giữ phân cấp danh mục. |
| **FE-03** | Header | Live AJAX Search (FiboSearch autocomplete) | `header.php`, plugin `ajax-search-for-woocommerce` | VERIFIED | Giữ cấu hình tích hợp shortcode/form FiboSearch, style lại input pill theo design system DeSpace. |
| **FE-04** | Header | Language Switcher song ngữ (VI / EN) | `header.php:85-95`, plugin `translatepress-multilingual` | VERIFIED | Giữ tích hợp TranslatePress, style dropdown ngôn ngữ bo tròn thanh lịch. |
| **FE-05** | Header | Tài khoản người dùng (Login / Account modal trigger) | `header.php:100-115`, `inc/axgnud-customer-auth.php` | VERIFIED | Chuyển modal xác thực khách hàng sang `despace-child`, đồng bộ trạng thái đăng nhập. |
| **FE-06** | Header | Wishlist counter badge (Đồng bộ thời gian thực) | `header.php:120-130`, `js/main.js:720-750` | VERIFIED | Kế thừa endpoint `wp_ajax_furniva_get_wishlist_count` sang `despace_get_wishlist_count`. |
| **FE-07** | Header | Mini-Cart Drawer trigger & counter badge | `header.php:135-150`, `js/main.js:410-440` | VERIFIED | Đồng bộ với drawer giỏ hàng trượt của DeSpace qua `despace_cart_state`. |
| **FE-08** | Header | Mobile Hamburger Menu & Off-canvas drawer | `header.php:155-180`, `style.css:480-530` | VERIFIED | Kế thừa responsive mobile drawer, chống tràn ngang (overflow-x: clip). |
| **HM-01** | Trang chủ | Act I — The Monumental Hero (Banner kiến trúc) | `front-page.php:20-65`, `assets/css/homepage-clone.css` | VERIFIED | Chuyển toàn bộ markup và CSS sang `themes/despace-child/front-page.php`. |
| **HM-02** | Trang chủ | Act II — The Manifesto of Stillness (Tuyên ngôn) | `front-page.php:68-110` | VERIFIED | Giữ nguyên Typography Newsreader Italic và nội dung triết lý thẩm mỹ tĩnh lặng. |
| **HM-03** | Trang chủ | Act III — Curated Sanctuaries (Bento category grid) | `front-page.php:112-190`, `assets/css/bento-edit.css` | VERIFIED | Kế thừa bento grid 6 không gian sống với hiệu ứng hover zoom mượt mà. |
| **HM-04** | Trang chủ | Act IV — The Icon Story (Sản phẩm biểu tượng) | `front-page.php:192-260` | VERIFIED | Giữ layout câu chuyện sản phẩm Haven Lounge Suite / Tủ Metro. |
| **HM-05** | Trang chủ | Act V — The Permanent Archive (Carousel sản phẩm) | `front-page.php:262-340` | VERIFIED | Query 8 sản phẩm nổi bật WooCommerce kèm swatch màu và giá sale. |
| **HM-06** | Trang chủ | Act VI — The Material Dialogue (Banner ưu đãi) | `front-page.php:342-390` | VERIFIED | Kế thừa 1-Click Copy Coupon `WELCOME10` kèm Toast alert. |
| **HM-07** | Trang chủ | Act VII — Architectural Journal (3 bài viết mới) | `front-page.php:392-450` | VERIFIED | Query 3 bài viết chuyên luận mới nhất từ post type `post`. |
| **HM-08** | Trang chủ | Act VIII — The Commitments (4 cam kết dịch vụ) | `front-page.php:452-510` | VERIFIED | Giữ 4 khối dịch vụ cao cấp: Bảo hành 5 năm, Giao hàng White-glove, 100% Gỗ chuẩn, 3D Scan. |
| **HM-09** | Toàn trang | Back to Top Button mượt mà | `footer.php:110-125`, `js/main.js:80-105` | VERIFIED | Kế thừa `#furniva-back-to-top` đổi tên thành `#despace-back-to-top`. |
| **CT-01** | Cửa hàng | Shop Hero & Danh mục Quick-Pills | `woocommerce.php:35-85`, `inc/template-tags.php` | VERIFIED | Chuyển sang `themes/despace-child/woocommerce.php`, giữ bộ pill chọn nhanh danh mục kèm số lượng. |
| **CT-02** | Cửa hàng | Grid Switcher (Bộ đổi 3 cột ↔ 4 cột) | `woocommerce.php:90-115`, `js/main.js:120-145` | VERIFIED | Kế thừa tính năng lưu chế độ hiển thị 3 cột / 4 cột vào `localStorage`. |
| **CT-03** | Cửa hàng | Active Filters Bar & 1-Click Clear All | `woocommerce.php:120-145`, `js/main.js:150-180` | VERIFIED | Hiển thị các tiêu chí đang lọc, cho phép gỡ từng chip hoặc xóa tất cả bằng AJAX. |
| **CT-04** | Cửa hàng | AJAX Filter Sidebar (Đa tiêu chí) | `inc/template-tags.php:180-320`, `functions.php:609` | VERIFIED | Chuyển endpoint `furniva_filter` thành `despace_filter`, lọc theo danh mục, giá, màu sắc, tình trạng kho. |
| **CT-05** | Cửa hàng | Skeleton Shimmer Loading khi lọc | `assets/css/performance.css`, `js/main.js:200-230` | VERIFIED | Giữ hiệu ứng shimmer ánh sáng thay cho spinner thô sơ khi tải dữ liệu AJAX. |
| **CT-06** | Cửa hàng | Sắp xếp & Phân trang sản phẩm | `woocommerce.php:150-180` | VERIFIED | Tương thích chuẩn phân trang WooCommerce (`paginate_links`). |
| **CT-07** | Cửa hàng | Empty State (Không có kết quả) | `woocommerce.php:190-210` | VERIFIED | Card thông báo trống tối giản kèm nút "Reset All Filters". |
| **PC-01** | Thẻ sản phẩm | Product Card Image & Hover transitions | `woocommerce/content-product.php:20-55` | VERIFIED | Chuyển sang `despace-child/woocommerce/content-product.php`, giữ tỷ lệ ảnh bo góc 24px. |
| **PC-02** | Thẻ sản phẩm | Badges (Sale / New) | `woocommerce/content-product.php:58-75` | VERIFIED | Kế thừa hàm tính phần trăm giảm giá và badge New cho sản phẩm mới. |
| **PC-03** | Thẻ sản phẩm | Color Swatches Preview ngoài danh sách | `woocommerce/content-product.php:80-110` | VERIFIED | Truy xuất `pa_color` và hàm ánh xạ màu hex hiển thị chấm màu tương tác. |
| **PC-04** | Thẻ sản phẩm | Thêm nhanh vào Wishlist (Icon trái tim) | `woocommerce/content-product.php:115-130` | VERIFIED | Kế thừa AJAX toggle wishlist, cập nhật badge header tức thì. |
| **PC-05** | Thẻ sản phẩm | Quick View Modal (Icon con mắt) | `woocommerce/content-product.php:135-145` | VERIFIED | Tích hợp YITH Quick View với styling chuẩn DeSpace Obsidian. |
| **PC-06** | Thẻ sản phẩm | AR Quick Button trên card sản phẩm | `inc/ar-visualizer-integration.php:263-280` | VERIFIED | Nút mở trực tiếp modal 3D hoặc chuyển sang AR Room Visualizer. |
| **PD-01** | Chi tiết SP | Single Product Gallery (Zoom & Lightbox) | `woocommerce/content-single-product.php:30-90` | VERIFIED | Kế thừa gallery ảnh lớn tràn viền, ảnh thumbnails cuộn mượt và lightbox phóng to. |
| **PD-02** | Chi tiết SP | Swatch UI tương tác thay dropdown | `js/main.js:280-360`, `woocommerce/content-single-product.php` | VERIFIED | Nút tròn màu viền đôi active ring và chip kích thước bo góc, đổi biến thể đổi giá và ảnh thật. |
| **PD-03** | Chi tiết SP | Stepper số lượng `[-] [qty] [+]` | `functions.php:683-705`, `style.css:980-1010` | VERIFIED | Bọc stepper dạng pill thanh lịch, ngăn nhập số âm hoặc vượt tồn kho. |
| **PD-04** | Chi tiết SP | Nút Add to Cart & 1-Click Buy Now | `woocommerce/content-single-product.php:120-150`, `functions.php:657` | VERIFIED | Chuyển endpoint `furniva_buy_now` thành `despace_buy_now`, chuyển thẳng đến checkout. |
| **PD-05** | Chi tiết SP | Modal 3D Viewer (`<model-viewer>`) | `woocommerce/content-single-product.php:160-210`, `class-despace-3d.php` | VERIFIED | Mở modal 3D xoay 360°, đổi vật liệu, phóng to thu nhỏ. |
| **PD-06** | Chi tiết SP | Trải nghiệm AR đa thiết bị (Desktop QR / iOS USDZ / Android GLB) | `inc/ar-visualizer-integration.php:158-220`, `class-despace-3d.php` | VERIFIED | Tự động phân nhánh thiết bị: QR code trên desktop, Quick Look trên iOS, Scene Viewer trên Android. |
| **PD-07** | Chi tiết SP | Trust Badges Strip & Metadata | `woocommerce/content-single-product.php:220-250` | VERIFIED | Hiển thị SKU, Danh mục, Thương hiệu, và 3 huy hiệu cam kết chất lượng. |
| **PD-08** | Chi tiết SP | Tabs chi tiết (Mô tả, Thông số, Đánh giá) | `woocommerce/content-single-product.php:260-310` | VERIFIED | Thiết kế tab phẳng tối giản, hỗ trợ đánh giá sao thực tế. |
| **PD-09** | Chi tiết SP | Sticky Add-to-Cart Bar chân trang | `functions.php:963-990`, `js/main.js:380-405` | VERIFIED | Thanh cố định trượt lên khi cuộn qua nút mua chính, đồng bộ số lượng và biến thể. |
| **PD-10** | Chi tiết SP | 1-Click Share & Toast Notification | `functions.php`, `js/main.js:408-430` | VERIFIED | Tự động sao chép liên kết vào clipboard và bật thông báo Toast nhẹ nhàng. |
| **PD-11** | Chi tiết SP | Schema.org JSON-LD SEO Rich Snippets | `functions.php:924-960` | VERIFIED | Xuất thẻ `<script type="application/ld+json">` chuẩn Schema Product cho Google. |
| **AR-01** | AR Studio | AR Room Visualizer (Trang đặt nội thất vào phòng) | `page-ar-visualizer.php`, `assets/js/ax-room-visualizer.js` | VERIFIED | Chuyển nguyên vẹn sang `themes/despace-child/page-ar-visualizer.php`. |
| **AR-02** | AR Studio | Upload ảnh phòng & Canvas 2D/3D | `page-ar-visualizer.php:40-120` | VERIFIED | Hỗ trợ kéo thả đồ nội thất, xoay, chỉnh kích thước theo tỷ lệ phòng. |
| **AR-03** | AR Studio | Lưu / Tải sơ đồ phòng (Save/Load Layout) | `inc/ar-visualizer-ajax.php:10-90` | VERIFIED | Kế thừa các AJAX handlers `ar_save_layout`, `ar_get_layouts`, `ar_delete_layout`. |
| **AR-04** | AR Studio | Thêm toàn bộ danh sách phòng vào Giỏ | `inc/ar-visualizer-ajax.php:112-150` | VERIFIED | AJAX handler `ar_add_to_cart` thêm trọn gói các món đồ đã bài trí vào giỏ hàng. |
| **WL-01** | Yêu thích | Trang Wishlist tùy chỉnh | `page-wishlist.php`, `assets/css/wishlist-cart.css` | VERIFIED | Chuyển sang `themes/despace-child/page-wishlist.php`. |
| **WL-02** | Yêu thích | Thêm vào giỏ từ Wishlist qua AJAX | `functions.php:1073-1100`, `js/main.js` | VERIFIED | Endpoint `despace_wishlist_add_to_cart` đồng bộ mini-cart drawer. |
| **WL-03** | Yêu thích | Xóa món / Xóa sạch Wishlist qua AJAX | `functions.php:1105-1150` | VERIFIED | Endpoint `despace_wishlist_remove` và `despace_wishlist_clear`. |
| **CA-01** | Giỏ hàng | Mini-Cart Off-Canvas Drawer | `functions.php:834-890`, `js/main.js:450-520` | VERIFIED | Drawer trượt từ phải, cập nhật số lượng và xóa sản phẩm không reload trang. |
| **CA-02** | Giỏ hàng | Free Shipping Progress Bar ($500) | `functions.php:845-855`, `style.css:1120-1145` | VERIFIED | Thanh tiến trình tính số tiền còn thiếu để được miễn phí vận chuyển. |
| **CA-03** | Giỏ hàng | Trang Cart đầy đủ (`/cart/`) | `page-cart.php`, WooCommerce Cart Block | VERIFIED | Chuyển sang `themes/despace-child/page-cart.php`, hỗ trợ coupon và cập nhật số lượng. |
| **CO-01** | Thanh toán | Form Checkout nhiều bước chuẩn hóa | `page.php`, WooCommerce Checkout | VERIFIED | Tùy biến giao diện checkout theo card sang trọng, bo góc 16px. |
| **CO-02** | Thanh toán | Xác thực 63 Tỉnh thành Việt Nam (2025) | `plugins/despace-core/includes/Checkout-Orders/Shipping/` | VERIFIED | Tích hợp dữ liệu địa giới hành chính `vietnam-regions-2025.json`. |
| **CO-03** | Thanh toán | Cổng thanh toán COD & Chuyển khoản BACS | `Checkout-Orders/Payment/`, WooCommerce Settings | VERIFIED | Kiểm tra phương thức, hiển thị hướng dẫn số tài khoản ngân hàng chi tiết. |
| **CO-04** | Thanh toán | Lưu đơn hàng chuẩn HPOS (`wp_wc_orders`) | `Checkout-Orders/Checkout/OrderMetaSaver.php` | VERIFIED | Lưu metadata tùy biến và không vi phạm cấu trúc bảng HPOS. |
| **AC-01** | Tài khoản | Form Đăng nhập & Đăng ký sang trọng | `woocommerce/myaccount/form-login.php` | VERIFIED | Chuyển sang `despace-child/woocommerce/myaccount/form-login.php`. |
| **AC-02** | Tài khoản | Dashboard quản lý tài khoản | `woocommerce/myaccount/dashboard.php` | VERIFIED | Thẻ thông tin khách hàng, tổng quan đơn hàng gần nhất. |
| **AC-03** | Tài khoản | Danh sách đơn hàng & Xem chi tiết đơn | `woocommerce/myaccount/orders.php`, `view-order.php` | VERIFIED | Truy xuất dữ liệu đơn hàng trực tiếp từ HPOS. |
| **AC-04** | Tài khoản | Quản lý sổ địa chỉ giao hàng & thanh toán | `woocommerce/myaccount/my-address.php`, `form-edit-address.php` | VERIFIED | Form chỉnh sửa địa chỉ đồng bộ với trường hành chính Việt Nam. |
| **BK-01** | Quản trị | Quản lý Phân quyền RBAC (`despace_staff`) | `plugins/despace-core/includes/Security/RoleManager.php` | VERIFIED | Quản lý 5 capabilities độc quyền, tích hợp vào `despace-core`. |
| **BK-02** | Quản trị | Nhật ký truy vết biến động kho (Traceability) | `plugins/despace-core/includes/Inventory/` | VERIFIED | Ghi nhận 100% sự kiện thay đổi tồn kho vào `wp_despace_inventory_logs`. |
| **BK-03** | Quản trị | Bảng đối soát & Xuất CSV kiểm toán kho | `plugins/despace-core/includes/Inventory/Admin/` | VERIFIED | Trang quản trị *DeSpace Inventory Logs* và nút *Export CSV*. |
| **BK-04** | Quản trị | Bảng điều khiển Studio 3D & AR tập trung | `themes/furniva-child/inc/admin-3d-manager.php` | VERIFIED | Chuyển thành module chính thức trong `despace-core` (`DeSpace\Product3D\Admin`). |
| **BK-05** | Quản trị | Việt hóa menu WordPress Admin cho chủ shop | `mu-plugins/furniva-store-admin.php` | VERIFIED | Chuyển thành module Admin trong `despace-core` hoặc giữ nguyên `mu-plugins/despace-store-admin.php`. |
| **BK-06** | Hạ tầng | Mailpit Transactional Email Staging | `mu-plugins/despace-smtp.php` | VERIFIED | Duy trì định tuyến email giao dịch về Mailpit local (port 1025). |
| **BK-07** | Hạ tầng | Dynamic Host & Tunnel Resolver | `mu-plugins/dynamic-tunnel.php` | VERIFIED | Duy trì hỗ trợ Cloudflare Tunnel / Ngrok phục vụ kiểm thử từ xa. |
| **API-01** | REST API | REST API Truy vết Kho hàng | `plugins/despace-core/includes/Inventory/REST/` | VERIFIED | Endpoint `/wp-json/despace/v1/inventory/logs` có phân quyền và phân trang. |
| **API-02** | REST API | REST API Siêu dữ liệu 3D / AR | `plugins/despace-core/includes/class-despace-rest-api.php` | VERIFIED | Endpoint `/wp-json/despace/v1/3d-models` truy xuất GLB/USDZ theo SKU. |
