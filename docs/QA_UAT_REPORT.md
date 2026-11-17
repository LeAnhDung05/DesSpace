# Biên Bản Nghiệm Thu Kiểm Thử Toàn Hệ Thống (QA & UAT Report)

**Dự án:** DeSpace — Nền Tảng Thương Mại Điện Tử Nội Thất 3D & WebXR AR  
**Ngày nghiệm thu:** 17/11/2026  
**Môi trường kiểm thử:** WordPress + WooCommerce 11.x (HPOS Enabled) + PHP 8.3 + MySQL 8.4 + Mailpit SMTP

---

## 1. Tổng Hợp Kết Quả Kiểm Thử Tự Động (Unit, Integration & Live UAT)

| Hạng mục / Bộ kiểm thử | File kịch bản | Số lượng Test Cases | Kết quả | Tỷ lệ |
|---|---|---:|---|---|
| **1. Kiến trúc & Phân quyền (RBAC)** | `tests/test-rbac-matrix.php` | 30 | **30 PASS / 0 FAIL** | 100% |
| **2. Kiểm toán Tồn kho (M4 Matrix)** | `tests/test-inventory-matrix.php` | 28 | **28 PASS / 0 FAIL** | 100% |
| **3. Đồng bộ Ứng dụng Desktop 3D (M1)** | `tests/test-desktop-3d-sync.php` | 12 | **12 PASS / 0 FAIL** | 100% |
| **4. Core Engine 3D Python (M1)** | `tests/test_desktop_app_core.py` | 10 | **10 PASS / 0 FAIL** | 100% |
| **5. Giỏ hàng (M3 Cart Unit)** | `tests/test-m3-cart-unit.php` | 18 | **18 PASS / 0 FAIL** | 100% |
| **6. Thanh toán & Địa chỉ 2 cấp VN (M3 Checkout)** | `tests/test-m3-checkout-unit.php` | 30 | **30 PASS / 0 FAIL** | 100% |
| **7. Mã giảm giá (M3 Coupon)** | `tests/test-m3-coupon-unit.php` | 15 | **15 PASS / 0 FAIL** | 100% |
| **8. Vận chuyển Việt Nam (M3 Shipping)** | `tests/test-m3-shipping-unit.php` | 16 | **16 PASS / 0 FAIL** | 100% |
| **9. Cổng thanh toán BACS/COD (M3 Payment)** | `tests/test-m3-payment-unit.php` | 18 | **18 PASS / 0 FAIL** | 100% |
| **10. Tài khoản & Địa chỉ (M3 Account)** | `tests/test-m3-account-unit.php` | 27 | **27 PASS / 0 FAIL** | 100% |
| **11. Tra cứu đơn hàng & Chống IDOR (M3 Tracking)** | `tests/test-m3-order-tracking-unit.php` | 18 | **18 PASS / 0 FAIL** | 100% |
| **12. Email giao dịch (M3 Email Unit)** | `tests/test-m3-email-unit.php` | 14 | **14 PASS / 0 FAIL** | 100% |
| **13. Smoke Test WooCommerce 11.3 HPOS** | `tests/test-m3-wc113-smoke.php` | 11 | **11 PASS / 0 FAIL** | 100% |
| **14. Live UAT: BACS Concurrency & Atomic Lock** | `tests/uat-phase6c-bacs-concurrency.php` | 6 | **6 PASS / 0 FAIL** | 100% |
| **15. Live UAT: M4 Inventory Lifecycle & Reconciler** | `tests/uat-m4-lifecycle-reconciliation.php` | 8 | **8 PASS / 0 FAIL** | 100% |
| **16. Live UAT: Mailpit Real Email Capture** | `tests/uat-phase6e-emails.php` | 6 | **6 PASS / 0 FAIL** | 100% |
| **17. Live UAT: Account Pagination (>100 Orders)** | `tests/uat-phase6f-account-tracking.php` | 6 | **6 PASS / 0 FAIL** | 100% |

---

## 2. Kết Quả Nghiệm Thu 12 Hạng Mục Chức Năng Cốt Lõi (DoD Checklist)

1. **[PASS] Danh mục 40 sản phẩm nội thất chuẩn hóa:** Đầy đủ thông số kích thước (Rộng × Sâu × Cao), chất liệu, màu sắc, giá VND và hình ảnh đa góc chụp.
2. **[PASS] Trình xem 3D PBR & WebXR AR (`<model-viewer>`):** 40/40 sản phẩm có đầy đủ mô hình `.glb` và `.usdz` gắn theo đúng mã SKU (`AXG-STR-001` đến `AXG-STR-040`).
3. **[PASS] Ứng dụng Desktop 3D Studio & Batch Excel Sync:** Kết nối hai chiều qua REST API `/wp-json/despace/v1/desktop-3d/*` và cổng local `58300`.
4. **[PASS] Tìm kiếm song ngữ & Bộ lọc AJAX:** Hỗ trợ tìm kiếm tiếng Việt có dấu/không dấu và tiếng Anh theo đúng 5 danh mục tủ kệ thực tế.
5. **[PASS] Giỏ hàng & Mini-Cart Drawer:** Tăng/giảm số lượng, xóa sản phẩm và tính tiến trình miễn phí vận chuyển (ngưỡng `5.000.000 ₫`).
6. **[PASS] Địa chỉ hành chính Việt Nam 2 cấp (Tỉnh/Thành phố — Phường/Xã):** Hoạt động chính xác trên cả trang Thanh toán và Sổ địa chỉ tài khoản.
7. **[PASS] Thanh toán COD & BACS (VietQR):** Khóa nguyên tử MySQL (`acquire_bacs_lock`) chống xác nhận trùng lặp khi nhiều quản trị viên thao tác đồng thời.
8. **[PASS] Nhật ký Kiểm toán Tồn kho (M4 Inventory Logs):** Tự động ghi nhận mọi biến động trừ kho khi đặt hàng, hoàn kho khi hủy/hoàn tiền và đối soát chính xác 100% qua `InventoryReconciler`.
9. **[PASS] Email giao dịch qua Mailpit:** Bắt giữ và xác thực thành công cả 6 loại email giao dịch WooCommerce với địa chỉ 2 cấp Việt Nam.
10. **[PASS] Tra cứu đơn hàng & Bảo mật chống IDOR:** Phân trang mượt mà với >100 đơn hàng HPOS, chặn truy cập trái phép giữa các khách hàng.
11. **[PASS] Bảng điều khiển quản trị DeSpace Hub:** 6 tab trực quan phục vụ quản lý cửa hàng, khôi phục tài nguyên, ma trận 3D, phân quyền RBAC và nhật ký tồn kho.
12. **[PASS] Bảo mật & Tương thích HPOS:** Đạt chuẩn khai báo `custom_order_tables` của WooCommerce và vượt qua toàn bộ bài kiểm toán bảo mật.
