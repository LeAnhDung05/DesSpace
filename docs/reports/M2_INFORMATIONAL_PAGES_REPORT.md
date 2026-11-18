# Báo Cáo Triển Khai: Các Trang Thông Tin Thương Hiệu & Chính Sách Mua Hàng DeSpace

- **Dự án:** WordPress + WooCommerce DeSpace Luxury Furniture
- **Phạm vi nhiệm vụ:** Module M2 (Issue #2, Phụ trách: Thành viên 02 — Anvu-IFBB)
- **Nhánh thực hiện:** `feature/member02-catalog`
- **Môi trường thử nghiệm:** Docker Local (`http://localhost:8090/`)
- **Ngày hoàn thành:** 18/11/2026

---

## 1. Bảng Kiểm Kê & Hiện Trạng 6 Trang Thông Tin

| TT | Trang | Slug Chính Thức | URL Alias (Rewrite) | Page ID | Trạng Thái | Tiêu Đề (Title) & H1 Duy Nhất |
|---|---|---|---|---|---|---|
| 1 | **Giới thiệu** | `ve-despace` | `/about/` | `11` | Published | Về DeSpace |
| 2 | **Liên hệ** | `lien-he` | `/contact/` | `12` | Published | Liên hệ |
| 3 | **Câu hỏi thường gặp** | `cau-hoi-thuong-gap` | `/faqs/`, `/faq/` | `14` | Published | Câu hỏi thường gặp |
| 4 | **Chính sách giao hàng** | `chinh-sach-giao-hang` | `/giao-hang-doi-tra/`, `/shipping-returns/` | `13` | Published | Chính sách giao hàng & lắp đặt |
| 5 | **Đổi trả & bảo hành** | `chinh-sach-doi-tra-bao-hanh` | `/doi-tra-bao-hanh/`, `/refund_returns/` | `9` | Published | Chính sách đổi trả & bảo hành |
| 6 | **Chính sách bảo mật** | `chinh-sach-bao-mat` | `/privacy-policy/` | `15` | Published | Chính sách bảo mật |
| * | *Điều khoản mua bán* | `dieu-khoan-mua-ban` | `/terms/` | `16` | Published | Điều khoản mua bán & Dịch vụ |

---

## 2. Căn Cứ Dữ Liệu & Các Mục Cần Nhóm Xác Nhận

### 2.1. Căn Cứ Đã Xác Nhận Từ Hệ Thống
- **Showroom DeSpace:** Số 20 ngách 21 ngõ 367, Phố Ngô Xuân Quảng, Gia Lâm, Hà Nội (cách Học viện Nông nghiệp ~500m, gần Vinhomes Ocean Park 1 & 2).
- **Hotline / Zalo tư vấn 24/7:** `0332 174 590`.
- **Hòm thư tiếp nhận bản vẽ / dự toán:** `noithat@despace.vn`.
- **Giờ mở cửa:** 08:30 – 21:30 (Thứ Hai – Chủ Nhật, bao gồm cả ngày lễ).
- **Đội ngũ:** Sáng lập & Giám đốc thiết kế tư vấn trực tiếp 1-1.
- **Tiêu chuẩn vật liệu:** 100% Gỗ sồi, óc chó tự nhiên đạt chuẩn FSC; vải bọc/đệm mút đạt chuẩn OEKO-TEX Standard 100; phụ kiện giảm chấn Blum & Hafele.
- **Thời hạn bảo hành:** Bảo hành kết cấu 5 năm, bảo hành phụ kiện 1 – 2 năm, hỗ trợ bảo dưỡng trọn đời.
- **Chính sách đổi trả:** 1 đổi 1 trong 30 ngày nếu phát sinh lỗi kỹ thuật từ nhà sản xuất.
- **Giao hàng:** Miễn phí giao hàng & lắp đặt nội thành Hà Nội cho đơn từ 10.000.000 VNĐ (hoặc $500).

### 2.2. Các Mục Cần Nhóm Xác Nhận (Được ghi chú minh bạch, không bịa đặt số liệu)
- **Biểu phí vận chuyển chi tiết theo km:** Cước vận chuyển chi tiết đối với khu vực ngoại thành xa và các tỉnh ngoài Hà Nội đối với sản phẩm cồng kềnh.
- **Phụ phí bốc xếp:** Chính sách phụ phí bốc vác thang bộ cho chung cư/nhà tầng không có thang máy chuyên dụng.
- **Đổi trả do thay đổi nhu cầu cá nhân:** Quy định về chi phí vận chuyển phát sinh khi khách hàng muốn đổi mẫu khác mà không phải do lỗi kỹ thuật của sản phẩm.
- **Chính sách sản phẩm may đo cá nhân hóa:** Quy trình điều chỉnh/đổi trả riêng đối với hàng đặt đóng phi tiêu chuẩn.

---

## 3. Kiến Trúc Kỹ Thuật & Giải Pháp Triển Khai

### 3.1. Form Liên Hệ (`inc/contact-form.php` & `assets/js/main.js`)
- **Khả năng cấu hình (Configurable):** Toàn bộ thông tin showroom (địa chỉ, hotline, email, giờ mở cửa) được lưu trong bảng tùy chọn `despace_contact_settings` với fallback mặc định chuẩn xác. Quản trị viên có thể cập nhật nhanh tại trang quản trị: `WP Admin -> Settings -> DeSpace Showroom`.
- **Chống Spam Đa Lớp:**
  - **Nonce Token:** Kiểm tra `wp_verify_nonce( $_POST['nonce'], 'despace_contact_nonce' )`, ngăn chặn tấn công CSRF.
  - **Honeypot Trap:** Trường ẩn `despace_hp` vô hình trước mắt người dùng thật. Bot điền vào sẽ bị chặn và phản hồi âm thầm `status: silent`.
  - **Rate Limiting:** Sử dụng WordPress Transient theo IP (`despace_contact_rl_{md5}`), giới hạn gửi lại sau 45–60 giây để chống flood spam.
- **Validation 2 Đầu (Client & Server):**
  - Tên tối thiểu 2 ký tự, số điện thoại đúng định dạng Việt Nam (9–11 chữ số), email chuẩn RFC.
- **Xử Lý Email & Lưu Trữ Dữ Liệu An Toàn:**
  - Mọi yêu cầu đăng ký tư vấn đều được lưu bền vững vào hệ thống (`despace_contact_inquiries`), lưu tối đa 100 lead gần nhất để không bao giờ bị mất dữ liệu khách hàng.
  - Gửi mail qua `wp_mail()`. Nếu mail transport nội bộ không khả dụng (môi trường local Docker chưa cấu hình SMTP), hệ thống phản hồi thông điệp thân thiện: *"Yêu cầu của bạn đã được ghi nhận trên hệ thống DeSpace. Chuyên viên thiết kế sẽ chủ động liên hệ lại qua số điện thoại/Zalo trong thời gian sớm nhất."* (Không báo gửi mail thành công sai sự thật, không làm lộ lỗi server hay thông tin nhạy cảm).

### 3.2. Cấu Trúc Điều Hướng (Navigation & Links)
- **Footer (`footer.php`):**
  - Cập nhật toàn bộ liên kết chăm sóc khách hàng tới đúng canonical slugs:
    - `/chinh-sach-giao-hang/`
    - `/chinh-sach-doi-tra-bao-hanh/`
    - `/cau-hoi-thuong-gap/`
    - `/lien-he/`
  - Bổ sung liên kết pháp lý tại thanh chân trang:
    - `/chinh-sach-bao-mat/`
    - `/dieu-khoan-mua-ban/`
  - **100% Không có liên kết rỗng `#`.**
- **Trang Thanh Toán (Checkout):**
  - Tích hợp khung thông tin quyền lợi khách hàng (`woocommerce_review_order_before_submit`): liên kết trực tiếp tới Chính sách giao hàng, Bảo hành 5 năm & Đổi trả 30 ngày, và Chính sách bảo mật.

### 3.3. Tiêu Chuẩn SEO & A11y
- **Heading Hierarchy:** Mỗi trang chỉ chứa **duy nhất 1 thẻ H1** do `furniva_page_head()` khởi tạo. Các phần tử con trong nội dung sử dụng phân cấp `H2`, `H3`.
- **Meta Description Thân Thiện:** Tự động sinh thẻ `<meta name="description">` chuẩn SEO cho từng trang thông tin và sản phẩm.
- **Alt Text Cho Toàn Bộ Hình Ảnh:** 100% hình ảnh có thuộc tính `alt` đầy đủ, không có ảnh gãy.

---

## 4. Kết Quả Kiểm Thử Tự Động (Playwright & API Test)

### 4.1. Kiểm Thử API Form Liên Hệ (`scratch/test_contact_api.js`)
1. **Validation Error (Dữ liệu không hợp lệ):** Trả về HTTP `400` kèm thông báo lỗi chi tiết `[PASS]`.
2. **Honeypot Trap (Spam bot điền trường ẩn):** Chặn đứng spam, trả về HTTP `200` `status: silent` `[PASS]`.
3. **Valid Submission (Gửi hợp lệ):** Trả về HTTP `200`, lưu lead vào database thành công `[PASS]`.
4. **Rate Limiting (Gửi liên tiếp nhanh):** Trả về HTTP `429` `[PASS]`.

### 4.2. Kiểm Thử Giao Diện & Đa Viewport (`scratch/test_policy_pages_visual.cjs`)
Kiểm thử 7 trang × 4 kích thước màn hình (1440×900, 1024×768, 768×1024, 390×844):
- **Tổng số test cases:** 28
- **Kết quả:** **28 / 28 PASS (100%)**
- **0 lỗi Console, 0 lỗi tràn ngang (Overflow = false), 0 request mạng thất bại.**

### 4.3. Kiểm Thử Hệ Thống Tổng Thể (`npm run test:visual`)
- Mobile Burger Navigation & Animated X: **PASS**
- Empty Cart State (Mobile & Tablet): **PASS**
- Multi-Viewport Audit across 11 Core Pages: **44 / 44 PASS**
