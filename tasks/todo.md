# Checklist triển khai DeSpace

## Cấu trúc và môi trường

- [ ] `wordpress/wp-content/themes/despace-child` và `plugins/despace-core` là source chính.
- [ ] Có Docker Compose cho WordPress, MySQL Community Edition và WP-CLI.
- [ ] Bootstrap idempotent cài WooCommerce/Astra, kích hoạt code DeSpace và seed sản phẩm mẫu.
- [ ] `.gitignore` loại `.env`, SQL dump cũ, uploads, cache/log và plugin vendor.

## DeSpace Core

- [ ] Thư mục module khớp cấu trúc người dùng yêu cầu.
- [ ] Bảng log dùng prefix động, dbDelta và index chống event trùng.
- [ ] Metadata GLB chỉ gắn attachment hợp lệ; upload bị giới hạn quyền/kích thước/định dạng.
- [ ] Routes có `permission_callback`, xác thực input và giới hạn phân trang.
- [ ] Đơn hàng/kho dùng WooCommerce CRUD và lifecycle hooks, tương thích HPOS.
- [ ] Reports chỉ đọc đơn qua WooCommerce APIs và kiểm tra quyền quản trị.

## Frontend

- [ ] Header/home/catalog/product/account/cart dùng giao diện DeSpace.
- [ ] Bộ lọc gồm từ khóa/SKU, danh mục, khoảng giá, màu, chất liệu/kích thước và 3D.
- [ ] Lọc AJAX có phân trang, trạng thái rỗng/lỗi/loading và cập nhật URL.
- [ ] DOM được dựng bằng text nodes; chỉ dùng HTML giá đã được lọc ở server.
- [ ] 3D tải lazy, có poster/fallback; AR phụ thuộc support/HTTPS.

## Kiểm chứng và bàn giao

- [ ] PHP lint và Docker Compose config qua.
- [ ] Môi trường mới khởi động bằng hướng dẫn trong README.
- [ ] REST filter trả sản phẩm demo đúng; dữ liệu đơn hàng/kho tạo qua WooCommerce.
- [ ] Tạo/hủy đơn thử tạo log giảm/hoàn kho đúng một lần.
- [ ] Docs gồm cài đặt, kiến trúc, database, API, test case và giới hạn.
