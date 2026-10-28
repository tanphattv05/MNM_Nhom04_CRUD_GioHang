# CRUD Giỏ hàng – Symfony MVC

Đồ án của **Nhóm 04 – Lớp DA23TTA** triển khai bốn thao tác **Create, Read, Update, Delete** cho giỏ hàng bằng Symfony MVC và Microsoft SQL Server. Đối tượng quản lý chính là bảng `GIO_HANG`; các dòng sách và số lượng của từng giỏ được lưu trong bảng `MUA`.

**Công nghệ:** PHP ≥ 8.2 · Symfony 7.4 · Twig · AssetMapper · Doctrine DBAL · Microsoft SQL Server.

**Nhóm:** 04 · **Lớp:** DA23TTA · **Trưởng nhóm:** Huỳnh Dương Tấn Phát.

**Học phần:** Phát triển ứng dụng web với mã nguồn mở · **Đơn vị:** Trường Kỹ thuật và Công nghệ – Đại học Trà Vinh.

**Mã nguồn:** [MNM_Nhom04_CRUD_GioHang trên GitHub](https://github.com/tanphattv05/MNM_Nhom04_CRUD_GioHang).

- [Cài đặt và chạy demo](#08-cài-đặt-và-chạy-dự-án)
- [Kịch bản thuyết trình trước hội đồng](docs/PRESENTATION.md)
- [Báo cáo kỹ thuật Cart](docs/CART_REPORT.md)
- [Test case và kết quả thực tế](docs/test-cases.md)
- [Hướng dẫn database](database/README_DATABASE.md)
- [Nội dung báo cáo: thiết kế bảng và cấu hình database (1.1–1.3)](docs/DATABASE_REPORT.md)

## 01. Giới thiệu dự án và phạm vi

Module quản lý quá trình tạo giỏ, xem danh sách và chi tiết, cập nhật nội dung, hủy và xóa giỏ theo trạng thái. Khách hàng thao tác trên giỏ của mình; quản trị viên xem và xử lý các giỏ theo quyền được cấp.

Giỏ đang soạn được lưu trong Symfony Session. Khi người dùng gửi giỏ, ứng dụng tạo bản ghi `GIO_HANG` ở trạng thái **Chờ duyệt** và lưu các dòng tương ứng vào `MUA` trong cùng transaction.

### Thành viên

**Nhóm 04 – Lớp DA23TTA**

| STT | Họ và tên | MSSV | Vai trò | Nhiệm vụ phân công |
|---|---|---|---|---|
| 1 | Huỳnh Dương Tấn Phát | 110123038 | Trưởng nhóm | Xây dựng chức năng tạo giỏ hàng (Create) |
| 2 | Trần Hoàng Oanh | 110123037 | Thành viên | Danh sách, tìm kiếm và chi tiết giỏ hàng (Read) |
| 3 | Cao Á Châu | 110123206 | Thành viên | Chuẩn bị và cấu hình cơ sở dữ liệu SQL Server |
| 4 | Trần Vũ Ngọc Huỳnh | 110123012 | Thành viên | Hủy và xóa giỏ hàng (Delete) |
| 5 | Võ Thị Kiều Nhi | 110123033 | Thành viên | Cập nhật, duyệt và từ chối giỏ hàng (Update) |
| 6 | Nguyễn An Ninh | 110123036 | Thành viên | Hoàn thiện giao diện và kiểm thử CRUD giỏ hàng |
| 7 | Phạm Bá Hạo Long | 110123024 | Thư ký | Biên soạn và hoàn thiện tài liệu hướng dẫn kỹ thuật Hands-on |

### Các thao tác CRUD giỏ hàng

| Thao tác | Nội dung |
|---|---|
| **Create – Tạo** | Thêm các sách đã có vào giỏ nháp, nhập số lượng và ghi chú; gửi giỏ để lưu vào `GIO_HANG` và `MUA` |
| **Read – Xem** | Xem giỏ nháp, danh sách và chi tiết giỏ đã gửi; lọc trạng thái, phân trang; quản trị viên tìm giỏ theo mã hoặc thông tin chủ giỏ |
| **Update – Cập nhật** | Thay đổi số lượng, thêm hoặc bỏ dòng sách, sửa ghi chú của giỏ Chờ duyệt; quản trị viên duyệt hoặc từ chối giỏ |
| **Delete – Xóa** | Bỏ dòng khỏi giỏ nháp; khách hàng hủy giỏ Chờ duyệt; quản trị viên xóa giỏ Đã hủy hoặc Từ chối cùng các dòng `MUA` |

## 02. Kiến trúc và sơ đồ

Giỏ nháp dùng Symfony Session; giỏ đã gửi dùng `GIO_HANG` và `MUA` trên SQL Server. Nhấn vào ảnh để xem sơ đồ ở kích thước đầy đủ.

| Sơ đồ | Hình ảnh |
|---|---|
| [Kiến trúc ứng dụng](docs/diagrams/architecture.md) — giao diện, Controller, Service và tầng dữ liệu | [![Kiến trúc ứng dụng](docs/diagrams/architecture.png)](docs/diagrams/architecture.png) |
| [Sequence](docs/diagrams/cart-sequence.md) — luồng tạo và lưu giỏ hàng | [![Sequence xử lý giỏ hàng](docs/diagrams/cart-sequence.png)](docs/diagrams/cart-sequence.png) |
| [ERD](docs/diagrams/database-erd.md) — mô hình thiết kế do nhóm cung cấp | [![ERD gốc của nhóm](docs/diagrams/database-erd-original.png)](docs/diagrams/database-erd-original.png) |

Phân tích chi tiết: [báo cáo Cart](docs/CART_REPORT.md). Sơ đồ vật lý để đối chiếu với database SQL Server: [PNG](docs/diagrams/database-erd.png), [SVG](docs/diagrams/database-erd.svg), [PDF](docs/diagrams/database-erd.pdf).

Thuyết minh cách triển khai: [Symfony và kiến trúc MVC trong module CRUD giỏ hàng](docs/SYMFONY_MVC.md).

## 03. Dữ liệu và cấu trúc repository

| Nơi lưu | Vai trò |
|---|---|
| Session `draft_cart_<id>` | `{items: [bookId => quantity], nonce}`; không lưu giá từ trình duyệt |
| `GIO_HANG` | Chủ giỏ, thời gian, trạng thái, ghi chú, Admin và lý do từ chối |
| `MUA` | Chi tiết sách/số lượng, khóa ghép thành viên–sách–giỏ |

Các khóa ngoại tham chiếu `THANH_VIEN`, `QTV` và `SACH` để xác định chủ giỏ, người xử lý và sách trong từng dòng giỏ hàng.

**Cấu trúc bảng GIO_HANG:**

| Cột | Kiểu dữ liệu | Vai trò |
|---|---|---|
| `GIO_HANG_ID_GIO` | INT, NOT NULL | Khóa chính |
| `THANH_VIEN_TEN_DANG_NHAP` | VARCHAR(10), NULL | Khóa ngoại đến thành viên |
| `QTV_USER_ADMIN` | VARCHAR(60), NULL | Khóa ngoại đến quản trị viên |
| `GIO_HANG_THOI_GIAN` | DATETIME, NULL | Thời điểm tạo |
| `GIO_HANG_TRANG_THAI` | NVARCHAR(30), NULL | Trạng thái xử lý |
| `GIO_HANG_GHI_CHU` | NVARCHAR(255), NULL | Ghi chú của giỏ hàng |
| `GIO_HANG_LY_DO_TU_CHOI` | NVARCHAR(255), NULL | Lý do từ chối riêng |

Các tệp và thư mục chính của module:

| Vị trí | Trách nhiệm |
|---|---|
| `src/Controller/GioHangController.php` | Giỏ nháp, thêm/sửa/bỏ dòng và gửi giỏ |
| `src/Controller/CartReadController.php` | Danh sách và chi tiết giỏ |
| `src/Controller/CartEditController.php` | Cập nhật giỏ đã gửi |
| `src/Controller/CartReviewController.php` | Duyệt hoặc từ chối giỏ |
| `src/Controller/CartDeleteController.php` | Hủy và xóa giỏ |
| `src/Service/CartService.php` | Kiểm tra dữ liệu, tính tổng, chuyển trạng thái và transaction |
| `src/Repository/CartRepository.php` | Truy vấn danh sách, chi tiết và tổng tiền giỏ |
| `templates/gio_hang/` | Giao diện thao tác giỏ hàng bằng Twig |
| `migrations/`, `database/` | Migration, tệp tạo CSDL và hướng dẫn kết nối |
| `tests/`, `scripts/check.ps1` | Kiểm thử và chạy các bước kiểm tra |

Ứng dụng hiện truy vấn bằng **Doctrine DBAL, không dùng Entity ORM cho nghiệp vụ**. Thư mục trùng tên `MNM_Nhom04_CRUD_GioHang/` bên trong workspace là một checkout riêng; ứng dụng và các lệnh trong tài liệu này sử dụng thư mục gốc chứa README.

## 04. Triển khai Symfony và quy tắc nghiệp vụ

Controller nhận yêu cầu và trả giao diện Twig; `CartService` xử lý nghiệp vụ và các thao tác ghi; `CartRepository` truy vấn danh sách, chi tiết và tổng tiền. Các truy vấn sử dụng tham số qua Doctrine DBAL.

- **Tạo giỏ:** số lượng phải là số nguyên dương và không vượt tồn kho. Thêm trùng sách sẽ cộng số lượng. Giá và tổng tiền được tính từ dữ liệu phía server.
- **Gửi giỏ:** kiểm tra token CSRF và nonce, lưu `GIO_HANG` cùng các dòng `MUA` trong một transaction, sau đó xóa giỏ nháp khi commit thành công.
- **Xem và sửa:** khách hàng chỉ được truy cập giỏ của mình. Giỏ đã gửi chỉ được sửa khi ở trạng thái **Chờ duyệt**.
- **Duyệt hoặc từ chối:** quản trị viên duyệt mới trừ tồn kho; duyệt lại bị chặn. Từ chối phải nhập lý do và không trừ kho.
- **Hủy và xóa:** hủy giỏ Chờ duyệt chỉ chuyển trạng thái, giữ lịch sử. Xóa giỏ Đã hủy hoặc Từ chối sẽ xóa các dòng `MUA` trước rồi xóa `GIO_HANG` trong cùng transaction.
- **Xử lý lỗi:** dữ liệu không hợp lệ được báo lỗi; thao tác ghi thất bại được rollback để tránh lưu giỏ thiếu dòng hoặc cập nhật tồn kho dở dang.

## 05. Routes CRUD giỏ hàng

| Method | Đường dẫn | Chức năng/quyền |
|---|---|---|
| GET | `/gio-hang` | Xem giỏ tạm |
| POST | `/gio-hang/thay-doi` | `book_id`, `action=add/update/remove`, `quantity`, `_token` |
| POST | `/gio-hang/gui` | Gửi nhanh: `nonce`, `note`, `_token` |
| GET | `/gio-hang/lich-su`, `/gio-hang/lich-su/{id}` | Danh sách/chi tiết giỏ của chính mình |
| GET/POST | `/gio-hang/lich-su/{id}/sua` | Sửa giỏ Chờ duyệt |
| POST | `/gio-hang/lich-su/{id}/huy` | Khách hàng hủy giỏ Chờ duyệt |
| GET | `/admin/gio-hang`, `/admin/gio-hang/{id}` | Danh sách, tìm kiếm và chi tiết giỏ cho Admin |
| POST | `/admin/gio-hang/{id}/approve`, `…/reject`, `…/xoa` | Duyệt/từ chối/xóa theo trạng thái |

Đây là ứng dụng HTML/Twig, chưa có REST API. Chi tiết payload, redirect và mã lỗi nằm trong [báo cáo kỹ thuật](docs/CART_REPORT.md#06-web-routes-và-kết-quả-trả-về).

## 06. Kiểm thử và bằng chứng

```powershell
# PHPUnit, lint và kiểm tra dịch vụ không kết nối database
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check.ps1

# Thêm integration test SQL Server; dùng database phát triển, chạy tuần tự
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check.ps1 -WithDatabase
```

Nội dung kiểm thử liên quan đến CRUD giỏ hàng gồm tạo giỏ và dòng `MUA`, tính tổng tiền, cập nhật số lượng, kiểm tra quyền sở hữu, chuyển trạng thái, hủy/xóa và rollback khi ghi dữ liệu thất bại.

PHPUnit sử dụng connection giả lập; nhóm integration kiểm tra SQL Server thật, tạo dữ liệu tạm trong transaction rồi rollback. Chạy nhóm có ghi dữ liệu trên database phát triển và chạy tuần tự. Phạm vi, kết quả từng đợt và log được lưu tại [test-cases.md](docs/test-cases.md).

## 07. Demo CRUD giỏ hàng

Chuẩn bị dữ liệu liên kết có sẵn và phiên làm việc có quyền `ROLE_USER` hoặc `ROLE_ADMIN` tương ứng với thao tác.

1. **Create:** thêm sách có sẵn vào giỏ nháp, nhập số lượng và ghi chú rồi gửi giỏ. Kiểm tra bản ghi mới trong `GIO_HANG` và các dòng `MUA`.
2. **Read:** mở `/gio-hang/lich-su`, lọc trạng thái và xem chi tiết giỏ vừa tạo.
3. **Update:** sửa số lượng hoặc ghi chú của giỏ Chờ duyệt; kiểm tra lại các dòng và tổng tiền sau khi lưu.
4. **Delete:** hủy giỏ Chờ duyệt, sau đó dùng quyền Admin xóa giỏ Đã hủy; kiểm tra header và các dòng liên quan đã được xóa.
5. **Kiểm tra trạng thái:** với một giỏ Chờ duyệt khác, thử duyệt hoặc từ chối và kiểm tra các thao tác sửa/xóa bị giới hạn theo trạng thái.

## 08. Cài đặt và chạy dự án

| Thành phần | Yêu cầu | Phiên bản đã chạy trên máy hiện tại |
|---|---|---|
| PHP | ≥ 8.2 | 8.4.26 x64 TS |
| Composer | 2.x | 2.10.3 |
| Symfony | 7.4.x theo `composer.lock` | 7.4.21 |
| Cơ sở dữ liệu | Microsoft SQL Server 2017+ | 17.0.1000.7, Standard Developer Edition |
| Driver | Microsoft ODBC Driver 18 và `pdo_sqlsrv` phù hợp PHP | `pdo_sqlsrv` 5.13.3 |

Bật `pdo_sqlsrv`, `ctype`, `iconv`; các extension còn lại được kiểm tra bằng Composer. DLL SQL Server phải khớp PHP, TS/NTS và x64/x86.

Thực hiện các bước dưới đây tại thư mục gốc chứa `composer.json` và `bin/console`.

### Bước 1. Kiểm tra PHP và cài thư viện

```powershell
php -v
php --ini
php --ri pdo_sqlsrv
composer install
composer check-platform-reqs
```

### Bước 2. Tạo cấu hình riêng cho máy

Nếu chưa có `.env.local`, tạo tệp này từ [.env.local.example](.env.local.example). Nếu tệp đã tồn tại, chỉnh các giá trị cần thiết trong tệp hiện có. Sinh secret bằng `php -r "echo bin2hex(random_bytes(32));"` rồi điền vào:

File `.env` chung giữ `APP_ENV`, `APP_SECRET` và `DATABASE_URL`. `APP_DEBUG` trong ví dụ dưới là tùy chọn.

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=thay_bang_secret_ngau_nhien_cua_may_ban
DATABASE_URL="mssql://localhost/BanSachDB"
```

`localhost` dùng instance mặc định và Windows Authentication của tài khoản chạy PHP. Named instance ví dụ `mssql://TEN_MAY%5CSQLEXPRESS/BanSachDB`; SQL Authentication dùng `mssql://username:password@HOST:1433/BanSachDB` và URL-encode ký tự đặc biệt.

### Bước 3. Tạo database bằng SSMS

1. Khởi động SQL Server, mở SSMS và kết nối bằng tài khoản có quyền tạo database.
2. Chọn **File → Open → File**, mở `database/BanSachDB_Full.sql`.
3. Nhấn **Execute (F5)** để chạy toàn bộ tệp; không tạo database trước khi chạy tệp này.
4. Nhấn **Refresh** trong Object Explorer, mở `Databases → BanSachDB → Tables` để kiểm tra.

Tệp tự tạo database `BanSachDB`, sáu bảng nghiệp vụ, các ràng buộc và bảng theo dõi migration. Kết quả cuối tệp là `business_tables = 6`, `baselined_migrations = 4`. Database mới chưa có dữ liệu sách hoặc tài khoản.

Nếu muốn tự chạy `CREATE DATABASE` và `USE` trong SSMS, chọn quy trình thủ công rồi chạy `database/BanSachDB_SchemaOnly.sql` để tạo bảng. Hai cách cài mới được mô tả đầy đủ tại [mục 1.2 của báo cáo](docs/DATABASE_REPORT.md#12-tạo-cơ-sở-dữ-liệu); chỉ chọn một cách.

Nếu `BanSachDB` đã tồn tại và có dữ liệu, bỏ qua bước tạo mới và xem [hướng dẫn nâng cấp](database/README_DATABASE.md#b-nâng-cấp-database-đã-có-dữ-liệu). File `BansachDB_Full_CoDuLieu.sql` là dữ liệu/schema lịch sử, có lệnh xóa/tạo lại bảng; không dùng để nâng cấp database đang có dữ liệu.

### Bước 4. Kiểm tra kết nối và migration

```powershell
php bin/console doctrine:query:sql "SELECT DB_NAME() AS database_name, @@SERVERNAME AS server_name"
php bin/console doctrine:migrations:status
```

Kết nối đúng trả về `database_name = BanSachDB`. Sau khi cài mới bằng một trong hai tệp SQL hiện tại, trạng thái mong đợi là **Executed=4, Available=4, New=0**. Script đã ghi nhận đủ bốn migration nên khi `New=0`, chuyển sang bước 5.

Nếu còn migration mới trên database cũ, sao lưu và kiểm tra bản sao lưu trước, sau đó đối chiếu SQL dự kiến rồi áp dụng những migration còn thiếu:

```powershell
php bin/console doctrine:migrations:list
php bin/console doctrine:migrations:migrate --dry-run
# Chỉ áp dụng migration còn thiếu sau khi đối chiếu schema và sao lưu
php bin/console doctrine:migrations:migrate
```

### Bước 5. Chạy module giỏ hàng

```powershell
php bin/console importmap:install
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Mở **http://127.0.0.1:8000/gio-hang** với phiên làm việc có quyền khách hàng hoặc **http://127.0.0.1:8000/admin/gio-hang** với quyền quản trị viên. Giữ terminal chạy server; `Ctrl+C` để dừng. Không cần Node.js/npm.

Trước khi thực hiện CRUD, cơ sở dữ liệu cần có dữ liệu thành viên, quản trị viên và sách để các khóa ngoại của giỏ tham chiếu. Với database cài mới, chuẩn bị các dữ liệu liên kết này trước khi thực hiện kịch bản ở mục 07.

Khắc phục lỗi thường gặp:

- `could not find driver`: kiểm tra PHP terminal/server dùng cùng `php.ini` và bật `pdo_sqlsrv`.
- Không kết nối: kiểm tra SQL Server service, instance/TCP, Windows user và `DATABASE_URL`.
- Thiếu cột hoặc sai kiểu: đối chiếu đủ bốn migration; không import lại SQL mẫu vào database đang dùng.
- Cache sau khi chuyển thư mục: `php bin/console cache:clear`; nếu CLI debug bị kẹt, dừng tiến trình PHP đang chạy rồi dùng `php bin/console cache:clear --no-debug`.
- CSS/JS lỗi: chạy `importmap:install`, kiểm tra asset trả 200 và dùng đúng router `public/index.php`.

## 09. Kết quả, hạn chế và hướng phát triển

Module triển khai các thao tác tạo, xem, cập nhật và xóa giỏ hàng, có kiểm tra dữ liệu, quyền sở hữu và trạng thái xử lý. Việc lưu header và chi tiết giỏ trong cùng transaction giúp duy trì tính nhất quán khi thao tác thành công hoặc thất bại.

Hạn chế và hướng phát triển trong phạm vi giỏ hàng:

1. Lưu đơn giá tại thời điểm tạo giỏ trên từng dòng `MUA`; hiện tổng tiền được tính theo giá sách hiện tại.
2. Lưu giỏ nháp trong database để có thể khôi phục khi phiên làm việc hết hạn.
3. Bổ sung lịch sử thay đổi số lượng, ghi chú và trạng thái để dễ đối chiếu các lần cập nhật giỏ.
4. Kiểm thử tải và truy cập đồng thời; cải tiến cơ chế cấp mã `MAX+1`, khóa dữ liệu và truy vấn danh sách giỏ.
