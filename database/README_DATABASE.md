# Cài đặt database SQL Server

Chọn **một** trong hai phương án dưới đây. Không chạy cả hai trên cùng database.
Giữ nguyên các script SQL và migration cũ; không dùng script khởi tạo để nâng cấp database đang có dữ liệu.

## Phương án A — Cài mới bằng BanSachDB_Full.sql (khuyến nghị)

1. Trong SSMS, kết nối đúng SQL Server, mở `database/BanSachDB_Full.sql` bằng cửa sổ query thông thường; không cần SQLCMD Mode.
2. Kiểm tra dòng `DECLARE @DatabaseName sysname = N'BanSachDB';`. Chỉ dùng tên chưa tồn tại. Để cài thử riêng, đổi giá trị này trong cửa sổ query sang tên khác, không chọn database đang sử dụng.
3. Chạy **toàn bộ** file. Script tự tạo database mới và từ chối nếu tên đích đã tồn tại, kể cả database rỗng. Không cần tự tạo database trước và không chạy từng đoạn riêng.
4. Kết quả mong đợi: `business_tables = 6`, `baselined_migrations = 2`. Có thêm bảng hạ tầng `dbo.doctrine_migration_versions`, nên tổng cộng là 7 bảng vật lý.
5. Trên bản cài ứng dụng tương ứng, cấu hình `DATABASE_URL` trong `.env.local` trỏ đúng database vừa tạo, rồi kiểm tra:

```powershell
php bin/console doctrine:query:sql "SELECT DB_NAME() AS database_name"
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:list
php bin/console doctrine:migrations:migrate --dry-run --no-interaction
```

Phải thấy 2 migration đã ghi nhận, 0 migration mới; dry-run báo `Already at the latest version`. **Không cần chạy lại hai migration**: schema tổng hợp đã có username, unique/check constraint và cột lý do từ chối. Hai dòng metadata là baseline cho DDL đã tích hợp, không có nghĩa file migration PHP được thực thi trong lần import.

File chỉ khởi tạo schema và metadata migration: không chứa sách mẫu, tài khoản, mật khẩu hay dữ liệu cá nhân. Danh mục ban đầu rỗng; khách hàng có thể đăng ký bằng ứng dụng. Nếu cần Admin, người quản trị tự tạo hash bằng lệnh tương tác `php bin/console security:hash-password`, rồi thêm tài khoản riêng vào bảng `QTV` qua SSMS với hash đó. Không lưu mật khẩu văn bản và không đưa tài khoản thật vào script SQL. Chỉ nhập dữ liệu sách được phép sử dụng.

Nếu import lỗi sau khi tạo database, transaction schema sẽ rollback; database mới có thể còn lại để kiểm tra. Script không tự xóa hoặc reset database. Không chạy lại với cùng tên rồi bỏ qua lỗi bảo vệ.

## Phương án B — Schema cũ rồi áp dụng migration

Phương án này dành cho bản cài mới theo quy trình cũ hoặc database cũ cần nâng cấp:

1. **Database mới:** mở `database/BansachDB_Full_CoDuLieu.sql` trong SSMS, chỉ chạy trên server chưa có `BanSachDB` cần giữ. File cũ hardcode `USE BanSachDB` và có lệnh xóa/tạo lại bảng; việc chọn tên database khác trong dropdown SSMS không đổi đích của script. Nó chứa dữ liệu demo cũ, không phải bản xuất dữ liệu hiện tại. Không import thêm `BanSachDB_Full.sql`.
2. **Database cũ đang có dữ liệu:** bỏ qua hoàn toàn bước import SQL khởi tạo; sao lưu và kiểm tra bản backup trước khi nâng cấp.
3. Cấu hình ứng dụng trỏ đúng database đích. Chạy `SELECT DB_NAME()`, `doctrine:migrations:status`, `doctrine:migrations:list` và `doctrine:migrations:migrate --dry-run` như trên.
4. Đối chiếu schema với từng migration còn thiếu. Sau sao lưu và duyệt SQL dry-run, người vận hành mới chủ động chạy:

```powershell
php bin/console doctrine:migrations:migrate
```

| Migration | Thay đổi |
|---|---|
| `DoctrineMigrations\Version20261026000100` | Thêm `THANH_VIEN_USERNAME` VARCHAR(30), collation không phân biệt hoa/thường, NOT NULL, UNIQUE và CHECK; backfill `user_<mã thành viên>`, giữ khóa chính INT |
| `DoctrineMigrations\Version20261026000200` | Thêm `GIO_HANG_LY_DO_TU_CHOI` NVARCHAR(255) NULL, tách khỏi ghi chú khách hàng |

Nếu cột đã tồn tại nhưng version chưa được ghi nhận, **dừng để đối chiếu đầy đủ cột, constraint và metadata**; không chạy lại ALTER, không tự đánh dấu tất cả migration đã chạy. Nếu đã ghi nhận đủ hai version thì không chạy lại. Hai migration không hỗ trợ down vì có thể làm mất dữ liệu username/lý do từ chối.

Không dùng `schema:drop`, `schema:update --force`, `setup.ps1 -Reset` hoặc SQL khởi tạo để xử lý lỗi migration trên database có dữ liệu.

## Xác minh đã thực hiện

- File tổng hợp đã import thành công vào `BanSachDB_ImportCheck_20261027_072307_3ca199` trên SQL Server hiện có, không import vào `BanSachDB` thật.
- Đã đối chiếu metadata cột, kiểu dữ liệu, nullable, collation, khóa/index, khóa ngoại và các ràng buộc; chạy thử đăng ký, provider đăng nhập User/Admin, catalog, CRUD giỏ, duyệt/từ chối/hủy và rollback trừ kho. Fixture được rollback, sáu bảng nghiệp vụ của database thử được giữ rỗng.
- Kiểm tra tiếp bằng Doctrine: `DB_NAME()` đúng tên database thử; `status` báo Executed=2, Available=2, New=0; `list` báo cả hai version `migrated`; `migrate --dry-run --no-interaction` báo đã ở version mới nhất, không có ALTER cần chạy.
- Database thử được giữ lại. Không tạo lại schema, không xóa database thử, không đổi cấu hình `.env.local` hay dữ liệu `BanSachDB` thật trong bước kiểm tra tiếp này; DATABASE_URL chỉ được thay cho tiến trình kiểm tra.
- Chưa kiểm thử thao tác import bằng giao diện SSMS trên máy khác; lần import thực tế đã chạy qua driver SQL Server của PHP. Các lệnh SSMS ở trên là hướng dẫn thực hiện.
