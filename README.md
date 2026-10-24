# MNM Nhom 04 - CRUD Gio Hang

Ứng dụng Symfony dùng Microsoft SQL Server và database chính thức `BanSachDB`.

## Yêu cầu

- PHP 8.2 trở lên và Composer.
- Microsoft SQL Server với instance mặc định `MSSQLSERVER`.
- ODBC Driver 18 for SQL Server và công cụ `sqlcmd`.
- PHP extensions `sqlsrv` và `pdo_sqlsrv`.

Trên Windows, Microsoft khuyến nghị cài PHP extensions bằng PIE:

```powershell
curl.exe -fL --output pie.phar https://github.com/php/pie/releases/latest/download/pie.phar
php .\pie.phar install microsoft/sqlsrv microsoft/pdo_sqlsrv
php --ri sqlsrv
php --ri pdo_sqlsrv
```

## Khởi tạo database

Mở PowerShell trong thư mục dự án và chạy:

```powershell
.\database\setup.ps1
```

Lệnh mặc định sẽ dừng nếu `BanSachDB` đã tồn tại. Chỉ khi chủ động muốn tạo lại sáu bảng và dữ liệu mẫu, chạy:

```powershell
.\database\setup.ps1 -Reset
```

Script SQL chính thức là `database/BansachDB_Full_CoDuLieu.sql`, bao gồm:

- 10 loại sách.
- 50 đầu sách.
- 5 thành viên.
- 1 quản trị viên.
- 8 giỏ hàng.
- 17 dòng mua.

Ứng dụng dùng Windows Authentication qua cấu hình mặc định:

```dotenv
DATABASE_URL="mssql://localhost/BanSachDB"
```

Nếu dùng SQL Server Authentication, tạo `.env.local` và URL-encode các ký tự đặc biệt trong mật khẩu:

```dotenv
DATABASE_URL="mssql://username:password@localhost/BanSachDB"
```

## Cài và chạy ứng dụng

```powershell
composer install
php bin\console doctrine:query:sql "SELECT DB_NAME() AS database_name"
php -S 127.0.0.1:8000 -t public public/index.php
```

Truy cập <http://127.0.0.1:8000/home>.

> Repo hiện chỉ có trang Symfony mẫu. Database đã sẵn sàng, nhưng cần bổ sung Entity, Repository, Controller và template CRUD để giao diện sử dụng sáu bảng trên.
