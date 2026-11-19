# DeSpace — Hợp Đồng Dữ Liệu API (API Contract)

**Mã tài liệu:** DESPACE-DOC-API-01  
**Phiên bản:** 1.1.0 (Hoàn thiện theo Issue #25)  
**Phạm vi:** Thống nhất giao thức và cấu trúc dữ liệu giữa các module M1–M5 của DeSpace Core Plugin (`despace-core`) và Child Theme (`despace-child`).

---

## 1. Quy ước chung (General Conventions)

- **Namespace REST API:** `despace/v1`
- **Đường dẫn gốc (Base URL):** `/wp-json/despace/v1/`
- **Phương thức HTTP:**
  - `GET`: Truy vấn đọc dữ liệu (Idempotent, Safe).
  - `POST`: Tạo mới hoặc cập nhật dữ liệu có thay đổi trạng thái (Yêu cầu xác thực nonce + capability).
- **Định dạng dữ liệu:** JSON UTF-8 cho cả request body và response.
- **Tiêu đề xác thực & Bảo mật (Headers):**
  - `Content-Type: application/json`
  - `X-WP-Nonce: <nonce>` (Bắt buộc với mọi request thay đổi trạng thái hoặc truy cập dữ liệu quản trị).
- **Kiểm soát quyền truy cập:** Mọi route nội bộ/quản trị phải định nghĩa `permission_callback` kiểm tra capability WordPress tương ứng (RBAC). Tuyệt đối không dùng `__return_true` cho các endpoint nhạy cảm.
- **Ranh giới thương mại điện tử:** Giỏ hàng, áp mã giảm giá, tính phí vận chuyển và quy trình đặt hàng tuân thủ 100% WooCommerce Store API (`/wp-json/wc/store/v1/`) và WooCommerce Core Hooks. Không tạo API giỏ hàng/đơn hàng song song.

---

## 2. Bảng tổng hợp Endpoints M1–M5

| Phương thức | Endpoint | Module | Quyền truy cập (Capability) | Mục đích |
|---|---|---|---|---|
| `GET` | `/products/filter`<br>*(alias: `/catalog`)* | **M2** | Công khai (`None`) | Tìm kiếm, lọc đa tiêu chí và phân trang danh mục sản phẩm |
| `GET` | `/products/{id}/model-3d`<br>*(alias: `/products/{id}/3d`)* | **M1** | Công khai (`None`) | Đọc metadata mô hình 3D (GLB), ảnh poster và cấu hình AR |
| `POST` | `/products/{id}/model-3d`<br>*(alias: `/products/{id}/3d`)* | **M1** | `manage_despace_3d` hoặc `manage_woocommerce` | Lưu/cập nhật attachment ID 3D GLB và cấu hình AR |
| `GET` | `/inventory/logs` | **M4** | `view_despace_inventory_logs` hoặc `manage_woocommerce` | Lọc và truy vết lịch sử biến động tồn kho chống ghi trùng |
| `GET` | `/dashboard/metrics`<br>*(alias: `/reports/overview`)* | **M5** | `view_despace_reports` hoặc `manage_woocommerce` | Thống kê doanh thu hợp lệ, đơn hàng, tồn kho 3D và Top 10 |
| `GET` | `/reports/top-products` | **M5** | `view_despace_reports` hoặc `manage_woocommerce` | Báo cáo chi tiết top sản phẩm bán chạy theo kỳ |

---

## 3. Quy ước Metadata 3D / AR (M1 Showroom)

Để đảm bảo an toàn dữ liệu, tính di động khi đổi domain và tương thích Media Library:
1. **Quy tắc vàng:** Chỉ lưu **Attachment ID** (số nguyên), **tuyệt đối không lưu URL tĩnh** vào post meta sản phẩm.
2. **Các Meta Keys chuẩn hóa:**

| Meta Key | Kiểu dữ liệu | Mô tả |
|---|---|---|
| `_despace_glb_attachment_id` | `int` | Attachment ID của file mô hình 3D nhị phân (`.glb`) trong WordPress Media. Giá trị `> 0` thể hiện sản phẩm có 3D. |
| `_despace_poster_attachment_id` | `int` | Attachment ID ảnh tĩnh hiển thị trước khi load 3D (Poster thumbnail). |
| `_despace_enable_ar` | `string` (`yes` / `no`) | Cờ bật/tắt chế độ thực tế tăng cường (AR) trên thiết bị di động. |
| `_despace_3d_dimensions` | `array` / `json` | Kích thước thực tế vật lý: `{"length": 210, "width": 90, "height": 85, "unit": "cm"}`. |
| `_despace_3d_camera_orbit` | `string` | Tọa độ góc quay khởi tạo (VD: `45deg 75deg 2.5m`). |
| `_despace_3d_field_of_view` | `string` | Góc nhìn camera (VD: `30deg`). |

3. **Tiêu chuẩn file `.glb`:**
   - Định dạng: Binary glTF 2.0 (`.glb`).
   - Dung lượng tối đa: `<= 20MB` (khuyến nghị `<= 12MB` để đảm bảo tải nhanh trên 4G/5G).
   - Tỷ lệ vật lý (Scale): 1 đơn vị 3D = 1 mét ngoài đời thực (Bắt buộc để AR hiển thị đúng kích cỡ phòng).

---

## 4. Chi tiết Hợp đồng từng Endpoint

### 4.1. Module M2 — Lọc danh mục sản phẩm: `GET /products/filter`

**Query Parameters:**
- `q` (*string, optional*): Từ khóa tìm kiếm theo tên sản phẩm hoặc mã SKU.
- `category` (*string/int, optional*): Slug hoặc Term ID danh mục sản phẩm (VD: `sofa-phong-khach`).
- `min_price` (*numeric, optional*): Giá tối thiểu (VND).
- `max_price` (*numeric, optional*): Giá tối đa (VND).
- `material` (*string, optional*): Thuộc tính chất liệu (VD: `go-soi`, `da-bo-that`).
- `has_3d` (*boolean, optional*): Lọc chỉ các sản phẩm có sẵn mô hình 3D (`true`/`false`).
- `orderby` (*string, optional*): Trường sắp xếp (`price`, `date`, `popularity`, `title`). Mặc định: `date`.
- `order` (*string, optional*): Hướng sắp xếp (`asc`, `desc`). Mặc định: `desc`.
- `page` (*int, optional*): Trang hiện tại. Mặc định: `1`.
- `per_page` (*int, optional*): Số sản phẩm/trang. Mặc định: `12` (Tối đa: `50`).

**Response Schema (`200 OK`):**
```json
{
  "success": true,
  "items": [
    {
      "id": 101,
      "name": "Sofa Da Cao Cấp DeSpace Elegance",
      "slug": "sofa-da-despace-elegance",
      "permalink": "https://despace.local/product/sofa-da-despace-elegance/",
      "sku": "DSP-SF-01",
      "price": 28500000,
      "regular_price": 32000000,
      "sale_price": 28500000,
      "price_html": "<span class=\"price\"><del>32.000.000&nbsp;₫</del> <ins>28.500.000&nbsp;₫</ins></span>",
      "stock_status": "instock",
      "categories": [
        { "id": 15, "name": "Sofa Phòng Khách", "slug": "sofa-phong-khach" }
      ],
      "thumbnail_url": "https://despace.local/wp-content/uploads/2026/11/sofa-thumb.jpg",
      "has_3d": true,
      "glb_attachment_id": 501,
      "enable_ar": true
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 12,
    "total_items": 45,
    "total_pages": 4
  },
  "applied_filters": {
    "category": "sofa-phong-khach",
    "has_3d": true,
    "min_price": 10000000,
    "max_price": 50000000
  }
}
```

---

### 4.2. Module M1 — Đọc/Lưu Model 3D: `/products/{id}/model-3d`

#### `GET /products/{id}/model-3d` (Công khai)
**Response (`200 OK`):**
```json
{
  "product_id": 101,
  "has_3d": true,
  "glb": {
    "attachment_id": 501,
    "url": "https://despace.local/wp-content/uploads/2026/11/sofa-elegance.glb",
    "filesize": 8345210,
    "mime_type": "model/gltf-binary"
  },
  "poster": {
    "attachment_id": 502,
    "url": "https://despace.local/wp-content/uploads/2026/11/sofa-poster.webp"
  },
  "config": {
    "enable_ar": true,
    "ar_modes": "webxr scene-viewer quick-look",
    "camera_orbit": "45deg 75deg 2.5m",
    "field_of_view": "30deg",
    "dimensions": {
      "length": 210,
      "width": 90,
      "height": 85,
      "unit": "cm"
    }
  }
}
```

#### `POST /products/{id}/model-3d` (Yêu cầu quyền `manage_despace_3d`)
**Request Body:**
```json
{
  "glb_attachment_id": 501,
  "poster_attachment_id": 502,
  "enable_ar": true,
  "camera_orbit": "45deg 75deg 2.5m",
  "field_of_view": "30deg",
  "dimensions": {
    "length": 210,
    "width": 90,
    "height": 85,
    "unit": "cm"
  }
}
```
**Validation:**
- `glb_attachment_id`: Phải là attachment tồn tại có MIME type `model/gltf-binary`.
- Trả về `403 Forbidden` nếu người dùng không có quyền `manage_despace_3d`.

---

### 4.3. Module M4 — Nhật ký kho: `GET /inventory/logs`

**Query Parameters:**
- `product_id` (*int, optional*): Lọc theo ID sản phẩm.
- `order_id` (*int, optional*): Lọc theo mã đơn hàng.
- `event_type` (*string, optional*): `order_reduction`, `stock_restoration`, `refund_restock`, `manual_adjustment`.
- `date_from` / `date_to` (*string, optional*): Khoảng thời gian `YYYY-MM-DD`.
- `page` (*int*): Trang (Mặc định: 1).
- `per_page` (*int*): Số log/trang (1–100, Mặc định: 20).

**Response (`200 OK`):**
```json
{
  "items": [
    {
      "id": 142,
      "event_key": "order_reduction_1024_101",
      "event_type": "order_reduction",
      "event_source": "woocommerce_order",
      "product_id": 101,
      "variation_id": null,
      "order_id": 1024,
      "order_item_id": 312,
      "refund_id": null,
      "actor_user_id": 15,
      "quantity_before": 10.0,
      "quantity_delta": -2.0,
      "stock_after": 8.0,
      "created_at": "2026-11-12 09:15:30"
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total_items": 1,
    "total_pages": 1
  }
}
```

---

### 4.4. Module M5 — Dashboard & Báo cáo: `GET /dashboard/metrics`

**Query Parameters:**
- `from_date` (*string, optional*): Ngày bắt đầu `YYYY-MM-DD` (Mặc định: 30 ngày trước).
- `to_date` (*string, optional*): Ngày kết thúc `YYYY-MM-DD` (Mặc định: hôm nay).

**Đặc tả tính toán:**
- `valid_revenue`: Chỉ cộng đơn `processing` và `completed`. Loại trừ `on-hold` (chưa đối soát), `cancelled`, `pending`, `refunded`. Trừ phần tiền hoàn lại nếu có.
- Tuân thủ HPOS qua `wc_get_orders()`.

**Response (`200 OK`):**
```json
{
  "success": true,
  "date_range": {
    "from_date": "2026-10-13",
    "to_date": "2026-11-12"
  },
  "metrics": {
    "valid_revenue": 148500000,
    "valid_revenue_formatted": "148.500.000 ₫",
    "total_orders": 34,
    "valid_orders_count": 29,
    "average_order_value": 5120689.66,
    "average_order_value_formatted": "5.120.690 ₫",
    "products_with_3d_count": 14,
    "total_products_count": 48,
    "percentage_3d": 29.2
  },
  "orders_by_status": {
    "completed": 21,
    "processing": 8,
    "on-hold": 3,
    "pending": 1,
    "cancelled": 1,
    "refunded": 0,
    "failed": 0
  },
  "top_products": [
    {
      "product_id": 101,
      "name": "Sofa Da Cao Cấp DeSpace Elegance",
      "sku": "DSP-SF-01",
      "permalink": "https://despace.local/product/sofa-da-despace-elegance/",
      "image_url": "https://despace.local/wp-content/uploads/2026/11/sofa-thumb.jpg",
      "has_3d": true,
      "quantity_sold": 12,
      "total_revenue": 342000000,
      "total_revenue_formatted": "342.000.000 ₫"
    }
  ]
}
```

---

## 5. Cấu trúc Lỗi Chuẩn (Error Envelope)

Mọi phản hồi lỗi từ REST API đều tuân thủ cấu trúc chuẩn của WordPress REST API:

```json
{
  "code": "despace_rest_forbidden",
  "message": "Bạn không có quyền xem báo cáo DeSpace.",
  "data": {
    "status": 403
  }
}
```

**Bảng mã lỗi chuẩn hóa:**
- `400 Bad Request`: `despace_invalid_from_date`, `despace_invalid_to_date`, `despace_invalid_date_range`, `despace_invalid_filter`.
- `401 Unauthorized`: `despace_rest_unauthorized` (Yêu cầu đăng nhập kèm nonce hợp lệ).
- `403 Forbidden`: `despace_rest_forbidden` (Tài khoản không đủ quyền capability).
- `404 Not Found`: `despace_product_not_found`, `despace_model_not_found`.

---

## 6. Quy trình Thay đổi Hợp đồng (Contract Change Protocol)

1. Thành viên đề xuất mở GitHub Issue mô tả rõ endpoint, lý do thay đổi và danh sách module bị ảnh hưởng.
2. Tạo Pull Request cập nhật file [API_CONTRACT.md](file:///c:/Users/Hadin/OneDrive/Tài%20liệu/GitHub/DesSpace/docs/API_CONTRACT.md).
3. Bắt buộc nhận được review và phê duyệt từ **tất cả** chủ sở hữu module liên quan (M1–M5).
4. Merge cập nhật tài liệu hợp đồng trước khi merge mã nguồn cài đặt phụ thuộc.
