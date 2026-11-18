# HƯỚNG DẪN ĐỒNG BỘ DỮ LIỆU DESPACE DÀNH CHO THÀNH VIÊN NHÓM

Tài liệu này giải thích lý do vì sao khi bạn mới kéo code về máy thì trang Cửa Hàng lại hiện **"0 sản phẩm được tìm thấy"** và hướng dẫn bạn cách đồng bộ toàn bộ **40 sản phẩm, 5 danh mục, giá bán, bảng màu vân gỗ và cấu hình** vào WordPress trên máy cá nhân chỉ trong vài cú nhấp chuột!

---

## 1. NGUYÊN NHÂN TRANG CỬA HÀNG HIỆN "0 SẢN PHẨM"
* **Git chỉ lưu mã nguồn** (code PHP của theme `despace-child`, plugin `despace-core`, CSS, JavaScript).
* **Git không lưu cơ sở dữ liệu MySQL:** Toàn bộ 40 sản phẩm, 5 danh mục, thuộc tính màu sắc, đơn hàng và bài viết được lưu trữ trong Database MySQL.
* Khi bạn tạo một trang WordPress mới trên máy (ví dụ bằng LocalWP chạy cổng `localhost:10016`), database của bạn là database trắng tinh (chưa có sản phẩm nào). Do đó WooCommerce hiển thị:
  - `DANH MỤC 0`
  - `0 sản phẩm được tìm thấy`
  - Chế độ *"This page is in 'Coming soon' mode"* (Chế độ cửa hàng mới tạo).

---

## 2. CÁCH 1: NÚT BẤM 1-CLICK TRỰC TIẾP TRÊN WEB (SIÊU NHANH - KHUYÊN DÙNG NHẤT ⚡)
*Không cần mở dòng lệnh, không cần gõ lệnh nào:*

1. Mở trang web của bạn trên trình duyệt (ví dụ: `http://localhost:10016/shop/` hoặc `http://localhost:10016/wp-admin/`) khi bạn đang đăng nhập quyền Admin.
2. Bạn sẽ thấy một thanh thông báo màu vàng cam hoặc banner nổi ở góc phải màn hình:
   > **🚀 DeSpace Auto-Setup: Chưa phát hiện sản phẩm mẫu (0 sản phẩm)**  
   > *[ ⚡ BẤM ĐỒNG BỘ 40 SẢN PHẨM NGAY (1 CLICK) ]*
3. Nhấp chuột vào nút màu xanh đó:
   - Hệ thống PHP sẽ tự động giải nén và nạp cơ sở dữ liệu.
   - Tự động nhận diện cổng của bạn (`localhost:10016`) để đổi link.
   - Tự động tắt chế độ "Coming Soon".
4. Sau 5–10 giây, trang sẽ tự tải lại: **Toàn bộ 40 sản phẩm, 5 danh mục và bảng màu vân gỗ sẽ xuất hiện 100%!**

---

## 3. CÁCH 2: CHẠY FILE TỰ ĐỘNG BẰNG FILE .BAT (CHO LOCALWP TRÊN WINDOWS)
1. Mở ứng dụng **Local (LocalWP)** trên máy tính.
2. Nhấp chọn trang web của bạn (ví dụ cổng `10016`).
3. Bấm vào nút **"Open Site Shell"** (nằm ngay dưới tên trang).
4. Cửa sổ dòng lệnh hiện ra, bạn chỉ cần gõ:
   ```cmd
   database\dong_bo_database_windows.bat
   ```
5. Nhập số cổng của bạn (mặc định bấm `Enter` để chọn `10016`).
6. Kịch bản sẽ tự động chạy: Import SQL → Đổi link sang cổng 10016 → Tắt Coming soon.
7. Mở trình duyệt `http://localhost:10016/shop/` và tận hưởng!

*(Thành viên dùng macOS / Linux có thể chạy: `bash database/dong_bo_database.sh`)*

---

## 4. LƯU Ý: KHÔNG NHẬP CSV HIỆN TẠI BẰNG WOOCommerce IMPORTER

Không dùng **Sản phẩm → Nhập** với `database/despace_products_40.csv` ở trạng thái hiện tại. File có 40 dòng dữ liệu nhưng thiếu dòng tiêu đề theo định dạng WooCommerce. Importer coi dòng sản phẩm đầu tiên là tiêu đề, bỏ qua dòng đó và tạo 39 sản phẩm rỗng có tên `Product`, không có SKU.

File CSV cũng chỉ chứa đường dẫn tham chiếu ảnh; nó không chứa các tệp ảnh. Vì vậy nhập CSV không khôi phục ảnh nếu thư mục `wp-content/uploads` chưa được đồng bộ.

- Database mẫu `database/despace_seed_data.sql` đã có 40 sản phẩm với SKU `AXG-STR-001` đến `AXG-STR-040`. Không nhập CSV vào database đã có các sản phẩm này.
- SQL seed là bản dump database có lệnh xóa/tạo bảng; chỉ nhập vào database mới hoặc database đã được sao lưu và được phép thay thế.
- Để có ảnh và mô hình 3D, cần nhận gói `uploads` từ leader rồi đồng bộ vào đúng site (LocalWP hoặc Docker). Hai môi trường lưu uploads riêng.
- Nếu cần nhập CSV vào một site trống, trước tiên phải tạo CSV có tiêu đề WooCommerce chuẩn và kiểm tra cột Images; không dùng file hiện tại cho thao tác đó.

---

## 5. THƯ MỤC ẢNH & MÔ HÌNH 3D (wp-content/uploads)
* Leader đã đóng gói sẵn tệp `uploads.zip` (chứa toàn bộ ảnh sản phẩm thực tế và các tệp 3D `.glb`, `.usdz`).
* Thành viên tải tệp `uploads.zip` do Leader gửi, sau đó giải nén chép vào thư mục:
  `app\public\wp-content\uploads\` (trên LocalWP Windows)
* Khi đó, toàn bộ ảnh sản phẩm và nút xem mô hình 3D AR sẽ hiển thị sắc nét 100%!
