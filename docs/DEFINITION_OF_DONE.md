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

## Tiêu chí nghiệm thu bổ sung cho Issues #29–#37

11. GLB được tối ưu ở mức phù hợp; có kiểm thử model-viewer trên thiết bị/trình duyệt mục tiêu, trạng thái loading/error và fallback ảnh 2D.
12. Trang chi tiết lấy dữ liệu từ WooCommerce, hiển thị đúng giá, SKU, ảnh, mô tả, thông số, biến thể và tồn kho; đổi biến thể phải cập nhật đúng dữ liệu.
13. Các trang Giới thiệu, Liên hệ, FAQ và chính sách dùng nội dung đã được nhóm xác nhận; không còn placeholder/link rỗng. SEO cơ bản có title, meta description, heading, ALT ảnh và sitemap theo cấu hình đã chọn.
14. Khu vực giao hàng, phương thức và phí vận chuyển được tính đúng tại WooCommerce cart/checkout; vùng không hỗ trợ được thông báo.
15. Đăng ký, đăng nhập, đăng xuất, quên mật khẩu, tài khoản và lịch sử đơn hoạt động; Customer/Guest không thể xem đơn hoặc dữ liệu của người khác.
16. Email giao dịch phản ánh đúng mã đơn và trạng thái thanh toán; đơn chuyển khoản chưa đối soát không được thông báo đã thanh toán. Có bằng chứng gửi email test hoặc hướng dẫn cấu hình SMTP và giới hạn môi trường.
17. Nhật ký tồn kho được kiểm thử với sản phẩm biến thể, chỉnh tồn thủ công, đơn nhiều dòng, hủy đơn và hoàn tiền một phần/toàn phần; không tự ý cộng/trừ tồn ngoài WooCommerce.
18. Có ma trận quyền Admin/Staff/Customer; REST endpoints, upload, CSRF, validate/sanitize/escape, SQL tùy chỉnh và dữ liệu nhạy cảm đã được rà soát. Không còn blocker bảo mật nghiêm trọng/cao chưa xử lý trước demo.
19. Staging được cấu hình HTTPS; secrets tách khỏi Git; có backup database + uploads, hướng dẫn rollback và bằng chứng restore thử nghiệm.
20. Có ma trận kiểm thử đa trình duyệt/thiết bị, kiểm tra lỗi console/request, hiệu năng cơ bản, khả năng truy cập và UAT; lỗi blocker của luồng duyệt sản phẩm/đặt hàng đã được khắc phục hoặc ghi rõ giới hạn được chấp nhận.
