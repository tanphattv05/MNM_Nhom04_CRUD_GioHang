# Thiết kế cơ sở dữ liệu và cấu hình ứng dụng

**Đề tài:** Website bán sách – Symfony MVC, CRUD Giỏ hàng. **Nhóm 04 – Lớp DA23TTA.** Nội dung phản ánh schema sau migration `Version20261028000100`.

## 1.1. Thiết kế bảng GIO_HANG

Cơ sở dữ liệu BanSachDB được xây dựng trên hệ quản trị Microsoft SQL Server nhằm phục vụ việc lưu trữ và quản lý thông tin của hệ thống nhà sách. Cơ sở dữ liệu gồm sáu bảng nghiệp vụ chính: LOAI_SACH, SACH, THANH_VIEN, QTV, GIO_HANG và MUA.

Trong đó, bảng GIO_HANG được lựa chọn làm đối tượng triển khai các chức năng CRUD. Bảng này lưu trữ thông tin giỏ hàng, thời gian tạo, trạng thái xử lý, ghi chú và thông tin liên kết đến khách hàng, quản trị viên. Bảng MUA lưu trữ danh sách sách và số lượng tương ứng của từng giỏ hàng.

**Bảng 3. Cấu trúc bảng GIO_HANG**

| Cột | Kiểu dữ liệu và ràng buộc | Ý nghĩa |
|---|---|---|
| `GIO_HANG_ID_GIO` | INT, khóa chính, NOT NULL | Mã định danh duy nhất của giỏ hàng |
| `THANH_VIEN_TEN_DANG_NHAP` | VARCHAR(10), khóa ngoại, cho phép NULL | Mã thành viên xác định khách hàng sở hữu giỏ hàng |
| `QTV_USER_ADMIN` | VARCHAR(60), khóa ngoại, cho phép NULL | Tài khoản quản trị viên xử lý giỏ hàng |
| `GIO_HANG_THOI_GIAN` | DATETIME, cho phép NULL | Thời điểm tạo giỏ hàng |
| `GIO_HANG_TRANG_THAI` | NVARCHAR(30), cho phép NULL | Trạng thái xử lý giỏ hàng |
| `GIO_HANG_GHI_CHU` | NVARCHAR(255), cho phép NULL | Ghi chú của khách hàng; chứa thông tin nhận hàng khi xác nhận COD |
| `GIO_HANG_LY_DO_TU_CHOI` | NVARCHAR(255), cho phép NULL | Lý do quản trị viên từ chối giỏ hàng |

Trường GIO_HANG_ID_GIO được sử dụng làm khóa chính nhằm phân biệt các giỏ hàng. Trường THANH_VIEN_TEN_DANG_NHAP liên kết với khóa chính cùng tên trong bảng THANH_VIEN và có cùng kiểu VARCHAR(10). Trường QTV_USER_ADMIN liên kết với bảng QTV. Các ràng buộc khóa ngoại góp phần bảo đảm tính toàn vẹn tham chiếu và hỗ trợ quá trình truy xuất dữ liệu giữa các bảng.

Mặc dù mang tên THANH_VIEN_TEN_DANG_NHAP, trường này hiện đóng vai trò mã định danh thành viên. Tên đăng nhập sử dụng trên giao diện được lưu riêng tại THANH_VIEN_USERNAME VARCHAR(30), có ràng buộc duy nhất. Việc chỉnh sửa username không làm thay đổi mã thành viên và các giỏ đã tạo. Các mã số cũ được giữ nguyên giá trị sau khi chuyển sang chuỗi; mã thành viên mới do ứng dụng sinh có độ dài 10 ký tự.

Trường THANH_VIEN_SO_DIEN_THOAI trong bảng THANH_VIEN sử dụng VARCHAR(10) để giữ được số 0 ở đầu. Database có ràng buộc CHECK chỉ chấp nhận NULL hoặc chuỗi gồm đúng 10 chữ số. Các form đăng ký, hồ sơ và checkout bắt buộc nhập đủ 10 chữ số. Hệ thống không tự cắt bớt số điện thoại dài.

Khi tạo giỏ mới, ứng dụng gán chủ giỏ, thời gian tạo và trạng thái Chờ duyệt. Quản trị viên có thể chưa được gán cho đến khi giỏ được xử lý. Các trạng thái nghiệp vụ gồm Chờ duyệt, Đã duyệt, Từ chối và Đã hủy. Chủ giỏ được phép NULL ở cấp schema để tương thích dữ liệu cũ; ứng dụng luôn gán chủ khi tạo mới.

Các trường chứa nội dung tiếng Việt sử dụng NVARCHAR để hỗ trợ Unicode. Lý do từ chối được lưu riêng nhằm tránh ghi đè ghi chú của khách hàng. Bảng MUA liên kết thành viên, sách và giỏ bằng khóa chính ghép ba cột; trường THANH_VIEN_TEN_DANG_NHAP trong MUA cũng có kiểu VARCHAR(10).

![ERD website bán sách](diagrams/database-erd.png)

*Hình. Sơ đồ quan hệ của sáu bảng nghiệp vụ sau khi cập nhật kiểu dữ liệu.* [Bản PDF](diagrams/database-erd.pdf), [SVG](diagrams/database-erd.svg) và [thuyết minh ERD](diagrams/database-erd.md).

## 1.2. Tạo cơ sở dữ liệu

Nhóm sử dụng SQL Server Management Studio (SSMS) để kết nối và quản lý cơ sở dữ liệu Microsoft SQL Server. Trước khi cài đặt, khởi động dịch vụ SQL Server, mở SSMS và kết nối bằng tài khoản có quyền tạo cơ sở dữ liệu.

Đối với môi trường cài mới, người dùng chọn **một trong hai phương án** sau:

### Phương án 1: Tự động tạo database và bảng bằng tệp SQL

1. Mở tệp `database/BanSachDB_Full.sql` trong mã nguồn dự án bằng SSMS.
2. Kiểm tra tên database BanSachDB ở đầu tệp.
3. Thực thi toàn bộ tệp để tự tạo database, các bảng và ràng buộc.

Tệp đã chứa lệnh tạo cơ sở dữ liệu và chuyển ngữ cảnh sang database đích. Với phương án này, không chạy thêm lệnh CREATE DATABASE riêng trước đó; script sẽ từ chối nếu database đích đã tồn tại.

### Phương án 2: Tạo database bằng câu lệnh SQL, sau đó tạo bảng

Mở cửa sổ New Query trong SSMS và thực thi:

```sql
USE [master];
GO

CREATE DATABASE [BanSachDB]
COLLATE SQL_Latin1_General_CP1_CI_AS;
GO

USE [BanSachDB];
GO
```

Lệnh CREATE DATABASE tạo cơ sở dữ liệu BanSachDB. Lệnh USE chuyển ngữ cảnh làm việc sang database vừa tạo; GO phân tách các batch lệnh trong SSMS.

Sau đó, mở và thực thi toàn bộ tệp `database/BanSachDB_SchemaOnly.sql` để tạo các bảng và ràng buộc trong database đã có. Tệp này không tạo lại database và sẽ từ chối nếu các bảng nghiệp vụ đã tồn tại. Không chạy tiếp `BanSachDB_Full.sql` sau bước tạo database thủ công.

Cả hai phương án đều tạo sáu bảng nghiệp vụ và bảng hạ tầng doctrine_migration_versions. Schema tích hợp đủ bốn migration, bao gồm khóa thành viên VARCHAR(10), số điện thoại VARCHAR(10) và CHECK đúng 10 chữ số. Hai tệp khởi tạo không chứa dữ liệu sách hoặc tài khoản mẫu.

### Kiểm tra kết quả khởi tạo

Sau khi hoàn thành một trong hai phương án, thực thi:

```sql
USE [BanSachDB];
GO

SELECT DB_NAME() AS TenCoSoDuLieu;

SELECT name AS TenBang
FROM sys.tables
WHERE schema_id = SCHEMA_ID(N'dbo')
ORDER BY name;
```

Đối với database được nâng cấp từ phiên bản cũ, migration Version20261028000100 chuyển đồng thời khóa thành viên tại ba bảng THANH_VIEN, GIO_HANG và MUA sang VARCHAR(10), thu hẹp cột điện thoại về VARCHAR(10), đồng thời khôi phục khóa chính, khóa ngoại và index. Migration kiểm tra dữ liệu trước khi thay đổi, được thực hiện trong transaction và không tự cắt dữ liệu không hợp lệ.

## 1.3. Cấu hình môi trường và kết nối database

Dự án sử dụng Symfony và Doctrine DBAL để kết nối Microsoft SQL Server. File .env chứa các giá trị mặc định; cấu hình riêng của máy được đặt trong .env.local và không đưa lên repository.

Ví dụ cấu hình phát triển bằng Windows Authentication với instance SQL Server mặc định:

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=thay_bang_chuoi_ngau_nhien_rieng_cua_may
DATABASE_URL="mssql://localhost/BanSachDB"
```

| Biến | Ý nghĩa |
|---|---|
| `APP_ENV` | Môi trường hoạt động của ứng dụng |
| `APP_DEBUG` | Bật chế độ gỡ lỗi trong môi trường phát triển |
| `APP_SECRET` | Chuỗi bí mật riêng của ứng dụng Symfony |
| `DATABASE_URL` | Thông tin kết nối đến SQL Server và database BanSachDB |

Cấu hình trên sử dụng quyền của tài khoản Windows đang chạy PHP. Tài khoản này phải được cấp quyền truy cập BanSachDB. PHP cần bật extension pdo_sqlsrv và cài Microsoft ODBC Driver 18 for SQL Server. Các tùy chọn kết nối của Doctrine nằm trong config/packages/doctrine.yaml. Named instance hoặc SQL Authentication phải điều chỉnh DATABASE_URL theo môi trường thực tế; không công bố mật khẩu trong báo cáo.

Sinh APP_SECRET riêng bằng lệnh:

```powershell
php -r "echo bin2hex(random_bytes(32));"
```

Sau khi cấu hình, kiểm tra kết nối và migration:

```powershell
php bin/console doctrine:query:sql "SELECT DB_NAME() AS database_name"
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --dry-run
```

Kết nối thành công trả về database_name là BanSachDB. Nếu còn migration mới, đối chiếu SQL và sao lưu trước khi áp dụng:

```powershell
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Ứng dụng được truy cập tại http://127.0.0.1:8000/. Cấu hình sử dụng DATABASE_URL của Symfony; các lệnh vận hành dùng bin/console của dự án.
