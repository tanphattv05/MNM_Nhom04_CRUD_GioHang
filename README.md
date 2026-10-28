# Website bán sách – Symfony MVC · CRUD Giỏ hàng

Website bán sách phục vụ đồ án: khách hàng tìm sách, quản lý giỏ, đặt hàng COD và theo dõi lịch sử; quản trị viên duyệt, từ chối và xóa giỏ theo trạng thái. Tài liệu mô tả code tại **thư mục gốc của repository**.

**Công nghệ:** PHP ≥ 8.2 · Symfony 7.4 · Twig · AssetMapper · Doctrine DBAL · Microsoft SQL Server.

**Nhóm:** 04 · **Lớp:** DA23TTA · **Trưởng nhóm:** Huỳnh Dương Tấn Phát.

**Học phần:** Phát triển ứng dụng web với mã nguồn mở · **Đơn vị:** Trường Kỹ thuật và Công nghệ – Đại học Trà Vinh.

**Mã nguồn:** [MNM_Nhom04_CRUD_GioHang trên GitHub](https://github.com/tanphattv05/MNM_Nhom04_CRUD_GioHang).

- [Cài đặt và chạy demo](#08-cài-đặt-và-chạy-dự-án)
- [Báo cáo kỹ thuật Cart](docs/CART_REPORT.md)
- [Test case và kết quả thực tế](docs/test-cases.md)
- [Hướng dẫn database](database/README_DATABASE.md)
- [Nội dung báo cáo: thiết kế bảng và cấu hình database (1.1–1.3)](docs/DATABASE_REPORT.md)

## 01. Giới thiệu dự án và phạm vi

Module chính là **CRUD giỏ hàng**, có đăng ký/đăng nhập bằng **username**, hồ sơ cá nhân, danh mục sách và khu vực Admin để demo nghiệp vụ đầy đủ. Email chỉ là thông tin liên hệ. Việc xác nhận COD tạo giỏ **Chờ duyệt**, chưa thu tiền và chưa trừ kho.

### Thành viên

**Nhóm 04 – Lớp DA23TTA**

| STT | Họ và tên | MSSV | Vai trò | Nhiệm vụ phân công |
|------|---|---|---|---|
| 1 | Huỳnh Dương Tấn Phát | 110123038 | Trưởng nhóm | Đăng ký, đăng nhập, phân quyền và tạo giỏ hàng (Create) |
| 2 | Trần Hoàng Oanh | 110123037 | Thành viên | Danh sách, tìm kiếm và chi tiết giỏ hàng (Read) |
| 3 | Cao Á Châu | 110123206 | Thành viên | Chuẩn bị và cấu hình cơ sở dữ liệu SQL Server |
| 4 | Trần Vũ Ngọc Huỳnh | 110123012 | Thành viên | Hủy và xóa giỏ hàng (Delete) |
| 5 | Võ Thị Kiều Nhi | 110123033 | Thành viên | Cập nhật, duyệt và từ chối giỏ hàng (Update) |
| 6 | Nguyễn An Ninh | 110123036 | Thành viên | Hoàn thiện giao diện và kiểm thử hệ thống |
| 7 | Phạm Bá Hạo Long | 110123024 | Thư ký | Biên soạn và hoàn thiện tài liệu hướng dẫn kỹ thuật Hands-on |

### Chức năng chính

- Đăng ký, đăng nhập bằng username, cập nhật hồ sơ và đổi mật khẩu.
- Xem danh mục sách; tìm theo tên/tác giả, lọc thể loại/giá và sắp xếp.
- CRUD giỏ hàng: xem, thêm sách, sửa số lượng, bỏ sách; kiểm tra tồn kho và tính tổng tiền.
- Xác nhận đặt hàng COD, xem lịch sử, sửa/hủy giỏ Chờ duyệt.
- Admin tìm kiếm, duyệt, từ chối và xóa giỏ theo trạng thái.

Phạm vi CRUD của đồ án là **giỏ hàng**. Repo hiện chưa có màn hình quản trị thêm/sửa/xóa sách.

## 02. Kiến trúc và sơ đồ

Giỏ nháp dùng Symfony Session; giỏ đã gửi dùng `GIO_HANG` và `MUA` trên SQL Server. Nhấn vào ảnh để xem sơ đồ ở kích thước đầy đủ.

| Sơ đồ | Hình ảnh |
|---|---|
| [Kiến trúc ứng dụng](docs/diagrams/architecture.md) — giao diện, Controller, Service và tầng dữ liệu | [![Kiến trúc ứng dụng](docs/diagrams/architecture.png)](docs/diagrams/architecture.png) |
| [Use Case](docs/diagrams/use-case.md) — chức năng của khách hàng và quản trị viên | [![Use Case module giỏ hàng](docs/diagrams/use-case.png)](docs/diagrams/use-case.png) |
| [Sequence](docs/diagrams/cart-sequence.md) — luồng thêm sách và xác nhận đặt hàng | [![Sequence thêm sách và đặt hàng](docs/diagrams/cart-sequence.png)](docs/diagrams/cart-sequence.png) |
| [ERD](docs/diagrams/database-erd.md) — mô hình thiết kế do nhóm cung cấp | [![ERD gốc của nhóm](docs/diagrams/database-erd-original.png)](docs/diagrams/database-erd-original.png) |

Phân tích chi tiết: [báo cáo Cart](docs/CART_REPORT.md). Sơ đồ vật lý để đối chiếu với database SQL Server: [PNG](docs/diagrams/database-erd.png), [SVG](docs/diagrams/database-erd.svg), [PDF](docs/diagrams/database-erd.pdf).

## 03. Dữ liệu và cấu trúc repository

| Nơi lưu | Vai trò |
|---|---|
| Session `draft_cart_<id>` | `{items: [bookId => quantity], nonce}`; không lưu giá từ trình duyệt |
| `THANH_VIEN`, `QTV` | Khách hàng và quản trị viên; mã thành viên VARCHAR(10), điện thoại VARCHAR(10), mật khẩu đã hash |
| `LOAI_SACH`, `SACH` | Loại sách, giá `NUMERIC(12,2)`, tồn kho |
| `GIO_HANG` | Chủ giỏ, thời gian, trạng thái, ghi chú, Admin và lý do từ chối |
| `MUA` | Chi tiết sách/số lượng, khóa ghép thành viên–sách–giỏ |

**Cấu trúc GIO_HANG sau migration mới:**

| Cột | Kiểu dữ liệu | Vai trò |
|---|---|---|
| `GIO_HANG_ID_GIO` | INT, NOT NULL | Khóa chính |
| `THANH_VIEN_TEN_DANG_NHAP` | VARCHAR(10), NULL | Khóa ngoại đến thành viên |
| `QTV_USER_ADMIN` | VARCHAR(60), NULL | Khóa ngoại đến quản trị viên |
| `GIO_HANG_THOI_GIAN` | DATETIME, NULL | Thời điểm tạo |
| `GIO_HANG_TRANG_THAI` | NVARCHAR(30), NULL | Trạng thái xử lý |
| `GIO_HANG_GHI_CHU` | NVARCHAR(255), NULL | Ghi chú/thông tin nhận hàng |
| `GIO_HANG_LY_DO_TU_CHOI` | NVARCHAR(255), NULL | Lý do từ chối riêng |

```text
├── src/
│   ├── Command/          # Kiểm tra/chuyển đổi mật khẩu tài khoản demo
│   ├── Controller/       # Tài khoản, catalog, tạo/đọc/sửa/duyệt/xóa giỏ
│   ├── Form/             # Form tài khoản và mật khẩu
│   ├── Repository/       # Truy vấn SQL có tham số qua DBAL
│   ├── Security/         # Provider, authenticator, logout
│   ├── Service/          # CartService, CheckoutService
│   └── Twig/             # Bộ lọc hiển thị tiền vnd
├── templates/            # Giao diện Twig
├── assets/               # CSS, JavaScript, Stimulus; không cần npm build
├── config/               # Symfony, Doctrine, Security, routes
├── migrations/           # 4 migration SQL Server
├── database/             # SQL cài mới, dữ liệu mẫu cũ, hướng dẫn
├── tests/                # PHPUnit và smoke test
├── scripts/check.ps1     # Chạy kiểm tra, lưu log vào var/checks
├── docs/                 # Báo cáo, sơ đồ, bằng chứng kiểm thử
└── public/index.php      # Điểm vào ứng dụng
```

Ứng dụng hiện truy vấn bằng **Doctrine DBAL, không dùng Entity ORM cho nghiệp vụ**. Thư mục trùng tên `MNM_Nhom04_CRUD_GioHang/` bên trong workspace là một checkout riêng; ứng dụng và các lệnh trong tài liệu này sử dụng thư mục gốc chứa README.

## 04. Triển khai Symfony và quy tắc nghiệp vụ

`GioHangController` xử lý giỏ tạm/checkout; các controller `CartRead`, `CartEdit`, `CartReview`, `CartDelete` chia theo trách nhiệm. `CartService` kiểm tra số lượng, tồn kho, nonce và chuyển trạng thái. `CheckoutService` kiểm tra người nhận, điện thoại, phương thức hỗ trợ và giới hạn lưu trữ.

Khi thêm sách từ danh mục hoặc hộp chi tiết bị lỗi, form đưa khách trở lại trang danh mục đang lọc và hiển thị thông báo; hộp chi tiết được mở lại khi có `cart_detail`. Thêm thành công vẫn chuyển đến giỏ hàng. URL quay lại chỉ được chấp nhận trong đường dẫn danh mục của ứng dụng.

Chỉ giỏ **Chờ duyệt** được sửa/hủy. Admin duyệt mới trừ kho trong transaction; duyệt lại bị chặn. Từ chối bắt buộc có lý do riêng và không trừ kho. Admin chỉ xóa vĩnh viễn giỏ **Đã hủy/Từ chối**, xóa dòng `MUA` cùng transaction. Khóa SQL Server và cập nhật có điều kiện bảo vệ dữ liệu khi trạng thái/tồn kho thay đổi.

Checkout hiện hỗ trợ **COD + giao tiêu chuẩn miễn phí**. Họ tên, điện thoại, địa chỉ và lời nhắn được ghép vào `GIO_HANG_GHI_CHU NVARCHAR(255)`: nếu vượt sức chứa sẽ báo lỗi và giữ dữ liệu nhập, không cắt mất địa chỉ. Có thể gửi nhanh giỏ với ghi chú, không qua form giao nhận.

## 05. Web routes chính

| Method | Đường dẫn | Chức năng/quyền |
|---|---|---|
| GET | `/`, `/home` | Trang giới thiệu công khai |
| GET/POST | `/dang-ky`, `/dang-nhap` | Đăng ký, đăng nhập username |
| POST | `/dang-xuat` | Đăng xuất, CSRF |
| GET | `/danh-muc` | Khách hàng: tìm/lọc/sắp xếp/phân trang |
| GET | `/gio-hang` | Xem giỏ tạm |
| POST | `/gio-hang/thay-doi` | `book_id`, `action=add/update/remove`, `quantity`, `_token` |
| GET/POST | `/gio-hang/thanh-toan` | Kiểm tra giỏ, thông tin nhận hàng, COD |
| POST | `/gio-hang/gui` | Gửi nhanh: `nonce`, `note`, `_token` |
| GET | `/gio-hang/lich-su`, `/gio-hang/lich-su/{id}` | Danh sách/chi tiết giỏ của chính mình |
| GET/POST | `/gio-hang/lich-su/{id}/sua` | Sửa giỏ Chờ duyệt |
| POST | `/gio-hang/lich-su/{id}/huy` | Khách hàng hủy giỏ Chờ duyệt |
| GET | `/admin/dashboard`, `/admin/gio-hang`, `/admin/gio-hang/{id}` | Dashboard, danh sách và chi tiết cho Admin |
| POST | `/admin/gio-hang/{id}/approve`, `…/reject`, `…/xoa` | Duyệt/từ chối/xóa theo trạng thái |
| GET/POST | `/tai-khoan`, `/tai-khoan/doi-mat-khau` | Hồ sơ, đổi mật khẩu |

Đây là ứng dụng HTML/Twig, chưa có REST API. Chi tiết payload, redirect và mã lỗi nằm trong [báo cáo kỹ thuật](docs/CART_REPORT.md#06-web-routes-và-kết-quả-trả-về).

## 06. Kiểm thử và bằng chứng

```powershell
# PHPUnit, lint và kiểm tra dịch vụ không kết nối database
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check.ps1

# Thêm integration test SQL Server; dùng database phát triển, chạy tuần tự
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check.ps1 -WithDatabase
```

Integration test tạo dữ liệu tạm trong transaction rồi rollback. `phase7_smoke` và `cart_checkout_smoke` đối chiếu fingerprint cả sáu bảng nghiệp vụ và bảng migration trước/sau. Không chạy nhóm ghi dữ liệu trên production hoặc khi có người khác đồng thời sửa database.

Kết quả thực tế ngày **28/10/2026**, môi trường PHP 8.4.26 / Symfony 7.4.21 / SQL Server: xem [bảng test case, kết quả và log](docs/test-cases.md). PHPUnit dùng connection giả lập; nhóm integration kiểm tra SQL Server thật. Không dùng kết quả cũ ngày 27/10 làm kết quả của đợt chạy này.

Kết quả mới nhất sau đồng bộ code: **79 test, 283 assertion** đạt, bao gồm thêm sách lỗi quay lại đúng bộ lọc/chi tiết và chặn URL chuyển hướng không hợp lệ; xem [log đồng bộ](docs/evidence/2026-10-28-pull-sync). Đợt trước sau migration VARCHAR(10)/điện thoại 10 chữ số đạt 72 test, 248 assertion; [log sau nâng cấp schema](docs/evidence/2026-10-28-member-schema) được giữ làm lịch sử.

## 07. Demo chức năng

1. Đăng ký tài khoản tại `/dang-ky`, đăng nhập bằng username.
2. Vào `/danh-muc`, tìm sách và thêm một cuốn hai lần để quan sát cộng số lượng.
3. Vào `/gio-hang`, sửa số lượng, bỏ sách; tải lại trang để kiểm tra giỏ còn được giữ.
4. Vào checkout, thử điện thoại không hợp lệ, sau đó điền người nhận hợp lệ và xác nhận COD.
5. Mở lịch sử, xem giỏ Chờ duyệt; thử sửa hoặc hủy.
6. Đăng nhập Admin riêng, duyệt và đối chiếu kho; thử duyệt lại hoặc từ chối một giỏ khác.

Ảnh và cách tạo lại nằm trong [hướng dẫn demo](docs/DEMO.md). Tài khoản/giỏ minh họa được tạo trong test và rollback; không thể đăng nhập tài khoản tạm sau khi test kết thúc.

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

File `.env` chung chỉ giữ `APP_ENV`, `APP_SECRET` và `DATABASE_URL`. `APP_DEBUG` trong ví dụ dưới là tùy chọn. Email và Messenger hiện được tắt vì nghiệp vụ chưa sử dụng; URL mặc định cho CLI nằm trong `config/packages/routing.yaml`.

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=thay_bang_secret_ngau_nhien_cua_may_ban
DATABASE_URL="mssql://localhost/BanSachDB"
```

`localhost` dùng instance mặc định và Windows Authentication của tài khoản chạy PHP. Named instance ví dụ `mssql://TEN_MAY%5CSQLEXPRESS/BanSachDB`; SQL Authentication dùng `mssql://username:password@HOST:1433/BanSachDB` và URL-encode ký tự đặc biệt. Cấu hình hiện tin cậy chứng chỉ local; production cần chứng chỉ tin cậy và tắt debug.

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

### Bước 5. Khởi động website

```powershell
php bin/console importmap:install
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Mở **http://127.0.0.1:8000/**. Giữ terminal chạy server; `Ctrl+C` để dừng. Không cần Node.js/npm. CSS/JS được phục vụ qua `/assets/…`.

### Bước 6. Tạo tài khoản và chuẩn bị dữ liệu

- **Khách hàng:** mở http://127.0.0.1:8000/dang-ky để đăng ký, sau đó đăng nhập tại `/dang-nhap`.
- **Quản trị viên:** làm theo [hướng dẫn tạo Admin cho database mới](database/README_DATABASE.md#tạo-tài-khoản-admin-cho-database-mới), rồi đăng nhập tại `/admin/dang-nhap` bằng mật khẩu đã tự đặt.
- **Sách:** nhập dữ liệu loại sách vào `LOAI_SACH` trước, sau đó nhập sách vào `SACH`. Catalog hiển thị rỗng khi chưa có dữ liệu; ứng dụng chưa có màn hình quản lý sách.

`THANH_VIEN_TEN_DANG_NHAP` là khóa định danh **VARCHAR(10)**, độc lập với username đăng nhập `THANH_VIEN_USERNAME VARCHAR(30)`. Mã mới dạng `tv` + 8 ký tự hex; đổi username không làm đổi chủ giỏ. Điện thoại bắt buộc nhập đúng 10 chữ số ở đăng ký/hồ sơ/checkout, lưu chuỗi để giữ số 0 đầu; database cho phép NULL với dữ liệu cũ, nhưng không tự cắt số dài.

Tài khoản đăng ký trên web được tự hash mật khẩu. Với tài khoản có sẵn từ database cũ, xem [cách kiểm tra hoặc đổi mật khẩu](database/README_DATABASE.md#tài-khoản-có-sẵn-trong-database-cũ).

Khắc phục lỗi thường gặp:

- `could not find driver`: kiểm tra PHP terminal/server dùng cùng `php.ini` và bật `pdo_sqlsrv`.
- Không kết nối: kiểm tra SQL Server service, instance/TCP, Windows user và `DATABASE_URL`.
- Thiếu cột hoặc sai kiểu: đối chiếu đủ bốn migration; không import lại SQL mẫu vào database đang dùng.
- Cache sau khi chuyển thư mục: `php bin/console cache:clear`; nếu CLI debug bị kẹt, dừng tiến trình PHP đang chạy rồi dùng `php bin/console cache:clear --no-debug`.
- CSS/JS lỗi: chạy `importmap:install`, kiểm tra asset trả 200 và dùng đúng router `public/index.php`.

## 09. Kết quả, hạn chế và hướng phát triển

Website đã triển khai nghiệp vụ quản lý giỏ hàng, xác nhận đặt hàng COD và quy trình duyệt của quản trị viên. Ứng dụng kiểm tra dữ liệu phía server, phân quyền theo chủ giỏ, sử dụng CSRF và transaction cho các thao tác ghi dữ liệu. Đánh giá chi tiết và định hướng mở rộng được trình bày tại [mục 10 của báo cáo Cart](docs/CART_REPORT.md#10-đánh-giá-và-hướng-phát-triển).

Hạn chế hiện tại và thứ tự phát triển đề xuất:

1. Tách địa chỉ giao hàng/phương thức/chi phí thành dữ liệu riêng, lưu đơn giá tại thời điểm đặt. Hiện lịch sử tính theo **giá sách hiện tại** và ghi rõ trên giao diện.
2. Lưu giỏ nháp lâu dài theo tài khoản nếu cần đồng bộ thiết bị; session hiện mất khi hết hạn/đăng xuất.
3. Tích hợp thanh toán online, callback có xác thực và trạng thái thanh toán trước khi mở VNPAY/VietQR; hiện chỉ COD.
4. Kiểm thử nhiều kết nối đồng thời, tải lớn; thay cấp ID `MAX+1` có khóa bảng nếu cần mở rộng.
5. Bổ sung quản lý sách/loại sách, phí vận chuyển, email và theo dõi giao hàng. Hiện chưa có các module này.
