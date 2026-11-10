# Hướng dẫn sử dụng GitHub Projects — Thực Tập Chuyên Ngành

## 1. Thông tin Project và Repository

- **Tên Project:** Thực Tập Chuyên Ngành
- **Repository:** `LeAnhDung05/DesSpace`
- **Địa chỉ repository:** https://github.com/LeAnhDung05/DesSpace
- Cả 5 module đều thuộc cùng một website và một repository, không tách thành 5 website riêng.

## 2. Thêm 37 Issues vào Project

Các Issues đã được tạo trong repository, nhưng cần thêm chúng vào bảng GitHub Projects v2 để theo dõi:

1. Mở Project **Thực Tập Chuyên Ngành**.
2. Trong chế độ Backlog/Table, chọn **Add item** (Thêm mục).
3. Tìm `repo:LeAnhDung05/DesSpace` hoặc dán URL của Issue.
4. Thêm các Issue **#1–#37** vào cùng Project. Các Issue mới #29–#37 là phần mở rộng, không thay thế các Issue gốc.
5. Dùng trường Module để nhóm Issue tổng và Issue công việc theo M1–M5.

Lưu ý: Issue trong repository và item trên Project là hai đối tượng riêng. Tạo Issue trong repository không có nghĩa là Issue tự động xuất hiện trên Project.

## 3. Phân công thành viên theo GitHub username

| Module | Thành viên | GitHub username | Module phụ trách |
|---|---|---|---|
| M1 | Thành viên 01 | [LeAnhDung05](https://github.com/LeAnhDung05) | Phòng trưng bày 3D/AR |
| M2 | Thành viên 02 | [hoangHT-w](https://github.com/hoangHT-w) | Giao diện Premium, danh mục, tìm kiếm |
| M3 | Thành viên 03 | [HaDiLuc](https://github.com/HaDiLuc) | Giỏ hàng, thanh toán, đơn hàng |
| M4 | Thành viên 04 | [Mod3r3d](https://github.com/Mod3r3d) | Nhật ký và truy vết tồn kho |
| M5 | Thành viên 05 | [Anvu-IFBB](https://github.com/Anvu-IFBB) | Dashboard, báo cáo, tích hợp/release |

## 4. Các trường nên có trên bảng Project

| Tên trường | Loại | Giá trị / mục đích |
|---|---|---|
| Module | Single select | M1, M2, M3, M4, M5 |
| Ưu tiên (Priority) | Single select | P0, P1, P2, P3 |
| Đợt thực hiện (Sprint) | Iteration | Sprint 4, Sprint 5, Sprint 6 |
| Ước lượng (Estimate) | Number | 1, 2, 3, 5, 8 điểm |
| Trạng thái (Status) | Status có sẵn | Backlog, Ready, In Progress, In Review, Testing, Done, Blocked |

## 5. Ý nghĩa các trạng thái

| Trạng thái GitHub | Ý nghĩa tiếng Việt | Khi nào dùng |
|---|---|---|
| Backlog | Chờ thực hiện | Việc đã ghi nhận nhưng chưa sẵn sàng bắt đầu |
| Ready | Sẵn sàng | Đã rõ phạm vi, phụ thuộc và tiêu chí nghiệm thu |
| In Progress | Đang thực hiện | Thành viên đang lập trình/thiết kế/kiểm thử |
| In Review | Chờ review | Đã mở Pull Request và đang chờ người review |
| Testing | Đang kiểm thử | Đã tích hợp hoặc đưa lên staging để xác minh |
| Done | Hoàn thành | Đã đạt tiêu chí nghiệm thu và hoàn tất |
| Blocked | Bị chặn | Đang chờ quyền truy cập, phụ thuộc, quyết định hoặc đầu vào khác |

Có thể đổi tên các tùy chọn Status sang tiếng Việt trong cấu hình trường của Project nếu GitHub cho phép chỉnh sửa trường đó. Nếu giữ nhãn tiếng Anh, dùng bảng giải thích này làm quy ước chung.

## 6. Các chế độ xem (Views) nên tạo

- **Backlog:** dạng bảng; nhóm theo Module, sắp xếp theo Priority.
- **Board:** dạng Kanban; nhóm theo Status để nhìn việc đang làm/chờ review/đã hoàn thành.
- **Current iteration:** chỉ hiển thị các việc thuộc Sprint hiện tại.
- **Roadmap:** dạng timeline/roadmap để nhìn mốc thực hiện theo Sprint.
- **My items:** mỗi thành viên lọc các nhiệm vụ được giao cho mình.

## 7. Bản đồ Issue theo module

| Module | Issue tổng | Các Issue công việc |
|---|---:|---|
| M1 — 3D/AR | #1 | #6–#9, #29 |
| M2 — Catalog & UI | #2 | #10–#14, #30–#31 |
| M3 — Checkout & Đơn hàng | #3 | #15–#19, #32–#33 |
| M4 — Tồn kho | #4 | #20–#23, #34 |
| M5 — Dashboard & Tích hợp | #5 | #24–#28, #35–#37 |

Các Issue tổng có liên kết đến Issue công việc. Dùng trường Module để nhóm. Trường Sub-issues progress chỉ tự phản ánh quan hệ con thực tế nếu đã tạo quan hệ Sub-issue trong giao diện GitHub; chỉ ghi số Issue trong nội dung chưa tự tạo quan hệ đó.

## 8. Kế hoạch Sprint đề xuất

Thời gian dự án: **19/10/2026 – 29/11/2026**. Bảng dưới đây tập trung vào phần việc còn lại tại thời điểm **10/11/2026**.

| Đợt | Thời gian | Trọng tâm |
|---|---|---|
| Sprint 4 | 09/11/2026 – 15/11/2026 | Hoàn thiện khung code, dữ liệu mẫu, giao diện và module MVP đầu tiên; thống nhất quyền truy cập/PR |
| Sprint 5 | 16/11/2026 – 22/11/2026 | Hoàn thiện trang chi tiết, 3D/AR, checkout, vận chuyển, tài khoản/email, log kho và tích hợp |
| Sprint 6 | 23/11/2026 – 29/11/2026 | Bảo mật, staging/production, backup/restore, cross-browser, UAT, sửa lỗi, demo và bàn giao |

## 9. Cấp quyền và gán Assignee

Repository hiện là Public. Kết quả kiểm tra cho thấy `LeAnhDung05` có quyền Admin; bốn tài khoản còn lại chưa có quyền Write được xác nhận. Để các thành viên có thể push code và nhận Issue:

1. Chủ repository mở **Settings → Collaborators**.
2. Chọn **Add people**.
3. Mời `hoangHT-w`, `HaDiLuc`, `Mod3r3d`, `Anvu-IFBB`.
4. Cấp quyền **Write** cho cả bốn người.
5. Mỗi người mở thông báo/email GitHub và chấp nhận lời mời.
6. Sau khi quyền được xác nhận, gán Assignee cho Issue #1–#37 theo bảng phân công trên.

Các username đã được ghi trong nội dung Issue và tài liệu nhóm; trường Assignees chưa thể gán thành công trước khi quyền Write được xác nhận. Sau khi các thành viên chấp nhận lời mời, quản lý cần gán cả các Issue bổ sung #29–#37.

## 10. Thứ tự ưu tiên bổ sung

- **P0 — Bắt buộc để demo luồng mua hàng:** trang chi tiết sản phẩm, giá/biến thể/tồn kho, giỏ hàng, checkout, vận chuyển cơ bản, ghi log kho và quyền truy cập.
- **P1 — Hoàn thiện vận hành:** tài khoản khách hàng, email giao dịch, trang giới thiệu/liên hệ/chính sách, bảo mật, triển khai staging và backup.
- **P2 — Chất lượng và trải nghiệm:** tối ưu GLB/AR, SEO cơ bản, hiệu năng, khả năng truy cập, kiểm thử đa trình duyệt và UAT.

Mốc đề xuất của các Issue bổ sung #29–#37 được ghi trực tiếp trong từng Issue; nếu tiến độ thực tế thay đổi, nhóm cập nhật ngày hoàn thành trên Project Board.

## 11. Quy tắc làm việc quan trọng

Chỉ có một ứng dụng, một repository và một Project dùng chung. Mỗi thành viên chạy môi trường WordPress/MySQL riêng trên máy cá nhân và dùng feature branch riêng; tích hợp qua Pull Request vào `develop`, sau đó kiểm thử đầu-cuối trên một môi trường staging chung.