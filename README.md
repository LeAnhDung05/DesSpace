# DeSpace

Website quản lý và bán nội thất trên WordPress + WooCommerce. Giao diện nằm trong child theme `despace-child`; nghiệp vụ mở rộng nằm trong `despace-core`. WooCommerce quản lý sản phẩm, biến thể, giỏ hàng, coupon, checkout, đơn hàng và tồn kho lõi.

## Bắt đầu nhanh

Yêu cầu Docker Desktop và Docker Compose. Từ thư mục dự án:

```bash
cp .env.example .env
./scripts/setup-local.sh
```

Sau khi bootstrap hoàn tất, mở `http://localhost:8090` và đăng nhập tại `/wp-admin`. Tài khoản local được cấu hình trong `.env`; đây là thông tin thử nghiệm, cần đổi trước khi dùng ở môi trường khác.

## Cấu trúc

```text
wordpress/wp-content/
  themes/despace-child/
  plugins/despace-core/
    includes/{Products,Product-3D,Catalog,Checkout-Orders,Inventory,Reports,REST-API,Admin,Security,Database}/
docs/
sql/
scripts/
tests/
.github/
```

WordPress Core và MySQL chạy trong Docker. WooCommerce cung cấp CRUD API cho dữ liệu thương mại. DeSpace chỉ tạo bảng log tồn kho riêng có prefix động; không tạo bảng products/orders/users/coupons thay thế nền tảng.

## Hướng dẫn

- [Cài đặt và vận hành local](docs/installation.md)
- [Kiến trúc và module](docs/architecture.md)
- [Database và nhật ký tồn kho](docs/database.md)
- [REST API](docs/api/rest-api.md)
- [Kiểm thử và phạm vi MVP](docs/testing.md)

Checkout hỗ trợ COD và chuyển khoản thủ công của WooCommerce. Chức năng AR cần HTTPS và thiết bị/trình duyệt tương thích. Không import SQL dump cũ từ ZIP vào môi trường mới; bootstrap tạo database local sạch.
