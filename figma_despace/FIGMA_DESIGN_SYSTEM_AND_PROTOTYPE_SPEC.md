# BẢN ĐẶC TẢ THIẾT KẾ UX/UI FIGMA & SƠ ĐỒ NỐI PROTOTYPE - DỰ ÁN DESPACE LUXURY

> **Dự án:** DeSpace Architectural Furniture Sanctuary  
> **Thư mục tài nguyên Figma:** `figma_despace/exports/`  
> **Định dạng sẵn có:** Full-page Retina @2x PNG & Clean Semantic HTML Snapshots (Desktop 1440px & Mobile 390px)

---

## 1. THIẾT KẾ HỆ THỐNG (DESIGN SYSTEM TOKENS)

### 🎨 Bảng màu chuẩn (Color Palette)
* **Background Primary (Thạch cao ấm):** `#FAF8F5` (Dùng cho toàn bộ nền trang, tạo cảm giác mộc mạc Scandinavian).
* **Background Secondary (Đá sỏi mềm):** `#F4EFE8` (Dùng cho Card sản phẩm, Bento container, ô tìm kiếm).
* **Surface White:** `#FFFFFF` (Nền Modal, Drawer giỏ hàng, thẻ lọc).
* **Text Primary (Đen than kiến trúc):** `#191816` (Heading, tên sản phẩm, nút bấm chính).
* **Text Muted (Xám khói):** `#6B655F` (Mô tả phụ, breadcrumb, nhãn SKU).
* **Accent Gold / Champagne (Vàng cát sâm panh):** `#C5A880` (Điểm nhấn ngôi sao, badge giảm giá, viền active).
* **Border Line:** `#E8E2D9` (Đường kẻ phân cách 1px siêu thanh).
* **Success Green:** `#10B981` (Thông báo thêm giỏ hàng, badge còn hàng).

### 🔤 Typography & Kiểu chữ
* **Font chính (Primary UI):** `Plus Jakarta Sans` (Geometric Sans-serif hiện đại, tối giản).
  - *Display H1:* 48px – 56px | Bold (700) | Line-height: 1.15
  - *Heading H2:* 32px – 38px | SemiBold (600) | Line-height: 1.2
  - *Heading H3:* 20px – 24px | Medium (500)
  - *Body Text:* 15px – 16px | Regular (400) | Line-height: 1.6
  - *Eyebrow / Sub-kicker:* 11px – 12px | Bold (700) | Letter-spacing: +0.12em | Uppercase
* **Font điểm nhấn (Editorial Accent):** `Newsreader` (Serif Italic cổ điển cho các câu triết lý sống và manifesto).

### 📐 Grids, Spacing & Bo góc (Border Radii)
* **Desktop Grid:** 1440px width, 12 cột, Gutter: 24px, Margin 2 bên: 80px (Container tối đa: 1280px).
* **Mobile Grid:** 390px width, 4 cột, Gutter: 16px, Margin 2 bên: 16px.
* **Border Radius:**
  - Nút bấm & Pills tag: `border-radius: 9999px` (Pill shape).
  - Card sản phẩm & Bento Grid: `border-radius: 24px` đến `32px`.
  - Input & Dropdown: `border-radius: 12px`.
  - Modal 3D AR: `border-radius: 28px`.

---

## 2. CẤU TRÚC LAYERS & AUTO-LAYOUT CHUẨN TRONG FIGMA

### 🖥️ Frame 1: Desktop - 01_Homepage (1440px × Auto)
```text
📦 [Desktop/01_Homepage] (Auto Layout: Vertical, Gap: 0, Padding: 0)
├── 📱 Navigation / [Header_Sticky] (Fill container, Height: 84px)
│   ├── Top_Bar (Height: 36px, Background: #2D2723, Text: Marquee announcements)
│   └── Main_Header (Height: 80px, Auto Layout: Space-between, Padding: 0 80px)
│       ├── Logo (DeSpace Vector SVG, 140px × 36px)
│       ├── Nav_Menu (Auto Layout: Horizontal, Gap: 32px)
│       │   ├── Nav_Item ("Trang chủ" - Active State)
│       │   ├── Nav_Item ("Cửa hàng")
│       │   ├── Nav_Item ("Tạp chí")
│       │   ├── Nav_Item ("Giới thiệu")
│       │   └── Nav_Item ("Liên hệ")
│       └── Header_Actions (Auto Layout: Horizontal, Gap: 16px)
│           ├── Lang_Switcher ("VI" Active / "EN")
│           ├── Search_Icon_Button
│           ├── Wishlist_Icon_Button (Badge count: 0)
│           ├── User_Icon_Button
│           └── Mini_Cart_Button (Badge count: 0, Pill button)
├── 🖼️ [Act_I_Hero_Banner] (Padding: 24px 80px, Height: 780px)
│   └── Hero_Container (Fill container, Radius: 34px, Clip content, Relative)
│       ├── Hero_Image (Background cover Scandinavian room)
│       └── Hero_Content_Overlay (Auto Layout: Left-Bottom, Padding: 64px)
│           ├── Tag_Pill ("BỘ SƯU TẬP 2026")
│           ├── Hero_Title ("Kiến tạo không gian tĩnh lặng.")
│           └── CTA_Group (Button Primary "Khám phá ngay" + Secondary "Tìm hiểu thêm")
├── 🌿 [Act_II_Manifesto] (Padding: 120px 80px, Centered Auto Layout)
│   ├── Eyebrow ("TRIẾT LÝ THIẾT KẾ")
│   └── Quote_Text ("Nội thất không chỉ lấp đầy không gian, mà định hình cách ta sống.")
├── 🛋️ [Act_III_Spaces_3_Rooms] (Padding: 80px, Auto Layout: Vertical, Gap: 48px)
│   ├── Section_Heading ("CHỌN THEO KHÔNG GIAN")
│   ├── Space_Pills_Filter ("Entryway & Living" | "Bedroom" | "Study & Kitchen")
│   └── Space_Showcase_Grid (3 Cột ngang, Banner phối cảnh thực tế + 4 sản phẩm khớp không gian)
├── 📦 [Act_IV_Bento_Curated] (6 Không gian nội thất sắp đặt dạng Bento Grid)
├── ⭐ [Act_V_Featured_Catalog] (Lưới 4 cột 8 sản phẩm tiêu biểu kèm swatch màu)
├── 💬 [Act_VI_Customer_Reviews] (Carousel đánh giá 5 sao từ khách hàng)
└── 🦶 [Footer_5_Columns] (Background: #191816, Text: #FFFFFF, Padding: 80px)
```

---

### 🖥️ Frame 2: Desktop - 02_Catalog_Shop (1440px × Auto)
```text
📦 [Desktop/02_Catalog_Shop] (Auto Layout: Vertical)
├── Header_Sticky
├── Shop_Hero_Banner ("Tất cả sản phẩm nội thất DeSpace", Breadcrumb)
└── Shop_Layout_Wrap (Auto Layout: Horizontal, Padding: 40px 80px, Gap: 48px)
    ├── 🎛️ [Filter_Sidebar] (Fixed Width: 280px, Sticky top: 100px)
    │   ├── Filter_Header ("BỘ LỌC SẢN PHẨM" + Button "Đặt lại")
    │   ├── Accordion_Categories (Danh mục: Tủ giày, Kệ sách, Bàn trà, Ghế sofa...)
    │   ├── Accordion_Price_Slider (Double-thumb slider $0 – $2,000)
    │   ├── Accordion_Wood_Colors (21 Ô tròn màu vân gỗ thực tế)
    │   └── Accordion_Availability (Còn hàng / Tạm hết)
    └── 🛍️ [Product_Grid_Main] (Fill container, Auto Layout: Vertical, Gap: 32px)
        ├── Grid_Control_Bar (Result Count "40 sản phẩm" + Switcher "3 Cột / 4 Cột" + Sorting Dropdown)
        ├── Active_Chips_Row (Các tag đã chọn kèm nút [x] gỡ)
        ├── Products_Matrix (Grid 4 cột, Card Auto Layout, Bo góc 24px)
        └── Pagination_Bar (1, 2, 3, Tiếp →)
```

---

### 🖥️ Frame 3: Desktop - 03_Single_Product_3D (1440px × Auto)
```text
📦 [Desktop/03_Single_Product_3D]
├── Header_Sticky
├── Product_Detail_Section (Auto Layout: Horizontal, Padding: 48px 80px, Gap: 64px)
│   ├── Left_Gallery (Width: 640px, Sticky)
│   │   ├── Main_Featured_Image (Bo góc 28px, nút Zoom Lightbox)
│   │   ├── Thumbnails_Strip (4 ảnh phụ cuộn ngang)
│   │   └── 🌟 [Button_View_3D_AR] (Nút đặc biệt: Icon 3D xoay + "XEM 3D & THỬ AR TRONG PHÒNG")
│   └── Right_Summary (Width: 540px, Auto Layout: Vertical, Gap: 24px)
│       ├── Brand_Eyebrow ("DESPACE LUXURY SANCTUARY")
│       ├── Product_Title ("Tủ sách cánh kính chống bụi DeSpace Shelvo")
│       ├── Price_Row ("$120.00", Giá gốc "$156.00", Badge "-23%")
│       ├── Divider
│       ├── Swatches_Wood_Finish (Tên màu đang chọn: "Gỗ sồi Modern Oak", các nút tròn màu)
│       ├── Size_Selector (Chips: "Tiêu chuẩn (80x35x180cm)")
│       ├── Quantity_Stepper ([-] [ 1 ] [+])
│       ├── Action_Buttons (Button "THÊM VÀO GIỎ HÀNG" + Button "MUA NGAY")
│       └── Commitments_Pills (Giao hàng miễn phí, Bảo hành 5 năm, Đổi trả 30 ngày)
└── Product_Tabs_Reviews_Related
```

---

### 🖥️ Frame 4 (Overlay): 04_Modal_3D_Interactive (1024px × 680px)
```text
📦 [Overlay/04_Modal_3D_AR] (Center overlay, Background: #FFFFFF, Radius: 28px, Shadow Elevation 4)
├── Modal_Header (Auto Layout: Space-between, Title: "Trải nghiệm 3D & Thực tế ảo AR", Button [X])
├── Modal_Body (Auto Layout: Horizontal, Gap: 0)
│   ├── Left_3D_Canvas (Width: 640px, Background: #F9F9F8)
│   │   ├── Interactive_3D_Model_Viewport (Góc xoay 360 độ)
│   │   ├── Canvas_Toolbar (Đổi ánh sáng Studio/Warm, Nút Reset góc, Nút phóng to)
│   │   └── Color_Finish_Switcher (Đổi vân gỗ trực tiếp trên 3D)
│   └── Right_AR_Instructions (Width: 340px, Padding: 32px, Auto Layout: Vertical)
│       ├── AR_Title ("Xem trong phòng của bạn (Real AR)")
│       ├── QR_Code_Box (Mã QR quét bằng camera iPhone/Android)
│       ├── Steps_List ("1. Mở Camera điện thoại" → "2. Hướng vào mặt sàn" → "3. Xem tỉ lệ 1:1")
│       └── Direct_AR_Button ("Mở AR trên thiết bị này")
```

---

### 🖥️ Frame 5 (Drawer): 05_Mini_Cart_Drawer (Width: 460px × Height: 100vh)
```text
📦 [Overlay/05_Mini_Cart_Drawer] (Right Slide-in, Background: #FFFFFF)
├── Drawer_Header (Title: "Giỏ hàng của bạn (1 món)", Close [X])
├── Free_Shipping_Progress_Bar ("Mua thêm $380.00 để được Miễn phí vận chuyển")
├── Cart_Items_List (Cuộn dọc, Ảnh + Tên + Biến thể màu + Stepper + Nút Xóa)
├── Subtotal_Row ("Tạm tính: $120.00")
└── Drawer_Footer (Button "TIẾN HÀNH THANH TOÁN" chuyển sang Checkout)
```

---

## 3. SƠ ĐỒ NỐI PROTOTYPE CHẠY THỬ (INTERACTION PROTOTYPE WIRING)

| Điểm bắt đầu (Hotspot Trigger) | Hành động (Action) | Màn hình đích (Target Frame) | Hiệu ứng chuyển động (Animation) |
|---|---|---|---|
| **Header $\rightarrow$ "Cửa hàng"** | On Click | `Desktop/02_Catalog_Shop` | Smart Animate (Ease Out, 300ms) |
| **Hero $\rightarrow$ Button "Khám phá ngay"** | On Click | `Desktop/02_Catalog_Shop` | Push / Navigate (Ease Out, 300ms) |
| **Shop $\rightarrow$ Click Thẻ sản phẩm Shelvo** | On Click | `Desktop/03_Single_Product_3D` | Smart Animate (Ease In-Out, 350ms) |
| **Product $\rightarrow$ Click Nút "XEM 3D & AR"** | On Click | `Overlay/04_Modal_3D_AR` | **Open Overlay** (Center, Fade In 250ms, Background 60% Black, Close when clicking outside) |
| **Modal 3D $\rightarrow$ Button [X] hoặc Click ngoài** | On Click | Đóng Overlay | Close Overlay |
| **Product $\rightarrow$ Click nút tròn vân gỗ** | On Click | Đổi Variant Component ảnh SP | Instant / Smart Animate 150ms |
| **Product $\rightarrow$ Button "THÊM VÀO GIỎ"** | On Click | `Overlay/05_Mini_Cart_Drawer` | **Open Overlay** (Top Right, Slide-in from Right 350ms, Background 40% Black) |
| **Mini-Cart $\rightarrow$ Button "THANH TOÁN"** | On Click | `Desktop/06_Checkout` | Navigate to Frame (Ease Out, 300ms) |
| **Checkout $\rightarrow$ Button "ĐẶT HÀNG"** | On Click | `Desktop/07_Order_Received` | Smart Animate Success Toast |
| **Header $\rightarrow$ Icon User** | On Click | `Desktop/05_My_Account` | Navigate to Frame |

---

## 4. HƯỚNG DẪN ĐƯA VÀO FIGMA ĐƠN GIẢN NHẤT (1 CLICK)

### Cách 1: Sử dụng Plugin "html.to.design" trong Figma (Khuyên dùng - Ra Layer & Auto Layout 100%)
1. Mở Figma $\rightarrow$ Tạo 1 file mới đặt tên: **`DeSpace Luxury Storefront`**.
2. Nhấn chuột phải $\rightarrow$ **Plugins** $\rightarrow$ Tìm và cài plugin: **`html.to.design`** (hoặc `Builder.io - Figma to Code`).
3. Chọn chế độ **"Import from HTML file"** (hoặc dán đường dẫn local).
4. Chọn lần lượt các file trong thư mục:
   - `01_homepage.html`
   - `02_catalog_shop.html`
   - `03_single_product_3d.html`
   - `04_cart.html`
   - `05_my_account.html`
5. Plugin sẽ tự động phân tích và tạo ra toàn bộ **Frames, Auto Layout, Text Layers và Vector SVG** chuẩn chỉnh 100% trong Figma!

### Cách 2: Kéo thả các ảnh Retina @2x (Pixel-perfect)
* Mở thư mục `figma_despace/exports/` trên máy tính.
* Tạo các Frame kích thước chuẩn: Desktop (`1440px`), Mobile (`390px`).
* Kéo các file PNG tương ứng vào Frame làm bản thiết kế chuẩn xác tuyệt đối từng pixel để trình chiếu hoặc trace layer.
