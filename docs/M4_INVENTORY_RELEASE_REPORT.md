# DeSpace M4 — Báo Cáo Nghiệm Thu & Release Gate Tồn Kho (PR #38)

**Tài liệu:** DESPACE-DOC-M4-01  
**Mô-đun:** M4 — Quản lý & Truy vết biến động tồn kho (Inventory Traceability)  
**Pull Request:** [#38](https://github.com/LeAnhDung05/DesSpace/pull/38)  
**Nhánh nguồn:** `feature/member04-inventory`  
**Nhánh đích:** `develop`  
**Commit triển khai (HEAD):** [`e3d0f6dc549e5fea13430196e19edc9cebf57af9`](https://github.com/LeAnhDung05/DesSpace/commit/e3d0f6dc549e5fea13430196e19edc9cebf57af9)  
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

## 2. LỊCH SỬ HARDENING & NGUYÊN NHÂN CỐT LÕI ĐÃ XỬ LÝ

| Commit SHA | Nội dung xử lý kỹ thuật |
|---|---|
| [`bd8a595`](https://github.com/LeAnhDung05/DesSpace/commit/bd8a59562b3c351d3ef76c8d47b746451edb1f7d) | **Thiết lập ngữ cảnh trước đột biến tồn kho:** Đăng ký ngữ cảnh đơn hàng/hoàn tiền trước khi WooCommerce thực hiện trừ tồn hoặc hoàn tồn, bảo đảm các hook `woocommerce_product_set_stock` luôn nhận đúng `order_id` thay vì phân loại nhầm thành `manual_adjustment`. |
| [`5fc996d`](https://github.com/LeAnhDung05/DesSpace/commit/5fc996d76ea39fb9ebf74ebbc8d3f61bbe3e00d4) | **Giải quyết trùng lặp sản phẩm bằng FIFO Queue:** Thay thế cấu trúc ánh xạ phẳng bằng hàng đợi FIFO (`items_queue`) theo từng thực thể quản lý tồn (`stock_entity_id`). Thêm bộ lọc dọn dẹp ngữ cảnh tại priority `999999` khi đơn hàng bị từ chối trừ/hoàn tồn. |
| [`e3d0f6d`](https://github.com/LeAnhDung05/DesSpace/commit/e3d0f6dc549e5fea13430196e19edc9cebf57af9) | **Xóa bỏ fallback `items_map` cũ & cô lập đột biến không khớp:** Loại bỏ hoàn toàn fallback tra cứu mảng tĩnh có nguy cơ ghi đè line-item ID. Khi hàng đợi rỗng hoặc sản phẩm đột biến không thuộc đơn hàng, M4 chủ động đặt lại `order_id = null`, `order_item_id = null`, ghi nhận cô lập thành `manual_adjustment` mà không gây nhiễm bẩn ngữ cảnh đơn hàng. Bổ sung ma trận kiểm thử biến thể (Case N), bộ lọc từ chối muộn (Case O1-O3) và kiểm thử an toàn FIFO (Case P). |

---

## 3. THÔNG SỐ MÔI TRƯỜNG STAGING CHUẨN

Kết quả kiểm thử được thực thi và chứng minh trên môi trường container Staging thực tế:

- **Hệ điều hành / Runtime:** Linux x86_64 / PHP 8.3.35 (memory_limit: 512M)
- **WordPress Core:** Phiên bản 7.1.4 (Active)
- **WooCommerce:** Phiên bản 11.1.2 (Active)
- **Cơ sở dữ liệu:** MariaDB 10.11.16 (`bttts5-db`, Database: `wordpress`, Table Prefix: `wp_`)
- **HPOS (Custom Orders Table):** Đang kích hoạt (`custom_orders_table_usage_is_enabled: YES`)
- **Container Staging:** `bttts5-wp` (WordPress Application Server), `bttts5-db` (MariaDB Server)

---

## 4. BẢNG TỔNG HỢP KẾT QUẢ KIỂM THỬ (100% PASS)

| STT | Bộ kiểm thử (Test Suite) | Tập tin kiểm thử | Kịch bản / Ca kiểm thử | Kết quả nghiệm thu |
|:---:|---|---|:---:|:---:|
| 1 | **Đơn vị & Ma trận truy vết (T01–T28)** | `tests/test-inventory-matrix.php` | 28 / 28 | **28 / 28 PASS (100%)** |
| 2 | **Tích hợp Live WooCommerce & DB (M4-INT)** | `tests/test-inventory-integration-live.php` | 9 / 9 | **9 / 9 PASS (100%)** |
| 3 | **Ngữ cảnh đơn hàng & Vòng đời tồn kho (Cases A–P)** | `tests/test-m4-order-context-live.php` | 18 / 18 | **18 / 18 PASS (100%)** |
| 4 | **UAT Đối soát vòng đời đơn hàng BACS (Phase 6D)** | `tests/uat-m4-lifecycle-reconciliation.php` | 8 / 8 bước | **8 / 8 PASS (100%)** |
| 5 | **Bộ lọc dọn dẹp ngữ cảnh từ chối muộn (Case O)** | `tests/test-m4-order-context-live.php` | 3 / 3 | **3 / 3 PASS (100%)** |
| 6 | **Vòng đời biến thể (Tự quản lý & Cha quản lý)** | `tests/test-m4-order-context-live.php` | Toàn bộ vòng đời | **PASS (100%)** |
| 7 | **Cách ly hàng đợi FIFO & Đột biến không khớp (Case P)** | `tests/test-m4-order-context-live.php` | 1 / 1 | **PASS (100%)** |

---

## 5. CHI TIẾT CÁC CA KIỂM THỬ TRỌNG TÂM

### A. Ma trận 18 ca kiểm thử ngữ cảnh đơn hàng (`Cases A–P`)

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
11. **Case K (Trùng lặp sản phẩm nhiều dòng khi hoàn tồn):** Xử lý đơn hàng có 2 dòng chứa cùng 1 sản phẩm (SL 4 và SL 6). Khi hoàn tồn, hàng đợi FIFO phân bổ chính xác 2 bản ghi riêng biệt với delta +4 và +6.
12. **Case L (Trùng lặp sản phẩm nhiều dòng khi hoàn tiền):** Hoàn tiền restock cho 2 dòng sản phẩm giống nhau; sinh đúng 2 bản ghi với 2 `order_item_id` tương ứng.
13. **Case M (Nhiều đợt hoàn tiền một phần liên tiếp):** Phân bổ chính xác từng ID đơn hoàn tiền riêng biệt (`#2326` và `#2327`).
14. **Case N (Vòng đời toàn diện của biến thể):**
    - *Phần 1 (Biến thể tự quản lý tồn):* Ghi nhận đầy đủ `product_id` (cha), `variation_id` (con), delta chính xác qua 3 giai đoạn: Trừ tồn (-4.0) → Hoàn tiền restock (+2.0) → Khôi phục hủy đơn (+2.0). Tồn kho kết thúc về 15.0.
    - *Phần 2 (Biến thể do sản phẩm cha quản lý tồn):* Theo chuẩn WooCommerce và test T08, tồn kho quản lý tại cha nên `product_id` ghi nhận ID cha, `variation_id` để `NULL`, trong khi `order_item_id` vẫn chỉ định chính xác dòng đặt hàng của biến thể.
15. **Case O-1 (Từ chối trừ tồn muộn tại Priority 20):** Filter trả về `false` sau khi M4 đã khởi tạo ngữ cảnh; guard priority 999999 dọn sạch bộ nhớ; không sinh log trừ tồn; thao tác điều chỉnh thủ công tiếp theo được ghi nhận độc lập thành `manual_adjustment`.
16. **Case O-2 (Từ chối hoàn tồn muộn tại Priority 20):** Filter trả về `false` trong luồng hoàn tồn; guard dọn sạch ngữ cảnh; không sinh log `stock_restoration`; thao tác thủ công sau đó ghi nhận `manual_adjustment` chuẩn xác.
17. **Case O-3 (Từ chối hoàn tiền restock muộn tại Priority 20):** Filter trả về `false` trong luồng hoàn tiền; guard dọn sạch ngữ cảnh; không sinh log `refund_restock`; thao tác thủ công sau đó ghi nhận `manual_adjustment` độc lập.
18. **Case P (An toàn hàng đợi FIFO & Cô lập thực thể không khớp):** Khi ngữ cảnh hoàn tồn của Đơn hàng #2345 đang mở, một sản phẩm không thuộc đơn hàng (#2344) đột ngột tăng tồn. M4 nhận diện hàng đợi của #2344 rỗng và lập tức cô lập bản ghi thành `manual_adjustment` (`order_id = NULL`, `order_item_id = NULL`). Ngay sau đó, sản phẩm hợp lệ của đơn hàng (#2343) hoàn tồn thành công với đầy đủ `order_id` và `order_item_id`.

---

## 6. DỮ LIỆU THỰC CHỨNG TỪ MARIADB STAGING

Trích xuất trực tiếp từ bảng `wp_despace_inventory_logs` trên container MariaDB 10.11 (`bttts5-db`):

```text
+-----+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
| id  | event_key        | event_type        | event_source | product_id | variation_id | order_id | order_item_id | refund_id | delta  | after |
+-----+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
| 620 | bf59365c296d5334 | refund_restock    | refund       | 2346       | NULL         | 2347     | 456           | 2348      | +1.0   | 18.0  |
| 619 | ac8fe0143612951a | order_reduction   | order        | 2346       | NULL         | 2347     | 456           | NULL      | -3.0   | 17.0  |
| 617 | 4f67c6701c74f6a7 | stock_restoration | order        | 2343       | NULL         | 2345     | 455           | NULL      | +3.0   | 20.0  |
| 616 | 981bd7f703c726de | manual_adjustment | system       | 2344       | NULL         | NULL     | NULL          | NULL      | +5.0   | 15.0  |
| 615 | 10f3214fe4955943 | order_reduction   | order        | 2343       | NULL         | 2345     | 455           | NULL      | -3.0   | 17.0  |
| 612 | b6c01066931d8f9e | manual_adjustment | system       | 2340       | NULL         | NULL     | NULL          | NULL      | +2.0   | 9.0   |
| 611 | 5f4e0b96249c1b42 | order_reduction   | order        | 2340       | NULL         | 2341     | 453           | NULL      | -3.0   | 7.0   |
| 609 | 88cee59a91fedf05 | manual_adjustment | system       | 2338       | NULL         | NULL     | NULL          | NULL      | +5.0   | 12.0  |
| 608 | 3572bbb30f7ae8ea | order_reduction   | order        | 2338       | NULL         | 2339     | 452           | NULL      | -3.0   | 7.0   |
| 606 | 2a0632b1be711a87 | manual_adjustment | system       | 2336       | NULL         | NULL     | NULL          | NULL      | +4.0   | 14.0  |
| 604 | 24f1589bddea0ff5 | stock_restoration | order        | 2332       | NULL         | 2334     | 449           | NULL      | +2.0   | 20.0  |
| 603 | e420124483bf9ca3 | refund_restock    | refund       | 2332       | NULL         | 2334     | 449           | 2335      | +1.0   | 18.0  |
| 602 | f77e2e7a7a62130b | order_reduction   | order        | 2332       | NULL         | 2334     | 449           | NULL      | -3.0   | 17.0  |
| 600 | ac9515d07e4fbdc7 | stock_restoration | order        | 2328       | 2329         | 2330     | 447           | NULL      | +2.0   | 15.0  |
| 599 | 670ed111a24acc13 | refund_restock    | refund       | 2328       | 2329         | 2330     | 447           | 2331      | +2.0   | 13.0  |
| 598 | 8221888fcec242a5 | order_reduction   | order        | 2328       | 2329         | 2330     | 447           | NULL      | -4.0   | 11.0  |
+-----+------------------+-------------------+--------------+------------+--------------+----------+---------------+-----------+--------+-------+
```

---

## 7. GIỚI HẠN VÀ KHUYẾN NGHỊ VẬN HÀNH (KNOWN LIMITATIONS)

1. **Thao tác can thiệp trực tiếp qua SQL thô:** Nếu người quản trị thực hiện cập nhật trường `_stock` hoặc bảng custom của WooCommerce trực tiếp bằng lệnh SQL từ bên ngoài mà không qua WooCommerce API (`$product->save()`, `wc_update_product_stock()`), WordPress hooks sẽ không được kích hoạt. Đây là đặc tính mặc định của WooCommerce.
2. **Khởi chạy mô-đun:** M4 tuân thủ nghiêm ngặt ranh giới mô-đun và cung cấp phương thức tĩnh:
   ```php
   \DeSpace\Inventory\InventoryModule::init();
   ```
   M5 chịu trách nhiệm gọi phương thức này trong file bootstrap chính của plugin (`despace-core.php`).
3. **Quyền hạn truy cập Admin & REST API:**  
   Chỉ người dùng có quyền `manage_woocommerce` hoặc custom capability `view_despace_inventory_logs` mới được phép xem nhật ký và tải xuất CSV. Người dùng thông thường (customer/subscriber) bị chặn tuyệt đối (HTTP 403 Forbidden).

---

## 8. KẾT LUẬN & ĐỀ NGHỊ HỢP NHẤT (MERGE RECOMMENDATION)

Mã nguồn PR #38 đáp ứng đầy đủ tiêu chí chấp thuận (Definition of Done), bảo toàn 100% tính toàn vẹn dữ liệu tồn kho, không gây hồi quy và đã được nghiệm thu kỹ thuật đạt **GO**.

**Đề xuất:** Phê duyệt hợp nhất PR #38 vào nhánh `develop`.
