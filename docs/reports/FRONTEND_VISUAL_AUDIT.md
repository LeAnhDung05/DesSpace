# BÁO CÁO KIỂM THỬ GIAO DIỆN FRONTEND (VISUAL AUDIT REPORT)
**Dự án:** WordPress + WooCommerce DeSpace Luxury Furniture  
**Môi trường:** Local Docker (http://localhost:8090/)  
**Hệ điều hành / Trình duyệt:** Windows / Google Chrome (Playwright Automation & Visual Inspection)  
**Thời gian thực hiện:** 17/11/2026  
**Người phụ trách:** Thành viên 02 — Anvu-IFBB (`feature/member02-catalog`)  
**Tài liệu tham chiếu:** `figma_despace/FIGMA_DESIGN_SYSTEM_AND_PROTOTYPE_SPEC.md` & `figma_despace/exports/`

---

## 1. TỔNG QUAN PHẠM VI KIỂM THỬ

### 1.1. Các trang đã kiểm tra thực tế
1. **Trang chủ (Homepage):** `http://localhost:8090/`
2. **Cửa hàng / Danh mục (Shop / Catalog):** `http://localhost:8090/shop/`
3. **Chi tiết sản phẩm (Single Product):** `http://localhost:8090/product/tu-sach-canh-kinh-chong-bui-despace-shelvo-go-soi-modern-oak/`
4. **Giỏ hàng (Cart):** `http://localhost:8090/cart/` (Đã test cả trạng thái trống và có sản phẩm)
5. **Thanh toán (Checkout):** `http://localhost:8090/checkout/` (Đã test cả trạng thái guest có Auth Gate và customer đã đăng nhập)
6. **Tài khoản khách hàng (My Account):** `http://localhost:8090/my-account/` (Đã test form Đăng nhập/Đăng ký và Dashboard tài khoản)
7. **Giới thiệu (About):** `http://localhost:8090/ve-despace/`
8. **Liên hệ (Contact):** `http://localhost:8090/lien-he/`
9. **Tạp chí (Blog / Journal):** `http://localhost:8090/blog/`
10. **Trải nghiệm phòng 3D / AR (AR Visualizer):** `http://localhost:8090/ar-room-visualizer/`
11. **Danh sách yêu thích (Wishlist):** `http://localhost:8090/wishlist/`

### 1.2. Các Viewport kiểm tra
- **Desktop:** 1440 × 900
- **Laptop / Tablet ngang:** 1024 × 768
- **Tablet dọc:** 768 × 1024
- **Mobile:** 390 × 844

### 1.3. Các thành phần tương tác, Modal và Drawer
- Menu điều hướng mobile (Off-canvas Drawer `#furniva-mobile-nav`)
- Thanh tìm kiếm mở rộng (Expandable Header Search `#fv-header-search-bar`) & Dialog tìm kiếm (`#furniva-search-dialog`)
- Mini Cart Drawer (`.f-drawer-cart`)
- Modal đăng nhập đặc quyền VIP (Auth Gate Modal `#axAuthGateModal`)
- Modal xem mô hình 3D & AR (`#ax-room-visualizer-modal`)
- Bộ lọc sản phẩm Shop trên Mobile (Filter Drawer `#furniva-filters`)
- Thao tác chọn biến thể, tăng giảm số lượng, Lightbox ảnh trên trang Single Product

---

## 2. MA TRẬN KẾT QUẢ KIỂM THỬ THEO TRANG VÀ VIEWPORT (11 TRANG × 4 VIEWPORT)

Toàn bộ 11 trang đã được chạy kiểm thử tự động tại 4 kích thước viewport chuẩn (Desktop 1440×900, Laptop 1024×768, Tablet 768×1024, Mobile 390×844) với tiêu chuẩn kiểm định nghiêm ngặt: yêu cầu `status404Count === 0`, `failedRequests === 0`, `consoleErrorCount === 0`, `brokenImageCount === 0`, và `hasHorizontalOverflow === false`.

| Trang / URL | Desktop (1440×900) | Laptop (1024×768) | Tablet (768×1024) | Mobile (390×844) | Trạng thái kiểm chứng thực tế |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Trang chủ** (`/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, không lỗi console, 0 overflow |
| **Cửa hàng** (`/shop/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, drawer & subnav chuẩn |
| **Chi tiết SP** (`/product/...`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, chuẩn Figma M2 & 3D M1 |
| **Giỏ hàng** (`/cart/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, Card layout mobile, dock ẩn |
| **Thanh toán** (`/checkout/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, Blocks Checkout chuẩn |
| **Tài khoản** (`/my-account/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, Form & Tabs hoạt động tốt |
| **Giới thiệu** (`/ve-despace/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, 2 cột responsive mượt mà |
| **Liên hệ** (`/lien-he/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, Form & thông tin liên hệ chuẩn |
| **Tạp chí** (`/blog/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, Lưới bài viết cân đối |
| **AR Visualizer** (`/ar-room-visualizer/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, 0 broken img, Canvas tải tốt |
| **Yêu thích** (`/wishlist/`) | **PASS** | **PASS** | **PASS** | **PASS** | 4/4 viewport đạt PASS, Bảng sản phẩm yêu thích chuẩn |

*Tổng cộng: 44/44 viewport kiểm thử đều vượt qua (100% PASS).*

---

## 3. DANH SÁCH LỖI VÀ VẤN ĐỀ GIAO DIỆN PHÁT HIỆN ĐƯỢC

### [BUG-01] [BLOCKER] Thẻ hình ảnh `src=""` gây tải lại trang dưới dạng ảnh và lỗi Console trên toàn bộ hệ thống
- **Mức độ:** Blocker / High
- **Trang & Viewport:** Toàn bộ 11 trang, trên tất cả 4 viewport (Desktop, Laptop, Tablet, Mobile).
- **Các bước tái hiện:**
  1. Mở bất kỳ trang nào (Trang chủ, Cửa hàng, Chi tiết sản phẩm, Giỏ hàng, AR Visualizer, v.v.).
  2. Mở DevTools Network Tab và Console.
  3. Quan sát request ảnh được gửi đi tới URL hiện tại (ví dụ: GET `http://localhost:8090/` với kiểu `image/*`).
- **Mô tả hiện tượng:**
  Trình duyệt gửi một request tải hình ảnh với `src=""` (rỗng), dẫn đến việc tải toàn bộ nội dung HTML của trang hiện tại về và cố gắng decode làm ảnh. Gây lãng phí băng thông, tăng thời gian load trang và gây lỗi console `broken image: img.naturalWidth === 0`.
- **Nguyên nhân kỹ thuật:**
  1. File `wordpress/wp-content/themes/despace-child/template-parts/modal-room-visualizer.php` (dòng 311) chứa thẻ `<img id="axModalProductThumb" src="" alt="Thumbnail" />`. Template này được include ở chân trang trên hầu hết các trang.
  2. File `wordpress/wp-content/themes/despace-child/woocommerce/content-single-product.php` chứa thẻ lightbox modal `<img src="" alt="" class="ax-lightbox-img">`.
  3. File `wordpress/wp-content/themes/despace-child/page-ar-visualizer.php` (dòng 111 & 344) chứa `<img id="arRoomImage" class="ar-room-image" alt="Your room" />` và `<img id="arModalProductImage" src="" alt="" />`.
- **Selector / File liên quan:**
  - `wordpress/wp-content/themes/despace-child/template-parts/modal-room-visualizer.php:311` (`#axModalProductThumb`)
  - `wordpress/wp-content/themes/despace-child/woocommerce/content-single-product.php` (`.ax-lightbox-img`)
  - `wordpress/wp-content/themes/despace-child/page-ar-visualizer.php:111, 344` (`#arRoomImage`, `#arModalProductImage`)
- **Giải pháp đề xuất:**
  Gán placeholder data URI chuẩn: `src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"` cho tất cả các thẻ `<img>` chờ dữ liệu JavaScript.

---

### [BUG-02] [HIGH] Lỗi 404 Stylesheet Astra Parent Theme trên tất cả các trang
- **Mức độ:** High
- **Trang & Viewport:** Toàn bộ các trang trên hệ thống.
- **Các bước tái hiện:**
  1. Tải trang bất kỳ.
  2. Kiểm tra Console và Network Tab.
  3. Báo lỗi: `GET http://localhost:8090/wp-content/themes/astra/style.css net::ERR_ABORTED 404 (Not Found)`.
- **Mô tả hiện tượng:**
  Browser cố gắng nạp file `style.css` của theme cha `astra`, nhưng theme `astra` không tồn tại trong thư mục themes (child theme `despace-child` là theme độc lập tự chứa toàn bộ CSS).
- **Nguyên nhân kỹ thuật:**
  File `wordpress/wp-content/themes/despace-child/functions.php` (dòng 50) gọi:
  `wp_enqueue_style( 'astra', get_template_directory_uri() . '/style.css', array(), null );` mà không kiểm tra xem file có tồn tại hay không.
- **Selector / File liên quan:**
  - `wordpress/wp-content/themes/despace-child/functions.php:50`
- **Giải pháp đề xuất:**
  Bọc điều kiện `if ( file_exists( get_template_directory() . '/style.css' ) )` trước khi enqueue `astra`.

---

### [BUG-03] [HIGH] Thanh Category Subnav và nút "Ưu đãi đặc biệt" bị đẩy ra ngoài và cắt mất trên Laptop (1024px)
- **Mức độ:** High
- **Trang & Viewport:** Tất cả các trang có header trên màn hình Laptop / Tablet ngang (1024 × 768).
- **Các bước tái hiện:**
  1. Mở màn hình với viewport width = 1024px.
  2. Quan sát thanh danh mục sản phẩm phụ nằm ngay dưới header bar (`.fv-header__subnav`).
- **Mô tả hiện tượng:**
  Tổng chiều rộng tự nhiên của các link danh mục là 1161px, trong khi container 1024px chỉ rộng ~976px. Nút `✦ Ưu đãi đặc biệt` (Special Offers) và link danh mục cuối bị đẩy ra ngoài cạnh phải tới 137px và bị `overflow: hidden` của viewport cắt mất hoàn toàn. Người dùng không thể thấy và không thể click vào "Ưu đãi đặc biệt".
  Đồng thời, các mega menu dropdown hover của các mục bên phải cũng tràn ra ngoài mép màn hình tới 211px.
- **Nguyên nhân kỹ thuật:**
  Tại dòng 8740-8748 trong `style.css`, rule `@media (min-width: 1024px)` áp đặt `overflow: visible !important;` cho `.fv-header__subnav-cats`, nhưng không giảm `gap` và kích thước font chữ cho khoảng màn hình 1024px - 1199px.
- **Selector / File liên quan:**
  - `wordpress/wp-content/themes/despace-child/style.css:7168-7175`, `8740-8748`
- **Giải pháp đề xuất:**
  - Thêm media query cho khoảng 1024px – 1199px: điều chỉnh `gap: clamp(8px, 1.2vw, 16px);`, `font-size: 12px;`, `padding: 0 8px;`.
  - Căn chỉnh mega menu của các mục danh mục bên phải theo `right: 0; left: auto;` và `max-width: calc(100vw - 32px);` để không bao giờ tràn lề phải.

---

### [BUG-04] [HIGH] Bảng giỏ hàng (Cart Table) bị méo, bóp nghẹt chữ và cụm dock nổi đè lên nút thao tác trên Mobile (390px) & Tablet (768px)
- **Mức độ:** High
- **Trang & Viewport:** Trang Giỏ hàng (`/cart/`) trên màn hình Mobile (390 × 844) và Tablet (768 × 1024) khi có sản phẩm và khi trống.
- **Các bước tái hiện:**
  1. Thêm một sản phẩm vào giỏ hàng (hoặc đăng nhập tài khoản `customer`).
  2. Chuyển sang viewport Mobile 390px hoặc Tablet 768px và vào trang `/cart/`.
- **Mô tả hiện tượng:**
  1. Bảng giỏ hàng `.ax-cart-table` giữ nguyên cấu trúc 5 cột dạng table HTML truyền thống (`Remove`, `Product`, `Price`, `Quantity`, `Subtotal`). Mỗi cột bị co hẹp lại chỉ còn 50-70px, làm tiêu đề sản phẩm bị bóp nghẹt thành 1 chữ mỗi dòng theo chiều dọc ("Tủ / sách / cánh / kính...").
  2. Cụm nút liên hệ nổi `.ax-chat-dock` (Zalo, Facebook, Mascot AI) cố định ở góc dưới bên phải (`right: 12px; bottom: 72px;`) đè trực tiếp lên mép phải vùng nút thao tác giỏ hàng, che khuất một phần nút "Tiến hành thanh toán" (`.ax-checkout-btn`), bộ tăng giảm số lượng và giá sản phẩm.
- **Nguyên nhân kỹ thuật:**
  1. Trong `assets/css/wishlist-cart.css` (dòng 896-899), `@media (max-width: 768px)` chỉ đặt `.ax-cart-table { display: block; overflow-x: auto; }` mà không chuyển đổi các hàng `tr.ax-cart-item` thành card view responsive.
  2. `.ax-chat-dock` hiển thị cố định nổi trên toàn trang mà chưa có rule ẩn trên trang giỏ hàng khi ở kích thước màn hình nhỏ hẹp.
- **Selector / File liên quan:**
  - `wordpress/wp-content/themes/despace-child/assets/css/wishlist-cart.css:880-1022`
  - `wordpress/wp-content/themes/despace-child/style.css:8815-8822`
  - `wordpress/wp-content/themes/despace-child/page-cart.php`
- **Giải pháp:**
  1. Chuyển đổi `.ax-cart-table` trên màn hình <= 768px sang dạng Card layout: ẩn thead, biến mỗi dòng sản phẩm thành một thẻ card bo góc thanh lịch (ảnh 72×72px bên trái, tiêu đề và phân loại bên phải, bộ tăng giảm số lượng và subtotal nằm gọn gàng bên dưới), đồng thời thêm `padding-bottom: 96px`.
  2. Thêm rule ẩn hoàn toàn `.ax-chat-dock` trên trang giỏ hàng cho Mobile & Tablet (`@media (max-width: 1023.98px) { body.woocommerce-cart .ax-chat-dock { display: none !important; } }`), giải phóng 100% không gian cho các nút thao tác giỏ hàng và thanh toán.

---

### [BUG-05] [MEDIUM] Menu di động mở ra bị cụm nút nổi (Floating Dock) đè lên link và biểu tượng burger không chuyển thành dấu "X"
- **Mức độ:** Medium
- **Trang & Viewport:** Menu di động trên Mobile (390 × 844) và Tablet (< 1024px).
- **Các bước tái hiện:**
  1. Mở trang chủ trên Mobile 390px.
  2. Bấm vào nút hamburger `#furniva-burger`.
  3. Quan sát biểu tượng icon và cụm nút nổi ở góc dưới.
- **Mô tả hiện tượng:**
  1. Nút hamburger vẫn giữ nguyên 3 gạch ngang chứ không chuyển thành dấu "X" (close), khiến người dùng khó nhận biết cách đóng menu.
  2. Cụm nút liên hệ nổi che khuất các link menu cuối cùng ("Liên hệ", "Giới thiệu").
- **Nguyên nhân kỹ thuật:**
  1. Hàm `furniva_svg('burger')` trong `functions.php` trả về 1 thẻ `<path d="M3 6h18M3 12h18M3 18h18"/>` duy nhất, trong khi CSS animation nhắm vào selector `#furniva-burger svg line`. Đồng thời, phép biến đổi CSS chưa thiết lập `transform-box: view-box; transform-origin: 12px 12px;` và thứ tự `rotate` / `translateY` chưa đồng trục, dẫn tới việc các nét không cắt nhau ở tâm.
  2. Trong `assets/js/main.js`, sự kiện click burger chưa toggle class `is-menu-open` cho `body` (CSS dòng 9696 ẩn dock khi có `body.is-menu-open`), đồng thời thiếu xử lý đóng menu bằng phím Escape, click bên ngoài và click link điều hướng.
- **Selector / File liên quan:**
  - `wordpress/wp-content/themes/despace-child/functions.php:428` (`furniva_svg('burger')`)
  - `wordpress/wp-content/themes/despace-child/style.css:8795-8815`
  - `wordpress/wp-content/themes/despace-child/assets/js/main.js:40-66`
- **Giải pháp:**
  1. Cập nhật `functions.php`: cấu trúc SVG burger gồm 3 thẻ `<line>` riêng biệt (`y=6`, `y=12`, `y=18`).
  2. Cập nhật `style.css`: đặt `transform-box: view-box; transform-origin: 12px 12px;`, khi active áp dụng `rotate(45deg) translateY(6px)` cho nét 1, `opacity: 0` cho nét 2, và `rotate(-45deg) translateY(-6px)` cho nét 3. Hai nét chéo cắt nhau đối xứng tuyệt đối tại tọa độ trung tâm (12, 12), tạo thành biểu tượng dấu "X" hoàn hảo.
  3. Cập nhật `assets/js/main.js`: hỗ trợ đóng/mở toàn diện bằng: Click nút burger (chuyển đổi burger/X và cập nhật `aria-expanded`), phím `Escape`, click bên ngoài menu, và click link điều hướng di động. Cập nhật đồng thời `body.is-menu-open` để ẩn dock nổi và khóa scroll nền.

---

### [BUG-06] [MEDIUM] Trùng lặp thanh chọn ngôn ngữ (VI | EN) khi mở Menu di động
- **Mức độ:** Medium
- **Trang & Viewport:** Menu di động trên Mobile (390 × 844).
- **Các bước tái hiện:**
  1. Mở trang trên Mobile.
  2. Nhìn vào thanh header: đã có nút gạt `VI | EN`.
  3. Bấm mở menu di động: ở dòng đầu tiên của menu lại xuất hiện thêm một nút gạt `VI | EN` y hệt ngay phía dưới.
- **Mô tả hiện tượng:**
  Hai nút gạt ngôn ngữ xuất hiện đè liền kề nhau, gây rối mắt và thừa thãi trên giao diện di động.
- **Nguyên nhân kỹ thuật:**
  Thanh header đã hiển thị `.fv-header-language`, trong khi template `header.php` (dòng 210) vẫn in thêm `<li class="f-mobile-language">` trong `#furniva-mobile-nav`.
- **Selector / File liên quan:**
  - `wordpress/wp-content/themes/despace-child/style.css`
  - `wordpress/wp-content/themes/despace-child/header.php:210`
- **Giải pháp đề xuất:**
  Ẩn `.f-mobile-nav .f-mobile-language` trong CSS khi màn hình di động đã hiển thị ngôn ngữ trên thanh header.

---

### [BUG-07] [LOW / A11Y] Kích thước vùng bấm (Tap Target) của nút Menu di động nhỏ hơn 44px
- **Mức độ:** Low / Accessibility
- **Trang & Viewport:** Mobile & Tablet (< 1024px).
- **Mô tả hiện tượng:**
  Nút hamburger `#furniva-burger` có kích thước `36px × 36px`, thấp hơn tiêu chuẩn tiếp cận Web WCAG 2.5.5 / 2.5.8 (khuyến nghị tối thiểu 44 × 44px cho tương tác cảm ứng).
- **Nguyên nhân kỹ thuật:**
  CSS dòng 6996 & 7099 đặt `width: 36px !important; height: 36px !important;`.
- **Selector / File liên quan:**
  - `wordpress/wp-content/themes/despace-child/style.css:6996`, `7099`
- **Giải pháp đề xuất:**
  Nâng kích thước nút lên `min-width: 44px; min-height: 44px; width: 44px; height: 44px;`.

---

## 4. BẢNG PHÂN LOẠI TRẠNG THÁI LỖI TRƯỚC VÀ SAU KHI SỬA

| Mã lỗi | Mức độ | Hiện tượng chính | Đã xác nhận/Tái hiện | Đã sửa | Đã xác minh lại |
| :--- | :---: | :--- | :---: | :---: | :---: |
| **BUG-01** | **Blocker** | Thẻ img `src=""` gây tải lại toàn bộ trang làm ảnh | [x] Đã tái hiện | [x] Đã sửa | [x] **PASS** (0 broken img, 0 console errors trên 11 trang) |
| **BUG-02** | **High** | 404 Stylesheet Astra parent theme trên 100% trang | [x] Đã tái hiện | [x] Đã sửa | [x] **PASS** (0 404 network errors, astra guarded) |
| **BUG-03** | **High** | Thanh Subnav và nút Ưu đãi đặc biệt bị cắt mất trên Laptop 1024px | [x] Đã tái hiện | [x] Đã sửa | [x] **PASS** (Subnav hiển thị trọn vẹn, right=865px, 0 item clipped) |
| **BUG-04** | **High** | Bảng giỏ hàng méo chữ & dock nổi đè lên nút trên Mobile/Tablet | [x] Đã tái hiện | [x] Đã sửa | [x] **PASS** (Card layout active, dock hidden on cart, 0 overlap, 0 overflow) |
| **BUG-05** | **Medium** | Biểu tượng burger không thành X, dock nổi đè lên link menu | [x] Đã tái hiện | [x] Đã sửa | [x] **PASS** (Biểu tượng X chuẩn xác, dock ẩn, đóng/mở 4 cơ chế PASS) |
| **BUG-06** | **Medium** | Nút chuyển ngôn ngữ VI/EN bị lặp lại 2 lần trên Mobile | [x] Đã tái hiện | [x] Đã sửa | [x] **PASS** (duplicateLanguageHidden: true) |
| **BUG-07** | **Low/A11y** | Vùng bấm nút hamburger 36px nhỏ hơn chuẩn 44px | [x] Đã tái hiện | [x] Đã sửa | [x] **PASS** (Touch target 44×44px đạt chuẩn WCAG 2.5.5) |

---

## 5. THỰC HIỆN SỬA LỖI VÀ KẾT QUẢ XÁC MINH CHI TIẾT

Toàn bộ 7 lỗi giao diện (Blocker, High, Medium, Low) đã được xử lý triệt để và kiểm chứng tự động bằng script Playwright chính thức của dự án (`tests/visual/frontend-audit.spec.cjs`):

1. **Sửa BUG-01 (Blocker):**
   - Đã thay placeholder data URI 1x1 GIF chuẩn cho `<img id="axModalProductThumb">` trong `modal-room-visualizer.php`, `<img class="ax-lightbox-img">` trong `content-single-product.php`, và `<img id="arRoomImage">`, `<img id="arModalProductImage">` trong `page-ar-visualizer.php`.
   - Kết quả xác minh: `brokenImageCount: 0` trên toàn bộ 11/11 trang.

2. **Sửa BUG-02 (High):**
   - Đã bọc `if ( file_exists( get_template_directory() . '/style.css' ) )` trước khi gọi `wp_enqueue_style( 'astra', ... )` trong `functions.php`.
   - Kết quả xác minh: `status404Count: 0` trên toàn bộ 11/11 trang.

3. **Sửa BUG-03 (High):**
   - Đã bổ sung media query cho màn hình Laptop 1024px – 1279.98px trong `style.css`: điều chỉnh padding container, gap (6px), ẩn caret decorative, và clamp mega menu với `right: 0; left: auto; max-width: calc(100vw - 32px)`.
   - Kết quả xác minh: Tọa độ cạnh phải của mục cuối cùng `✦ Ưu đãi đặc biệt` đạt 865px (nằm trọn trong khung 1024px, dư +159px lề an toàn), 0 mục bị clipped.

4. **Sửa BUG-04 (High):**
   - Đã tái cấu trúc `.ax-cart-table` trên màn hình `<= 768px` trong `wishlist-cart.css`: chuyển đổi từ bảng 5 cột bị bóp nghẹt thành danh sách thẻ sản phẩm (Card layout) sang trọng, phân bổ ảnh 72x72px, tiêu đề 2 dòng, giá, stepper số lượng và nút xóa gọn gàng.
   - Đã thêm CSS ẩn hoàn toàn `.ax-chat-dock` trên trang giỏ hàng ở Mobile (390px) và Tablet (768px).
   - Kết quả xác minh:
     - Mobile (390×844) có sản phẩm: `dockDisplay: 'none'`, `overlapsCheckout: false`, `scrollWidth: 390px`, `hasHorizontalOverflow: false`. Ảnh: `cart_mobile_fixed.png`.
     - Tablet (768×1024) có sản phẩm: `dockDisplay: 'none'`, `overlapsCheckout: false`, `scrollWidth: 768px`, `hasHorizontalOverflow: false`. Ảnh: `cart_tablet_fixed.png`.
     - Giỏ hàng trống (390px & 768px): Dock ẩn hoàn toàn, không che lấp nút "Quay lại cửa hàng". Ảnh: `cart_empty_mobile.png`, `cart_empty_tablet.png`.

5. **Sửa BUG-05 & BUG-06 & BUG-07 (Medium & Low):**
   - Cấu trúc lại SVG burger thành 3 `<line>` trong `functions.php`, áp dụng `transform-box: view-box; transform-origin: 12px 12px;` và xoay đối xứng trong `style.css`.
   - Bổ sung xử lý đóng/mở hoàn chỉnh trong `assets/js/main.js` (click burger, phím `Escape`, click ra ngoài, click link).
   - Kết quả xác minh tự động:
     - Trạng thái đóng: `ariaExpanded: "false"`, `isActive: false`, `lineCount: 3`.
     - Trạng thái mở: `ariaExpanded: "true"`, `isActive: true`, `line2Opacity: "0"`, `line1Rect: { x: 40, y: 54, w: 11, h: 11 }`, `line3Rect: { x: 40, y: 54, w: 11, h: 11 }` (2 nét chéo giao nhau đối xứng thành dấu X). Ảnh: `mobile_menu_fixed.png`.
     - Đóng bằng click lại: `ariaExpanded: "false"`, `isActive: false`.
     - Đóng bằng phím Escape: `ariaExpanded: "false"`, `isActive: false`.
     - Đóng bằng click bên ngoài: `ariaExpanded: "false"`, `isActive: false`.
     - Kích thước touch target: `44px × 44px` (đạt chuẩn WCAG 2.5.5).

---

## 6. TÍNH TÁI LẬP & BẰNG CHỨNG KIỂM THỬ TỰ ĐỘNG (REPRODUCIBILITY & EVIDENCE)

Tất cả các khẳng định và kết quả kiểm thử trong báo cáo đều được bảo chứng bằng script kiểm thử tự động lưu cố định trong repo và file log kết quả machine-readable:

- **Script kiểm thử lưu tại:** `tests/visual/frontend-audit.spec.cjs`
- **Cấu hình độc lập (Cross-Platform / Zero Machine Path):** Đã cấu hình `package.json` với `playwright-core` cục bộ. Bất kỳ thành viên nào cũng có thể cài đặt và chạy ngay mà không cần biến đường dẫn riêng:
  ```bash
  npm install
  ```
- **Kiến trúc Test Harness chuẩn mực (Chuẩn hóa phát hiện lỗi mạng & Đánh giá tổng hợp):**
  - **Giám sát mạng 2 tầng (Dual-layer Network Monitoring):** Tách biệt rõ ràng lỗi mạng vật lý/kết nối (`page.on('requestfailed')` -> `networkFailures`, ví dụ DNS, net::ERR_CONNECTION_REFUSED) với lỗi phản hồi HTTP máy chủ (`page.on('response')` với status >= 400 -> `httpErrors`, bao gồm `status404Count`).
  - **Đánh giá trạng thái tổng thể & Exit Code:** 
    - Nếu có bất kỳ lỗi nào (`FAIL`): `overallStatus = 'FAIL'`, đặt `process.exitCode = 1`.
    - Nếu thiếu thông tin xác thực (`TEST_USER` / `TEST_PASS`): ghi nhận rõ ràng `BLOCKED` cho bài test giỏ hàng, `overallStatus = 'BLOCKED'`, tuyệt đối KHÔNG tính là `PASS`.
    - Khi tất cả bài test vượt qua: `overallStatus = 'PASS'`, `process.exitCode = 0`.
- **Câu lệnh chạy kiểm thử chuẩn (Đọc thông tin đăng nhập từ biến môi trường, bảo mật tuyệt đối):**
  - **Trên Windows PowerShell:**
    ```powershell
    $env:TEST_USER="<username>"; $env:TEST_PASS="<your_password>"; npm run test:visual
    ```
  - **Trên Linux / macOS / Bash:**
    ```bash
    TEST_USER="<username>" TEST_PASS="<your_password>" npm run test:visual
    ```
  - *Lưu ý:* Nếu không truyền `TEST_USER` và `TEST_PASS`, script sẽ tự động ghi nhận trạng thái `BLOCKED` một cách minh bạch cho bài test cần xác thực giỏ hàng, không làm crash toàn bộ suite, không bao giờ để lộ mật khẩu trong code repository, và không tính trạng thái này là PASS.
- **Bằng chứng dữ liệu chi tiết:** `docs/reports/frontend-audit-results.json` (ghi nhận chi tiết `summary`, `networkFailures`, `httpErrors`, metrics viewport và các tương tác UI).
- **Bộ ảnh chụp bằng chứng (Screenshots):**
  - Menu di động với biểu tượng dấu X hoàn chỉnh: `docs/reports/screenshots/mobile_menu_fixed.png`
  - Giỏ hàng Mobile (390px) có sản phẩm (Card layout, không bị dock che, có nút checkout): `docs/reports/screenshots/cart_mobile_fixed.png`
  - Giỏ hàng Tablet (768px) có sản phẩm (Không bị dock che, có nút checkout): `docs/reports/screenshots/cart_tablet_fixed.png`
  - Giỏ hàng Mobile trống (Có thông báo trống và nút bắt đầu mua sắm): `docs/reports/screenshots/cart_empty_mobile.png`
  - Giỏ hàng Tablet trống: `docs/reports/screenshots/cart_empty_tablet.png`
  - Header Subnav trên Laptop 1024px: `docs/reports/screenshots/laptop_subnav_fixed.png`
  - Ảnh chụp toàn bộ 11 trang × 4 viewport (Desktop, Laptop, Tablet, Mobile) lưu tại `docs/reports/screenshots/`.
