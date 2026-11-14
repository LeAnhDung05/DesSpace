# Kế hoạch chuyển đổi DeSpace

## Tổng quan

Chuyển source BTTTS_5 thành dự án WordPress + WooCommerce có thể cài mới bằng Docker, giữ giao diện DeSpace hiện có, đóng gói nghiệp vụ trong `despace-core` và dùng WooCommerce làm nguồn dữ liệu chính. Cơ sở dữ liệu mới phải được khởi tạo sạch; không nhập SQL cũ đang trỏ về site Furniva/AXGNUD.

## Quyết định kiến trúc

- Thư mục triển khai là `wordpress/wp-content`; WordPress Core do image chính thức cung cấp.
- `despace-child` chỉ trình bày giao diện; `despace-core` quản lý API, GLB/AR, kiểm kê, báo cáo và checkout.
- Sản phẩm, biến thể, coupon, khách hàng và đơn hàng dùng WordPress/WooCommerce APIs. Plugin chỉ tạo bảng `{$wpdb->prefix}despace_inventory_logs`.
- Dùng WordPress REST API cho bộ lọc catalog; WooCommerce tiếp tục xử lý giỏ hàng, coupon, checkout và vòng đời tồn kho.
- Dữ liệu cũ trong ZIP là nguồn tham khảo, không phải lệnh triển khai. Bootstrap cục bộ tạo database và dữ liệu demo mới, không mang tài khoản hoặc PII cũ sang.

## Các giai đoạn

### Giai đoạn 1: Cấu trúc dự án và môi trường cục bộ

- [ ] Đặt theme/plugin vào đúng thư mục; tạo docs, scripts, SQL và cấu hình Docker.
- [ ] Thêm cấu hình mẫu, loại dữ liệu cũ/secret khỏi Git và hướng dẫn khởi động.
- [ ] Tạo kịch bản cài WordPress, WooCommerce, Astra, DeSpace và dữ liệu thử.

### Giai đoạn 2: Nền tảng plugin và dữ liệu

- [ ] Tách code hiện có vào các module theo cấu trúc yêu cầu.
- [ ] Tạo/cập nhật bảng nhật ký kho bằng `dbDelta()` và xác nhận schema qua WP-CLI.
- [ ] Tách bảo mật upload GLB, kiểm tra quyền và xác thực attachment.

### Giai đoạn 3: Catalog và 3D/AR

- [ ] Xây query catalog phân trang, tìm SKU/tên, lọc danh mục, giá, thuộc tính và 3D.
- [ ] Chuẩn hóa response REST và xử lý lỗi/giới hạn đầu vào.
- [ ] Nối form lọc AJAX với REST; dựng product cards an toàn và đồng bộ URL/trạng thái.

### Giai đoạn 4: Checkout, kho và báo cáo

- [ ] Hiển thị trường ghi chú giao hàng/lắp đặt qua checkout chuẩn WooCommerce.
- [ ] Ghi log giảm/hoàn/điều chỉnh tồn kho idempotent, không trừ tồn lần hai.
- [ ] Tổng hợp báo cáo cơ bản bằng WooCommerce order/product APIs và kiểm soát capabilities.

### Giai đoạn 5: Kiểm chứng và bàn giao

- [ ] Chạy lint PHP, kiểm tra cấu hình Docker và khởi tạo môi trường sạch.
- [ ] Kiểm tra trang chủ, catalog/REST, metadata 3D, bảng log, checkout và đơn thử.
- [ ] Hoàn thiện docs cài đặt, kiến trúc, database, API, dữ liệu demo và giới hạn nghiệm thu.

## Điểm kiểm tra

- Sau giai đoạn 1: `docker compose up` và kịch bản bootstrap tạo được WordPress/WooCommerce.
- Sau giai đoạn 3: endpoint lọc hoạt động với sản phẩm demo và frontend cập nhật bằng Fetch API.
- Trước bàn giao: đơn thử tạo/hủy theo WooCommerce, có log kho duy nhất; source PHP lint sạch.

## Rủi ro và cách xử lý

| Rủi ro | Tác động | Cách xử lý |
|---|---|---|
| SQL cũ chứa URL, tài khoản và dữ liệu site trước | Lộ dữ liệu hoặc khởi tạo sai site | Không tự động import; tạo database local sạch |
| Phiên bản plugin/theme thay đổi upstream | Khác biệt khi cài mới | Pin WooCommerce/Astra cho môi trường demo; ghi rõ PHP/MySQL image |
| AR phụ thuộc thiết bị và HTTPS | Không thể xác minh đầy đủ trên localhost | Hiển thị fallback 3D/2D; ghi rõ AR cần HTTPS và thiết bị phù hợp |
| Catalog legacy có metadata GLB dạng URL | Mô hình không còn tồn tại hoặc URL không an toàn | Ưu tiên attachment ID; kiểm tra scheme/đuôi tệp cho dữ liệu cũ |

## Phạm vi ngoài MVP

Thanh toán tự động khi chưa có thông tin sandbox; tích hợp ERP/vận chuyển; AI; tạo GLB từ ảnh; cam kết tải đồng thời hoặc SLA production.
