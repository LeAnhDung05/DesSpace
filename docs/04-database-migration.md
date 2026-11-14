# BÁO CÁO CHUYỂN ĐỔI DATABASE VÀ DỮ LIỆU SẢN PHẨM (DATABASE MIGRATION)

> **Mã tài liệu:** `docs/04-database-migration.md`  
> **Dự án:** Re-platforming BTTTS_5 → DeSpace  
> **Nguồn dữ liệu (Source):** MariaDB 10.11 (`bttts5-db`)  
> **Đích dữ liệu (Destination):** MySQL 8.4 (`despace-db-1`)  
> **Thời điểm xác nhận số liệu:** 2026-11-14 17:10  
> **Nguyên tắc đối soát:** Đối soát thực tế 100% bằng câu lệnh truy vấn SQL trực tiếp qua SKU làm khóa đối chiếu.

---

## 1. BẢNG TỔNG HỢP ĐỐI SOÁT DỮ LIỆU NGUỒN VÀ ĐÍCH

| Loại dữ liệu | Số lượng nguồn | Số lượng đích | Thành công | Thiếu | Trùng | Trạng thái | Ghi chú kỹ thuật |
|---|---:|---:|---:|---:|---:|---|---|
| **Sản phẩm Catalog chính thức** | 40 | 40 | 40 | 0 | 0 | **VERIFIED** | 100% SKU `AXG-STR-001` đến `AXG-STR-040` được giữ nguyên. |
| **Mô hình 3D & Siêu dữ liệu AR** | 40 | 40 | 40 | 0 | 0 | **VERIFIED** | Bảng `wp_despace_product_3d_models` (và `wp_axgnud_product_3d_models`) lưu đủ 40 bản ghi. |
| **Danh mục sản phẩm (`product_cat`)** | 5 | 5 | 5 | 0 | 0 | **VERIFIED** | Tủ giày, Tủ quần áo, Kệ sách, Tủ bếp, Tủ ngăn kéo & Sideboard. |
| **Thuộc tính kỹ thuật (`pa_*`)** | 4 | 4 | 4 | 0 | 0 | **VERIFIED** | Thương hiệu (`pa_brand`), Màu sắc (`pa_color`), Finish (`pa_finish`), Size (`pa_size`). |
| **Mã giảm giá (Coupons)** | 2 | 2 | 2 | 0 | 0 | **VERIFIED** | `welcome10` (giảm 10%), `save20` (giảm cố định $20/đơn). |
| **Nhật ký kiểm toán kho (`inventory_logs`)** | 1,634 | 1,681 | 1,634 | 0 | 0 | **VERIFIED** | Chuyển thành công 1,634 logs từ nguồn + 47 logs có sẵn, chống trùng qua `event_key`. |
| **Tệp nhị phân mô hình 3D (GLB + USDZ)** | 64 | 64 | 64 | 0 | 0 | **VERIFIED** | 32 file GLB và 32 file USDZ tại `wp-content/uploads/3d/` (13MB). |
| **Thư viện tệp đính kèm Media** | 353 | 353 | 353 | 0 | 0 | **VERIFIED** | Toàn bộ ảnh sản phẩm, ảnh gallery, banner hero, ảnh phòng tại `uploads/`. |

---

## 2. QUY TRÌNH MIGRATION AN TOÀN ĐÃ THỰC HIỆN

1. **Backup an toàn:** Đã tạo bản sao lưu dữ liệu nguyên vẹn của `bttts5-db` tại `backup/bttts5_live_backup_20261114.sql` (6.4MB).
2. **Đối soát khóa nghiệp vụ SKU:**
   - Source database sử dụng post_id 378–417 tương ứng với các SKU `AXG-STR-001` đến `AXG-STR-040`.
   - Destination database có các post_id từ 46 đến 467.
   - Script `migrate_3d_and_inventory.py` đã trích xuất danh sách SKU từ nguồn và tự động tra cứu ID tương ứng ở đích, đảm bảo mọi mô hình 3D, kích thước (width, depth, height), vật liệu và màu sắc gắn chính xác 100% vào đúng sản phẩm.
3. **Tương thích MySQL 8.4 Strict Mode:**
   - Trong quá trình migration, phát hiện MariaDB cho phép `DEFAULT ''` trên cột `text`, trong khi MySQL 8.4 bắt buộc `DEFAULT NULL` cho `text`.
   - Đã điều chỉnh câu lệnh tạo bảng `wp_despace_product_3d_models` tương thích nghiêm ngặt với MySQL 8.4 Community Edition.
4. **Cập nhật Postmeta đồng bộ:**
   - Đồng bộ siêu dữ liệu sản phẩm trong bảng `wp_postmeta`:
     * `_despace_glb_url`: Đường dẫn file GLB chuẩn.
     * `_despace_enable_ar`: 'yes' (kích hoạt AR).
     * `_axgnud_model_glb`, `_glb_file`: Các meta key dự phòng tương thích ngược.
5. **Đồng bộ bảng nhật ký tồn kho (`wp_despace_inventory_logs`):**
   - Đảm bảo `UNIQUE KEY event_key` ngăn chặn hoàn toàn việc nhân đôi bản ghi khi chạy lại script (Idempotent Migration).
