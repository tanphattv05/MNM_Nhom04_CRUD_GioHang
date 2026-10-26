# Giai đoạn 2 – tài khoản và phân quyền

Đã triển khai trực tiếp trên dự án hiện có. Giữ HomeController, cấu hình kết nối Windows Authentication và database BanSachDB. Tham khảo cơ chế Security Symfony 7.4: https://symfony.com/doc/7.4/security.html.

## Cấu trúc thực tế

Trước triển khai, chỉ có HomeController và GioHangController, chưa có Entity/Repository tài khoản; Security dùng provider bộ nhớ rỗng. Dùng Doctrine DBAL với provider tùy chỉnh để làm việc trực tiếp với các bảng cũ, không tạo Entity hay tự đồng bộ schema.

Đã kiểm tra trực tiếp SQL Server ACER qua Doctrine ngày 26/10/2026:

- THANH_VIEN: mã INT không IDENTITY; họ tên NVARCHAR(120), email VARCHAR(150), điện thoại VARCHAR(20), địa chỉ NVARCHAR(255), ảnh VARCHAR(255), mật khẩu VARCHAR(255).
- QTV: username VARCHAR(60), họ tên NVARCHAR(120), email VARCHAR(150), mật khẩu VARCHAR(255).
- Các khóa ngoại liên quan: FK_MUA_MUA_THANH_VI, FK_GIO_HANG_THANH_VIEN, FK_GIO_HANG_DUYET_QTV.
- Ban đầu có 5 thành viên, 1 Admin, 9 giỏ hàng, 17 dòng MUA; không có nhóm email trùng sau chuẩn hóa.
- 5 thành viên và 1 Admin cũ đều chưa có hash bcrypt/Argon2. Không thay đổi các mật khẩu này khi triển khai.

Không có thay đổi schema hay migration cần chạy. Đăng ký cấp mã INT bằng MAX+1 trong transaction với TABLOCKX, HOLDLOCK; khóa độc quyền bảng bao trùm cả kiểm tra email và INSERT. Vì vậy các đăng ký qua ứng dụng được tuần tự hóa, tránh trùng email/mã khi đồng thời. Không dùng MAX+1 ngoài khóa. Các công cụ ghi dữ liệu ngoài ứng dụng cũng cần tuân thủ chuẩn hóa và tính duy nhất email.

`phase2_auth_checks.sql` là script kiểm tra chỉ đọc. Không chạy lại `BansachDB_Full_CoDuLieu.sql` hay `setup.ps1` để triển khai giai đoạn 2.

## Chạy tại máy hiện tại

```powershell
Set-Location D:\MNM_Nhom04_CRUD_GioHang
composer install
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public
```

Nếu server cũ đang chạy cổng 8000 thì giữ server đó. Mở http://127.0.0.1:8000/home. Các trang chính:

| Đường dẫn | Chức năng |
|---|---|
| /dang-ky | Đăng ký khách hàng |
| /dang-nhap | Đăng nhập bằng email |
| /trang-chu | Trang khách hàng ROLE_USER |
| /tai-khoan | Xem mã, email; sửa tên, điện thoại, địa chỉ |
| /tai-khoan/doi-mat-khau | Xác nhận mật khẩu cũ; đổi và đăng nhập lại |
| /admin/dang-nhap | Đăng nhập từ QTV |
| /admin/dashboard | Dashboard ROLE_ADMIN |
| /gio-hang, /danh-muc | Placeholder khách hàng |
| /admin/gio-hang | Placeholder quản trị |

Đăng xuất qua nút POST có CSRF, vô hiệu hóa session. Không bật Remember Me. Giới hạn thử đăng nhập: 5 lần/phút cho định danh/IP, kèm giới hạn IP mặc định của Symfony. Vai trò không kế thừa nhau. Provider nạp lại tài khoản theo mã; cập nhật hồ sơ lấy mã từ phiên đăng nhập, không lấy mã khách hàng từ biểu mẫu. Trang đăng nhập chung dùng thông báo lỗi tổng quát. Khách hàng/Admin được xác thực từ hai bảng riêng.

Session cookie có HttpOnly, SameSite=Lax, Secure tự động khi HTTPS. Dùng HTTPS và APP_ENV=prod, APP_DEBUG=0 khi triển khai thật; cấu hình APP_SECRET riêng ngoài mã nguồn. Đã tắt ghi truy vấn/profiler để tránh lưu tham số mật khẩu/hash. Không thay đổi cấu hình kết nối đang hoạt động.

## Mật khẩu tài khoản cũ

Tài khoản cũ có mật khẩu văn bản sẽ bị từ chối đăng nhập cho đến khi chuyển đổi. Tài khoản đăng ký mới luôn được hash bằng Symfony PasswordHasher.

Trước khi chuyển đổi, sao lưu trong SSMS: chuột phải BanSachDB → Tasks → Back Up → Full → chọn đường dẫn riêng → Backup, sau đó kiểm tra bản sao lưu. Chỉ thao tác với tài khoản demo đã xác nhận, không chuyển đổi hàng loạt tài khoản thật.

Lệnh dưới đây chỉ kiểm tra, chưa ghi gì:

```powershell
php bin/console app:account:password user 1001
php bin/console app:account:password admin admin
```

Sau sao lưu, người vận hành có thể chủ động chuyển mật khẩu hiện tại của đúng một tài khoản sang hash:

```powershell
php bin/console app:account:password user 1001 --convert-legacy --apply
php bin/console app:account:password admin admin --convert-legacy --apply
```

Lệnh bỏ qua nếu đã hash, không in mật khẩu. Thay ID bằng tài khoản demo đã kiểm tra. Hoặc đặt lại mật khẩu bằng nhập ẩn, không truyền mật khẩu trên dòng lệnh:

```powershell
php bin/console app:account:password user 1001 --apply
```

Lệnh chỉ chạy ở môi trường dev. Chưa tự thực hiện bất kỳ lệnh chuyển đổi nào trên dữ liệu cũ. Không nhúng tài khoản/mật khẩu demo vào Controller hay Twig.

## Kết quả kiểm thử thực tế

Chạy `php tests/phase2_smoke.php`: **34 kiểm tra đạt**, qua KernelBrowser và SQL Server thật. Script tạo tài khoản tạm trong transaction rồi rollback; so sánh SHA-256 toàn bộ dữ liệu từng bảng trước/sau. Không tạo dữ liệu giả thay thế database. Không cần chạy SQL khởi tạo. Chỉ chạy script này với cấu hình database phát triển; không chạy đồng thời với người khác đang sửa dữ liệu vì phép so sánh bao gồm toàn bộ bảng.

| Yêu cầu | Kết quả |
|---|---|
| Đăng ký hợp lệ, trùng email, email sai, xác nhận sai | Đạt |
| Mật khẩu ngắn, CSRF đăng ký và đăng nhập sai | Đạt |
| Khách hàng đăng nhập đúng/sai, đăng xuất | Đạt |
| Khách hàng vào Admin bị từ chối | Đạt, HTTP 403 |
| Admin đăng nhập đúng/sai, Dashboard, đăng xuất | Đạt |
| Admin vào hồ sơ khách hàng bị từ chối | Đạt, HTTP 403 |
| Khách chưa đăng nhập vào khu vực bảo vệ | Đạt, chuyển đúng trang đăng nhập |
| Sửa thông tin hồ sơ | Đạt, lưu dữ liệu Unicode trên SQL Server |
| Đổi mật khẩu với mật khẩu cũ đúng/sai | Đạt; phiên bị vô hiệu sau đổi thành công |
| Mật khẩu đăng ký/đổi được hash trong SQL Server | Đạt |
| Không dùng mật khẩu văn bản để đăng nhập | Đạt |
| Chuyển đổi mật khẩu mẫu, không hash lặp | Đạt trên tài khoản kiểm thử được rollback |
| CSRF đăng xuất và giới hạn đăng nhập sai | Đạt |
| Toàn bộ tài khoản/dữ liệu cũ sáu bảng giữ nguyên | Đạt, so sánh fingerprint toàn bộ hàng |
| Desktop/tablet/mobile bằng trình duyệt thật | **Chưa kiểm thử trực quan**; đã có CSS responsive ở 850px và 600px |

Đã chạy thành công `lint:twig templates`, `lint:container`, `debug:router`, `doctrine:query:sql "SELECT DB_NAME() AS database_name"`, PHP lint toàn bộ src và script kiểm thử, `git diff --check`. Kết nối SQL trong sandbox bị lỗi mã hóa; chạy ngoài sandbox bằng Windows Authentication đã thành công, không sửa DATABASE_URL.

Chưa kiểm thử tải đăng ký đồng thời bằng nhiều kết nối; cơ chế khóa đã được kiểm tra trong mã và chạy thực tế trong luồng đăng ký. KernelBrowser kiểm thử HTTP phía server, không chạy JavaScript và không đánh giá bố cục. Cần mở trình duyệt thật ở các kích thước 1440px, 768px, 390px để kiểm tra giao diện và nút hiện/ẩn mật khẩu.

Không triển khai CRUD giỏ hàng/chọn sách/duyệt ở giai đoạn này. Route `/gio-hang/new` có sẵn được bảo vệ ROLE_USER và tạm trả 404 trước khi chạy mã cũ; mã cũ được giữ để tránh xóa thay đổi đang làm của người dùng.

## File tạo và chỉnh sửa

Tạo:

- src/Security/Account.php, AccountProvider.php, LoginAuthenticator.php.
- src/Repository/AccountRepository.php.
- src/Form/AccountType.php, PasswordType.php.
- src/Controller/AccountController.php.
- src/Command/AccountPasswordCommand.php.
- templates/account/layout.html.twig, login.html.twig, form.html.twig, dashboard.html.twig.
- public/css/account.css, public/js/account.js.
- database/phase2_auth_checks.sql, database/PHASE2_AUTH.md.
- tests/phase2_smoke.php.

Chỉnh sửa:

- composer.json và composer.lock: thêm symfony/rate-limiter.
- config/packages/security.yaml, csrf.yaml, framework.yaml: xác thực, phân quyền, session và CSRF.
- config/packages/doctrine.yaml, web_profiler.yaml: tắt thu thập truy vấn/request nhạy cảm.
- config/packages/translation.yaml: ngôn ngữ mặc định tiếng Việt.
- config/reference.php: Symfony tự sinh lại tham chiếu cấu hình RateLimiter.
- templates/home/index.html.twig: trang chào và liên kết tài khoản, giữ HomeController.
- src/Controller/GioHangController.php: chặn nghiệp vụ giai đoạn sau.

File SQL khởi tạo và template giỏ hàng đã có thay đổi trước phiên làm việc này; không ghi đè hay chạy lại. Không sửa .env, không tạo dự án mới, không xuất ZIP.
