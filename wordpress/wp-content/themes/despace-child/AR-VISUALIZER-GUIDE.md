# AR Room Visualizer - Hướng dẫn sử dụng

## 🎯 Tổng quan

AR Room Visualizer là tính năng đặc biệt cho phép khách hàng **xem sản phẩm nội thất trong không gian thực** của họ bằng cách tải lên ảnh phòng và đặt sản phẩm vào.

---

## 📁 Cấu trúc File

```
furniva-child/
├── page-ar-visualizer.php           # Trang AR chính
├── assets/
│   ├── css/
│   │   ├── ar-visualizer.css       # Styles trang AR
│   │   └── ar-integration.css      # Styles nút AR
│   └── js/
│       └── ar-visualizer.js        # JavaScript AR (nếu có)
├── inc/
│   ├── ar-visualizer-ajax.php      # AJAX handlers
│   └── ar-visualizer-integration.php # Tích hợp với WC
└── functions.php                   # Đã load tất cả file
```

---

## 🚀 Cách cài đặt

### 1. Tạo Page AR Visualizer

1. Đăng nhập WordPress Admin
2. Pages → Add New
3. **Title:** `AR Room Visualizer`
4. **Template:** Chọn `AR Room Visualizer` (từ dropdown Template)
5. **Slug:** `ar-room-visualizer`
6. Publish

### 2. Thêm vào Navigation

**Cách 1: Tự động (đã cài đặt)**
- File `ar-visualizer-integration.php` đã tự động thêm link vào menu chính

**Cách 2: Thủ công**
```php
// Thêm vào menu via WP Admin
// Appearance → Menus → Add Items → AR Room Visualizer
```

### 3. Thêm vào Header

```php
<a href="/ar-room-visualizer/" class="ar-nav-link">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
        <path d="M2 17l10 5 10-5"/>
        <path d="M2 12l10 5 10-5"/>
    </svg>
    <span>AR Visualizer</span>
</a>
```

---

## 🎨 Vị trí hiển thị nút AR

| Vị trí | Mô tả | File |
|---------|--------|------|
| **Product Page** | Nút "View in Your Room" bên dưới Add to Cart | `ar-visualizer-integration.php` |
| **Product Tabs** | Tab mới "View in Your Space" | `ar-visualizer-integration.php` |
| **Shop Grid** | Icon AR nhỏ trên product card | `ar-visualizer-integration.php` |
| **Header Menu** | Link AR Visualizer trong navigation | `ar-visualizer-integration.php` |
| **Footer** | URL được truyền qua JS | `ar-visualizer-integration.php` |

---

## 🔧 Tính năng chính

### Upload ảnh phòng
- **Chọn file:** JPG, PNG, max 10MB
- **Chụp ảnh:** Sử dụng webcam
- **Flip camera:** Chuyển front/back trên mobile

### Đặt sản phẩm
- **Drag & drop:** Kéo sản phẩm từ panel vào canvas
- **Di chuyển:** Drag sản phẩm trên canvas
- **Xoay:** ±15° mỗi lần click
- **Scale:** Phóng to/thu nhỏ sản phẩm

### Lưu & Chia sẻ
- **Save Layout:** Lưu vào localStorage/guest/transient
- **Export Image:** Tải ảnh kết quả về
- **Share:** Chia sẻ layout

---

## 🎯 Tùy chỉnh

### Thay đổi Free Shipping Threshold
```php
// Trong page-ar-visualizer.php, line ~15
$shipping_threshold = 500; // USD
```

### Thêm Categories cho AR
```php
// Trong page-ar-visualizer.php, line ~175
$categories = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'parent'     => 0,
) );
// Giới hạn số categories hiển thị
foreach ( array_slice( $categories, 0, 5 ) as $cat ) :
```

### Thay đổi màu sắc
```css
/* Trong ar-visualizer.css */
:root {
    --ar-primary: #c5a880;      /* Champagne gold */
    --ar-dark: #191816;          /* Near black */
    --ar-light: #f7f4ef;         /* Warm cream */
    --ar-accent: #6f806c;        /* Sage green */
    --ar-danger: #bf5b50;        /* Terracotta red */
}
```

### Tắt AR Button cho sản phẩm cụ thể
```php
// Trong functions.php hoặc custom plugin
add_filter('ar_show_button_for_product', function($show, $product_id) {
    // Ẩn AR button cho sản phẩm có ID 123
    if ($product_id === 123) return false;
    return $show;
}, 10, 2);
```

---

## 📱 Responsive Breakpoints

| Breakpoint | Layout |
|-----------|--------|
| > 1200px | Canvas + Sidebar 380px |
| 1024-1200px | Canvas + Sidebar nhỏ hơn |
| 768-1024px | Stacked (Canvas → Sidebar) |
| < 768px | Full-width, controls tối ưu mobile |

---

## 🔒 AJAX Endpoints

| Action | Mô tả | User |
|--------|--------|------|
| `ar_save_layout` | Lưu layout | All |
| `ar_get_layouts` | Lấy danh sách layout | All |
| `ar_delete_layout` | Xóa layout | Logged in |
| `ar_add_to_cart` | Thêm vào giỏ từ AR | All |
| `ar_get_products` | Lấy sản phẩm | All |

---

## 🚨 Troubleshooting

### Không thấy nút AR trên product page
1. Kiểm tra sản phẩm có dimensions không (chiều rộng/cao/dài)
2. Kiểm tra file `ar-visualizer-integration.php` đã được require
3. Kiểm tra CSS `ar-integration.css` đã load

### Camera không hoạt động
1. Kiểm tra HTTPS (Camera API cần secure context)
2. Kiểm tra quyền camera trong trình duyệt
3. Fallback: Sử dụng upload file thay vì camera

### Sản phẩm không hiển thị đúng kích thước
1. Đảm bảo sản phẩm có dimensions trong WooCommerce
2. Dimensions cần bằng cm trong WC settings

---

## 🎨 Animation Reference

### Page Load
```css
.ar-upload-icon { animation: ar-float 4s ease-in-out infinite; }
.ar-bottom-actions { animation: ar-slide-up 0.4s ease; }
```

### Product Hover
```css
.ar-product-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 32px rgba(43, 29, 22, 0.12);
}
```

### Modal
```css
.ar-modal-content {
    animation: ar-modal-pop 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
```

---

## 📊 Performance Tips

1. **Ảnh phòng:** Khuyến khích upload ảnh < 2MB
2. **Số sản phẩm:** Limit 20 sản phẩm trên AR page
3. **Lazy loading:** Đã enable cho ảnh sản phẩm
4. **Canvas rendering:** Sử dụng CSS transforms thay vì JS canvas

---

## 🔮 Future Enhancements

1. **WebXR AR Mode:** AR thực sự trên mobile
2. **Save to Account:** Lưu layout vào user account
3. **Multiple Rooms:** Nhiều phòng trong 1 layout
4. **Social Share:** Chia sẻ lên Facebook/Instagram
5. **AR Quick View:** AR button popup trên product page
6. **3D Models:** Hỗ trợ 3D models (.glb, .gltf)
7. **Room Templates:** Pre-made room layouts
8. **Measurement Tool:** Đo kích thước trong phòng

---

## 💡 Marketing Tips

1. **Landing Page:** Tạo trang landing cho AR feature
2. **Banner:** Thêm banner trên shop page giới thiệu AR
3. **Email:** Include AR link trong email sản phẩm
4. **Social:** Demo video AR trên Instagram/Facebook
5. **Blog:** Bài viết hướng dẫn sử dụng AR

---

*Document version: 1.0*
*Last updated: October 2024*
