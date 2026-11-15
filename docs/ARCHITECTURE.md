# DeSpace — Kiến trúc hệ thống và quyền sở hữu dữ liệu

## 1. Bộ công nghệ đã chốt

- Frontend: HTML5, CSS3, JavaScript ES6+.
- Design: Figma — Premium UI/UX.
- CMS: WordPress.
- E-commerce: WooCommerce.
- Backend: PHP.
- Database: MySQL Community Edition.
- API: WordPress REST API + AJAX/Fetch API.
- 3D/AR: Google model-viewer + GLB.
- Module riêng: DeSpace Core Plugin (`despace-core`).
- Theme: DeSpace Child Theme (`despace-child`).
- Version Control: Git + GitHub.

## 2. Trách nhiệm từng lớp

1. **Presentation:** Child Theme, template, CSS, JavaScript và giao diện responsive.
2. **Application:** WordPress Core, WooCommerce và DeSpace Core Plugin.
3. **Data:** MySQL Community Edition với các bảng do WordPress/WooCommerce quản lý và bảng nhật ký tồn kho mở rộng có phạm vi rõ ràng.
4. **Media:** WordPress Media Library; lưu attachment ID cho ảnh sản phẩm, poster và GLB.
5. **External services:** Ngân hàng/cổng thanh toán, SMTP hoặc CDN chỉ dùng khi đã cấu hình và được cấp thông tin kết nối.

## 3. Nguồn dữ liệu chính (System of Record)

| Nhóm dữ liệu | Nguồn dữ liệu chính | Trách nhiệm của DeSpace |
|---|---|---|
| Users/Customers | WordPress users/WooCommerce customer APIs | Chỉ bổ sung capabilities và metadata riêng nếu cần |
| Products/Variations/SKU/Price | WooCommerce product objects | Metadata bổ sung và trình bày |
| Categories/Attributes | WordPress/WooCommerce taxonomy APIs | Bộ lọc nội thất và hiển thị theo phòng |
| Coupons | WooCommerce coupon objects | Tích hợp giao diện |
| Cart/Checkout/Orders/Order items | WooCommerce | Giao diện checkout riêng và mở rộng qua API |
| Stock quantity | WooCommerce | Đọc/hiển thị và ghi nhật ký sự kiện |
| Metadata 3D/AR | WordPress attachment + product meta | GLB attachment ID, poster ID, cờ AR và kiểm tra file |
| Lịch sử biến động kho | `{$wpdb->prefix}despace_inventory_logs` | Nhật ký chỉ ghi thêm, có cơ chế chống trùng |

Không tạo bảng `users`, `products`, `orders`, `order_items` hoặc `coupons` trùng với dữ liệu lõi.

Dùng WooCommerce CRUD APIs như `wc_get_order()`, `WC_Order`, `WC_Order_Item`, `WC_Product`, `WC_Coupon`. Không truy cập trực tiếp nơi lưu đơn hàng; phải giữ tương thích HPOS.

## 4. Ranh giới module trong plugin

```text
despace-core/
  despace-core.php
  includes/
    Products/
    Product-3D/
    Catalog/
    Checkout-Orders/
    Inventory/
    Reports/
    REST-API/
    Admin/
    Security/
    Database/
```

## 5. Quy tắc lập trình

- Đặt prefix/namespace DeSpace cho function, hook, option, metadata và bảng dữ liệu riêng.
- Không đặt nghiệp vụ trong `functions.php`.
- Đăng ký REST route với permission callback.
- Dùng nonce để chống CSRF khi phù hợp; nonce không thay thế xác thực người dùng.
- Kiểm tra capabilities ở mọi thao tác có quyền quản trị.
- Sanitize dữ liệu đầu vào, escape dữ liệu đầu ra; SQL tùy chỉnh dùng `$wpdb->prepare()`.
- Không bỏ qua kiểm tra giá, coupon, thuế, phí giao hàng hoặc tồn kho của WooCommerce.

## 6. Nhật ký biến động tồn kho

Bảng nhật ký không phải hệ thống quản lý tồn kho. Dùng `event_key` duy nhất để ngăn xử lý trùng. Chỉ ghi các sự kiện biến động kho thực tế do WooCommerce quản lý. Khi có dữ liệu, ghi nguồn sự kiện, sản phẩm/biến thể, đơn hàng, người thao tác, số lượng thay đổi, tồn sau sự kiện và thời điểm. Không hoàn tồn cho đơn hàng chưa từng làm giảm tồn.

## 7. Phạm vi thanh toán

MVP hỗ trợ COD và chuyển khoản ngân hàng thủ công. Chuyển khoản chưa đối soát không được ghi nhận là đã thanh toán. Cổng thanh toán tự động chỉ triển khai khi có nhà cung cấp, tài khoản sandbox và tài liệu API.