# BÁO CÁO KIỂM TOÁN CHUYÊN SÂU CODEBASE — DESPACE (`despace-child` + `despace-core`)

- **Ngày kiểm toán:** 2026-11-22
- **Phạm vi:** Child Theme `wordpress/wp-content/themes/despace-child` (kế thừa từ `furniva-child` trên nền Astra), Core Plugin `wordpress/wp-content/plugins/despace-core` (M1–M5), MU-Plugin `wordpress/wp-content/mu-plugins/despace-store-admin.*`, và môi trường Docker live (`http://localhost:8090`, 40 sản phẩm xuất bản, WooCommerce HPOS + TranslatePress + YITH Wishlist/QuickView + FiboSearch).
- **Kiểm tra cú pháp PHP (`php -l`):** **0 lỗi cú pháp** trên toàn bộ tệp `.php` của theme, plugin và mu-plugin.

---

## TỔNG QUAN PHÂN LOẠI MỨC ĐỘ

| Mức độ | Số lượng | Trọng tâm xử lý ở Pha 2 |
| --- | :---: | --- |
| **CRITICAL** | **3** | Hỏng bộ chuyển ngôn ngữ `/en/` (kẹt `/en/en/` & rò `?i=1`), Live Camera AR tự tắt ngay lập tức do thiếu class `.is-open`, lỗi 403 xóa Wishlist do lệch nonce/action |
| **HIGH** | **13** | Lệch ngưỡng Freeship (`$500` vs `5.000.000₫` vs WC flat_rate), chuỗi song ngữ/USD rò rỉ trong Modal 3D/AR & `screen-reader-text`, lọc `has_3d` / `on_sale=1` không hoạt động trên SSR & thiếu 5 SP, ẩn nút VI/EN trên mobile, bloat CSS/JS & trùng modal 3D, xung đột z-index & header 156px gây CLS |
| **MEDIUM** | **7** | Căn hàng Product Card desktop thiếu `line-clamp: 2`, thiếu hover kích thước trên card, thiếu poster/progress cho `<model-viewer>`, Mega-Menu thiếu hover-intent delay & dính trên tablet, thiếu thanh chuyển 2D↔3D↔AR trực quan trên PDP, Cart/Checkout sticky summary & Dashboard M5 lệch Design Tokens với Storefront |

---

## NHÓM 1: BACKEND & PHP INTEGRITY

### [HIGH-1.1] Trùng lặp Modal 3D, Enqueue CSS/JS thừa & Bloat Google Fonts
- **Vị trí:**
  - `wordpress/wp-content/plugins/despace-core/includes/Product-3D/Product3DModule.php` (lines 261–291, 498–527)
  - `wordpress/wp-content/themes/despace-child/functions.php` (lines 187, 344–366)
- **Thực trạng:**
  - Trang chi tiết sản phẩm đang tải **26 stylesheets**. `style.css` dài **11.418 dòng** chứa **1.193 `!important`** và **2 khối `:root` trùng nhau** (dòng 18 & dòng 4448); tổng cộng **1.752 `!important`** trên toàn bộ CSS theme + plugin.
  - `Product3DModule` của `despace-core` vừa enqueue `despace-3d.css` toàn cục, vừa chèn thêm một modal 3D thứ hai (`#despace-3d-modal`, `z-index: 999999`) vào `wp_footer` trên mọi trang, và nạp eager `model-viewer.min.js` (~350KB) trên `is_product()` làm vô hiệu hóa chiến lược lazy-load của `despace-child`.
  - `functions.php:344–352` tải `ax-room-visualizer.css` (2.414 dòng) ngay cả ở trang Checkout & Account dù `ax-room-visualizer.js` đã loại trừ các trang này.
  - `functions.php:187` tải đồng thời **7 họ font Google Fonts** (`Caveat`, `Cormorant Garamond`, `DM Serif Display`, `Inter`, `Newsreader`, `Playfair Display`, `Plus Jakarta Sans`) trong một request chặn render; thực tế hệ thống Editorial Luxury chỉ cần `Newsreader` + `Inter` (và `Caveat` riêng cho trang chủ).
  - Tồn tại các file JS không dùng (`assets/js/despace-3d-visualizer.js`, `despace-catalog.js`, `despace-theme.js`).
- **Hướng xử lý:** Tránh load `ax-room-visualizer.css` trên Checkout/Account; bỏ nạp eager duplicate modal/script của `Product3DModule` khi theme `despace-child` đã cung cấp 3D Studio modal thống nhất (giữ nguyên shortcode `[despace_3d]` & toàn bộ API/DB của M1); tinh gọn Google Fonts chỉ còn các font thực sự sử dụng (`Newsreader`, `Inter`, và `Caveat` chỉ trên front-page).

### [HIGH-1.2] Bộ lọc `has_3d=yes` và `on_sale=1` bị lệch dữ liệu & không lọc khi tải trang trực tiếp
- **Vị trí:**
  - `wordpress/wp-content/themes/despace-child/inc/template-tags.php` (lines 147–152)
  - `wordpress/wp-content/themes/despace-child/functions.php` (lines 785–792)
  - `wordpress/wp-content/themes/despace-child/assets/js/main.js` (lines 822–834)
  - `wordpress/wp-content/themes/despace-child/header.php` (lines 54, 61, 89, 214)
- **Thực trạng:**
  - Truy vấn DB thực tế: toàn bộ **40/40 sản phẩm** đều có GLB & USDZ hợp lệ trong bảng `wp_despace_product_3d_models`, nhưng `_despace_glb_attachment_id > 0` chỉ có ở **35/40 sản phẩm** → bộ lọc "Xem được 3D/AR" đếm sai và bỏ sót 5 sản phẩm.
  - Khi người dùng truy cập trực tiếp `/cua-hang/?has_3d=yes` (từ Bento Card 3D/AR ở trang chủ) hoặc `/cua-hang/?on_sale=1` (từ nút "✦ Ưu đãi đặc biệt" trên Category Bar), WordPress không lọc trong `pre_get_posts` và `main.js` chỉ gọi `restoreFiltersFromUrl()` mà **không** chạy `runFilter(false, true)`.
  - `header.php` gắn cứng `/shop/` thay vì dùng `$shop_url` chuẩn (`/cua-hang/`).
  - `inc/template-tags.php:41` truy vấn slug danh mục `chairs` không tồn tại trong DB hiện tại.
- **Hướng xử lý:** Đồng bộ truy vấn `has_3d` đọc chuẩn từ bảng `wp_despace_product_3d_models` kết hợp fallback meta; bổ sung hook `pre_get_posts` xử lý sạch `has_3d=yes`, `on_sale=1`, `availability=instock`, `price_min`/`price_max` ngay từ SSR và đồng bộ chip filter đang chọn trên client; thay toàn bộ link `/shop/` tĩnh trong `header.php` bằng `$shop_url`.

---

## NHÓM 2: LỖI SONG NGỮ (VI/EN), URL & DỮ LIỆU UX

### [CRITICAL-2.1] Bộ chuyển ngôn ngữ VI/EN vỡ nặng trên `/en/` (tạo `/en/en/`, không quay về VI được) và rò tham số rác (`?i=1`, `?i=2`)
- **Vị trí:** `wordpress/wp-content/themes/despace-child/inc/i18n.php` (lines 541–590)
- **Kiểm chứng live:**
  - Truy cập `http://localhost:8090/?i=1` → link VI là `/?i=1`, link EN là `/en/?i=1` (giữ nguyên query rác `?i=1`).
  - Truy cập `http://localhost:8090/en/?i=2` → link VI bị xuất thành **`http://localhost:8090/en/?i=2`** (bấm VI vẫn ở tiếng Anh!), link EN bị nhân đôi thành **`http://localhost:8090/en/en/?i=2`**!
- **Nguyên nhân gốc:** `despace_language_switcher_html()` lấy trực tiếp `$_SERVER['REQUEST_URI']` không strip các query param rác từ hosting/tracking (`i`, `fbclid`, `gclid`, `utm_*`, `_gl`, `mc_cid`, `mc_eid`) và tin hoàn toàn kết quả của TranslatePress `get_url_for_language()` mà không chuẩn hóa/khử trùng lặp tiền tố `/en/`.
- **Hướng xử lý:** Xây dựng hàm chuẩn hóa URL sạch (`despace_clean_switcher_url()`): loại bỏ mọi param rác (`i`, `fbclid`, `gclid`, `utm_*`, ...), tách sạch tiền tố `/en/` ở đầu path (kể cả `/en/en/...`), dựng chuẩn tuyệt đối `$vi_url` (không có `/en/`) và `$en_url` (chính xác 1 tiền tố `/en/`).

### [HIGH-2.2] Rò rỉ chuỗi tiếng Anh/USD trên bản VI, chuỗi tiếng Việt trên bản EN & lỗi double-escape `screen-reader-text`
- **Vị trí:**
  - `wordpress/wp-content/themes/despace-child/header.php` (lines 57–59): hiển thị `"đơn từ $500"` (`$500 / 12.000.000 VNĐ`) trong khi giỏ hàng & mini-cart dùng ngưỡng `5.000.000 ₫`.
  - `wordpress/wp-content/themes/despace-child/inc/i18n.php` (lines 61–67, 624): thiếu map tiêu đề tiếng Anh cho các Page ID thật trong DB (`486, 487, 488, 489, 501, 514, 515`); filter `wc_price` trả về `(&approx; $...)` bị WooCommerce double-escape thành `(&amp;approx; $60)` trong `screen-reader-text` (124 chỗ trên `/en/`!).
  - `wordpress/wp-content/themes/despace-child/functions.php` (lines 516, 1049–1057, 1362–1492): `priceRange` hardcode `$%1$s – $%2$s`; chuyển ký hiệu `₫` sang bên trái (`₫1.500.000`) ở bản EN; thông báo lỗi AJAX (`Invalid security token`, `Cart not available`, `Invalid product`) chưa dịch VI/EN.
  - `wordpress/wp-content/themes/despace-child/template-parts/modal-room-visualizer.php` (40+ chuỗi): rò `Auto-Rotate`, `Reset Angle`, `SCAN TO OPEN MOBILE AR`, `Product Name`, `$0.00` trên VI và toàn bộ hướng dẫn AR/Camera tiếng Việt trên `/en/`.
  - `wordpress/wp-content/themes/despace-child/assets/js/ax-room-visualizer.js` (30+ chuỗi) & `assets/js/main.js` (lines 279–362, 1036, 1042): hardcode `$` trong sync giá biến thể (`'$' + Number(...).toFixed(2)`), hardcode tiếng Việt trong validate form liên hệ và UI 3D/AR.
  - `wordpress/wp-content/themes/despace-child/woocommerce/content-single-product.php` (lines 79, 351–375): hardcode `DESIGNER PICK` trên VI và câu chữ nội bộ (`"Tình trạng kho WooCommerce"`).
  - `wordpress/wp-content/themes/despace-child/inc/ar-visualizer-integration.php` (line 108): hardcode `"Rộng ... × Sâu ... × Cao ..."` ở cả bản EN.
- **Hướng xử lý:** Bổ sung filter `woocommerce_format_sale_price` chuẩn VI/EN không double-escape; thay `&approx;` bằng ký tự Unicode `≈`; giữ định dạng VND chuẩn (`1.500.000 ₫`) ở cả VI và EN; đồng bộ toàn bộ thông điệp Freeship về `5.000.000 ₫` (có chú thích quy đổi `≈ $200` ở bản EN); dịch đầy đủ 100% chuỗi trong `modal-room-visualizer.php`, `ax-room-visualizer.js` (qua `window.despaceArI18n`), `main.js`, `functions.php`, `content-single-product.php`, `i18n.php`.

### [HIGH-2.3] Search Quick-Tags tĩnh & trùng lặp cơ chế tìm kiếm
- **Vị trí:** `wordpress/wp-content/themes/despace-child/header.php` (lines 165–172, 242–248)
- **Thực trạng:** Các chip gợi ý tìm kiếm nhanh được viết cứng đường dẫn thay vì truy vấn động từ các danh mục `product_cat` thực sự có sản phẩm (`hide_empty => true`), và `header.php` tồn tại song song cả `#fv-header-search-bar` lẫn `<dialog id="furniva-search-dialog">` chứa `[fibosearch]`.
- **Hướng xử lý:** Sinh chip gợi ý động trực tiếp từ `get_terms('product_cat', ['hide_empty' => true, 'parent' => 0])` theo thứ tự số lượng sản phẩm/ưu tiên (đảm bảo 100% chip luôn trả ra danh mục có sản phẩm thật, hỗ trợ song ngữ & link chuẩn `/en/`). Đồng bộ luồng mở Search sạch sẽ, không xung đột aria/focus giữa bar và dialog.

---

## NHÓM 3: MODULE 3D/AR (M1 — `<model-viewer>` & `<dialog>`)

### [CRITICAL-3.1] Live Camera AR (`getUserMedia`) LUÔN bị hủy luồng ngay khi cấp quyền
- **Vị trí:** `wordpress/wp-content/themes/despace-child/assets/js/ax-room-visualizer.js` (lines 263, 271, 1257)
- **Thực trạng:**
  - `openModal()` (dòng 263) hiển thị modal bằng `$modal.fadeIn(200).attr('aria-hidden', 'false')` nhưng **KHÔNG bao giờ thêm class `.is-open`**.
  - Trong `startLiveCameraAR()` (dòng 1257), điều kiện guard kiểm tra `!$('#ax-room-visualizer-modal').hasClass('is-open')` → **luôn trả về `true`** → lập tức gọi `track.stop()` và hủy camera vừa mở! Kết quả: tính năng Camera AR Trực Tiếp thất bại âm thầm trên mọi thiết bị.
- **Hướng xử lý:** Thêm/gỡ nhất quán class `.is-open` trong `openModal()` / `closeModal()`, kết hợp khóa cuộn bằng class `body.ax-3d-modal-open` thay vì ghi đè inline `body.style.overflow`.

### [HIGH-3.2] Lệch metadata USDZ/GLB trong Postmeta, thiếu `preventDefault()` trên link Apple AR Quick Look & rủi ro 404/fallback
- **Vị trí:**
  - DB `wp_postmeta`: 32/40 sản phẩm thiếu `_despace_model_usdz`, 5/40 thiếu `_despace_glb_attachment_id` (trong khi bảng `wp_despace_product_3d_models` & thư mục `/wp-content/uploads/3d/<SKU>/` có đủ 40/40 file `.glb` và `.usdz` hợp lệ).
  - `wordpress/wp-content/themes/despace-child/inc/ar-visualizer-integration.php` (lines 45–125)
  - `wordpress/wp-content/themes/despace-child/assets/js/ax-room-visualizer.js` (lines 280–325, 878–899, 2115–2134)
- **Thực trạng:**
  - Khi `usdzUrl` rỗng hoặc chưa sẵn sàng, `setupARCard()` gán `href = currentProduct.usdzUrl || currentProduct.glbUrl` vào `<a id="axAppleArLink" rel="ar">` — trên iOS Safari, mở `.glb` qua `rel="ar"` bị lỗi tải file/không kích hoạt Quick Look; và nếu URL rỗng (`#`), click không có `preventDefault()` sẽ nhảy trang/đổi hash.
  - `ax_get_product_3d_info()` không tự kiểm tra file tồn tại trên đĩa / không tự suy luận `.usdz` cùng thư mục với `.glb` nếu postmeta thiếu.
  - `<model-viewer>` trong modal chưa gắn `poster` từ ảnh sản phẩm thật, chưa hiển thị thanh tiến trình thực theo sự kiện `progress` (`totalProgress`), và thiếu timeout fallback rõ ràng nếu asset 3D bị lỗi mạng.
  - Modal 3D chưa có keyboard **Focus Trap** (`Tab` / `Shift+Tab`) và không trả focus về nút kích hoạt sau khi nhấn `ESC` hoặc nút Đóng.
- **Hướng xử lý:** Đồng bộ backfill metadata `_despace_model_glb` / `_despace_model_usdz` / `_ar_enabled` cho đủ 40/40 sản phẩm; bổ sung cơ chế auto-resolve file `.usdz` cùng thư mục + verify `file_exists()` trong `ax_get_product_3d_info()`; chặn click bằng `e.preventDefault()` + thông báo lịch sự khi thiếu USDZ hợp lệ trên iOS (chuyển mượt sang Live Camera AR); bổ sung `poster`, thanh progress thực, error fallback có ảnh sản phẩm + nút thử lại, nút phóng to toàn màn hình & ẩn/hiện kích thước thực, focus trap chuẩn WAI-ARIA.

---

## NHÓM 4: HEADER 3 TẦNG, MEGA-MENU & RESPONSIVE

### [HIGH-4.1] Tổng chiều cao Header 3 tầng (156px) chiếm 21.7% viewport laptop & gây giật CLS khi cuộn
- **Vị trí:** `wordpress/wp-content/themes/despace-child/style.css` (lines 7312–7680, 8131–8144, 8709, 10333–10365)
- **Thực trạng:**
  - Trên laptop `1280×720` / `1366×768`, 3 tầng Header cao tổng cộng **156px** (`36px` marquee + `76px` main bar + `44px` subnav) và giữ nguyên độ cao khi cuộn xuống.
  - Trên mobile (`<= 768px`), `.fv-header.is-scrolled .fv-announcement` thu gọn `max-height: 0` bên trong sticky header gây dịch chuyển đột ngột nội dung phía dưới (CLS).
  - Dòng `8709` ghi `.fv-announcement:hover .fv-announcement__track { animation-play-state: running; }` làm marquee **không dừng khi rê chuột**.
- **Hướng xử lý:** Đặt `animation-play-state: paused` khi `:hover` / `:focus-within` và tôn trọng `prefers-reduced-motion`; thiết kế trạng thái sticky compact thông minh (không gây CLS): giữ chiều cao gọn gàng (~132px ở top, thu gọn padding thanh chính và subnav mượt mà bằng `transform`/compact bar khi cuộn qua ngưỡng an toàn).

### [HIGH-4.2] Nút chuyển ngôn ngữ VI/EN biến mất hoàn toàn trên Mobile/Tablet (`<= 1023.98px`) & Mega-Menu dính trên cảm ứng
- **Vị trí:**
  - `wordpress/wp-content/themes/despace-child/style.css` (lines 7563, 8960–8991, 9352–9359)
  - `wordpress/wp-content/themes/despace-child/header.php` (lines 221–239)
- **Thực trạng:**
  - Ở `@media (max-width: 1023.98px)`, dòng `7563` ẩn `.fv-header-language`, đồng thời dòng `9353` ẩn luôn `.f-mobile-nav .f-mobile-language { display: none !important; }` → người dùng trên `375px`, `768px`, `921px` **không thể chuyển đổi VI / EN**!
  - `#furniva-mobile-nav` chưa có ô tìm kiếm nhanh, chưa có backdrop che mờ và chuyển đổi chưa dạng Drawer trượt phân tầng mượt.
  - `.fv-subnav-mega` mở ngay tức thì qua CSS `:hover` (không có hover-intent delay nên dễ nháy đóng/mở khi rê chéo chuột) và trên màn cảm ứng/tablet (`<= 921px`) dễ bị kẹt mở đè lên sản phẩm; đồng thời `z-index: 120` bị trộn lẫn với hệ thang z-index rời rạc (`800–999999`).
- **Hướng xử lý:** Chuyển Mobile Navigation thành off-canvas Drawer chuẩn ở `<= 921px` (khớp breakpoint Astra!) có đầy đủ Search input, VI/EN switcher rõ ràng, danh mục sản phẩm có số lượng, link Tài khoản/Yêu thích, backdrop + khóa cuộn sạch. Trên desktop, bổ sung JS hover-intent delay (`110ms` open / `180ms` close) + tắt mega dropdown trên thiết bị `hover: none` / `<= 921px`.

---

## NHÓM 5: STOREFRONT (M2), CHECKOUT (M3) & DASHBOARD (M5)

### [CRITICAL-5.1] Lỗi 403 xóa Wishlist & Lệch đồng bộ Badge số lượng Giỏ hàng / Yêu thích khi thao tác AJAX
- **Vị trí:**
  - `wordpress/wp-content/themes/despace-child/assets/js/main.js` (lines 1132, 1182, 1202, 1485, 1521–1525, 1595, 1623, 1670, 1698, 1751–1776)
  - `wordpress/wp-content/themes/despace-child/functions.php` (lines 1542–1575)
  - `wordpress/wp-content/themes/despace-child/page-wishlist.php` (lines 114, 273)
- **Thực trạng:**
  - `main.js:1521` gửi `nonce: furnivaData.nonce` (`furniva_filter`) tới `furniva_wishlist_remove`, nhưng server (`functions.php:1544`) gọi `check_ajax_referer('furniva_wishlist', 'security')` → **luôn 403 Forbidden**!
  - Các hàm cập nhật giỏ hàng AJAX trong `main.js` cập nhật `.f-badge` nhưng bỏ sót `.f-cart-bnav-count` (thanh điều hướng mobile) và không ẩn/hiện badge khi số lượng bằng `0`.
  - `#axAddToWishlist` (PDP) gửi thiếu `security` (chỉ có `nonce`) và không hiển thị thông báo toast khi lỗi mạng / chưa đăng nhập.
- **Hướng xử lý:** Chuẩn hóa hàm helper `syncCartBadges(count, totalHtml)` & `syncWishlistBadges(count)` trong `main.js` cập nhật đồng loạt mọi vị trí (Header Desktop, Mobile Bottom Nav, Drawer, Wishlist Subtitle); cho `furniva_ajax_wishlist_remove` chấp nhận `furniva_verify_cart_or_filter_nonce()` và truyền `security: furnivaData.wishlistNonce`; tối ưu AJAX Add-to-Cart trên trang Wishlist không cần `location.reload()`.

### [MEDIUM-5.2] Product Card lệch chiều cao trên Desktop, thiếu Hover Kích Thước & Single Product chưa có bộ chuyển 2D ↔ 3D ↔ AR liền mạch
- **Vị trí:**
  - `wordpress/wp-content/themes/despace-child/woocommerce/content-product.php`
  - `wordpress/wp-content/themes/despace-child/style.css` (lines 1622–1911)
  - `wordpress/wp-content/themes/despace-child/woocommerce/content-single-product.php`
- **Thực trạng:**
  - `.f-card-title` chỉ có `line-clamp: 2` ở `@media (max-width: 768px)`, còn trên desktop tên sản phẩm dài 1–3 dòng làm hàng đánh giá/giá/nút Thêm vào giỏ bị nhấp nhô giữa các cột.
  - Dù 40/40 sản phẩm đều có kích thước thật (`L × W × H cm`) và mô hình 3D+AR, Product Card chưa hiển thị thanh xem nhanh kích thước khi hover (`dimension preview`) và badge `3D · AR` tối giản.
  - Trang chi tiết sản phẩm chưa có thanh chuyển chế độ trực quan **Ảnh 2D ↔ Không gian 3D ↔ Xem AR** ngay trên khung trưng bày chính, và khối thông số kỹ thuật / cam kết bảo hành 5 năm – đổi trả 30 ngày cần trình bày gọn dạng khối/accordion chuẩn showroom.
- **Hướng xử lý:** Khóa cứng `aspect-ratio: 4 / 4.2`, áp dụng `line-clamp: 2` + `min-height: 2.7em` cho `.f-card-title` trên mọi màn hình, thêm thanh hover kích thước tinh tế (`R × S × C cm`) và Quick Actions gọn (3D·AR / Wishlist / Xem nhanh). Trên PDP, thêm bộ chuyển chế độ trực quan `Ảnh 2D ↔ 3D 360° ↔ Xem AR` tích hợp ngay trên gallery chính cùng khối accordion thông số kỹ thuật & cam kết showroom.

### [MEDIUM-5.3] Cart/Checkout (M3) & Dashboard (M5) cần đồng bộ Design Tokens Editorial Luxury và nâng cấp Responsive Mobile
- **Vị trí:**
  - `wordpress/wp-content/themes/despace-child/style.css` (WooCommerce `[woocommerce_cart]` & `[woocommerce_checkout]`)
  - `wordpress/wp-content/plugins/despace-core/assets/css/admin-dashboard.css`
  - `wordpress/wp-content/plugins/despace-core/includes/Reports/Admin/DashboardPage.php`
  - `wordpress/wp-content/plugins/despace-core/assets/js/admin-dashboard.js`
  - `wordpress/wp-content/mu-plugins/despace-store-admin.css`
- **Thực trạng:**
  - Live `/gio-hang/` và `/thanh-toan/` chạy shortcode chuẩn WooCommerce (`[woocommerce_cart]` / `[woocommerce_checkout]`) nhưng thiếu thanh tiến trình miễn phí vận chuyển đồng bộ ngưỡng thực tế (`5.000.000 ₫`) ngay đầu giỏ hàng, và bố cục 2 cột sticky Order Summary trên Checkout cần tinh chỉnh sắc nét hơn về input focus/error state.
  - Dashboard M5 (`admin-dashboard.css` & `despace-store-admin.css`) đang dùng tông Indigo/Slate (`#4f46e5` / `#f8fafc`) lệch bản sắc ấm sang trọng của Storefront (`#F7F4EF` / `#FAF7F2` / `#9A6745` / `#1C1917`), đồng thời bảng Top 10 sản phẩm thiếu wrapper cuộn ngang (`overflow-x: auto`) cho mobile và chưa có trạng thái skeleton từng dòng bảng + nút thử lại khi API lỗi.
- **Hướng xử lý:** Bổ sung thanh Free Shipping Progress nhất quán trên Cart/Mini-Cart (đọc chung 1 nguồn ngưỡng `5.000.000 ₫`), nâng cấp Checkout 2 cột sticky Order Summary + focus/error states rõ ràng; đồng bộ 100% Design Tokens của Dashboard M5 & DeSpace Admin Cockpit theo bảng màu Editorial Luxury (`#FAF7F2`, `#9A6745`, `#1C1917`, `#E7E0D7`) **giữ nguyên phạm vi cô lập trong `.ds-cockpit-wrap` / `.despace-dashboard-wrap` để không ảnh hưởng giao diện WordPress gốc**, bổ sung đầy đủ trạng thái Loading Skeleton / Empty / Error (kèm nút thử lại) và responsive table mobile.

---

## KẾ HOẠCH TRIỂN KHAI TUẦN TỰ
1. **Pha 2 (Deep Bug Fixing):** Xử lý dứt điểm 100% lỗi **CRITICAL** & **HIGH** ở 5 nhóm trên (backfill DB metadata 3D, sửa VI/EN switcher & query param rác, sửa Live Camera AR `.is-open` + Apple Quick Look guard + focus trap, sửa 403 Wishlist & đồng bộ badge Cart/Wishlist, sửa `has_3d`/`on_sale=1` SSR+AJAX, dịch sạch 100% chuỗi VI/EN, dọn duplicate modal/CSS/fonts).
2. **Pha 3 (Editorial Luxury UI/UX Upgrade):** Chuẩn hóa Design Tokens & thang `z-index` thống nhất, tối ưu Header 3 tầng sticky không CLS + Mega-Menu hover-intent + Mobile Drawer `<= 921px`, hoàn thiện Product Card (uniform clamp + hover dimensions + Quick Actions), Single Product 2D↔3D↔AR showcase + spec accordion, Checkout 2 cột sticky + Free Shipping Progress, đồng bộ Design Tokens Dashboard M5 & responsive mobile.
3. **Pha 4 (Verification & Acceptance):** Chạy `php -l`, `node --check`, toàn bộ bộ test tự động (`tests/test-*.php` + `tests/test_desktop_app_core.py`), kiểm tra live HTTP/HTML/CSS/assets/overflow tại `375px`, `768px`, `921px`, `1280px`, `1440px`, commit cục bộ & đồng bộ vào bản chạy Docker local (**chưa `git push`**).
