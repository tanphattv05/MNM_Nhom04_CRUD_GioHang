# Nhà sách Nhóm 04 – Symfony CRUD Giỏ hàng

Symfony 7.4, PHP 8.2+, Twig/AssetMapper và Microsoft SQL Server. Khách hàng xem sách, tạo/sửa/hủy giỏ Chờ duyệt; Admin tìm kiếm, duyệt/từ chối và xóa giỏ Đã hủy/Từ chối. Đăng nhập chung bằng **tên đăng nhập**, không dùng email.

## Cài đặt trên máy khác

1. Cài PHP 8.2+ và Composer; bật `pdo_sqlsrv`, `ctype`, `iconv` (nên có `mbstring`, `openssl`). Cài Microsoft ODBC Driver 18 for SQL Server. Kiểm tra đúng PHP mà terminal/server sử dụng:

```powershell
php -v
php --ini
php --ri pdo_sqlsrv
composer check-platform-reqs
```

DLL PHP SQL Server phải khớp phiên bản PHP, TS/NTS và kiến trúc x64/x86. Không sao chép đường dẫn DLL của máy cũ.

2. Mở PowerShell tại thư mục dự án đã sao chép. Tạo `.env.local` (không commit) trước khi chạy Composer:

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=thay_bang_chuoi_ngau_nhien_rieng_cua_may_ban
DATABASE_URL="mssql://localhost/BanSachDB"
```

Sinh secret bằng `php -r "echo bin2hex(random_bytes(32));"` rồi dán kết quả vào APP_SECRET. Windows Authentication dùng tài khoản Windows đang chạy PHP; tài khoản đó phải có quyền truy cập database.

- Instance mặc định trên máy khác: `mssql://TEN_MAY/BanSachDB`.
- Named instance: ví dụ `mssql://TEN_MAY%5CSQLEXPRESS/BanSachDB` (encode dấu `\` thành `%5C`), hoặc dùng host/cổng TCP SQL Server đã cấu hình.
- SQL Authentication: `mssql://username:password@HOST:1433/BanSachDB`; URL-encode username/password có ký tự đặc biệt.

Kết nối hiện tại được kiểm tra là `ACER\SQLEXPRESS`, database `BanSachDB`; không bắt buộc máy khác có cùng tên server. Cấu hình `config/packages/doctrine.yaml` đang dùng ODBC Driver 18, Encrypt=1 và TrustServerCertificate=1 cho máy phát triển. Khi triển khai thật, dùng chứng chỉ tin cậy, HTTPS, APP_ENV=prod, APP_DEBUG=0 và secret riêng.

```powershell
composer install
php bin/console importmap:install
php bin/console doctrine:query:sql "SELECT DB_NAME() AS database_name, @@SERVERNAME AS server_name"
```

## Database và hai migration

**Máy đã có dữ liệu:** sao lưu Full Backup trong SSMS, kiểm tra bản sao lưu; không chạy lại SQL khởi tạo hoặc `setup.ps1 -Reset`. File SQL khởi tạo có lệnh tạo lại bảng và sẽ làm mất dữ liệu cũ.

**Máy mới chưa có BanSachDB:** ưu tiên restore bản backup dự án. Hoặc khởi tạo dữ liệu demo đúng một lần bằng `database/BansachDB_Full_CoDuLieu.sql` trong SSMS, trên đúng server và database mới. `database/setup.ps1` chỉ hỗ trợ instance mặc định local (`-S .`), mặc định dừng nếu database đã tồn tại; không dùng nó cho named instance mà chưa điều chỉnh server.

Hai migration hiện có:

| Migration | Thay đổi |
|---|---|
| Version20261026000100 | Thêm username VARCHAR(30) duy nhất, tạo tên `user_<mã>` cho dữ liệu cũ; giữ khóa INT và quan hệ giỏ |
| Version20261026000200 | Thêm lý do từ chối NVARCHAR(255), tách khỏi ghi chú khách hàng |

```powershell
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:list
php bin/console doctrine:migrations:migrate --dry-run
```

Đọc SQL dry-run và sao lưu trước khi **chủ động** áp dụng migration còn thiếu:

```powershell
php bin/console doctrine:migrations:migrate
```

Nếu đã có cột nhưng migration chưa được ghi nhận, dừng để đối chiếu schema và bảng `doctrine_migration_versions`; không chạy lại ALTER hoặc tự đánh dấu migration đã chạy. Cả hai migration đều không hỗ trợ rollback xuống vì sẽ mất username/lý do từ chối. Không dùng `schema:update --force` hoặc `schema:drop`.

Kiểm tra ngày 27/10/2026 trên workspace: 2 migration đã thực thi, 0 migration mới; không áp dụng migration trong đợt kiểm thử.

## Tài khoản và đăng nhập

- Trang chung `/dang-nhap` nhận tên đăng nhập và mật khẩu, tự điều hướng theo ROLE_USER/ROLE_ADMIN. `/admin/dang-nhap` chỉ chuyển về trang chung.
- Khách mới đăng ký tại `/dang-ky`. Email chỉ là thông tin liên hệ. Username không trùng tên Admin, không phân biệt chữ hoa/thường.
- Dữ liệu mẫu sau migration có username `user_1001`…`user_1005`, Admin `admin`. Tài khoản đã đổi username dùng tên hiện tại.
- Mật khẩu văn bản trong SQL mẫu không được chấp nhận để đăng nhập; tài khoản mới được hash ngay. Không đặt lại mật khẩu thật chỉ để chạy demo.

Lệnh chỉ kiểm tra (không ghi):

```powershell
php bin/console app:account:password user 1001
php bin/console app:account:password admin admin
```

Sau sao lưu và xác nhận đúng tài khoản demo, chuyển mật khẩu hiện có sang hash; lệnh bỏ qua tài khoản đã hash, không in mật khẩu:

```powershell
php bin/console app:account:password user 1001 --convert-legacy --apply
php bin/console app:account:password admin admin --convert-legacy --apply
```

Hoặc đặt mật khẩu riêng bằng nhập ẩn: `php bin/console app:account:password admin admin --apply`. Các lệnh ghi mật khẩu chỉ hoạt động trong dev, từng tài khoản được chỉ định; không tự chạy khi khởi động.

## Chạy và sử dụng

```powershell
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Mở http://127.0.0.1:8000/ (hoặc `/home`). CSS/JS do AssetMapper phục vụ qua `/assets/…`; không mở file Twig trực tiếp.

| Vai trò | Đường dẫn |
|---|---|
| Khách hàng | `/trang-chu`, `/danh-muc`, `/gio-hang`, `/gio-hang/lich-su`, `/tai-khoan` |
| Admin | `/admin/dashboard`, `/admin/gio-hang` |

Giỏ mới Chờ duyệt không trừ kho. Chỉ giỏ Chờ duyệt được sửa/hủy; Admin duyệt mới trừ kho, duyệt lại không trừ lần hai. Từ chối lưu lý do riêng và không trừ kho. Admin chỉ xóa giỏ Đã hủy/Từ chối, xóa dòng MUA cùng transaction. Giá lịch sử chưa lưu riêng: màn hình lịch sử ghi rõ tổng tính theo giá sách hiện tại. Tiền hiển thị kiểu `85.000 ₫`.

## Kiểm thử

Chỉ chạy các test ghi dữ liệu trên database phát triển đã sao lưu, khi không có người khác đồng thời sửa dữ liệu. Không chạy smoke test trên production. Không có test nào được xem là PASS khi thiếu kết nối.

```powershell
php vendor/phpunit/phpunit/phpunit --no-progress
php tests/phase5_smoke.php
php tests/phase6_smoke.php
php tests/money_smoke.php
php tests/password_input_smoke.php
php tests/phase2_smoke.php
php tests/phase3_smoke.php
php tests/phase4_smoke.php
php tests/logout_smoke.php
php tests/phase7_smoke.php
php bin/console lint:twig templates
php bin/console lint:yaml config
php bin/console lint:container
```

Kết quả đã chạy ngày 27/10/2026:

| Nhóm | Kết quả và phạm vi |
|---|---|
| PHPUnit | 38 test, 163 assertion; connection giả lập, không chứng minh hành vi SQL Server |
| phase5/phase6 | 49/47 kiểm tra dịch vụ giả lập: trạng thái, phân quyền, rollback và thứ tự khóa |
| money/password_input | 18 kiểm tra tiền; kiểm tra nhập mật khẩu terminal giả lập, không đọc/ghi DB |
| phase2 | 60 kiểm tra đăng ký, đăng nhập chung, xung đột tên Admin/User, hồ sơ, CSRF, đổi mật khẩu, throttling; SQL Server thật, rollback |
| phase3 | 32 kiểm tra catalog/Create và rollback; SQL Server thật, lỗi dòng thứ hai được tiêm qua mock chuyển tiếp tới DB thật |
| phase4 | 53 kiểm tra: 50 kiểm tra đọc/HTTP SQL Server thật, 3 kiểm tra Twig độc lập về NULL/XSS với hàm CSRF giả lập |
| logout | 46 kiểm tra session/CSRF qua HTTP kernel, tài khoản nạp từ SQL Server thật; không ghi DB |
| phase7 | 31 kiểm tra CRUD/duyệt/từ chối/hủy/xóa, chống ID quá lớn, trừ kho đúng một lần, rollback sau trừ kho dòng đầu; SQL Server thật |

phase2/phase3/phase7 dùng transaction ngoài và rollback; phase7 so sánh fingerprint cả sáu bảng nghiệp vụ cùng bảng version migration trước/sau. Không xóa giỏ cũ; thao tác xóa chỉ dùng giỏ tạm tạo trong test. phase2 có thể thử migration username trong transaction nếu thiếu cột; vì vậy luôn kiểm tra trạng thái migration trước khi chạy. Trong lần kiểm thử này schema đã đầy đủ.

Đã kiểm tra Chrome headless ở 390/768/1440px với snapshot HTML thật của catalog, chi tiết, sửa và Admin: CSS được tải, không tràn ngang toàn trang; bảng chi tiết cuộn ngang trong vùng riêng. Snapshot dùng trang render từ SQL Server trong test rollback, không phải dữ liệu thay thế. Chưa thao tác toàn bộ biểu mẫu trực tiếp trong trình duyệt thật. KernelBrowser kiểm tra HTTP trong tiến trình PHP. Kiểm thử SQL hiện chạy tuần tự; chưa kiểm thử tải nhiều kết nối cùng duyệt/sửa/hủy và chưa xác minh cài đặt trên một máy khác.

## Khi gặp lỗi

- `could not find driver`: đối chiếu `php --ini`, `php --ri pdo_sqlsrv`; khởi động lại server sau khi bật DLL.
- Không kết nối SQL: kiểm tra instance, port/TCP, Windows user chạy PHP, chứng chỉ và DATABASE_URL; không sửa bằng cách tạo lại database.
- Thiếu username/lý do từ chối: xem trạng thái và schema của hai migration, thực hiện theo mục migration.
- CSS/JS lỗi: chạy `importmap:install`, kiểm tra `/assets/…` trả đúng CSS/JS và server dùng `public/index.php`.
- Đăng nhập thất bại: dùng username hiện tại; kiểm tra hash bằng lệnh chỉ đọc ở trên, không in mật khẩu ra log.