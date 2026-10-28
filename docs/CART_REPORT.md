# Báo cáo kỹ thuật · Module Cart

**Đề tài:** Website bán sách – Symfony MVC, CRUD Giỏ hàng. **Nhóm:** 04 · **Lớp:** DA23TTA. **Trưởng nhóm:** Huỳnh Dương Tấn Phát (110123038). **Thư ký:** Phạm Bá Hạo Long (110123024). [Danh sách đầy đủ bảy thành viên](../README.md#thành-viên).

Đối chiếu mã nguồn tại thư mục gốc, ngày 28/10/2026. Tài liệu tổng quan và hướng dẫn chạy nhanh: [README](../README.md). Kết quả đo được: [test-cases.md](test-cases.md).

## 01. Giới thiệu dự án và module

Nhà sách Nhóm 04 cho phép khách hàng chọn sách, gửi yêu cầu đặt mua và theo dõi xử lý. Module Cart bao gồm giỏ nháp, xác nhận COD, giỏ đã gửi, sửa/hủy và quy trình duyệt của Admin. Các chức năng tài khoản/catalog cung cấp đầu vào và phân quyền cho luồng này.

Chưa có bảng đơn hàng/thanh toán độc lập. Tên “đơn hàng” ở checkout là giỏ `GIO_HANG` đã gửi, không phải giao dịch thu tiền thành công. Danh sách, vai trò và nhiệm vụ kỹ thuật của các thành viên được ghi tại [README](../README.md#thành-viên).

## 02. Phân tích yêu cầu

| Nhóm | Yêu cầu và cách đáp ứng |
|---|---|
| Chức năng khách hàng | Tìm/lọc/sắp xếp sách; thêm/sửa/xóa từng dòng; xem tổng; COD; lịch sử; sửa/hủy giỏ Chờ duyệt |
| Chức năng Admin | Tìm giỏ theo mã/chủ/tên; lọc trạng thái; xem chi tiết; duyệt, từ chối có lý do, xóa giỏ không thực hiện |
| An toàn dữ liệu | Giá/chủ giỏ/trạng thái từ server; SQL tham số hóa; CSRF; kiểm tra quyền ở route và nghiệp vụ |
| Tính nhất quán | Transaction toàn bộ giỏ; khóa SQL Server; kiểm tra trạng thái lại khi ghi; rollback khi một dòng lỗi |
| Khả dụng | Dùng PRG sau thao tác thành công; thông báo lỗi; giữ giỏ tạm khi gửi thất bại; phân trang 12 mục |
| Giao diện | Twig, CSS responsive, nhãn trường nhập, thông báo có role, định dạng tiền Việt |

Quy tắc nghiệp vụ:

1. Sách phải tồn tại, có giá hợp lệ và kho đủ. Schema chưa có cờ “ngừng bán”; không mô tả cờ này như đã triển khai.
2. Mỗi giỏ tối đa 100 đầu sách. Số lượng là số nguyên dương tối đa 999.999.999, nhỏ hơn hoặc bằng kho; xóa dùng action riêng.
3. Thêm lại một sách cộng số lượng, không tạo dòng trùng. Tổng lượng sau cộng cũng phải hợp lệ.
4. Thành tiền = đơn giá server × số lượng; tổng tiền là tổng các dòng. Dùng số nguyên phần trăm đồng khi tính trong PHP, tránh phép nhân float thông thường.
5. Thêm/gửi/sửa giỏ không trừ hoặc giữ kho; Admin duyệt mới trừ. Vì vậy giỏ Chờ duyệt có thể thiếu hàng khi được duyệt sau đó.
6. Nonce thay đổi sau mỗi thao tác thành công trên giỏ nháp; token cũ bị từ chối khi gửi. Session locking bảo vệ thao tác cùng session, không phải cơ chế idempotency trên nhiều thiết bị.
7. Chỉ chủ giỏ được xem/sửa/hủy; Admin dùng khu vực riêng. Giỏ đã duyệt không sửa/hủy/xóa theo nghiệp vụ hiện tại.
8. COD + giao tiêu chuẩn là hai lựa chọn đang được hỗ trợ. Không chấp nhận `express`, `banking`, `vnpay` hoặc giá trị tùy ý qua POST.
9. Thông tin nhận hàng ghép vào ghi chú tối đa 255 đơn vị UTF-16 của `NVARCHAR(255)`. Dữ liệu dài bị từ chối; không tự cắt bỏ địa chỉ.

Ngoại lệ cần giải thích khi báo cáo: sách bị xóa, hết hàng sau khi thêm, giá NULL/sai định dạng, số lượng rỗng/0/âm/thập phân, mã vượt SQL INT, CSRF sai, không phải chủ giỏ, trạng thái đã đổi, lỗi ghi dòng thứ hai, bấm gửi/duyệt lại.

## 03. Sơ đồ thiết kế và use case

- [Use case theo tác nhân](diagrams/use-case.md)
- [Sequence thêm sách → session → checkout → database](diagrams/cart-sequence.md)
- [ERD](diagrams/database-erd.md)

**UC-CART-01 — Thêm sách vào giỏ**

| Thuộc tính | Nội dung |
|---|---|
| Tác nhân | Khách hàng đã đăng nhập ROLE_USER |
| Mục tiêu | Chọn sách chuẩn bị đặt mua |
| Tiền điều kiện | Session khả dụng; sách tồn tại, có giá và đủ kho |
| Đầu vào | Mã sách, số lượng, action `add`, CSRF token |
| Hậu điều kiện | Một dòng cho mỗi mã sách, lượng được cộng và tổng hiển thị theo giá server |

Luồng chính:

1. Khách hàng chọn sách tại danh mục, nhập lượng và gửi form.
2. Controller kiểm tra CSRF, định dạng mã SQL INT và số lượng nguyên dương.
3. Service đọc giỏ của thành viên trong session, truy vấn sách bằng repository.
4. Cộng lượng cũ với lượng mới; kiểm tra giới hạn số lượng, tồn kho và số đầu sách.
5. Lưu items cùng nonce mới; controller trả 303 đến giỏ hàng.
6. GET giỏ lấy giá/kho mới nhất, tính thành tiền và tổng.

Ngoại lệ: token sai trả 403; dữ liệu nghiệp vụ sai đưa thông báo `cart_error` và chuyển về giỏ; không cập nhật session/nonce khi thao tác bị từ chối. Lỗi DB được hiển thị bằng thông báo chung.

**UC-CART-02 — Xác nhận COD:** kiểm tra giỏ trước khi vào checkout, kiểm tra người nhận và nonce, kiểm tra kho lại trong transaction, ghi `GIO_HANG` + `MUA`, commit rồi xóa giỏ nháp. Nếu lỗi, rollback và giữ giỏ để sửa/thử lại.

**UC-CART-03 — Duyệt giỏ:** Admin mở giỏ Chờ duyệt, gửi POST hợp lệ; service khóa giỏ/dòng/sách, kiểm tra kho tổng, trừ kho có điều kiện và đổi trạng thái trong cùng transaction. Duyệt lại không trừ lần hai.

## 04. Thiết kế dữ liệu

Ứng dụng dùng **Doctrine DBAL và SQL Server**, không có Entity ORM nghiệp vụ. Không thêm hai bảng `cart/cart_item` chỉ để khớp tên trong kiến trúc mẫu: tên thực tế là `GIO_HANG/MUA`.

Giỏ có hai giai đoạn lưu:

| Giai đoạn | Dữ liệu | Thời hạn |
|---|---|---|
| Nháp | Session key `draft_cart_<memberId>` chứa items và nonce; giá lấy từ `SACH` mỗi lần đọc | Trong session hiện tại, không đồng bộ nhiều thiết bị |
| Đã gửi | Header `GIO_HANG`, dòng `MUA` tham chiếu thành viên/sách/giỏ | Bền vững qua đăng nhập và tải lại |

Schema quan trọng:

- `THANH_VIEN_TEN_DANG_NHAP` là khóa định danh VARCHAR(10), giữ nguyên giá trị mã cũ sau migration. Mã mới có dạng `tv` + 8 ký tự hex. Username đăng nhập là `THANH_VIEN_USERNAME VARCHAR(30)` không phân biệt hoa/thường, UNIQUE và CHECK; đổi username không làm đổi mã hay chủ giỏ.
- `THANH_VIEN_SO_DIEN_THOAI VARCHAR(10)` lưu số điện thoại dạng chuỗi. CHECK cho phép NULL hoặc đúng 10 chữ số ASCII; đăng ký/hồ sơ/checkout yêu cầu nhập đủ 10 chữ số, không nhận dấu phân cách hoặc mã quốc gia.
- `SACH_GIA_BAN NUMERIC(12,2)` và kho có thể NULL trong schema cũ. Service không cho đặt sách có giá NULL hoặc kho thiếu.
- `MUA` có khóa ghép `(THANH_VIEN_TEN_DANG_NHAP, SACH_ID_SACH, GIO_HANG_ID_GIO)`; chưa có cột đơn giá tại thời điểm đặt.
- `GIO_HANG.QTV_USER_ADMIN` nullable cho giỏ chưa duyệt; chủ giỏ nullable để tương thích dữ liệu cũ nhưng ứng dụng gán chủ khi tạo mới.
- Ghi chú khách và `GIO_HANG_LY_DO_TU_CHOI` được lưu riêng; từ chối không ghi đè thông tin nhận hàng.
- Database không cascade delete giỏ; service xóa `MUA` trước header trong transaction.

Bốn migration nâng cấp schema theo thứ tự. Migration 003 backfill chủ giỏ bằng MIN mã thành viên trong MUA; với dữ liệu lịch sử có nhiều chủ trong cùng giỏ cần đối chiếu thủ công trước nâng cấp. Migration 20261028000100 đổi đồng thời khóa thành viên trong THANH_VIEN, GIO_HANG và MUA sang VARCHAR(10), khôi phục PK/FK/index và thêm ràng buộc điện thoại 10 chữ số. Không tự cắt hoặc xóa dữ liệu dài; dữ liệu không hợp lệ phải được xử lý trước khi chạy.

## 05. Chi tiết triển khai Symfony

| Thành phần | Trách nhiệm |
|---|---|
| `GioHangController` | HTTP giỏ nháp, gửi nhanh, checkout GET/POST, CSRF, thông báo/redirect |
| `CartReadController` | Danh sách và chi tiết theo quyền sở hữu/Admin |
| `CartEditController` | Form sửa giỏ đã gửi, thêm/gộp/bỏ sách |
| `CartReviewController` | Nhận yêu cầu duyệt/từ chối của Admin |
| `CartDeleteController` | Hủy giỏ của khách, xóa giỏ không thực hiện của Admin |
| `CartService` | Giỏ session, validation, tiền, nonce, tạo/sửa/hủy/duyệt/xóa bằng transaction |
| `CheckoutService` | Kiểm tra tên/điện thoại/địa chỉ, lựa chọn hỗ trợ, độ dài dữ liệu nhận hàng |
| `BookRepository` | Tra cứu sách, danh mục, bộ lọc và chuyển số tiền sang số nguyên |
| `CartRepository` | SQL danh sách/chi tiết, tổng theo giá hiện tại, bộ lọc trạng thái |
| `AccountRepository`, `Security/*` | Tài khoản, hash, provider, đăng nhập username, logout/throttling |
| `MoneyExtension` | Twig filter `vnd`, ví dụ `85.000 ₫` |

`CartService` hiện cũng chứa SQL ghi trực tiếp qua connection; không mô tả toàn bộ SQL đều đã tách sang repository. Đây là điểm có thể tách tiếp khi module lớn hơn.

Transaction và khóa:

| Thao tác | Cơ chế |
|---|---|
| Tạo giỏ | `TABLOCKX,HOLDLOCK` trên GIO_HANG khi cấp `MAX+1`; sách đọc `HOLDLOCK`; header/dòng commit cùng nhau |
| Sửa giỏ | `UPDLOCK,HOLDLOCK` header; kiểm tra chủ và Chờ duyệt lại; sách theo ID tăng dần; thay dòng cùng transaction |
| Duyệt | Khóa header, dòng MUA và sách theo ID tăng dần; gộp lượng dòng cũ trùng trước kiểm tra; UPDATE kho với `stock >= quantity` |
| Hủy/từ chối | Khóa và kiểm tra Chờ duyệt; đổi trạng thái, không đổi kho |
| Xóa | Khóa và kiểm tra Đã hủy/Từ chối; xóa dòng trước header; lỗi FK/ghi sẽ rollback |

Security: request ghi có CSRF; logout là POST có CSRF; quyền kiểm tra ở Security/attribute và service; truy vấn có tham số; Twig escape đầu ra. Login giới hạn 5 lần/phút. Khi đổi mật khẩu thành công, session bị vô hiệu để đăng nhập lại.

## 06. Web routes và kết quả trả về

Tất cả là form HTML, không trả JSON API. Form nhận thêm `_token`; giỏ tạm gửi cần `nonce` do server cấp.

| Route/method | Đầu vào nghiệp vụ | Thành công | Lỗi |
|---|---|---|---|
| GET `/danh-muc` | `q, category, min, max, sort, page` | 200, 12 sách/trang | Bộ lọc sai được thông báo và chuẩn hóa |
| GET `/gio-hang` | Session hiện tại | 200, cả trạng thái rỗng | Lỗi DB đọc do exception handler xử lý |
| POST `/gio-hang/thay-doi` | `book_id, action, quantity` | 303 về giỏ | Sai nghiệp vụ: 303 + flash; CSRF sai: 403 |
| GET `/gio-hang/thanh-toan` | Giỏ session | 200, người nhận từ hồ sơ | Giỏ trống/không hợp lệ: 302 về giỏ |
| POST `/gio-hang/thanh-toan` | `recipient_name, recipient_phone, recipient_address, shipping_method=standard, payment_method=cod, note, nonce` | 303 tới chi tiết giỏ mới | Dữ liệu/nonce/kho: 422 giữ input; trống: 302; CSRF: 403 |
| POST `/gio-hang/gui` | `note` ≤255, `nonce` | 303 về giỏ | Dữ liệu/nonce/DB: 422; CSRF: 403 |
| GET `/gio-hang/lich-su` | `status, page` | 200, giỏ của mình | Chưa login: chuyển tới đăng nhập |
| GET `/gio-hang/lich-su/{id}` | Mã giỏ | 200, header/dòng | Không có/không phải chủ/vượt INT: 404 |
| GET/POST `…/{id}/sua` | `items[id]=quantity, remove[id], new_book, new_quantity, note` | GET 200, POST 303 | Dữ liệu: 422; trạng thái thay đổi: 303 + flash; sai quyền: 403/404 |
| POST `…/{id}/huy` | Mã giỏ, token theo giỏ | 303 tới chi tiết | Nghiệp vụ đổi: flash; sai token/quyền: 403/404 |
| GET `/admin/gio-hang` | `q, status, page` | 200, tất cả giỏ phù hợp | Khách hàng: 403 |
| GET `/admin/gio-hang/{id}` | Mã giỏ | 200 | Không có: 404; sai quyền: 403 |
| POST `/admin/gio-hang/{id}/approve` | Mã giỏ | 303 chi tiết, trừ kho | Trạng thái/kho đổi: flash, không ghi dở |
| POST `…/{id}/reject` | `reason` bắt buộc, ≤255 | 303 chi tiết, lưu lý do | Lý do sai: flash; không trừ kho |
| POST `…/{id}/xoa` | Mã giỏ | 303 danh sách | Trạng thái bảo vệ/FK: flash; giỏ không có: 404 |

GET vào endpoint chỉ POST trả 405. Khách chưa xác thực bị chuyển `/dang-nhap`; Admin không có ROLE_USER và dùng đường dẫn quản trị riêng. Xem danh sách runtime bằng `php bin/console debug:router --show-controllers`.

## 07. Kiểm thử

Ba mức bằng chứng:

1. PHPUnit chạy service và HTTP kernel/security/form với connection giả lập, không chứng minh SQL Server/locking thật.
2. Smoke test integration dùng SQL Server thật. Fixture nằm trong transaction ngoài, các transaction service dùng savepoint, cuối cùng rollback. `phase7` kiểm tra lỗi sau khi đã trừ kho dòng đầu; `phase3` kiểm tra lỗi sau khi đã ghi header và một dòng.
3. Ảnh Chrome headless chụp HTML được render từ dữ liệu SQL Server thật trong test rollback, tài khoản minh họa tạm. Đây là ảnh render, không phải kiểm thử click toàn bộ luồng trong browser.

`CartDraftTest` bổ sung trường hợp gọi service trực tiếp với lượng add âm/0 vào dòng đã có, đảm bảo từ chối và giữ giỏ/nonce cũ. `CheckoutServiceTest` kiểm tra dữ liệu nhận hàng, dấu câu không được coi là số điện thoại, phương thức không hỗ trợ và giới hạn UTF-16. `CartCheckoutWebTest` kiểm tra tổng server, sửa payload, CSRF, tồn kho thay đổi, replay và giữ input.

`tests/cart_checkout_smoke.php` xác minh checkout qua form thật, ghi header/MUA thật và đối chiếu fingerprint bảy bảng sau rollback. Có thể tạo HTML snapshot bằng `CART_DEMO_DIR`; xem [demo](DEMO.md). Danh sách case, kết quả và log ở [test-cases](test-cases.md).

## 08. Demo chức năng và bằng chứng

Kịch bản demo có ảnh danh mục, thêm/sửa giỏ, checkout, lỗi validation và giỏ đã gửi: [DEMO.md](DEMO.md). Ảnh dùng cùng một tài khoản tạm “Khách hàng Demo”; dữ liệu nhận hàng là dữ liệu minh họa. Log không chứa mật khẩu.

Với báo cáo hội đồng, nên demo trực tiếp một giỏ mới: đối chiếu lượng kho trước gửi, sau gửi và sau duyệt. Không dùng lại ID trong ảnh vì giỏ minh họa đã rollback. Chưa có video trong repo.

## 09. Cài đặt và vận hành

Quy trình dưới đây dành cho người cài đặt dự án lần đầu trên Windows. Hệ thống sử dụng Symfony, Doctrine DBAL và Microsoft SQL Server. Thực hiện các lệnh PowerShell tại thư mục gốc chứa `composer.json` và `bin/console`.

### Bước 1. Chuẩn bị môi trường và cài thư viện

Cài PHP từ 8.2, Composer 2, SQL Server từ 2017, SQL Server Management Studio (SSMS), Microsoft ODBC Driver 18 và extension PHP `pdo_sqlsrv`. Kiểm tra PHP và cài thư viện:

```powershell
php -v
php --ri pdo_sqlsrv
composer install
composer check-platform-reqs
```

### Bước 2. Tạo cơ sở dữ liệu trong SSMS

1. Khởi động dịch vụ SQL Server, mở SSMS và kết nối bằng tài khoản có quyền tạo database.
2. Chọn **File → Open → File**, mở `database/BanSachDB_Full.sql` trong mã nguồn.
3. Kiểm tra tên database là `BanSachDB`, sau đó nhấn **Execute (F5)** để chạy toàn bộ tệp.
4. Trong Object Explorer, nhấn **Refresh**, mở `Databases → BanSachDB → Tables` để xem các bảng vừa tạo.

Tệp SQL tự tạo database, sáu bảng nghiệp vụ, các ràng buộc và bảng `doctrine_migration_versions`. Với cách này, không tạo database trước khi chạy tệp. Kết quả cuối tệp cần hiển thị `created_database = BanSachDB`, `business_tables = 6` và `baselined_migrations = 4`.

Nếu muốn tự chạy `CREATE DATABASE` và `USE`, dùng phương án thủ công tại [mục 1.2 của báo cáo database](DATABASE_REPORT.md#12-tạo-cơ-sở-dữ-liệu), rồi chạy `database/BanSachDB_SchemaOnly.sql`. Chỉ chọn một trong hai cách tạo mới.

Mở **New Query** trong SSMS và kiểm tra:

```sql
USE [BanSachDB];
GO

SELECT DB_NAME() AS TenCoSoDuLieu;

SELECT name AS TenBang
FROM sys.tables
WHERE schema_id = SCHEMA_ID(N'dbo')
ORDER BY name;
```

### Bước 3. Cấu hình kết nối của ứng dụng

Nếu chưa có `.env.local`, tạo tệp này từ `.env.local.example`. Điền cấu hình phù hợp với SQL Server trên máy:

```dotenv
APP_ENV=dev
APP_DEBUG=1
APP_SECRET=thay_bang_chuoi_ngau_nhien_cua_may_ban
DATABASE_URL="mssql://localhost/BanSachDB"
```

Sinh chuỗi ngẫu nhiên bằng lệnh sau rồi chép kết quả vào `APP_SECRET`:

```powershell
php -r "echo bin2hex(random_bytes(32));"
```

Ví dụ trên dùng instance SQL Server mặc định và Windows Authentication. Tài khoản Windows chạy PHP cần có quyền truy cập `BanSachDB`. Nếu dùng named instance, ví dụ `TEN_MAY\SQLEXPRESS`, điều chỉnh URL thành `mssql://TEN_MAY%5CSQLEXPRESS/BanSachDB`. Cấu hình riêng trong `.env.local` được Git bỏ qua.

### Bước 4. Kiểm tra kết nối và migration

```powershell
php bin/console doctrine:query:sql "SELECT DB_NAME() AS database_name"
php bin/console doctrine:migrations:status
```

Kết nối đúng trả về `database_name = BanSachDB`. Với các tệp cài mới hiện tại, trạng thái mong đợi là **Executed=4, Available=4, New=0** vì script đã tạo schema và ghi nhận đủ bốn migration. Khi `New=0`, chuyển sang bước 5.

Nếu sử dụng database từ phiên bản cũ, làm theo [hướng dẫn nâng cấp database](../database/README_DATABASE.md#b-nâng-cấp-database-đã-có-dữ-liệu): sao lưu, kiểm tra bản sao lưu và SQL dự kiến trước khi chạy migration còn thiếu. Không chạy lại tệp khởi tạo trên database đã có dữ liệu.

### Bước 5. Chạy website và đăng nhập

```powershell
php bin/console importmap:install
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Mở **http://127.0.0.1:8000/**, giữ terminal chạy server và nhấn `Ctrl+C` khi muốn dừng. Có thể tạo tài khoản khách tại `/dang-ky`, sau đó đăng nhập tại `/dang-nhap`.

Database cài mới chưa có sách hoặc tài khoản mẫu. Để sử dụng khu vực quản trị, thực hiện [hướng dẫn tạo Admin cho database mới](../database/README_DATABASE.md#tạo-tài-khoản-admin-cho-database-mới); đăng nhập bằng tên và mật khẩu đã tự đặt. Dữ liệu loại sách và sách được nhập riêng vào `LOAI_SACH` và `SACH`; khi chưa có dữ liệu, danh mục hiển thị rỗng.

Hướng dẫn tổng quan: [README mục 08](../README.md#08-cài-đặt-và-chạy-dự-án). Nội dung thiết kế bảng, hai cách tạo database và cấu hình môi trường dùng trong báo cáo: [DATABASE_REPORT.md](DATABASE_REPORT.md).

## 10. Đánh giá và hướng phát triển

### 10.1. Kết quả đạt được

Hệ thống đã triển khai các nghiệp vụ quản lý giỏ hàng trong website bán sách, gồm thêm sách, cập nhật số lượng, xóa sách khỏi giỏ, xác nhận đặt hàng COD và xem lịch sử. Khách hàng có thể sửa hoặc hủy giỏ đang Chờ duyệt; quản trị viên có thể duyệt, từ chối và xóa giỏ theo quyền và trạng thái cho phép.

Ứng dụng được tổ chức theo mô hình MVC của Symfony, sử dụng Doctrine DBAL để truy vấn Microsoft SQL Server. Giỏ nháp được lưu trong session; giỏ đã gửi và các dòng sách được lưu trong `GIO_HANG` và `MUA`. Hệ thống kiểm tra dữ liệu đầu vào, quyền sở hữu giỏ, trạng thái xử lý và tồn kho phía server. Các thao tác ghi nhiều bước được thực hiện trong transaction để duy trì tính nhất quán của dữ liệu.

Các kiểm thử đã thực hiện bao gồm xử lý nghiệp vụ, phân quyền, dữ liệu không hợp lệ và rollback khi ghi thất bại. Phạm vi, kết quả và bằng chứng kiểm thử được trình bày tại [test-cases.md](test-cases.md).

### 10.2. Hạn chế

- Chi tiết giỏ chưa lưu đơn giá tại thời điểm đặt. Tổng tiền trong lịch sử được tính theo giá sách hiện tại nên có thể thay đổi khi cập nhật giá.
- Hệ thống chỉ hỗ trợ đặt hàng COD, chưa quản lý riêng trạng thái thu tiền và giao hàng, chưa tích hợp thanh toán trực tuyến hoặc email thông báo.
- Giỏ nháp phụ thuộc session, không được giữ lại sau khi đăng xuất hoặc hết hạn và chưa đồng bộ giữa các thiết bị.
- Chưa có giao diện quản lý sách, loại sách và cấu hình phí vận chuyển.
- Hiệu năng khi có nhiều yêu cầu đồng thời chưa được đánh giá. Cơ chế cấp mã giỏ bằng `MAX+1` kết hợp khóa bảng cần được xem xét khi mở rộng hệ thống.

### 10.3. Hướng phát triển

- Bổ sung đơn giá tại thời điểm đặt và lưu riêng thông tin người nhận, địa chỉ giao hàng, phương thức thanh toán để quản lý lịch sử mua hàng.
- Lưu giỏ nháp trong cơ sở dữ liệu theo tài khoản, hỗ trợ khôi phục sau khi đăng nhập lại và đồng bộ giữa các thiết bị.
- Tích hợp thanh toán trực tuyến, quản lý trạng thái thanh toán, giao hàng và gửi email thông báo khi trạng thái thay đổi.
- Xây dựng giao diện quản trị sách, loại sách, tồn kho và phí vận chuyển.
- Kiểm thử tải và truy cập đồng thời; cải tiến cơ chế cấp mã, khóa dữ liệu và truy vấn để đáp ứng quy mô sử dụng lớn hơn.
