# DeSpace — Phân công module và trách nhiệm thành viên

## 1. Phân công 5 thành viên

### M1 — Phòng trưng bày sản phẩm 3D/AR
**Phụ trách:** Thành viên 01 — GitHub: [@hoangHT-w](https://github.com/hoangHT-w)

Công việc:
- Quản lý metadata file GLB, ảnh poster và cờ bật/tắt AR của sản phẩm.
- Kiểm tra file GLB và xử lý an toàn khi upload.
- Tích hợp model-viewer vào trang chi tiết sản phẩm.
- Xây dựng thao tác xoay/thu phóng, trạng thái đang tải, lỗi và phương án dự phòng.
- Kiểm tra khả năng AR và thử nghiệm trên thiết bị phù hợp.
- Tối ưu GLB, texture và tải model; dùng lazy loading/poster khi phù hợp.
- Kiểm thử thực tế trên thiết bị Android và iOS/iPadOS mục tiêu, ghi lại trình duyệt và kết quả.

Tiêu chí nghiệm thu:
- GLB hợp lệ được hiển thị và có thể tương tác.
- Chỉ cung cấp thao tác AR khi thiết bị hỗ trợ.
- AR lỗi hoặc không được hỗ trợ không làm hỏng việc xem ảnh 2D/3D.
- Metadata lưu attachment ID, không ghi cứng URL media.
- Có bằng chứng kiểm thử tương thích, trạng thái loading/error và fallback ảnh 2D.
- Issue bổ sung: [#29 — Tối ưu GLB và kiểm thử tương thích 3D/AR](https://github.com/LeAnhDung05/DesSpace/issues/29).

### M2 — Giao diện Premium, danh mục và tìm kiếm sản phẩm
**Phụ trách:** Thành viên 02 — GitHub: [@Anvu-IFBB](https://github.com/Anvu-IFBB)

Công việc:
- Xây dựng `despace-child`, header/footer và các thành phần giao diện dùng chung.
- Xây dựng trang chủ, danh mục, thẻ sản phẩm, trang chi tiết sản phẩm và giao diện responsive.
- Trang chi tiết hiển thị mô tả, thông số, ảnh gallery, SKU, giá, biến thể, tồn kho và sản phẩm liên quan theo dữ liệu WooCommerce.
- Xây dựng các trang Giới thiệu, Liên hệ, FAQ, Chính sách giao hàng, đổi trả/bảo hành và bảo mật; dùng nội dung đã được nhóm duyệt.
- Hoàn thiện SEO cơ bản: title, meta description, heading, URL thân thiện, ALT ảnh và sitemap theo cấu hình đã chọn.
- Tìm kiếm theo tên sản phẩm/SKU.
- Lọc nhiều tiêu chí bằng Fetch API/AJAX.
- Xử lý trạng thái không có kết quả, phân trang, xóa bộ lọc và giữ trạng thái trên URL khi phù hợp.

Tiêu chí nghiệm thu:
- Dữ liệu danh mục và giá lấy từ WooCommerce.
- Kết hợp nhiều bộ lọc cho kết quả đúng.
- Giao diện desktop/tablet/mobile hoạt động.
- Giao diện thống nhất với file Figma và Design Tokens DeSpace Premium.
- Trang chi tiết cập nhật đúng giá/ảnh/SKU/tồn khi đổi biến thể; thêm giỏ hàng hoạt động với sản phẩm đơn giản và biến thể.
- Các trang thông tin không còn placeholder, link rỗng hoặc thông tin liên hệ/chính sách chưa được xác nhận.
- Issues bổ sung: [#30 — Trang chi tiết sản phẩm](https://github.com/LeAnhDung05/DesSpace/issues/30), [#31 — Trang nội dung, chính sách và SEO](https://github.com/LeAnhDung05/DesSpace/issues/31).

### M3 — Giỏ hàng, thanh toán và quản lý đơn hàng
**Phụ trách:** Thành viên 03 — GitHub: [@LeAnhDung05](https://github.com/LeAnhDung05)

Công việc:
- Thay đổi số lượng, xóa sản phẩm và kiểm tra giỏ hàng.
- Tích hợp mã giảm giá WooCommerce.
- Checkout cho Customer và Guest nếu được bật.
- Hướng dẫn COD và chuyển khoản ngân hàng thủ công/mã QR khi có cấu hình.
- Xây dựng trang xác nhận và tra cứu đơn hàng.
- Cấu hình Shipping Zones, phương thức vận chuyển và phí ship theo phạm vi demo; hiển thị và tính phí tại giỏ hàng/checkout.
- Hoàn thiện đăng ký, đăng nhập, đăng xuất, quên mật khẩu, thông tin tài khoản và lịch sử đơn.
- Cấu hình email giao dịch WooCommerce và kiểm thử SMTP nếu môi trường có thông tin cấu hình.

Tiêu chí nghiệm thu:
- WooCommerce xác thực giá, coupon và tồn kho.
- Chuyển khoản chưa đối soát không được đánh dấu đã thanh toán.
- Trạng thái đơn hàng và trạng thái thanh toán hiển thị chính xác.
- Customer chỉ xem đơn của mình; Guest phải có cơ chế xác minh an toàn.
- Phí giao hàng/tổng đơn khớp WooCommerce; vùng không hỗ trợ được thông báo rõ.
- Email không được đánh dấu đơn chuyển khoản là đã thanh toán khi chưa đối soát.
- Issues bổ sung: [#32 — Vận chuyển và phí ship](https://github.com/LeAnhDung05/DesSpace/issues/32), [#33 — Tài khoản khách hàng và email giao dịch](https://github.com/LeAnhDung05/DesSpace/issues/33).

### M4 — Nhật ký và truy vết biến động tồn kho
**Phụ trách:** Thành viên 04 — GitHub: [@Mod3r3d](https://github.com/Mod3r3d)

Công việc:
- Thiết kế schema và migration dbDelta cho `despace_inventory_logs`.
- Dùng event_key và cơ chế ghi log chống trùng lặp.
- Ghi nhận sự kiện nhập/bán/hoàn/điều chỉnh kho.
- Xây dựng màn hình lịch sử biến động và bộ lọc.
- Kiểm thử hủy đơn, hoàn kho và hook bị gọi lặp.
- Kiểm thử thêm sản phẩm biến thể, chỉnh tồn thủ công, đơn nhiều dòng, hoàn tiền một phần/toàn phần và các tình huống biên.

Tiêu chí nghiệm thu:
- WooCommerce là nguồn dữ liệu tồn kho duy nhất.
- Một sự kiện thực tế chỉ tạo tối đa một bản ghi log.
- Hủy đơn chưa từng làm giảm tồn kho thì không được cộng tồn.
- Log ghi nguồn sự kiện, sản phẩm/biến thể, đơn hàng, số lượng thay đổi, tồn sau sự kiện và thời điểm.
- Có test case Pass/Fail và đối chiếu tồn thực tế WooCommerce với nhật ký; không tự giả định hoàn tiền luôn đồng nghĩa hoàn kho.
- Issue bổ sung: [#34 — Đối soát tồn kho cho biến thể và tình huống biên](https://github.com/LeAnhDung05/DesSpace/issues/34).

### M5 — Dashboard, báo cáo và tích hợp
**Phụ trách:** Thành viên 05 — GitHub: [@HaDiLuc](https://github.com/HaDiLuc) — Điều phối tích hợp/release.

Công việc:
- Khởi tạo Core Plugin và ranh giới tích hợp module.
- Quản lý API Contract, kiến trúc chung và phối hợp tích hợp.
- Xây dựng KPI Dashboard quản trị.
- Báo cáo doanh thu, trạng thái đơn và sản phẩm bán chạy.
- Kiểm tra capabilities cho Staff/Admin và điều phối release.
- Lập ma trận quyền Admin/Staff/Customer; rà soát REST API, upload file, CSRF, validate/sanitize/escape, SQL và dữ liệu nhạy cảm.
- Điều phối staging/production, HTTPS, secrets, sao lưu database + uploads, thử khôi phục và hướng dẫn rollback.
- Điều phối kiểm thử đa trình duyệt, hiệu năng, khả năng truy cập, regression và UAT; từng module owner trực tiếp sửa lỗi module mình.

Tiêu chí nghiệm thu:
- Dashboard sử dụng dữ liệu WooCommerce thực và bộ lọc thời gian rõ ràng.
- M1–M4 tích hợp không trùng lặp nghiệp vụ.
- REST endpoints kiểm tra quyền và validate dữ liệu đầu vào.
- Pull Request được review và kiểm thử smoke test trên staging đạt.
- Có ma trận quyền/endpoint/upload, không còn blocker bảo mật nghiêm trọng/cao chưa xử lý trước demo.
- Có staging HTTPS, hướng dẫn triển khai/rollback và đã thử khôi phục backup.
- Có ma trận test đa trình duyệt/thiết bị, UAT và known limitations.
- Issues bổ sung: [#35 — Bảo mật và phân quyền](https://github.com/LeAnhDung05/DesSpace/issues/35), [#36 — Staging/production và sao lưu](https://github.com/LeAnhDung05/DesSpace/issues/36), [#37 — Kiểm thử phi chức năng và UAT](https://github.com/LeAnhDung05/DesSpace/issues/37).

## 2. Trách nhiệm chung của mỗi thành viên

Mỗi người phụ trách module phải bàn giao:
1. Liên kết màn hình Figma hoặc mô tả vị trí giao diện đã triển khai.
2. Source PHP/JavaScript trong đúng thư mục module.
3. Kiểm tra dữ liệu đầu vào, quyền truy cập và xử lý lỗi.
4. Unit test/functional test cho logic mình phụ trách.
5. Ít nhất một kiểm thử tích hợp với module liên quan.
6. Tài liệu cấu hình và các giới hạn đã biết.
7. Pull Request có ảnh chụp hoặc bằng chứng kiểm thử.

## 3. Phụ thuộc và phối hợp giữa các module

- M2 cung cấp thẻ sản phẩm, bố cục trang chi tiết và ngữ cảnh danh mục; M1 tích hợp model-viewer vào trang chi tiết.
- M1 thống nhất/công bố các metadata 3D của sản phẩm; M2 sử dụng metadata để hiển thị nhãn 3D và hỗ trợ bộ lọc.
- M3 sử dụng sản phẩm, biến thể, coupon và tồn kho của WooCommerce; không tạo hệ thống giỏ hàng/đơn hàng riêng.
- M4 lắng nghe các sự kiện tồn kho của WooCommerce; không tự điều khiển số lượng tồn độc lập.
- M5 phụ trách bootstrap dùng chung, API Contract và tích hợp cuối; mỗi thành viên vẫn chịu trách nhiệm code và test của module mình.

## 4. Quyền truy cập repository và gán Issue

Các username đã được ghép theo thứ tự danh sách nhóm gửi:
- M1: `hoangHT-w`
- M2: `Anvu-IFBB`
- M3: `LeAnhDung05`
- M4: `Mod3r3d`
- M5: `HaDiLuc`

**Lưu ý:** Tài khoản chủ repository `LeAnhDung05` có quyền Admin. Hai tài khoản `Mod3r3d` và `HaDiLuc` hiện vẫn chỉ có quyền Read; cần nâng lên Write và xác nhận lời mời đã được chấp nhận. Chủ repository cần vào **Settings → Collaborators → Add people**, mời từng tài khoản và cấp quyền **Write**. Sau khi các bạn chấp nhận lời mời, quản lý mới có thể gán Assignee và thành viên mới có thể push branch/tạo Pull Request theo quy trình nhóm.