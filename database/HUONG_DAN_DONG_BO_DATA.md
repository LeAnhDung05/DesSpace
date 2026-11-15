# HƯỚNG DẪN ĐỒNG BỘ DỮ LIỆU DESPACE DÀNH CHO THÀNH VIÊN NHÓM

Tài liệu này giải thích lý do vì sao khi bạn mới tải code về máy thì trang Cửa Hàng lại hiện **"0 sản phẩm được tìm thấy"** và hướng dẫn bạn cách đồng bộ toàn bộ **40 sản phẩm, danh mục, giá bán và cấu hình** vào WordPress trên máy cá nhân trong 1–2 phút.

---

## 1. NGUYÊN NHÂN TRANG CỬA HÀNG HIỆN "0 SẢN PHẨM"
* **Git chỉ lưu mã nguồn** (code PHP của theme `despace-child`, plugin `despace-core`, CSS, JavaScript).
* **Git không lưu cơ sở dữ liệu MySQL:** Toàn bộ 40 sản phẩm, 5 danh mục, thuộc tính màu sắc, đơn hàng và bài viết được lưu trữ trong Database MySQL.
* Khi bạn tạo một trang WordPress mới trên máy (ví dụ bằng LocalWP chạy cổng `localhost:10016`), database của bạn là database trắng tinh (chưa có sản phẩm nào). Do đó WooCommerce hiển thị:
  - `DANH MỤC 0`
  - `0 sản phẩm được tìm thấy`
  - Chế độ *"This page is in 'Coming soon' mode"* (Chế độ cửa hàng mới tạo).

---

## 2. CÁCH 1: NHẬP SẢN PHẨM NHANH QUA FILE CSV (KHUYÊN DÙNG - DỄ NHẤT)
*Không cần can thiệp database, làm trực tiếp trên giao diện Admin WordPress:*

1. Đăng nhập vào trang quản trị WordPress của bạn (ví dụ: `http://localhost:10016/wp-admin/`).
2. Vào menu: **Sản phẩm (Products) → Nhập (Import)** (hoặc nếu chưa có sản phẩm nào, bấm nút **"Bắt đầu nhập" / "Start import"** giữa màn hình).
3. Tại ô chọn tệp, chọn file:
   `database/despace_products_40.csv`
4. Bấm **Tiếp tục (Continue) → Chạy trình nhập dữ liệu (Run the importer)**.
5. Đợi khoảng 20–30 giây: Hệ thống sẽ tự động tạo đủ **40 sản phẩm chuẩn**, 5 danh mục (*Tủ giày, Tủ quần áo, Kệ sách, Tủ bếp, Tủ ngăn kéo & Sideboard*), các thông số kích thước, màu sắc và giá bán!
6. **Tắt chế độ Coming Soon:**
   - Trên thanh Admin Bar màu đen ở trên cùng, bấm vào nút **"Store coming soon"** → chọn **"Live"** (hoặc vào *WooCommerce → Cài đặt → Trang web* để kích hoạt).

---

## 3. CÁCH 2: IMPORT TOÀN BỘ DATABASE BẢNG GỐC DESPACE (ĐỒNG BỘ 100%)
*Dành cho thành viên muốn trang web của mình có đầy đủ 100% giống hệt máy Leader (gồm cả tài khoản, trang, bài viết blog, banner, menu, thiết lập đa ngôn ngữ...):*

### Dành cho thành viên dùng LocalWP (Local by Flywheel trên Windows):
1. Mở ứng dụng **LocalWP**, chọn trang web của bạn (ví dụ đang chạy `localhost:10016`).
2. Nhấp vào nút **"Open Site Shell"** (nút màu xám ngay dưới tên trang).
3. Màn hình dòng lệnh hiện lên, gõ 2 lệnh sau:
   ```bash
   # Bước 1: Import toàn bộ dữ liệu DeSpace
   wp db import database/despace_seed_data.sql

   # Bước 2: Đổi cổng URL từ localhost:8090 sang cổng của bạn (ví dụ 10016)
   wp search-replace "http://localhost:8090" "http://localhost:10016"
   ```
4. Mở lại trình duyệt và tải lại trang `http://localhost:10016/shop/` (bấm Ctrl + F5) → Toàn bộ trang web sẽ hiện lên đầy đủ 100%!

---

## 4. VỀ THƯ MỤC ẢNH & MÔ HÌNH 3D (wp-content/uploads)
* Thư mục `wordpress/wp-content/uploads/` chứa toàn bộ ảnh sản phẩm thực tế và các file mô hình 3D (.glb, .usdz).
* Nhận file nén `uploads.zip` từ Leader (được chia sẻ qua Drive/Zalo), sau đó giải nén vào đúng đường dẫn:
  `wp-content/uploads/` trên máy của bạn.
* Khi đó các ảnh đại diện sản phẩm và mô hình 3D tương tác sẽ hiển thị đầy đủ và sắc nét.
