# DeSpace M4 — Báo Cáo Nghiệm Thu & Release Gate Tồn Kho (PR #38)

**Tài liệu:** DESPACE-DOC-M4-01  
**Mô-đun:** M4 — Quản lý & Truy vết biến động tồn kho (Inventory Traceability)  
**Pull Request:** [#38](https://github.com/LeAnhDung05/DesSpace/pull/38)  
**Nhánh nguồn:** `feature/member04-inventory`  
**Nhánh đích:** `develop`  
**Trạng thái kiểm thử:** 75 / 75 Test Cases PASS (100%), Exit Code: 0 trên cả 4 bộ test Staging (WordPress 7.1.4, WooCommerce 11.1.2, MariaDB 10.11, HPOS)  
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
| [`5fc996d`](https://github.com/LeAnhDung05/DesSpace/commit/5fc996d76ea39fb9ebf74ebbc8d3f61bbe3e00d4) | **Giải quyết trùng lặp sản phẩm bằng FIFO Queue:** Thay thế cấu trúc ánh xạ phẳng bằng hàng đợi FIFO (`items_queue`) theo từng thực thể quản lý tồn (`stock_entity_id`). Thêm bộ lọc dọn dẹp ngữ cảnh khi đơn hàng bị từ chối trừ/hoàn tồn. |
| [`e3d0f6d`](https://github.com/LeAnhDung05/DesSpace/commit/e3d0f6dc549e5fea13430196e19edc9cebf57af9) | **Xóa bỏ fallback `items_map` cũ & cô lập đột biến không khớp:** Loại bỏ fallback tra cứu mảng tĩnh. Khi hàng đợi rỗng hoặc sản phẩm đột biến không thuộc đơn hàng, cô lập thành `manual_adjustment` (`order_id = null`, `order_item_id = null`). Bổ sung ma trận kiểm thử biến thể (Case N), bộ lọc từ chối muộn (Case O1-O3) và kiểm thử an toàn FIFO (Case P). |
| [`5c24fdf`](https://github.com/LeAnhDung05/DesSpace/commit/5c24fdf8b1d9bf5c117b1897cbb1936b85848bb2) | **Báo cáo nghiệm thu & phát hành ban đầu:** Tổng hợp kết quả kiểm thử trên môi trường Staging Docker, chuẩn bị hồ sơ release gate. |
| [`37ca37f`](https://github.com/LeAnhDung05/DesSpace/commit/37ca37fdc8d6c52f2043496c771ee02086e57bfd) | **Giải quyết các phát hiện Conditional GO (M4-REV-001 đến M4-REV-004):**<br>• **M4-REV-001:** Chuẩn hóa đối soát biến thể do cha quản lý (`parent_managed_shared` scope, truy vấn cha).<br>• **M4-REV-002:** Bảo vệ tính năng xuất CSV bằng Nonce (`wp_nonce_url`, `wp_verify_nonce`).<br>• **M4-REV-003:** Đăng ký Action Scheduler & shutdown hook (priority 999) dọn dẹp ngữ cảnh bộ nhớ.<br>• **M4-REV-004:** Đối soát sản phẩm chưa có lịch sử log trả về `insufficient_history` và `is_reconciled = false`.<br>• **Legacy Cleanup:** Xóa bỏ hoàn toàn code chết `build_order_items_map()` và key `'items_map'`.<br>• **Boundary:** Nâng priority của guard dọn dẹp late denial lên `PHP_INT_MAX`. |
| [`3e3dd03`](https://github.com/LeAnhDung05/DesSpace/commit/3e3dd03abedc3a483fa05d0243652661c0e69fa4) | **Giải quyết toàn diện các phát hiện Final Sign-Off (M4-REV-006 đến M4-REV-008):**<br>• **M4-REV-006:** Chuẩn hóa Exit Codes cho tất cả test runners: Bổ sung nhánh `exit(1)` khi có bất kỳ assertion thất bại hoặc thiếu kịch bản; bọc `run()` bằng `try/catch` bắt ngoại lệ; kiểm chứng bằng test giả lập fail thực tế trả về exit code 1.<br>• **M4-REV-007:** Xác minh End-to-End luồng Admin CSV Export: Tách `validate_export_request()` độc lập; bổ sung direct stream cho `export_csv()`; kiểm chứng đăng ký hook `admin_init`, từ chối missing nonce, từ chối invalid nonce, chặn guest không có quyền, cấp phép admin, URL sinh đúng nonce, và stream trực tiếp CSV có UTF-8 BOM, đúng header và dữ liệu lọc.<br>• **M4-REV-008:** Phân tích ranh giới cùng priority tại `PHP_INT_MAX` (Case U-2) & kiểm thử Action Scheduler thực tế trên Staging (Case T): Chạy trực tiếp action qua `ActionScheduler::runner()->process_action()`; xác minh dọn dẹp tự động khi thành công lẫn khi ném exception; xác minh cách ly đột biến tiếp theo; ghi nhận rõ kiến trúc FIFO của WordPress `WP_Hook` vào Known Limitations. |
| [`a2bae1d`](https://github.com/LeAnhDung05/DesSpace/commit/a2bae1d8dc0ee6c196cb552b408e9d4237e0eab0) | **Khắc phục lỗi cô lập ngữ cảnh khi bị từ chối tại cùng priority PHP_INT_MAX (M4-REV-009):**<br>• Bổ sung cơ chế Active Caller Verification Guard (`is_in_stock_caller()`) kết hợp theo dõi ngữ cảnh `expected_caller` (`wc_reduce_stock_levels`, `wc_increase_stock_levels`, `wc_restock_refunded_items`).<br>• Khi filter bên ngoài từ chối thao tác ở cùng priority `PHP_INT_MAX`, mọi đột biến kho thủ công tiếp theo trên CÙNG SẢN PHẨM sẽ kích hoạt `on_set_stock()`, phát hiện sai lệch call stack, dọn sạch toàn bộ context/FIFO queue (`clear_all_context()`) và ghi nhận chính xác thành `manual_adjustment` (`order_id = null`, `order_item_id = null`, `refund_id = null`).<br>• Bổ sung 3 ca kiểm thử thực tế Cases U-3, U-4, U-5 kiểm chứng trên cả 3 luồng: Trừ tồn, Hoàn tồn và Hoàn tiền restock. Nâng tổng số ca kiểm thử lên 72/72 PASS (100%), Exit Code 0. |
| **Latest** | **Cô lập đột biến tồn kho lồng nhau & Phân loại người gọi trực tiếp (M4-REV-010):**<br>• Thay thế kiểm tra boolean `is_in_stock_caller()` bằng `classify_stock_mutation_caller()` phân loại 4 trạng thái: `direct`, `nested`, `stale`, `none`.<br>• Khi phát hiện `nested` (hàm WooCommerce là ancestor trên stack nhưng đột biến do callback/hook trung gian gọi), cô lập cục bộ đột biến đó thành `manual_adjustment` (`order_id = null`, `order_item_id = null`, `refund_id = null`), TUYỆT ĐỐI KHÔNG xóa ngữ cảnh hoặc hàng đợi FIFO của đơn hàng cha đang xử lý.<br>• Theo dõi `active_stock_entity_id` trong `on_filter_order_item_quantity()` và đối soát với `stock_entity_id` hiện tại để chặn hành vi chiếm dụng line item trái phép.<br>• Hoàn thiện Case U-5 với thực thể hoàn tiền thực tế `WC_Order_Refund` (`refund_id > 0`), bổ sung Cases U-6, U-7, U-8 kiểm chứng đột biến lồng nhau trên cả 3 luồng (Trừ tồn, Hoàn tồn, Hoàn tiền restock). Nâng tổng số ca kiểm thử lên 75/75 PASS (100%), Exit Code 0. |

---

## 3. CHI TIẾT GIẢI QUYẾT CÁC PHÁT HIỆN CODE REVIEW

### 3.1. M4-REV-001 — Đối soát biến thể do cha quản lý (Parent-Managed Variation)
- **Vấn đề:** Khi biến thể không tự quản lý tồn mà dùng chung kho với sản phẩm cha (`manage_stock = false`), WooCommerce trừ kho ở sản phẩm cha. Bảng log ghi nhận `product_id = parent_id` và `variation_id = NULL`. Khi gọi `InventoryReconciler::reconcile($parent_id, $variation_id)`, truy vấn lọc theo `variation_id = $variation_id` trả về 0 kết quả delta.
- **Giải pháp:** `InventoryReconciler::reconcile()` tự động phát hiện quyền quản lý kho thông qua `wc_get_product()`, kiểm tra `$product->get_stock_managed_by_id()` và `$product->is_type('variation')`. Nếu biến thể do cha quản lý, đối soát tự động chuẩn hóa về sản phẩm cha, thiết lập `stock_scope = 'parent_managed_shared'`, truy vấn `WHERE product_id = %d AND variation_id IS NULL`, đối soát chính xác số dư kho dùng chung.

### 3.2. M4-REV-002 & M4-REV-007 — Bảo vệ Nonce & Kiểm thử End-to-End luồng xuất CSV
- **Vấn đề:** Cần xác minh toàn bộ luồng request admin (`admin_menu` -> `register_admin_menu()` -> `admin_init` -> `handle_export()` -> Nonce & Capability verification -> `InventoryExportService::export_csv()`), không chỉ dừng ở `wp_verify_nonce()`.
- **Giải pháp:**
  1. Tách logic kiểm tra quyền và nonce thành phương thức tĩnh `InventoryLogsPage::validate_export_request( array $request_params )` trả về `true|\WP_Error` với HTTP status 403.
  2. Bổ sung tham số tùy chọn `$output_stream = null` trong `InventoryExportService::export_csv( $filters, $output_stream )`. Khi truyền stream (như memory stream), service ghi trực tiếp mà không gọi `exit;` hoặc gửi HTTP headers, cho phép kiểm thử tự động nội dung CSV hoàn chỉnh.
  3. Kiểm chứng Case S bao phủ đầy đủ: Hook đăng ký thành công trên `admin_init`, từ chối missing nonce, từ chối invalid nonce, chặn guest không có quyền (`permission_denied`), cấp quyền cho admin có capability, URL chứa đúng nonce, và luồng ghi file CSV có UTF-8 BOM `\xEF\xBB\xBF`, header cột chuẩn và dữ liệu được escape công thức spreadsheet.

### 3.3. M4-REV-003 & M4-REV-008 — Cách ly vòng đời CLI, Action Scheduler & Ranh giới cùng Priority
- **Vấn đề:** Cần kiểm thử Action Scheduler thực tế (không chỉ mô phỏng qua `do_action`), và làm rõ hành vi khi có filter bên ngoài đăng ký cùng priority `PHP_INT_MAX`.
- **Giải pháp & Kiểm chứng thực tế:**
  1. **Action Scheduler Thực tế (Case T):** Sử dụng trực tiếp `as_enqueue_async_action()` và thực thi qua `ActionScheduler::runner()->process_action()`. Kiểm chứng thành công:
     - Khi action hoàn thành bình thường: hook `action_scheduler_after_execute` dọn sạch ngữ cảnh.
     - Khi action ném ngoại lệ (`RuntimeException`): hook `action_scheduler_failed_execution` dọn sạch ngữ cảnh.
     - Sau cả 2 trường hợp, đột biến tồn kho tiếp theo trong cùng tiến trình ghi nhận sạch `manual_adjustment` (`order_id = null`, `order_item_id = null`).
     - Các action kiểm thử được xóa triệt để khỏi database qua `ActionScheduler::store()->delete_action()`, không tồn đọng rác trong hàng đợi.
  2. **Ranh giới cùng priority tại `PHP_INT_MAX` (Case U-2):**
     - Trong WordPress, các callback có cùng priority được lưu trong mảng và thực thi tuần tự theo thứ tự thêm vào (FIFO). Nếu một plugin bên ngoài đăng ký filter tại `PHP_INT_MAX` *sau* khi M4 đã khởi tạo guard, guard của M4 chạy trước và nhận `$can = true`, sau đó filter bên ngoài chạy và trả về `false`.
     - WooCommerce nhận `false` và lập tức hủy trừ tồn kho (kho giữ nguyên 20.0, 0 bản ghi trừ tồn phát sinh).
     - Ngữ cảnh in-memory tạm thời chưa bị guard của filter này xóa ngay, nhưng cơ chế cách ly Case P (kiểm tra hàng đợi rỗng theo thực thể) bảo đảm an toàn: nếu một sản phẩm khác đột biến trong cùng tiến trình, M4 chủ động xóa `order_id` và ghi nhận độc lập thành `manual_adjustment`.
     - Ngữ cảnh còn lại sẽ được dọn sạch hoàn toàn khi luồng chạm tới `shutdown`, `action_scheduler_after_execute`, hoặc khi đơn hàng tiếp theo được khởi tạo.
     - Hành vi này được ghi nhận tường minh trong mục Known Limitations thay vì tuyên bố tuyệt đối sai kiến trúc WordPress.

### 3.4. M4-REV-004 — Tính toàn vẹn Baseline đối soát khi không có log & Phát hiện Discrepancy thực tế
- **Vấn đề:** Khi sản phẩm chưa từng có lịch sử log (`total_events === 0`), đối soát không được báo false positive `is_reconciled = true`. Đồng thời cần kiểm chứng khả năng phát hiện sai lệch thực tế khi số tồn WooCommerce không khớp delta lịch sử.
- **Giải pháp:**
  - Khi `total_events === 0`, nếu tồn kho khác 0, trả về `is_reconciled = false`, `reconciliation_status = 'insufficient_history'`, `discrepancy = null`.
  - Bổ sung kiểm thử Case R4: Tạo sản phẩm có baseline và log trừ tồn (tồn lý thuyết 15.0), cố tình can thiệp số tồn WooCommerce thành 12.0 qua postmeta. `InventoryReconciler` phát hiện chính xác `is_reconciled = false`, `reconciliation_status = 'discrepancy'`, `discrepancy = 3.0` (chênh lệch 3 đơn vị).

### 3.5. M4-REV-006 — Chuẩn hóa Exit Codes cho Test Runners
- **Vấn đề:** Trước đây hai script `test-inventory-matrix.php` và `test-inventory-integration-live.php` chỉ in kết quả ra màn hình mà không gọi `exit(1)` khi có assertion thất bại, khiến CI/CD pipeline có thể hiểu nhầm là pass.
- **Giải pháp:** Cập nhật cả hai runner với cấu trúc:
  - Nếu `0 === $failed` và tổng số case khớp đúng yêu cầu: gọi `exit( 0 )`.
  - Nếu `$failed > 0` hoặc số case không đủ: gọi `exit( 1 )`.
  - Toàn bộ thân hàm `run()` được bọc trong `try ... catch (\Throwable $e)` xuất thông báo lỗi và gọi `exit( 1 )`.
  - Đã kiểm chứng với test giả lập assertion `false`, runner lập tức dừng và trả về Exit Code: 1.

### 3.6. M4-REV-009 — Khắc phục rò rỉ ngữ cảnh & gán nhầm thao tác thủ công khi filter từ chối ở cùng Priority PHP_INT_MAX
- **Vấn đề (Root Cause):**  
  Trong WordPress, các callback ở cùng priority được thực thi theo thứ tự thêm vào (FIFO). Khi một external plugin đăng ký filter từ chối (`return false`) tại `PHP_INT_MAX` sau khi M4 đã nạp guard, guard của M4 chạy trước và thấy `$can = true`. Filter bên ngoài chạy sau và trả về `false`. WooCommerce lập tức hủy thao tác và return mà không gọi các hook hành động (`woocommerce_reduce_order_stock`, `woocommerce_restore_order_stock`, v.v.).  
  Hậu quả: Ngữ cảnh đơn hàng và hàng đợi FIFO (`items_queue`) vẫn tồn đọng trong bộ nhớ. Nếu ngay sau đó xuất hiện thao tác điều chỉnh kho thủ công trên **CÙNG SẢN PHẨM** trong cùng PHP process, `on_set_stock()` sẽ dequeue sản phẩm này từ hàng đợi cũ và gán nhầm thành event của đơn hàng đã bị từ chối (`order_id`, `order_item_id`, `event_type` sai lệch)!
- **Giải pháp thiết kế (Active Caller Verification Guard):**  
  1. Khi các hook WooCommerce (`on_can_reduce_order_stock`, `on_can_restore_order_stock`, `on_can_restock_refunded_items`) khởi tạo ngữ cảnh, M4 ghi nhận `expected_caller`:
     - Giảm tồn đơn hàng: `expected_caller = 'wc_reduce_stock_levels'`
     - Hoàn tồn hủy đơn: `expected_caller = 'wc_increase_stock_levels'`
     - Hoàn tiền restock: `expected_caller = 'wc_restock_refunded_items'`
  2. Tại `InventoryStockObserver::on_set_stock()`, M4 bổ sung phương thức kiểm tra vết thực thi nhẹ `is_in_stock_caller( array $allowed_callers )` dựa trên `debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25)`:
     - Nếu ngữ cảnh in-memory đang có `expected_caller`, M4 đối soát xem call stack hiện tại có chứa hàm WooCommerce tương ứng hay không.
     - Nếu call stack **KHÔNG** chứa hàm tương ứng (chứng tỏ thao tác WooCommerce đã bị từ chối/abort từ trước đó và stock mutation hiện tại là độc lập), M4 lập tức kích hoạt `self::clear_all_context()`, hủy sạch toàn bộ `order_id`, `refund_id`, `order_item_id` và xóa sạch hàng đợi `items_queue`!
     - Thao tác đột biến hiện tại được phân loại chính xác 100% thành `manual_adjustment` (`order_id = null`, `order_item_id = null`, `refund_id = null`).
  3. Bổ sung 3 ca kiểm thử chuyên biệt **Case U-3, U-4, U-5** xác thực triệt để trên cả 3 luồng nghiệp vụ. Case U-5 được xác thực với đối tượng hoàn tiền thực tế `WC_Order_Refund` (`refund_id > 0`). Hoàn toàn bảo vệ hệ thống trước mọi hình thức xung đột hook priority.

### 3.7. M4-REV-010 — Cô lập đột biến tồn kho lồng nhau (Nested Stock Mutation) và Phân loại người gọi trực tiếp
- **Vấn đề (Root Cause):**  
  M4-REV-009 xử lý triệt để tình huống đột biến thủ công diễn ra sau khi thao tác WooCommerce đã bị từ chối/kết thúc (`stale` context). Tuy nhiên, nếu một plugin bên thứ ba móc vào hook hành động (ví dụ `woocommerce_product_set_stock`) trong lúc hàm WooCommerce (`wc_reduce_stock_levels`, `wc_increase_stock_levels`, `wc_restock_refunded_items`) đang chạy cho Sản phẩm A, và plugin đó thực hiện thay đổi số lượng tồn kho của Sản phẩm B:
  1. Trong call stack, hàm WooCommerce gốc (`wc_reduce_stock_levels`, v.v.) vẫn tồn tại ở các khung stack tổ tiên (ancestor frames).
  2. Việc chỉ kiểm tra sự tồn tại boolean đơn thuần của hàm WooCommerce trong backtrace sẽ ngộ nhận đột biến của Sản phẩm B là thuộc về thao tác đơn hàng của Sản phẩm A.
  3. Hậu quả: Đột biến thủ công của Sản phẩm B bị gán nhầm thành sự kiện đơn hàng của Sản phẩm A; hoặc nếu đột biến này kích hoạt xóa context, toàn bộ ngữ cảnh và hàng đợi FIFO của các sản phẩm còn lại trong đơn hàng sẽ bị xóa sổ; hoặc trong luồng hoàn tồn, đột biến của B sẽ "cướp" mất phần tử trong hàng đợi FIFO dành cho B, khiến thao tác hoàn tồn thật sự diễn ra sau đó bị mất ngữ cảnh và bị xếp nhầm thành `manual_adjustment`.
- **Giải pháp thiết kế (Direct/Nested/Stale Caller Classification):**  
  1. Thay thế kiểm tra boolean `is_in_stock_caller()` bằng `classify_stock_mutation_caller(?string $expected_caller): string` phân loại 4 trạng thái:
     - `'direct'`: `wc_update_product_stock` hoặc `$product->save()` được gọi TRỰC TIẾP bởi chính hàm WooCommerce dự kiến (`wc_reduce_stock_levels`, `wc_increase_stock_levels`, `wc_restock_refunded_items`).
     - `'nested'`: Hàm WooCommerce dự kiến có nằm trong call stack (ancestor), nhưng người gọi trực tiếp mutation là một hàm callback/hook trung gian.
     - `'stale'`: Có `expected_caller` nhưng hàm đó hoàn toàn không có trong call stack (thao tác bị từ chối/abort từ trước).
     - `'none'`: Không có ngữ cảnh đơn hàng/hoàn tiền.
  2. Tại `InventoryStockObserver::on_set_stock()`:
     - Khi trạng thái là `'stale'`: Kích hoạt `self::clear_all_context()` dọn sạch bộ nhớ (bảo vệ M4-REV-009).
     - Khi trạng thái là `'nested'`: Chỉ nullify các biến ngữ cảnh cục bộ (`$order_id = null`, `$order_item_id = null`, `$refund_id = null`, v.v.) cho RIÊNG đột biến lồng nhau này để ghi nhận thành `manual_adjustment`. TUYỆT ĐỐI KHÔNG xóa ngữ cảnh in-memory và KHÔNG dequeue hàng đợi FIFO của đơn hàng đang chạy (bảo vệ M4-REV-010).
     - Bổ sung theo dõi `'active_stock_entity_id'` trong `on_filter_order_item_quantity()` và đối soát với `stock_entity_id` hiện tại tại `on_set_stock()`. Nếu không khớp, cô lập thành `manual_adjustment`.
  3. Bổ sung và kiểm chứng có kiểm chứng 3 ca kiểm thử **Case U-6, Case U-7, Case U-8** trên cả 3 luồng nghiệp vụ. Nâng tổng số ca kiểm thử lên 75/75 PASS (100%), Exit Code 0.

---

## 4. THÔNG SỐ MÔI TRƯỜNG STAGING CHUẨN

Kết quả kiểm thử được thực thi và chứng minh trên môi trường container Staging thực tế:

- **Hệ điều hành / Runtime:** Linux x86_64 / PHP 8.3.35 (memory_limit: 512M)
- **WordPress Core:** Phiên bản 7.1.4 (Active)
- **WooCommerce:** Phiên bản 11.1.2 (Active)
- **Cơ sở dữ liệu:** MariaDB 10.11.16 (`bttts5-db`, Database: `wordpress`, Table Prefix: `wp_`)
- **HPOS (Custom Orders Table):** Đang kích hoạt (`custom_orders_table_usage_is_enabled: YES`)
- **Action Scheduler:** Phiên bản tích hợp sẵn trong WooCommerce 11.1.2 (`ActionScheduler_QueueRunner: Active`)
- **Container Staging:** `bttts5-wp` (WordPress Application Server), `bttts5-db` (MariaDB Server)

---

## 5. BẢNG TỔNG HỢP KẾT QUẢ KIỂM THỬ (100% PASS, EXIT CODE: 0)

| STT | Bộ kiểm thử (Test Suite) | Tập tin kiểm thử | Kịch bản / Ca kiểm thử | Exit Code | Kết quả nghiệm thu |
|:---:|---|---|:---:|:---:|:---:|
| 1 | **Đơn vị & Ma trận truy vết (T01–T28)** | `tests/test-inventory-matrix.php` | 28 / 28 | **0** | **28 / 28 PASS (100%)** |
| 2 | **Tích hợp Live WooCommerce & DB (M4-INT)** | `tests/test-inventory-integration-live.php` | 9 / 9 | **0** | **9 / 9 PASS (100%)** |
| 3 | **Ngữ cảnh đơn hàng & Vòng đời tồn kho (Cases A–U)** | `tests/test-m4-order-context-live.php` | 30 / 30 | **0** | **30 / 30 PASS (100%)** |
| 4 | **UAT Đối soát vòng đời đơn hàng BACS (Phase 6D)** | `tests/uat-m4-lifecycle-reconciliation.php` | 8 / 8 bước | **0** | **8 / 8 PASS (100%)** |

**TỔNG CỘNG: 75 / 75 TEST CASES PASSED (100%), TẤT CẢ RUNNER TRẢ EXIT CODE 0**

---

## 6. CHI TIẾT 30 CA KIỂM THỬ NGỮ CẢNH ĐƠN HÀNG (`Cases A–U`)

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
20. **Case R (Baseline kiểm toán, Trạng thái thiếu lịch sử & Discrepancy thực tế - M4-REV-004):**
    - Sản phẩm có tồn kho nhưng 0 bản ghi log được đánh dấu chính xác là `insufficient_history` và `is_reconciled = false`.
    - Sản phẩm có lịch sử đối soát bình thường đạt `reconciled`.
    - Sản phẩm bị lệch tồn kho thực tế (WC = 12, Expected = 15) phát hiện chính xác `reconciliation_status = 'discrepancy'`, `is_reconciled = false`, `discrepancy = 3.0`.
21. **Case S (Admin CSV Export End-to-End Request & Streaming - M4-REV-002, M4-REV-007):**
    - Đăng ký hook `admin_init` qua `admin_menu` được xác nhận thực tế.
    - Từ chối request thiếu nonce (`invalid_nonce`).
    - Từ chối request nonce sai (`invalid_nonce`).
    - Từ chối người dùng không đủ capability (`permission_denied`).
    - Cho phép quản trị viên có quyền quản lý kho (`manage_woocommerce`).
    - Kiểm tra sinh URL có nonce chính xác và bảo toàn query params.
    - Stream trực tiếp vào memory stream: xác nhận UTF-8 BOM `\xEF\xBB\xBF`, cấu trúc header chuẩn, dữ liệu lọc chính xác và escape công thức.
22. **Case T (Real Action Scheduler Lifecycle, Shutdown & CLI Isolation - M4-REV-003, M4-REV-008):**
    - Thực thi action bất đồng bộ thực tế qua Action Scheduler runner: xác nhận `action_scheduler_after_execute` dọn sạch ngữ cảnh.
    - Thực thi action ném exception qua Action Scheduler runner: xác nhận `action_scheduler_failed_execution` dọn sạch ngữ cảnh.
    - Dọn dẹp ngữ cảnh qua hook `shutdown`.
    - Khối `try ... finally` dọn sạch ngữ cảnh trong script batch, đột biến sau đó ghi nhận `manual_adjustment` sạch (`order_id = null`).
    - Xóa sạch action kiểm thử khỏi store database.
23. **Case U-1 (Ranh giới từ chối muộn tại Priority 1000000 & Guard PHP_INT_MAX):** Kiểm tra bộ lọc từ chối tại priority cực cao (1,000,000). Guard tại `PHP_INT_MAX` dọn sạch ngữ cảnh an toàn, không rò rỉ log.
24. **Case U-2 (Ranh giới cùng priority PHP_INT_MAX & Phân tích FIFO Isolation - M4-REV-008):**
    - Đăng ký filter tại `PHP_INT_MAX` trả về `false` sau M4: WooCommerce hủy trừ tồn an toàn (stock không đổi, 0 log trừ tồn).
    - Xác nhận in-memory context tạm thời còn lưu do thứ tự FIFO của WordPress `WP_Hook`.
    - Xác nhận cơ chế cô lập Case P bảo vệ an toàn: sản phẩm khác đột biến trong cùng tiến trình được gán `manual_adjustment` độc lập (`order_id = null`).
    - Dọn sạch ngữ cảnh qua `clear_all_context()`.
25. **Case U-3 (Đột biến thủ công trên CÙNG SẢN PHẨM sau khi bị từ chối trừ tồn tại PHP_INT_MAX - M4-REV-009):**
    - Filter bên ngoài tại `PHP_INT_MAX` từ chối trừ tồn (`return false`). WooCommerce hủy trừ tồn kho.
    - Đột biến kho thủ công tiếp theo trên **CÙNG SẢN PHẨM** kích hoạt `on_set_stock()`.
    - Active Caller Guard phát hiện call stack không phải `wc_reduce_stock_levels`, lập tức kích hoạt `clear_all_context()`, xóa sạch ngữ cảnh và hàng đợi FIFO.
    - Ghi nhận chính xác 100% thành `manual_adjustment`, `order_id = null`, `order_item_id = null`, delta -6.0, stock sau thay đổi = 14.0.
26. **Case U-4 (Đột biến thủ công trên CÙNG SẢN PHẨM sau khi bị từ chối hoàn tồn tại PHP_INT_MAX - M4-REV-009):**
    - Filter bên ngoài tại `PHP_INT_MAX` từ chối hoàn tồn `wc_increase_stock_levels`. WooCommerce hủy hoàn tồn (kho giữ nguyên 15.0).
    - Đột biến kho thủ công tiếp theo trên **CÙNG SẢN PHẨM** kích hoạt `on_set_stock()`.
    - Active Caller Guard phát hiện call stack không phải `wc_increase_stock_levels`, dọn sạch context/queue, ghi nhận chuẩn xác `manual_adjustment`, delta +3.0, `order_id = null`, `order_item_id = null`.
27. **Case U-5 (Đột biến thủ công trên CÙNG SẢN PHẨM sau khi bị từ chối hoàn tiền restock tại PHP_INT_MAX với Real Refund - M4-REV-009):**
    - Tạo đối tượng hoàn tiền thực tế `WC_Order_Refund` (`refund_id > 0`).
    - Filter bên ngoài tại `PHP_INT_MAX` từ chối `wc_restock_refunded_items`. WooCommerce hủy restock (kho giữ nguyên 15.0).
    - Xác nhận `refund_id > 0` thực tế tồn tại trong ngữ cảnh trước khi đột biến.
    - Đột biến kho thủ công tiếp theo trên **CÙNG SẢN PHẨM** kích hoạt `on_set_stock()`.
    - Active Caller Guard phát hiện call stack không phải `wc_restock_refunded_items`, dọn sạch context/queue, ghi nhận chuẩn xác `manual_adjustment`, delta +2.0, `refund_id = null`, `order_id = null`, `order_item_id = null`.
28. **Case U-6 (Đột biến lồng nhau trong khi đang trừ tồn đơn hàng - M4-REV-010):**
    - Đơn hàng 2 sản phẩm A & B. Khi Sản phẩm A đang giảm tồn, hook bên ngoài kích hoạt tăng tồn Sản phẩm B.
    - M4 phân loại người gọi của B là `'nested'`, cô lập thành `manual_adjustment` (`order_id = null`, `order_item_id = null`, delta +5.0), không phá vỡ ngữ cảnh đơn hàng.
    - Sản phẩm A ghi nhận đúng `order_reduction` (item A), và Sản phẩm B sau đó được trừ tồn chính xác `order_reduction` (item B, delta -3.0).
29. **Case U-7 (Đột biến lồng nhau trong khi đang hoàn tồn đơn hàng - M4-REV-010):**
    - Khi Sản phẩm A đang hoàn tồn trong `wc_increase_stock_levels`, hook bên ngoài kích hoạt giảm tồn Sản phẩm B.
    - M4 phân loại người gọi của B là `'nested'`, cô lập thành `manual_adjustment` (`order_id = null`, `order_item_id = null`, delta -2.0).
    - Hàng đợi FIFO của Sản phẩm B không bị chiếm dụng trái phép; Sản phẩm B sau đó hoàn tồn chuẩn xác với `stock_restoration` và đúng `order_item_id` (delta +3.0).
30. **Case U-8 (Đột biến lồng nhau trong khi đang hoàn tiền restock - M4-REV-010):**
    - Đơn hàng có refund thực tế cho cả Sản phẩm A & B. Khi A đang restock từ refund, hook bên ngoài kích hoạt giảm tồn Sản phẩm B.
    - M4 phân loại người gọi của B là `'nested'`, cô lập thành `manual_adjustment` (`order_id = null`, `refund_id = null`, `order_item_id = null`, delta -3.0).
    - Cả 2 sản phẩm A & B sau đó đều hoàn tất restock chính xác với `refund_restock`, kế thừa đúng `order_id`, `refund_id` thực tế và `order_item_id`.

---

## 7. DỮ LIỆU THỰC CHỨNG TỪ MARIADB STAGING

Trích xuất trực tiếp từ bảng `wp_despace_inventory_logs` trên container MariaDB 10.11 (`bttts5-db`) trong phiên chạy kiểm thử mới nhất:

```text
+------+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
| id   | event_key        | event_type        | event_source | product_id | variation_id | order_id | order_item_id | refund_id | delta  | after |
+------+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
| 1686 | 90c32f284bcc8172 | refund_restock    | refund       | 3176       | NULL         | 3177     | 936           | 3178      | +1.0   | 18.0  |
| 1685 | dd5a7adc11396905 | order_reduction   | order        | 3176       | NULL         | 3177     | 936           | NULL      | -3.0   | 17.0  |
| 1683 | d66494559a0f6bad | refund_restock    | refund       | 3173       | NULL         | 3174     | 933           | 3175      | +2.0   | 16.0  |
| 1682 | 6021614fa40fdfae | manual_adjustment | system       | 3173       | NULL         | NULL     | NULL          | NULL      | -3.0   | 14.0  |
| 1681 | d4c4741c1c2af219 | refund_restock    | refund       | 3172       | NULL         | 3174     | 932           | 3175      | +1.0   | 19.0  |
| 1680 | f4c772e7ba418e24 | order_reduction   | order        | 3173       | NULL         | 3174     | 933           | NULL      | -3.0   | 17.0  |
| 1679 | 7ee8da3b430c1ded | order_reduction   | order        | 3172       | NULL         | 3174     | 932           | NULL      | -2.0   | 18.0  |
| 1676 | d3c405af1350be06 | stock_restoration | order        | 3170       | NULL         | 3171     | 931           | NULL      | +3.0   | 18.0  |
| 1675 | 25fca339534a886c | manual_adjustment | system       | 3170       | NULL         | NULL     | NULL          | NULL      | -2.0   | 15.0  |
| 1674 | 6e7c7d8b79706384 | stock_restoration | order        | 3169       | NULL         | 3171     | 930           | NULL      | +2.0   | 20.0  |
| 1673 | b52b369e1d17841e | order_reduction   | order        | 3170       | NULL         | 3171     | 931           | NULL      | -3.0   | 17.0  |
| 1672 | 21d3e520f2c02378 | order_reduction   | order        | 3169       | NULL         | 3171     | 930           | NULL      | -2.0   | 18.0  |
+------+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
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
   Khi phát triển các script đồng bộ kho bên ngoài (WP-CLI command hoặc custom daemon dài hạn) không thông qua Action Scheduler hoặc không kết thúc bằng WordPress `shutdown`, bắt buộc phải bao bọc logic trong khối `try/finally` và gọi `\DeSpace\Inventory\Events\InventoryStockObserver::clear_all_context()` trong khối `finally` để đảm bảo cô lập bộ nhớ hoàn toàn.
5. **Ranh giới thực thi cùng priority tại `PHP_INT_MAX` & Đột biến lồng nhau (Bảo vệ bởi M4-REV-009 & M4-REV-010):**  
   - Khi một plugin bên thứ ba đăng ký filter từ chối tồn kho tại `PHP_INT_MAX` *sau* khi M4 đã nạp guard, guard của M4 chạy trước và nhận `$can = true`.
   - Với cơ chế **Caller Classification Guard** (`classify_stock_mutation_caller()` phân loại `direct`, `nested`, `stale`) và theo dõi `active_stock_entity_id`, hệ thống phân biệt chính xác:
     - Thao tác sau khi hàm WooCommerce đã abort (`stale`): Kích hoạt `clear_all_context()` dọn sạch toàn bộ context/FIFO queue, cô lập thành `manual_adjustment`.
     - Thao tác lồng nhau trong khi hàm WooCommerce vẫn đang chạy (`nested`): Cô lập cục bộ thao tác lồng nhau thành `manual_adjustment` (`order_id = null`, `order_item_id = null`), bảo toàn nguyên vẹn ngữ cảnh và hàng đợi FIFO cho đơn hàng cha.

---

## 9. KẾT LUẬN & ĐỀ NGHỊ HỢP NHẤT (FINAL RELEASE GATE: GO)

Mã nguồn PR #38 đã hoàn thành khắc phục và xác minh có kiểm chứng toàn bộ các phát hiện kỹ thuật (`M4-REV-001`, `M4-REV-002`, `M4-REV-003`, `M4-REV-004`, `M4-REV-006`, `M4-REV-007`, `M4-REV-008`, `M4-REV-009`, `M4-REV-010`), chuẩn hóa exit codes cho 100% test runners, xác minh luồng xuất CSV end-to-end, kiểm thử thực tế Action Scheduler trên Staging, bảo vệ tuyệt đối ranh giới `PHP_INT_MAX` bằng Active Caller Verification Guard, cô lập triệt để đột biến lồng nhau (nested mutations), và kiểm chứng phát hiện sai lệch đối soát tồn kho thực tế.

Tất cả 4 bộ kiểm thử đạt **100% PASS (75/75 test cases)** và đều trả **Exit Code: 0** trên môi trường Staging Docker MariaDB/HPOS.

**Trạng thái nghiệm thu cuối cùng: GO (SẴN SÀNG MERGE VÀO DEVELOP).**
