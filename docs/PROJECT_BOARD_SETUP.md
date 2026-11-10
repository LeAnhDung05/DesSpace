# Hướng dẫn sử dụng GitHub Projects — Thực Tập Chuyên Ngành

## 1. Thông tin Project và Repository

- **Tên Project:** Thực Tập Chuyên Ngành
- **Repository:** `LeAnhDung05/DesSpace`
- **Địa chỉ repository:** https://github.com/LeAnhDung05/DesSpace
- Cả 5 module đều thuộc cùng một website và một repository, không tách thành 5 website riêng.

## 2. Thêm 28 Issues vào Project

Các Issues đã được tạo trong repository, nhưng cần thêm chúng vào bảng GitHub Projects v2 để theo dõi:

1. Mở Project **Thực Tập Chuyên Ngành**.
2. Trong chế độ Backlog/Table, chọn **Add item** (Thêm mục).
3. Tìm `repo:LeAnhDung05/DesSpace` hoặc dán URL của Issue.
4. Thêm các Issue **#1–#28** vào cùng Project.
5. Dùng trường Module để nhóm Issue tổng và Issue công việc theo M1–M5.

Lưu ý: Issue trong repository và item trên Project là hai đối tượng riêng. Tạo Issue trong repository không có nghĩa là Issue tự động xuất hiện trên Project.

## 3. Các trường nên có trên bảng Project

| Tên trường | Loại | Giá trị / mục đích |
|---|---|---|
| Module | Single select | M1, M2, M3, M4, M5 |
| Ưu tiên (Priority) | Single select | P0, P1, P2, P3 |
| Đợt thực hiện (Sprint) | Iteration | Sprint 4, Sprint 5, Sprint 6 |
| Ước lượng (Estimate) | Number | 1, 2, 3, 5, 8 điểm |
| Trạng thái (Status) | Status có sẵn | Backlog, Ready, In Progress, In Review, Testing, Done, Blocked |

## 4. Ý nghĩa các trạng thái

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

## 5. Các chế độ xem (Views) nên tạo

- **Backlog:** dạng bảng; nhóm theo Module, sắp xếp theo Priority.
- **Board:** dạng Kanban; nhóm theo Status để nhìn việc đang làm/chờ review/đã hoàn thành.
- **Current iteration:** chỉ hiển thị các việc thuộc Sprint hiện tại.
- **Roadmap:** dạng timeline/roadmap để nhìn mốc thực hiện theo Sprint.
- **My items:** mỗi thành viên lọc các nhiệm vụ được giao cho mình.

## 6. Bản đồ Issue theo module

| Module | Issue tổng | Các Issue công việc |
|---|---:|---|
| M1 — 3D/AR | #1 | #6–#9 |
| M2 — Catalog & UI | #2 | #10–#14 |
| M3 — Checkout & Đơn hàng | #3 | #15–#19 |
| M4 — Tồn kho | #4 | #20–#23 |
| M5 — Dashboard & Tích hợp | #5 | #24–#28 |

Các Issue tổng có liên kết đến Issue công việc. Dùng trường Module để nhóm. Trường Sub-issues progress chỉ tự phản ánh quan hệ con thực tế nếu đã tạo quan hệ Sub-issue trong giao diện GitHub; chỉ ghi số Issue trong nội dung chưa tự tạo quan hệ đó.

## 7. Kế hoạch Sprint đề xuất

Thời gian dự án: **19/10/2026 – 29/11/2026**. Bảng dưới đây tập trung vào phần việc còn lại tại thời điểm **10/11/2026**.

| Đợt | Thời gian | Trọng tâm |
|---|---|---|
| Sprint 4 | 09/11/2026 – 15/11/2026 | Repository chung, API Contract, nền tảng Child Theme/Core Plugin và phiên bản chạy được đầu tiên của từng module |
| Sprint 5 | 16/11/2026 – 22/11/2026 | Hoàn thiện 5 module và tích hợp luồng liên module |
| Sprint 6 | 23/11/2026 – 29/11/2026 | Kiểm thử staging, sửa lỗi, demo, tài liệu và bàn giao |

## 8. Phân công nhóm

Chỉ gán M1–M5 với GitHub username thật sau khi các thành viên chấp nhận lời mời vào repository.

- **M1:** Phòng trưng bày 3D/AR.
- **M2:** Giao diện Premium, danh mục và tìm kiếm.
- **M3:** Giỏ hàng, thanh toán và quản lý đơn.
- **M4:** Nhật ký và truy vết tồn kho.
- **M5:** Dashboard, báo cáo và điều phối tích hợp/release.

## 9. Quy tắc làm việc quan trọng

Chỉ có một ứng dụng, một repository và một Project dùng chung. Mỗi thành viên chạy môi trường WordPress/MySQL riêng trên máy cá nhân và dùng feature branch riêng; tích hợp qua Pull Request vào `develop`, sau đó kiểm thử đầu-cuối trên một môi trường staging chung.