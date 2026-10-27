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

Ứng dụng có đăng nhập khách hàng/Admin, danh mục sách, tạo giỏ, lịch sử, sửa giỏ Chờ duyệt và Admin duyệt/từ chối giỏ. Chạy `php bin/console doctrine:migrations:migrate` để áp dụng các migration bổ sung trước khi sử dụng.

Giai đoạn 6 đã bổ sung khách hàng hủy giỏ Chờ duyệt và Admin xóa giỏ Đã hủy/Từ chối. Xem [quy định và cách kiểm thử](database/PHASE6_DELETE.md).

## Đăng nhập và chuẩn bị tài khoản demo

Mở <http://127.0.0.1:8000/dang-nhap>. Sau migration username, thành viên mẫu có tên đăng nhập `user_1001` đến `user_1005`; Admin mẫu là `admin`.

SQL khởi tạo lưu mật khẩu mẫu dạng văn bản, trong khi ứng dụng yêu cầu hash. Trên database demo mới tạo, chuyển đúng tài khoản cần thử sang hash bằng các lệnh sau; mật khẩu hiện tại được giữ nguyên:

```powershell
php bin/console app:account:password admin admin --convert-legacy --apply
php bin/console app:account:password user 1001 --convert-legacy --apply
```

| Vai trò | Tên đăng nhập | Mật khẩu trong dữ liệu mẫu |
|---|---|---|
| Admin | `admin` | `Demo@2026!` |
| Khách hàng | `user_1001` | `Demo@2026!` |

Các thông tin này chỉ áp dụng dữ liệu demo chính thức. Tài khoản đã đổi mật khẩu sử dụng mật khẩu hiện tại; lệnh chuyển đổi bỏ qua tài khoản đã hash. Với database có dữ liệu cần giữ, sao lưu trước khi chuyển đổi. Không chạy lại SQL khởi tạo để sửa lỗi đăng nhập vì SQL đó tạo lại các bảng ứng dụng.

Khách hàng cũng có thể tạo tài khoản tại <http://127.0.0.1:8000/dang-ky>; tài khoản đăng ký mới được hash mật khẩu ngay.

## Xử lý lỗi khi chạy trên Windows

| Lỗi | Cách kiểm tra/xử lý |
|---|---|
| `could not find driver` | Chạy `php --ri pdo_sqlsrv` và `php --ini`; bật DLL phù hợp phiên bản PHP, TS/NTS và x64/x86 rồi khởi động lại server PHP. |
| Composer báo thiếu `ext-pdo_sqlsrv` | Cài/bật driver trước, sau đó chạy lại `composer install`. |
| Thiếu `THANH_VIEN_USERNAME` hoặc `GIO_HANG_LY_DO_TU_CHOI` | Chạy `php bin/console doctrine:migrations:migrate`. |
| Thiếu asset Stimulus/Turbo | Chạy `php bin/console importmap:install`. |
| Đúng mật khẩu demo nhưng không đăng nhập được | Kiểm tra tài khoản đã chuyển sang hash chưa và đang dùng username, không dùng email. |

## Kiểm thử giai đoạn 6

```powershell
php tests/phase6_smoke.php
php vendor/phpunit/phpunit/phpunit tests/CartDeleteWebTest.php
```

Xem [kịch bản kiểm thử trên web](database/PHASE6_MANUAL_TESTS.md) và [SQL kiểm tra dữ liệu](database/phase6_delete_checks.sql) để đối chiếu kết quả hủy/xóa với database thật.
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

Ứng dụng có đăng nhập khách hàng/Admin, danh mục sách, tạo giỏ, lịch sử, sửa giỏ Chờ duyệt và Admin duyệt/từ chối giỏ. Chạy `php bin/console doctrine:migrations:migrate` để áp dụng các migration bổ sung trước khi sử dụng.

Giai đoạn 6 đã bổ sung khách hàng hủy giỏ Chờ duyệt và Admin xóa giỏ Đã hủy/Từ chối. Xem [quy định và cách kiểm thử](database/PHASE6_DELETE.md).

## Đăng nhập và chuẩn bị tài khoản demo

Mở <http://127.0.0.1:8000/dang-nhap>. Sau migration username, thành viên mẫu có tên đăng nhập `user_1001` đến `user_1005`; Admin mẫu là `admin`.

SQL khởi tạo lưu mật khẩu mẫu dạng văn bản, trong khi ứng dụng yêu cầu hash. Trên database demo mới tạo, chuyển đúng tài khoản cần thử sang hash bằng các lệnh sau; mật khẩu hiện tại được giữ nguyên:

```powershell
php bin/console app:account:password admin admin --convert-legacy --apply
php bin/console app:account:password user 1001 --convert-legacy --apply
```

| Vai trò | Tên đăng nhập | Mật khẩu trong dữ liệu mẫu |
|---|---|---|
| Admin | `admin` | `Demo@2026!` |
| Khách hàng | `user_1001` | `Demo@2026!` |

Các thông tin này chỉ áp dụng dữ liệu demo chính thức. Tài khoản đã đổi mật khẩu sử dụng mật khẩu hiện tại; lệnh chuyển đổi bỏ qua tài khoản đã hash. Với database có dữ liệu cần giữ, sao lưu trước khi chuyển đổi. Không chạy lại SQL khởi tạo để sửa lỗi đăng nhập vì SQL đó tạo lại các bảng ứng dụng.

Khách hàng cũng có thể tạo tài khoản tại <http://127.0.0.1:8000/dang-ky>; tài khoản đăng ký mới được hash mật khẩu ngay.

## Xử lý lỗi khi chạy trên Windows

| Lỗi | Cách kiểm tra/xử lý |
|---|---|
| `could not find driver` | Chạy `php --ri pdo_sqlsrv` và `php --ini`; bật DLL phù hợp phiên bản PHP, TS/NTS và x64/x86 rồi khởi động lại server PHP. |
| Composer báo thiếu `ext-pdo_sqlsrv` | Cài/bật driver trước, sau đó chạy lại `composer install`. |
| Thiếu `THANH_VIEN_USERNAME` hoặc `GIO_HANG_LY_DO_TU_CHOI` | Chạy `php bin/console doctrine:migrations:migrate`. |
| Thiếu asset Stimulus/Turbo | Chạy `php bin/console importmap:install`. |
| Đúng mật khẩu demo nhưng không đăng nhập được | Kiểm tra tài khoản đã chuyển sang hash chưa và đang dùng username, không dùng email. |

## Kiểm thử giai đoạn 6

```powershell
php tests/phase6_smoke.php
php vendor/phpunit/phpunit/phpunit tests/CartDeleteWebTest.php
```

Xem [kịch bản kiểm thử trên web](database/PHASE6_MANUAL_TESTS.md) và [SQL kiểm tra dữ liệu](database/phase6_delete_checks.sql) để đối chiếu kết quả hủy/xóa với database thật.
