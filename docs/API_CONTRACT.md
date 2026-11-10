# DeSpace — Quy ước API dùng chung (API Contract)

Tài liệu này là hợp đồng kết nối giữa các module. Trước khi lập trình phụ thuộc, các thành viên phải thống nhất tên endpoint, cấu trúc dữ liệu và quyền truy cập. Không tạo endpoint DeSpace trùng lặp chức năng mà WooCommerce đã có.

## 1. Quy ước chung

- Namespace: `despace/v1`
- Đường dẫn gốc: `/wp-json/despace/v1/`
- GET dùng để đọc dữ liệu; POST dùng cho thao tác thay đổi trạng thái/dữ liệu.
- Response trả JSON với các key ổn định và HTTP status code rõ ràng.
- Mọi route có quyền quản trị phải khai báo permission callback và kiểm tra capability.
- Tất cả tham số đều phải validate và sanitize.
- Bộ lọc danh mục công khai có thể chỉ cần quyền đọc; quản trị sản phẩm và báo cáo cần capability phù hợp.
- Dùng WordPress REST API cho thao tác dữ liệu có cấu trúc; dùng AJAX/Fetch API khi cần tương tác giao diện theme.

## 2. Các endpoint ban đầu

| Method | Endpoint | Phụ trách | Mục đích |
|---|---|---|---|
| GET | `/catalog` | M2 | Tìm kiếm, lọc và phân trang sản phẩm WooCommerce đã công khai |
| GET | `/products/{id}/3d` | M1 | Đọc metadata GLB/poster đã được kiểm tra |
| POST | `/products/{id}/3d` | M1 | Lưu metadata attachment 3D; chỉ nhân viên có quyền mới được thao tác |
| GET | `/inventory/logs` | M4 | Lọc nhật ký biến động tồn kho; chỉ người có quyền |
| GET | `/reports/overview` | M5 | KPI Dashboard theo khoảng thời gian; chỉ người có quyền |
| GET | `/reports/top-products` | M5 | Sản phẩm bán chạy trong khoảng thời gian chọn |

Giỏ hàng, coupon, checkout và vòng đời đơn hàng phải dùng WooCommerce APIs cùng các endpoint/hooks được hỗ trợ. Không xây dịch vụ đơn hàng DeSpace song song.

## 3. Quy ước tham số lọc danh mục

Các query parameters đề xuất:
- `q`: tìm theo tên sản phẩm/SKU.
- `category`: term danh mục sản phẩm/phòng.
- `min_price`, `max_price`: khoảng giá.
- `material`, `color`, `size`: chất liệu, màu sắc, kích thước.
- `has_3d`: giá trị boolean, sản phẩm có GLB hay không.
- `page`, `per_page`, `orderby`, `order`: phân trang và sắp xếp.

Response nên có:
- `items`: product ID, tên, permalink, ảnh, giá hiển thị, SKU, trạng thái tồn kho, danh mục và trạng thái có 3D.
- `pagination`: page, per_page, total_items, total_pages.
- `applied_filters`: các bộ lọc đã được chuẩn hóa.

Máy chủ phải kiểm tra giới hạn và các trường sắp xếp được phép. Giá/tình trạng hàng trên giao diện không phải căn cứ cuối cùng để checkout; WooCommerce phải kiểm tra lại khi đặt hàng.

## 4. Cấu trúc lỗi

```json
{
  "code": "despace_invalid_filter",
  "message": "The selected filter is invalid.",
  "data": { "status": 400 }
}
```

Giữ các mã lỗi/key trong API nhất quán để frontend xử lý được; nội dung message có thể Việt hóa nếu nhóm thống nhất.

## 5. Quy trình thay đổi API

Khi một module cần thay đổi hợp đồng API:
1. Tạo Issue mô tả thay đổi và các module bị ảnh hưởng.
2. Cập nhật tài liệu này trong Pull Request.
3. Xin xác nhận từ các module owner liên quan.
4. Merge thay đổi API trước khi merge phần code phụ thuộc.