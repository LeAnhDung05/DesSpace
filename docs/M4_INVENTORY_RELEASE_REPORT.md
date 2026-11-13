# DeSpace M4 — Báo Cáo Nghiệm Thu & Release Gate Tồn Kho (PR #38)

**Tài liệu:** DESPACE-DOC-M4-01  
**Mô-đun:** M4 — Quản lý & Truy vết biến động tồn kho (Inventory Traceability)  
**Pull Request:** [#38](https://github.com/LeAnhDung05/DesSpace/pull/38)  
**Nhánh nguồn:** `feature/member04-inventory`  
**Nhánh đích:** `develop`  
**Trạng thái kiểm thử:** 68 / 68 Tests PASS (100%) trên Staging WordPress 7.1.4, WooCommerce 11.1.2, MariaDB 10.11, HPOS  
**Đánh giá kỹ thuật:** **GO (Đạt chuẩn release, sẵn sàng hợp nhất vào develop)**

---

## 1. TỔNG QUAN NGUYÊN TẮC KIẾN TRÚC & PHẠM VI

1. **WooCommerce là nguồn chân lý duy nhất (Single Source of Truth):**  
   M4 không sở hữu trường số lượng tồn riêng, không can thiệp trực tiếp làm lệch số lượng tồn của WooCommerce và không xây dựng lại logic trừ tồn của WooCommerce Core. M4 đóng vai trò là hệ thống quan sát (Observer) và ghi nhật ký truy vết (Traceability Audit Log) tuân thủ kiến trúc Event-Driven.
2. **Tính Idempotent & Chống ghi trùng:**  
   Mọi bản ghi sự kiện biến động kho đều có `event_key` được sinh tất định (deterministic hash SHA-256) từ ID nghiệp vụ gốc (`order_id`, `order_item_id`, `refund_id`, `source_operation_id`). Cơ sở dữ liệu MariaDB bảo vệ tuyệt đối bằng ràng buộc `UNIQUE KEY (event_key)`.
3. **Tuân thủ HPOS (High-Performance Order Storage):**  
   M4 tương thích hoàn toàn với chế độ bảng đơn hàng tùy chỉnh của WooCommerce (`wp_wc_orders`, `wp_wc_order_product_lookup`). Không sử dụng truy vấn thô vào `wp_posts` hay `wp_postmeta`.
4. **Cô lập ranh giới mô-đun:**  
   Toàn bộ mã nguồn M4 nằm độc lập trong `wordpress/wp-content/plugins/despace-core/includes/Inventory/`. Không sửa đổi file bootstrap `despace-core.php` (do M5 quản lý), không can thiệp luồng thanh toán Checkout/BACS của M3 và không can thiệp giao diện Catalog của M2.

---

## 2. LỊCH SỬ HARDENING & GIẢI QUYẾT CÁC PHÁT HIỆN CODE REVIEW

| Commit SHA | Nội dung xử lý kỹ thuật |
|---|---|
| [`bd8a595`](https://github.com/LeAnhDung05/DesSpace/commit/bd8a59562b3c351d3ef76c8d47b746451edb1f7d) | **Thiết lập ngữ cảnh trước đột biến tồn kho:** Đăng ký ngữ cảnh đơn hàng/hoàn tiền trước khi WooCommerce thực hiện trừ tồn hoặc hoàn tồn, bảo đảm các hook `woocommerce_product_set_stock` luôn nhận đúng `order_id` thay vì phân loại nhầm thành `manual_adjustment`. |
| [`5fc996d`](https://github.com/LeAnhDung05/DesSpace/commit/5fc996d76ea39fb9ebf74ebbc8d3f61bbe3e00d4) | **Giải quyết trùng lặp sản phẩm bằng FIFO Queue:** Thay thế cấu trúc ánh xạ phẳng bằng hàng đợi FIFO (`items_queue`) theo từng thực thể quản lý tồn (`stock_entity_id`). Thêm bộ lọc dọn dẹp ngữ cảnh tại priority `999999` khi đơn hàng bị từ chối trừ/hoàn tồn. |
| [`e3d0f6d`](https://github.com/LeAnhDung05/DesSpace/commit/e3d0f6dc549e5fea13430196e19edc9cebf57af9) | **Xóa bỏ fallback `items_map` cũ & cô lập đột biến không khớp:** Loại bỏ hoàn toàn fallback tra cứu mảng tĩnh có nguy cơ ghi đè line-item ID. Khi hàng đợi rỗng hoặc sản phẩm đột biến không thuộc đơn hàng, M4 chủ động đặt lại `order_id = null`, `order_item_id = null`, ghi nhận cô lập thành `manual_adjustment` mà không gây nhiễm bẩn ngữ cảnh đơn hàng. Bổ sung ma trận kiểm thử biến thể (Case N), bộ lọc từ chối muộn (Case O1-O3) và kiểm thử an toàn FIFO (Case P). |
| [`5c24fdf`](https://github.com/LeAnhDung05/DesSpace/commit/5c24fdf8b1d9bf5c117b1897cbb1936b85848bb2) | **Báo cáo nghiệm thu & phát hành ban đầu:** Tổng hợp kết quả kiểm thử trên môi trường Staging Docker, chuẩn bị hồ sơ release gate. |
| **Latest** | **Giải quyết toàn bộ các phát hiện Conditional GO (M4-REV-001 đến M4-REV-004):**<br>• **M4-REV-001:** Bổ sung cơ chế chuẩn hóa đối soát cho biến thể do cha quản lý (`parent_managed_shared` scope, truy vấn theo cha `product_id = %d AND variation_id IS NULL`).<br>• **M4-REV-002:** Bảo vệ tính năng xuất CSV bằng Nonce (`wp_nonce_url`, `wp_verify_nonce`).<br>• **M4-REV-003:** Đăng ký Action Scheduler (`action_scheduler_after_execute`, `action_scheduler_failed_execution`) & `shutdown` hook (priority 999) để dọn dẹp ngữ cảnh bộ nhớ, chống rò rỉ ngữ cảnh trong CLI/Queue dài hạn.<br>• **M4-REV-004:** Xử lý trường hợp đối soát sản phẩm chưa có lịch sử log, trả về `insufficient_history` và `is_reconciled = false` (không cho phép false PASS).<br>• **Legacy Cleanup:** Xóa bỏ hoàn toàn code chết `build_order_items_map()` và key `'items_map'`.<br>• **Boundary Hardening:** Nâng priority của guard dọn dẹp late denial lên `PHP_INT_MAX`. |

---

## 3. CHI TIẾT GIẢI QUYẾT CÁC PHÁT HIỆN CODE REVIEW

### 3.1. M4-REV-001 — Đối soát biến thể do cha quản lý (Parent-Managed Variation)
- **Vấn đề trước:** Khi biến thể không tự quản lý tồn mà dùng chung kho với sản phẩm cha (`manage_stock = false`), WooCommerce trừ kho ở sản phẩm cha. Bảng log ghi nhận `product_id = parent_id` và `variation_id = NULL`. Khi gọi `InventoryReconciler::reconcile($parent_id, $variation_id)`, truy vấn lọc theo `variation_id = $variation_id` trả về 0 kết quả delta, gây sai lệch trạng thái đối soát.
- **Giải pháp:** `InventoryReconciler::reconcile()` tự động phát hiện quyền quản lý kho thông qua `wc_get_product()`, kiểm tra `$product->get_stock_managed_by_id()` và `$product->is_type('variation')`. Nếu biến thể do cha quản lý, đối soát tự động chuẩn hóa về sản phẩm cha, thiết lập `stock_scope = 'parent_managed_shared'`, truy vấn `WHERE product_id = %d AND variation_id IS NULL`, đối soát chính xác số dư kho dùng chung.

### 3.2. M4-REV-002 — Bảo vệ liên kết xuất CSV bằng Nonce (Admin Export Nonce)
- **Vấn đề trước:** Đường dẫn xuất CSV trong Admin UI không có WordPress Nonce bảo vệ, tiềm ẩn nguy cơ CSRF kích hoạt tải dữ liệu lớn.
- **Giải pháp:** Bổ sung `wp_nonce_url($raw_export_url, 'despace_export_csv')` khi render nút xuất CSV trên trang Admin. Tại hook `admin_init` (`InventoryLogsPage::handle_export()`), kiểm tra nghiêm ngặt `wp_verify_nonce($_GET['_wpnonce'], 'despace_export_csv')`, trả về HTTP 403 nếu nonce thiếu hoặc không hợp lệ.

### 3.3. M4-REV-003 — Cách ly vòng đời CLI / Action Scheduler & Ngoại lệ
- **Vấn đề trước:** Khi xử lý batch đơn hàng qua CLI script hoặc Action Scheduler worker, nếu xảy ra ngoại lệ không bắt được (uncaught exception) làm gián đoạn luồng trừ tồn, ngữ cảnh `InventoryOrderContext` có thể tồn tại trong tiến trình PHP daemon.
- **Giải pháp:** 
  1. Đăng ký hook dọn dẹp tự động vào `action_scheduler_after_execute` và `action_scheduler_failed_execution` (priority 999).
  2. Đăng ký hook `shutdown` tại priority 999 dọn sạch in-memory context trước khi tiến trình kết thúc.
  3. Cung cấp và tài liệu hóa phương thức public `InventoryStockObserver::clear_all_context()` để các tác vụ CLI/Batch bên ngoài có thể đặt trong khối `finally` một cách an toàn.

### 3.4. M4-REV-004 — Tính toàn vẹn Baseline đối soát khi không có log (Zero-History Safety)
- **Vấn đề trước:** Sản phẩm có tồn kho thực tế trong WooCommerce nhưng chưa từng có bản ghi biến động nào trong bảng log (`total_events === 0`) trước đây được trả về `is_reconciled = true` với discrepancy = 0.
- **Giải pháp:** Khi `total_events === 0`, nếu tồn kho hiện tại khác 0 (hoặc không xác định được baseline ban đầu), `InventoryReconciler` trả về:
  - `is_reconciled`: `false`
  - `reconciliation_status`: `'insufficient_history'`
  - `discrepancy`: `null`
  Ngăn chặn hoàn toàn việc xác nhận sai lệch trạng thái đối soát khi thiếu dữ liệu kiểm toán.

### 3.5. Loại bỏ hoàn toàn mã nguồn thừa (Legacy Code Cleanup)
- Phương thức chết `InventoryStockObserver::build_order_items_map()` đã bị xóa bỏ hoàn toàn khỏi mã nguồn.
- Loại bỏ tham số `'items_map'` khỏi `InventoryOrderContext::set()` và `$current_context`.

### 3.6. Nâng cấp bộ lọc dọn dẹp ngữ cảnh từ chối muộn (Late Denial Boundary)
- Bộ lọc dọn dẹp ngữ cảnh dự phòng (`on_check_can_reduce_order_stock_final`, `on_check_can_restore_order_stock_final`, `on_check_can_restock_refunded_items_final`) được nâng từ priority `999999` lên `PHP_INT_MAX`.
- Đảm bảo thực thi sau tất cả các custom plugin/filter của bên thứ ba bất kể giá trị priority (đã kiểm chứng với custom filter tại priority `1000000`).

---

## 4. THÔNG SỐ MÔI TRƯỜNG STAGING CHUẨN

Kết quả kiểm thử được thực thi và chứng minh trên môi trường container Staging thực tế:

- **Hệ điều hành / Runtime:** Linux x86_64 / PHP 8.3.35 (memory_limit: 512M)
- **WordPress Core:** Phiên bản 7.1.4 (Active)
- **WooCommerce:** Phiên bản 11.1.2 (Active)
- **Cơ sở dữ liệu:** MariaDB 10.11.16 (`bttts5-db`, Database: `wordpress`, Table Prefix: `wp_`)
- **HPOS (Custom Orders Table):** Đang kích hoạt (`custom_orders_table_usage_is_enabled: YES`)
- **Container Staging:** `bttts5-wp` (WordPress Application Server), `bttts5-db` (MariaDB Server)

---

## 5. BẢNG TỔNG HỢP KẾT QUẢ KIỂM THỬ (100% PASS)

| STT | Bộ kiểm thử (Test Suite) | Tập tin kiểm thử | Kịch bản / Ca kiểm thử | Kết quả nghiệm thu |
|:---:|---|---|:---:|:---:|
| 1 | **Đơn vị & Ma trận truy vết (T01–T28)** | `tests/test-inventory-matrix.php` | 28 / 28 | **28 / 28 PASS (100%)** |
| 2 | **Tích hợp Live WooCommerce & DB (M4-INT)** | `tests/test-inventory-integration-live.php` | 9 / 9 | **9 / 9 PASS (100%)** |
| 3 | **Ngữ cảnh đơn hàng & Vòng đời tồn kho (Cases A–U)** | `tests/test-m4-order-context-live.php` | 23 / 23 | **23 / 23 PASS (100%)** |
| 4 | **UAT Đối soát vòng đời đơn hàng BACS (Phase 6D)** | `tests/uat-m4-lifecycle-reconciliation.php` | 8 / 8 bước | **8 / 8 PASS (100%)** |

**TỔNG CỘNG: 68 / 68 TEST ASSERTIONS PASSED (100%) TRÊN STAGING RUNTIME THẬT**

---

## 6. CHI TIẾT 23 CA KIỂM THỬ NGỮ CẢNH ĐƠN HÀNG (`Cases A–U`)

1. **Case A (Trừ tồn đơn hàng đơn dòng):** Trừ 20 → 17, ghi nhận `order_reduction`, `order_id` và `order_item_id`.
2. **Case B (Đơn hàng nhiều sản phẩm):** Phân bổ chính xác line-item ID cho từng sản phẩm, không xung đột `event_key`.
3. **Case C (Trừ tồn biến thể):** Lưu trữ chính xác `product_id` (ID cha) và `variation_id` (ID biến thể).
4. **Case D (Hủy đơn / Hoàn tồn):** Hoàn lại +3 đơn vị, ghi nhận `stock_restoration`, kế thừa đúng ngữ cảnh đơn hàng.
5. **Case E (Hoàn tiền một phần có restock):** Ghi nhận `refund_restock`, đầy đủ `order_id`, `refund_id`, `order_item_id`.
6. **Case F (Hoàn tiền toàn phần có restock):** Hoàn đủ số lượng ban đầu qua 2 đợt hoàn tiền khác nhau, ghi nhận 2 `refund_id` riêng biệt.
7. **Case G (Hoàn tiền tài chính không restock):** 0 delta số lượng, 0 bản ghi nhật ký phát sinh.
8. **Case H (Điều chỉnh thủ công trên WP-Admin):** Ghi nhận `manual_adjustment` kèm `actor_user_id = 1`.
9. **Case I (Cập nhật tự động hệ thống/cron):** Ghi nhận `event_source = system`, `actor_user_id = NULL`.
10. **Case J (Tính Idempotent & Khóa UNIQUE):** Thử nghiệm gọi lặp hook cùng tham số; bản ghi trùng bị MariaDB chặn an toàn, không sinh lỗi fatal.
11. **Case K (Trùng lặp sản phẩm nhiều dòng khi hoàn tồn):** Xử lý đơn hàng có 2 dòng chứa cùng 1 sản phẩm (SL 4 và SL 6). Hàng đợi FIFO phân bổ chính xác 2 bản ghi riêng biệt với delta +4 và +6.
12. **Case L (Trùng lặp sản phẩm nhiều dòng khi hoàn tiền):** Hoàn tiền restock cho 2 dòng sản phẩm giống nhau; sinh đúng 2 bản ghi với 2 `order_item_id` tương ứng.
13. **Case M (Nhiều đợt hoàn tiền một phần liên tiếp):** Phân bổ chính xác từng ID đơn hoàn tiền riêng biệt.
14. **Case N (Vòng đời toàn diện của biến thể):**
    - *Phần 1 (Biến thể tự quản lý tồn):* Ghi nhận đầy đủ `product_id` (cha), `variation_id` (con), delta chính xác qua 3 giai đoạn: Trừ tồn (-4.0) → Hoàn tiền restock (+2.0) → Khôi phục hủy đơn (+2.0). Tồn kho kết thúc về 15.0.
    - *Phần 2 (Biến thể do sản phẩm cha quản lý tồn):* Theo chuẩn WooCommerce và test T08, tồn kho quản lý tại cha nên `product_id` ghi nhận ID cha, `variation_id` để `NULL`, trong khi `order_item_id` vẫn chỉ định chính xác dòng đặt hàng của biến thể.
15. **Case O-1 (Từ chối trừ tồn muộn tại Priority 20):** Filter trả về `false` sau khi M4 đã khởi tạo ngữ cảnh; guard PHP_INT_MAX dọn sạch bộ nhớ; không sinh log trừ tồn; thao tác điều chỉnh thủ công tiếp theo được ghi nhận độc lập thành `manual_adjustment`.
16. **Case O-2 (Từ chối hoàn tồn muộn tại Priority 20):** Filter trả về `false` trong luồng hoàn tồn; guard dọn sạch ngữ cảnh; không sinh log `stock_restoration`; thao tác thủ công sau đó ghi nhận `manual_adjustment` chuẩn xác.
17. **Case O-3 (Từ chối hoàn tiền restock muộn tại Priority 20):** Filter trả về `false` trong luồng hoàn tiền; guard dọn sạch ngữ cảnh; không sinh log `refund_restock`; thao tác thủ công sau đó ghi nhận `manual_adjustment` độc lập.
18. **Case P (An toàn hàng đợi FIFO & Cô lập thực thể không khớp):** Khi ngữ cảnh hoàn tồn của một đơn hàng đang mở, một sản phẩm không thuộc đơn hàng đột ngột tăng tồn. M4 nhận diện hàng đợi rỗng và lập tức cô lập bản ghi thành `manual_adjustment` (`order_id = NULL`, `order_item_id = NULL`). Ngay sau đó, sản phẩm hợp lệ của đơn hàng hoàn tồn thành công với đầy đủ `order_id` và `order_item_id`.
19. **Case Q (Đối soát biến thể do cha quản lý - M4-REV-001):** Tạo sản phẩm biến thể dùng chung kho cha (30 đơn vị), tạo đơn hàng trừ 4 và 6 đơn vị. Đối soát qua parent ID hoặc variation ID đều chuẩn hóa về kho cha, trả về `is_reconciled = true`, `stock_scope = 'parent_managed_shared'`, khớp chính xác tồn kho 20.0.
20. **Case R (Baseline kiểm toán & Trạng thái thiếu lịch sử - M4-REV-004):** Sản phẩm có tồn kho nhưng 0 bản ghi log được đánh dấu chính xác là `insufficient_history` và `is_reconciled = false`. Sản phẩm có lịch sử đối soát bình thường đạt `reconciled`.
21. **Case S (Bảo vệ Nonce & Phân quyền trang Admin CSV - M4-REV-002):** Kiểm tra tính hợp lệ của Nonce xuất CSV (`despace_export_csv`), phát hiện và chặn Nonce giả mạo, chặn khách không đăng nhập, cho phép người dùng có quyền quản lý kho.
22. **Case T (Vòng đời Action Scheduler & Dọn dẹp ngoại lệ Batch - M4-REV-003):** Kích hoạt các hook `action_scheduler_after_execute`, `action_scheduler_failed_execution`, `shutdown` và khối `try/finally` dọn sạch bộ nhớ ngữ cảnh trong mọi kịch bản.
23. **Case U (Ranh giới từ chối muộn tại Priority 1000000 & Guard PHP_INT_MAX):** Kiểm tra bộ lọc từ chối tại priority cực cao (1,000,000). Guard tại `PHP_INT_MAX` dọn sạch ngữ cảnh an toàn, không rò rỉ log.

---

## 7. DỮ LIỆU THỰC CHỨNG TỪ MARIADB STAGING

Trích xuất trực tiếp từ bảng `wp_despace_inventory_logs` trên container MariaDB 10.11 (`bttts5-db`) trong phiên chạy kiểm thử mới nhất:

```text
+-----+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
| id  | event_key        | event_type        | event_source | product_id | variation_id | order_id | order_item_id | refund_id | delta  | after |
+-----+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
| 682 | f8a98b584988ce2a | refund_restock    | refund       | 2491       | NULL         | 2492     | 546           | 2493      | +1.0   | 18.0  |
| 681 | b352a97bcff48db5 | order_reduction   | order        | 2491       | NULL         | 2492     | 546           | NULL      | -3.0   | 17.0  |
| 679 | 5ad62d2a588b509f | stock_restoration | order        | 2475       | NULL         | 2477     | 544           | NULL      | +3.0   | 20.0  |
| 678 | 07a1f5f25a9ce925 | manual_adjustment | system       | 2476       | NULL         | NULL     | NULL          | NULL      | +5.0   | 15.0  |
| 677 | 3ea6b6ca059f1c7d | order_reduction   | order        | 2475       | NULL         | 2477     | 544           | NULL      | -3.0   | 17.0  |
| 674 | ccbfe128ec03185d | manual_adjustment | system       | 2472       | NULL         | NULL     | NULL          | NULL      | +2.0   | 9.0   |
| 673 | ab35f992d9bb4e73 | order_reduction   | order        | 2472       | NULL         | 2473     | 542           | NULL      | -3.0   | 7.0   |
| 671 | 14aee8cbb94d509d | manual_adjustment | system       | 2470       | NULL         | NULL     | NULL          | NULL      | +5.0   | 12.0  |
| 670 | a17be3407987034b | order_reduction   | order        | 2470       | NULL         | 2471     | 541           | NULL      | -3.0   | 7.0   |
| 668 | aee7eeff6c7cb366 | manual_adjustment | system       | 2468       | NULL         | NULL     | NULL          | NULL      | +4.0   | 14.0  |
| 666 | a862eaec1dbe225d | stock_restoration | order        | 2464       | NULL         | 2466     | 538           | NULL      | +2.0   | 20.0  |
| 665 | ea2c75a40bb2be73 | refund_restock    | refund       | 2464       | NULL         | 2466     | 538           | 2467      | +1.0   | 18.0  |
| 664 | a12c5b7cb3b9a24a | order_reduction   | order        | 2464       | NULL         | 2466     | 538           | NULL      | -3.0   | 17.0  |
| 662 | 237d4546554b5df5 | stock_restoration | order        | 2460       | 2461         | 2462     | 536           | NULL      | +2.0   | 15.0  |
| 661 | 143899f1fa02c0b4 | refund_restock    | refund       | 2460       | 2461         | 2462     | 536           | 2463      | +2.0   | 13.0  |
| 660 | 664d9fae15fba33e | order_reduction   | order        | 2460       | 2461         | 2462     | 536           | NULL      | -4.0   | 11.0  |
+-----+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
```

---

## 8. GIỚI HẠN VÀ KHUYẾN NGHỊ VẬN HÀNH (KNOWN LIMITATIONS)

1. **Thao tác can thiệp trực tiếp qua SQL thô:** Nếu người quản trị thực hiện cập nhật trường `_stock` hoặc bảng custom của WooCommerce trực tiếp bằng lệnh SQL từ bên ngoài mà không qua WooCommerce API (`$product->save()`, `wc_update_product_stock()`), WordPress hooks sẽ không được kích hoạt. Đây là đặc tính mặc định của WooCommerce.
2. **Khởi chạy mô-đun:** M4 tuân thủ nghiêm ngặt ranh giới mô-đun và cung cấp phương thức tĩnh:
   ```php
   \DeSpace\Inventory\InventoryModule::init();
   ```
   M5 chịu trách nhiệm gọi phương thức này trong file bootstrap chính của plugin (`despace-core.php`).
3. **Quyền hạn truy cập Admin & REST API:**  
   Chỉ người dùng có quyền `manage_woocommerce` hoặc custom capability `view_despace_inventory_logs` mới được phép xem nhật ký và tải xuất CSV. Người dùng thông thường (customer/subscriber) bị chặn tuyệt đối (HTTP 403 Forbidden).
4. **Vận hành Batch / CLI Script ngoài luồng chuẩn:**  
   Khi phát triển các script đồng bộ kho bên ngoài (WP-CLI command hoặc custom daemon), khuyến nghị bao bọc logic trong khối `try/finally` và gọi `\DeSpace\Inventory\Events\InventoryStockObserver::clear_all_context()` để đảm bảo cô lập bộ nhớ hoàn toàn.

---

## 9. KẾT LUẬN & ĐỀ NGHỊ HỢP NHẤT (FINAL RELEASE GATE: GO)

Mã nguồn PR #38 đã hoàn thành khắc phục toàn bộ 4 phát hiện kiểm tra độc lập (`M4-REV-001`, `M4-REV-002`, `M4-REV-003`, `M4-REV-004`), loại bỏ hoàn toàn code thừa `items_map`, vượt qua 100% các bộ kiểm thử tự động (68/68 test assertions) trên môi trường Staging Docker MariaDB/HPOS.

**Trạng thái nghiệm thu cuối cùng: UNCONDITIONAL GO — SẴN SÀNG MERGE VÀO DEVELOP.**
