# DeSpace — Website thương mại điện tử nội thất cao cấp

**Mã dự án:** DESPACE-2026  
**Thời gian thực hiện:** 19/10/2026 – 29/11/2026  
**Nhóm:** 5 thành viên  
**Thiết kế:** [File Figma DeSpace](https://www.figma.com/design/2O1yGyEkoUrDRcGX6upmNw)

## 1. Công nghệ sử dụng đã chốt

| Lớp | Công nghệ |
|---|---|
| Frontend | HTML5 + CSS3 + JavaScript ES6+ |
| Thiết kế | Figma — Premium UI/UX |
| CMS | WordPress |
| Thương mại điện tử | WooCommerce |
| Backend | PHP |
| Cơ sở dữ liệu | MySQL Community Edition |
| API | WordPress REST API + AJAX / Fetch API |
| 3D / AR | Google model-viewer + GLB |
| Module riêng | DeSpace Core Plugin (`despace-core`) |
| Giao diện | DeSpace Child Theme (`despace-child`) |
| Quản lý mã nguồn | Git + GitHub |

## 2. Kiến trúc sản phẩm

- Một website dùng chung, một repository và một ứng dụng WordPress/WooCommerce tích hợp.
- `despace-child` phụ trách giao diện, template và tài nguyên theme.
- `despace-core` phụ trách nghiệp vụ riêng và các module mở rộng của DeSpace.
- WordPress/WooCommerce là nguồn dữ liệu chính cho người dùng, sản phẩm, biến thể, coupon, đơn hàng và tồn kho.
- Không tạo các bảng `users`, `products`, `orders`, `coupons` trùng với dữ liệu lõi.
- Thao tác đơn hàng qua WooCommerce CRUD API để tương thích HPOS.
- Bảng `despace_inventory_logs` chỉ lưu nhật ký truy vết; không được trở thành hệ thống tồn kho thứ hai hoặc trừ/hoàn tồn hai lần.

## 3. Phân công 5 module

| Thành viên | Module | Trách nhiệm chính |
|---|---|---|
| M1 | Phòng trưng bày 3D/AR | Metadata GLB, model-viewer, AR và phương án dự phòng |
| M2 | Danh mục & Giao diện Premium | Child Theme, trang chủ, danh mục, tìm kiếm và lọc AJAX |
| M3 | Giỏ hàng & Đơn hàng | Giỏ hàng, coupon, checkout, hướng dẫn thanh toán và tra cứu đơn |
| M4 | Truy vết tồn kho | Nhật ký biến động tồn kho chống ghi trùng và giao diện kiểm tra |
| M5 | Dashboard & Tích hợp | Dashboard, báo cáo, API Contract, tích hợp và điều phối release |

Công việc chi tiết được theo dõi bằng GitHub Issues. Thêm Issues #1–#28 vào Project **Thực Tập Chuyên Ngành** và gán cho đúng thành viên.

## 4. Cấu trúc repository

```text
wordpress/
  wp-content/
    themes/despace-child/
    plugins/despace-core/
      despace-core.php
      includes/
        Products/
        Product-3D/
        Catalog/
        Checkout-Orders/
        Inventory/
        Reports/
        REST-API/
        Admin/
        Security/
        Database/
docs/
.github/
```

Tài liệu cần đọc:
- [Phân công module và trách nhiệm](docs/TEAM_TASKS.md)
- [Quy trình Git/GitHub](docs/GIT_WORKFLOW.md)
- [Kiến trúc và quyền sở hữu dữ liệu](docs/ARCHITECTURE.md)
- [Quy ước API dùng chung](docs/API_CONTRACT.md)
- [Tiêu chí hoàn thành công việc](docs/DEFINITION_OF_DONE.md)
- [Hướng dẫn thiết lập GitHub Project](docs/PROJECT_BOARD_SETUP.md)

## 5. Môi trường phát triển

Mỗi thành viên chạy một môi trường WordPress + WooCommerce + MySQL riêng trên máy cá nhân, sử dụng cùng phiên bản đã thống nhất và cùng bộ dữ liệu mẫu. Tích hợp qua nhánh `develop` và kiểm thử đầu-cuối trên một môi trường staging dùng chung.

Không commit `wp-config.php`, thông tin đăng nhập, database local, cache, WordPress Core hoặc dữ liệu khách hàng chưa được phép. Dùng cấu hình riêng theo môi trường và file `.gitignore`.

## 6. Trọng tâm bàn giao

Tại thời điểm 10/11/2026, ưu tiên hoàn thành MVP cho cả 5 module, tích hợp liên module, kiểm tra bảo mật, responsive và luồng đặt hàng đầu-cuối có thể demo trước 29/11/2026.