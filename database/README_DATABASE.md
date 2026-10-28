# Cài đặt và nâng cấp database SQL Server

Schema hiện tại của website dùng **Microsoft SQL Server**, database **BanSachDB**, sáu bảng nghiệp vụ và bốn migration. Trạng thái đích: **Executed=4, Available=4, New=0**.

## Kiểu dữ liệu hiện tại

| Vị trí | Kiểu dữ liệu |
|---|---|
| `THANH_VIEN.THANH_VIEN_TEN_DANG_NHAP` | VARCHAR(10), PK, NOT NULL |
| `GIO_HANG.THANH_VIEN_TEN_DANG_NHAP` | VARCHAR(10), FK, NULL |
| `MUA.THANH_VIEN_TEN_DANG_NHAP` | VARCHAR(10), thuộc PK ghép và FK, NOT NULL |
| `THANH_VIEN.THANH_VIEN_SO_DIEN_THOAI` | VARCHAR(10), NULL hoặc đúng 10 chữ số ASCII |
| `THANH_VIEN.THANH_VIEN_USERNAME` | VARCHAR(30), UNIQUE; username đăng nhập độc lập với mã thành viên |

Các cột mã thành viên dùng cùng collation `Latin1_General_100_CI_AS`. Sách/giỏ/loại sách vẫn dùng khóa INT. `QTV_USER_ADMIN` giữ VARCHAR(60). Trạng thái, ghi chú và lý do từ chối của giỏ dùng NVARCHAR để lưu tiếng Việt.

## A. Cài mới bằng BanSachDB_Full.sql

1. Trong SSMS, kết nối đúng SQL Server, mở `database/BanSachDB_Full.sql` ở cửa sổ query thông thường.
2. Kiểm tra `DECLARE @DatabaseName sysname = N'BanSachDB';`. Chỉ chọn tên database chưa tồn tại; không cần tự tạo database trước.
3. Chạy **toàn bộ** file. Script tự tạo database/schema và từ chối nếu database đích đã tồn tại, kể cả database rỗng.
4. Kết quả: `business_tables = 6`, `baselined_migrations = 4`. Tổng cộng có 7 bảng vật lý vì thêm bảng hạ tầng `doctrine_migration_versions`.
5. Cấu hình `DATABASE_URL` trong `.env.local`, kiểm tra bằng các lệnh ở mục C.

File tổng hợp đã tích hợp schema của cả bốn migration và ghi nhận metadata baseline tương ứng. Không chạy lại các file migration đã baseline. Script không chứa dữ liệu sách, tài khoản, mật khẩu hoặc thông tin cá nhân; catalog ban đầu rỗng. Người vận hành tự cấp tài khoản Admin với mật khẩu đã hash và nhập dữ liệu sách được phép sử dụng.

Nếu lỗi schema sau khi database mới được tạo, transaction schema rollback; database mới có thể còn để kiểm tra. Script không tự xóa/reset database. File `BansachDB_Full_CoDuLieu.sql` và `setup.ps1` là quy trình demo lịch sử, chưa có schema mới; không dùng để nâng cấp database hiện tại.

## B. Nâng cấp database đã có dữ liệu

Sao lưu Full/COPY_ONLY và kiểm tra bản sao lưu trước khi nâng cấp. Không import lại SQL khởi tạo, không dùng `setup.ps1 -Reset`, `schema:drop` hoặc `schema:update --force` để sửa schema nghiệp vụ DBAL.

| Migration | Nội dung |
|---|---|
| `Version20261026000100` | Thêm username VARCHAR(30), UNIQUE/CHECK; backfill `user_<mã>`, tại thời điểm này mã vẫn là INT |
| `Version20261026000200` | Thêm lý do từ chối NVARCHAR(255), tách khỏi ghi chú |
| `Version20261026000300` | Admin nullable, thêm FK chủ giỏ và backfill từ MUA |
| `Version20261028000100` | Chuyển khóa thành viên và hai FK sang VARCHAR(10), điện thoại VARCHAR(10), CHECK đúng 10 chữ số |

Migration mới giữ giá trị mã cũ dưới dạng chuỗi, không đổi chủ giỏ, không xóa dòng. Các PK/FK/index phụ thuộc được tháo và tạo lại trong cùng transaction. Migration kiểm tra dữ liệu trước khi thu hẹp cột, không tự cắt số điện thoại dài. Dữ liệu ngoài giới hạn phải được xác minh và sửa theo thông tin đúng trước khi chạy.

Có thể kiểm tra điện thoại chưa hợp lệ trên schema cũ bằng:

```sql
SELECT THANH_VIEN_TEN_DANG_NHAP,
       DATALENGTH(THANH_VIEN_SO_DIEN_THOAI) AS DoDaiDienThoai
FROM dbo.THANH_VIEN
WHERE THANH_VIEN_SO_DIEN_THOAI IS NOT NULL
  AND (DATALENGTH(THANH_VIEN_SO_DIEN_THOAI) <> 10
       OR THANH_VIEN_SO_DIEN_THOAI COLLATE Latin1_General_100_BIN2
          LIKE '%[^0-9]%');
```

Sau khi đối chiếu SQL dry-run, áp dụng những migration còn thiếu. Không tự đánh dấu migration đã chạy nếu schema chưa được đối chiếu. Các migration không hỗ trợ down vì dữ liệu mới có thể không chuyển lại thành INT hoặc mất thông tin đăng nhập/quan hệ.

## C. Kiểm tra và chạy ứng dụng

```powershell
php bin/console doctrine:query:sql "SELECT DB_NAME() AS database_name"
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:list
php bin/console doctrine:migrations:migrate --dry-run
# Chỉ áp dụng nếu còn migration mới và đã sao lưu/đối chiếu
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
php -S 127.0.0.1:8000 -t public public/index.php
```

Mở http://127.0.0.1:8000/. PHP cần `pdo_sqlsrv` và Microsoft ODBC Driver 18. Cấu hình riêng dùng `.env.local` với `DATABASE_URL="mssql://localhost/BanSachDB"` cho instance mặc định và Windows Authentication.

## D. Xác minh ngày 28/10/2026

- Đã tạo bản sao lưu SQL Server COPY_ONLY, có CHECKSUM, và chạy `RESTORE VERIFYONLY` thành công trước nâng cấp.
- Đã thử migration trong transaction rồi rollback trên SQL Server thật, kiểm tra kiểu dữ liệu, sáu FK, PK ghép, provider và CRUD giỏ với mã thành viên có chữ.
- Đã áp dụng `Version20261028000100` vào BanSachDB. Số thử 11 chữ số của tài khoản `gggg` được đổi sang `0000000000` theo xác nhận của người dùng.
- So sánh fingerprint đã chuẩn hóa INT thành chuỗi xác nhận mọi dữ liệu khác giữ nguyên: 6 thành viên, 1 QTV, 50 sách, 10 loại, 12 giỏ và 27 dòng MUA ở thời điểm nâng cấp.
- Schema cài mới đã được kiểm tra ở database riêng `BanSachDB_SchemaCheck_20261028_4905f658`, đủ bốn migration baseline; đăng ký và tạo giỏ bằng khóa chuỗi hoạt động. Fixture được rollback, sáu bảng nghiệp vụ để rỗng và database thử được giữ lại.

Bằng chứng kiểm thử được lưu ở [docs/evidence/2026-10-28-member-schema](../docs/evidence/2026-10-28-member-schema). Nội dung trình bày báo cáo mục 1.1–1.3: [DATABASE_REPORT.md](../docs/DATABASE_REPORT.md).
