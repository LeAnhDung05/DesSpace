# BÁO CÁO BẢO TOÀN VÀ CHUYỂN ĐỔI GIAO DIỆN (UI MIGRATION)

> **Mã tài liệu:** `docs/03-ui-migration.md`  
> **Dự án:** Re-platforming BTTTS_5 → DeSpace  
> **Đích chuyển đổi:** `wordpress/wp-content/themes/despace-child`  
> **Cam kết:** BẢO TOÀN NGUYÊN VẸN 100% GIAO DIỆN, THIẾT KẾ, TYPOGRAPHY, HIỆU ỨNG VÀ TRẢI NGHIỆM NGƯỜI DÙNG BTTTS_5.

---

## 1. NGUYÊN TẮC BẢO TOÀN THIẾT KẾ

Thiết kế của BTTTS_5 được định vị là **Architectural Furniture Sanctuary** kết hợp:
- *Apple Simplicity* (Tối giản sắc nét, bo cong viên thuốc `border-radius: 9999px`, khoảng trắng siêu thoáng).
- *Muji Serenity* (Sự tĩnh lặng, mộc mạc, cân bằng thị giác, tôn vinh chất liệu gỗ tự nhiên).
- *Scandinavian Warmth* (Bảng màu thạch cao ấm `#FAF8F5`, đá sỏi `#F4EFE8`, điểm nhấn sâm panh `#C5A880`, chữ đen than `#191816`).
- *Editorial Typography* (Sự kết hợp giữa font hình học Plus Jakarta Sans và nét nghiêng cổ điển Newsreader Italic).

**QUY TẮC BẢO TOÀN:**
1. Giữ nguyên 100% tệp tin CSS nền tảng: `style.css` (hơn 9,900 dòng, 226KB), `homepage-clone.css` (68KB), `my-account.css` (44KB), `ax-room-visualizer.css` (26KB), `ar-visualizer.css` (21KB), `animations.css` (17KB), `wishlist-cart.css` (17KB), `performance.css` (15KB), `bento-edit.css` (14KB), `single-product.css` (13KB), `accessibility.css` (8KB).
2. Định nghĩa hệ thống token CSS mới `--despace-*` và liên kết ánh xạ 100% tương thích ngược với các biến `--furniva-*`.
3. Giữ nguyên toàn bộ 48 tệp hình ảnh thương hiệu, bento grid, room sets, hero banner tại `assets/images/`.

---

## 2. BẢNG ĐỐI CHIẾU THÀNH PHẦN GIAO DIỆN

| Khu vực | Thành phần giao diện gốc (BTTTS_5) | Vị trí đích (DeSpace Child Theme) | Chi tiết bảo toàn | Trạng thái |
|---|---|---|---|---|
| **Header** | Top announcement bar xoay vòng | `themes/despace-child/header.php` | Thông báo ưu đãi, giao hàng, bảo hành 5 năm, mã `WELCOME10`. | VERIFIED |
| **Header** | Brand Logo & Hamburger mobile | `themes/despace-child/header.php` | Logo thương hiệu sang trọng, icon SVG burger không layout 0px. | VERIFIED |
| **Header** | Mega Menu đa cấp & Walker | `themes/despace-child/inc/template-tags.php` | Hiển thị 6 danh mục kèm ảnh đại diện và liên kết Shop All. | VERIFIED |
| **Header** | Live Search Bar | `themes/despace-child/header.php` | Tích hợp FiboSearch AJAX gợi ý sản phẩm, giá, ảnh thumb, SKU. | VERIFIED |
| **Header** | Bộ đếm Wishlist & Mini-cart | `themes/despace-child/header.php`, `js/main.js` | Badge số lượng cập nhật động qua AJAX không reload trang. | VERIFIED |
| **Trang chủ** | Act I — The Monumental Hero | `themes/despace-child/front-page.php` | Hero canvas tràn viền bo góc 34px, CTA viên thuốc, ảnh Scandinavian. | VERIFIED |
| **Trang chủ** | Act II — The Manifesto of Stillness | `themes/despace-child/front-page.php` | Đoạn tuyên ngôn triết lý sống tĩnh lặng với Newsreader Italic. | VERIFIED |
| **Trang chủ** | Act III — Curated Sanctuaries | `themes/despace-child/front-page.php`, `bento-edit.css` | Bento grid 6 không gian sống (Living, Dining, Bedroom, Workspace, Lighting, Decor). | VERIFIED |
| **Trang chủ** | Act IV — The Icon Story | `themes/despace-child/front-page.php` | Khối phân tích sản phẩm biểu tượng Haven Lounge Suite / Tủ Metro. | VERIFIED |
| **Trang chủ** | Act V — The Permanent Archive | `themes/despace-child/front-page.php` | Lưới sản phẩm nổi bật kèm swatches màu và badge sale. | VERIFIED |
| **Trang chủ** | Act VI — The Material Dialogue | `themes/despace-child/front-page.php` | Banner điện ảnh khổ lớn, 1-Click Copy mã `WELCOME10` kèm Toast. | VERIFIED |
| **Trang chủ** | Act VII — Architectural Journal | `themes/despace-child/front-page.php` | 3 bài viết chuyên luận kiến trúc và gỗ tự nhiên. | VERIFIED |
| **Trang chủ** | Act VIII — The Commitments | `themes/despace-child/front-page.php` | 4 cam kết dịch vụ (Bảo hành 5 năm, White-glove, 100% Solid wood, 3D Scan). | VERIFIED |
| **Cửa hàng** | Editorial Shop Hero & Quick-Pills | `themes/despace-child/woocommerce.php` | Banner tiêu đề kiến trúc, thanh chọn nhanh danh mục dạng pill. | VERIFIED |
| **Cửa hàng** | Grid Switcher (3 cột ↔ 4 cột) | `themes/despace-child/woocommerce.php`, `main.js` | Bộ chuyển đổi layout linh hoạt, lưu trạng thái vào `localStorage`. | VERIFIED |
| **Cửa hàng** | Active Filters Bar | `themes/despace-child/woocommerce.php` | Chip hiển thị tiêu chí lọc đang chọn kèm nút gỡ nhanh và "Clear all". | VERIFIED |
| **Cửa hàng** | AJAX Filter Sidebar & Shimmer | `themes/despace-child/woocommerce.php`, `template-tags.php` | Lọc theo Danh mục, Giá, Màu sắc, Tồn kho. Shimmer loading khi fetch. | VERIFIED |
| **Thẻ sản phẩm** | Product Card Card UI | `themes/despace-child/woocommerce/content-product.php` | Bo góc 24px, hover nâng nhẹ, swatches preview màu tròn, nút AR quick. | VERIFIED |
| **Chi tiết SP** | Gallery & Zoom Lightbox | `themes/despace-child/woocommerce/content-single-product.php` | Gallery ảnh độ phân giải cao, thumbnails cuộn mượt mà. | VERIFIED |
| **Chi tiết SP** | Swatch UI & Stepper | `themes/despace-child/woocommerce/content-single-product.php` | Swatch màu nút tròn viền active ring, chip size, stepper `[-] [1] [+]`. | VERIFIED |
| **Chi tiết SP** | Nút Add to Cart & Buy Now | `themes/despace-child/woocommerce/content-single-product.php` | Nút đen pill cong mềm mại, 1-Click Buy Now chuyển thẳng Checkout. | VERIFIED |
| **Chi tiết SP** | 3D Modal & Cross-device AR | `themes/despace-child/woocommerce/content-single-product.php` | Modal xoay 360°, mã QR quét cho điện thoại, Apple Quick Look / Scene Viewer. | VERIFIED |
| **Chi tiết SP** | Sticky Add-to-Cart Bar | `themes/despace-child/functions.php`, `main.js` | Thanh cố định chân trang khi cuộn qua nút mua chính. | VERIFIED |
| **AR Studio** | AR Room Visualizer | `themes/despace-child/page-ar-visualizer.php` | Tải ảnh phòng, bài trí đồ nội thất 2D/3D, lưu/xóa sơ đồ, Add All to Cart. | VERIFIED |
| **Giỏ hàng** | Mini-Cart Off-canvas Drawer | `themes/despace-child/functions.php`, `main.js` | Drawer trượt từ phải, thanh Free Shipping $500, tăng giảm xóa AJAX. | VERIFIED |
| **Giỏ hàng** | Trang Cart đầy đủ | `themes/despace-child/page-cart.php` | Bảng giỏ hàng chuẩn mực, cập nhật số lượng và coupon tức thì. | VERIFIED |
| **Thanh toán** | Multi-step Checkout | `themes/despace-child/page.php` | Giao diện thẻ thanh toán cao cấp, chọn 63 tỉnh thành Việt Nam, COD + BACS. | VERIFIED |
| **Tài khoản** | My Account Suite | `themes/despace-child/woocommerce/myaccount/*` | Bộ 9 template tùy biến: Dashboard, Orders, Addresses, Login, Edit account. | VERIFIED |
| **Nội dung** | Blog & Bài viết đơn | `themes/despace-child/home.php`, `single.php` | Tạp chí kiến trúc phong cách Kinfolk/Cereal, thời gian đọc, mục lục. | VERIFIED |
| **Chân trang** | 5-Column Editorial Footer | `themes/despace-child/footer.php` | Đăng ký newsletter, liên kết danh mục, chính sách, Back to Top mượt. | VERIFIED |

---

## 3. KIỂM THỬ ĐÁP ỨNG TRÊN CÁC KÍCH THƯỚC MÀN HÌNH (RESPONSIVE VIEWPORTS)

Hệ thống CSS kế thừa từ BTTTS_5 đã được thiết kế sẵn và kiểm chứng chống tràn ngang (`scrollWidth === clientWidth`) tuyệt đối trên 4 kích thước tiêu chuẩn:

1. **Desktop (1440px × 900px):**
   - Lưới sản phẩm: 4 cột chuẩn kiến trúc.
   - Sidebar filter: Cố định bên trái (`position: sticky; top: 120px`).
   - Mega menu: Mở rộng toàn chiều ngang container.
   - Summary sản phẩm đơn: Cố định bên phải khi cuộn.

2. **Laptop (1280px × 800px):**
   - Padding container co giãn tự động qua hàm `clamp()`.
   - Lưới sản phẩm: 3 hoặc 4 cột tùy tùy chọn Grid Switcher.

3. **Tablet (768px × 1024px):**
   - Lưới sản phẩm: Tự động chuyển 3 cột cân đối.
   - Sidebar filter: Thu gọn thành thanh trượt hoặc drawer.
   - Header: Menu chính co gọn, giữ các icon thiết yếu (Search, Wishlist, Cart).

4. **Mobile (390px × 844px - iPhone standard):**
   - Lưới sản phẩm: 2 cột sắc nét, sử dụng `minmax(0, 1fr)` triệt tiêu tràn ngang.
   - Filter toggle: Nút "⚙ Bộ lọc" mở ngăn kéo lọc từ dưới lên (Off-canvas Bottom Sheet).
   - Menu: Icon Hamburger mở toàn màn hình ngăn nắp.
   - Sticky buy bar: Co gọn tối đa chỉ hiển thị giá và nút Mua.
