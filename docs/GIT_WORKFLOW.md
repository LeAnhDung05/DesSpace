# Quy trình làm việc với Git và GitHub

## 1. Các nhánh chính

- `main`: phiên bản ổn định, sẵn sàng demo/nghiệm thu.
- `develop`: nhánh tích hợp chung của cả nhóm.
- Feature branch: mỗi Issue thực hiện trên một nhánh riêng, tập trung vào một nhiệm vụ.

Quy tắc đặt tên:
```text
feature/<issue-number>-<short-description>
fix/<issue-number>-<short-description>
docs/<issue-number>-<short-description>
```

Ví dụ:
```text
feature/7-product-3d-viewer
feature/13-catalog-filter
feature/17-checkout
feature/21-inventory-event-log
feature/26-admin-dashboard
```

Các nhánh module đã được khởi tạo:
- M1: `feature/member01-3d-ar`
- M2: `feature/member02-catalog`
- M3: `feature/member03-checkout`
- M4: `feature/member04-inventory`
- M5: `feature/member05-dashboard`

Có thể tạo nhánh theo từng Issue từ nhánh module hoặc `develop`, nhưng phải thống nhất một cách làm trong nhóm và luôn tạo Pull Request vào `develop`.

## 2. Quy trình đầu ngày

Mỗi thành viên mở Terminal tại máy cá nhân và chạy:

```bash
git clone https://github.com/LeAnhDung05/DesSpace.git
cd DesSpace
git switch develop
git pull origin develop
git switch -c feature/<issue-number>-<short-description>
```

Nếu nhánh module đã tồn tại và nhóm chọn làm trực tiếp trên nhánh đó thì dùng:
```bash
git switch feature/member01-3d-ar
git pull origin feature/member01-3d-ar
```

Thay tên nhánh cho đúng module được giao.

## 3. Sau khi lập trình xong

Chỉ thêm các file thuộc nhiệm vụ của mình:
```bash
git add <specific-files>
git commit -m "feat(<module>): <short description>"
git push -u origin feature/<issue-number>-<short-description>
```

Ví dụ:
```bash
git add wordpress/wp-content/plugins/despace-core/includes/Product-3D/
git commit -m "feat(m1): integrate product model viewer"
git push -u origin feature/7-product-3d-viewer
```

Sau đó tạo Pull Request trên GitHub:
- **Base:** `develop`
- **Compare:** nhánh feature của bạn.
- Gắn Issue liên quan bằng `Closes #<issue-number>` khi nhiệm vụ đã hoàn thành.
- Yêu cầu ít nhất một thành viên review.
- Ghi rõ cách kiểm thử, ảnh chụp nếu có thay đổi giao diện, rủi ro và ghi chú migration.

## 4. Quy tắc merge

- Không đẩy code tính năng trực tiếp vào `main`.
- Không merge Pull Request khi còn nhận xét review chưa xử lý hoặc kiểm tra bắt buộc bị lỗi.
- Ưu tiên Squash and merge đối với nhánh feature nhỏ, tập trung.
- Nếu có xung đột, cập nhật/rebase nhánh feature từ `develop` trước khi yêu cầu review cuối.
- Người phụ trách release chỉ merge `develop` vào `main` sau khi đã kiểm thử tích hợp.

## 5. Ranh giới sở hữu source code

| Thành viên | Khu vực code chính |
|---|---|
| M1 | `includes/Product-3D/` |
| M2 | `themes/despace-child/`, `includes/Catalog/` |
| M3 | `includes/Checkout-Orders/` |
| M4 | `includes/Inventory/`, file migration nhật ký kho |
| M5 | `despace-core.php`, `includes/Reports/`, API bootstrap và tài liệu chung |

Không để cả 5 người cùng sửa một file bootstrap. Khi thay đổi interface dùng chung hoặc cơ chế nạp plugin, phải yêu cầu M5 review.

## 6. Những file tuyệt đối không commit

- `wp-config.php`, file `.env`, database dump hoặc thông tin đăng nhập.
- WordPress Core hoặc source của plugin bên thứ ba đã cài.
- Upload/cache/file sinh ra từ môi trường local.
- Dữ liệu khách hàng, thanh toán hoặc thông tin cá nhân thật.
- File GLB dung lượng lớn, trừ khi nhóm đã thống nhất và cấu hình Git LFS. Ưu tiên lưu media ở staging/shared storage.

## 7. Quy ước tiền tố commit

- `feat`: thêm chức năng mới.
- `fix`: sửa lỗi.
- `docs`: cập nhật tài liệu.
- `test`: bổ sung/cập nhật kiểm thử.
- `refactor`: thay đổi cấu trúc bên trong, không đổi hành vi.
- `chore`: công việc bảo trì/công cụ.

## 8. Quy tắc phối hợp hằng ngày

1. Đầu ngày: cập nhật `develop`, xem Issue và xác nhận phạm vi công việc.
2. Trong ngày: chỉ sửa khu vực code được phân công; nếu cần sửa module khác phải trao đổi trước.
3. Cuối ngày: commit và push phần code đã làm; cập nhật tiến độ Issue.
4. Khi hoàn thành: mở Pull Request, yêu cầu review và xử lý nhận xét.
5. Chỉ đánh dấu Done sau khi code đã merge và tiêu chí nghiệm thu đã đạt.