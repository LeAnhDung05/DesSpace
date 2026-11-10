# DeSpace — Tiêu chí xác nhận hoàn thành công việc (Definition of Done)

Một Issue chỉ được đánh dấu Done khi các kiểm tra phù hợp bên dưới đều đạt:

- [ ] Phạm vi code đúng với Issue; không sửa lan sang module không liên quan.
- [ ] Code tuân thủ bộ công nghệ đã chốt và đúng thư mục phụ trách.
- [ ] Có kiểm tra quyền, validate, sanitize và escape dữ liệu đầu ra.
- [ ] Không commit secret, database local hoặc file upload chưa được phép.
- [ ] Có bằng chứng unit test/functional test.
- [ ] Đã kiểm tra tích hợp với module phụ thuộc.
- [ ] Giao diện khớp màn hình Figma đã duyệt và đã kiểm tra responsive.
- [ ] Pull Request liên kết Issue và có ít nhất một người review.
- [ ] Không còn nhận xét review chưa xử lý hoặc blocker đã biết.
- [ ] Kiểm thử smoke test trên staging đạt.
- [ ] Tài liệu và giới hạn đã biết được cập nhật.

## Tiêu chí nghiệm thu MVP từ đầu đến cuối

1. Admin tạo/sửa sản phẩm WooCommerce với SKU, giá, danh mục, biến thể, tồn kho và ảnh.
2. Sản phẩm có GLB hợp lệ hiển thị model-viewer trên trang chi tiết; AR chỉ xuất hiện trên thiết bị hỗ trợ.
3. Khách hàng tìm kiếm và kết hợp nhiều bộ lọc danh mục; kết quả và phân trang đúng.
4. Khách hàng thêm sản phẩm vào giỏ, áp dụng coupon hợp lệ và checkout bằng COD hoặc chuyển khoản thủ công.
5. WooCommerce tạo đơn hàng và tiếp tục chịu trách nhiệm quản lý biến động tồn kho.
6. Nhật ký tồn kho DeSpace ghi nhận mỗi sự kiện một lần, có tham chiếu đơn hàng/sản phẩm.
7. Quyền truy cập Staff/Admin được kiểm soát bằng capabilities.
8. Dashboard lấy số liệu từ WooCommerce theo khoảng thời gian đã chọn.
9. Kiểm thử smoke test desktop, tablet và mobile đạt.
10. Tài liệu sao lưu, hướng dẫn cài đặt, bằng chứng kiểm thử và giới hạn đã biết được bàn giao.