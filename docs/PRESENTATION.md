# Kịch bản thuyết trình · CRUD Giỏ hàng – Symfony MVC

**Nhóm 04 – Lớp DA23TTA.** Kịch bản dành cho một người đại diện trình bày trong khoảng **10–12 phút**, gồm giới thiệu, giải thích thiết kế và demo trực tiếp. Các câu trong phần trích dẫn là lời có thể nói trước hội đồng; phần **Thao tác** là hướng dẫn điều khiển màn hình.

Nội dung bám theo [README](../README.md), tập trung vào CRUD của `GIO_HANG` và các dòng `MUA`.

## Chuẩn bị trước buổi trình bày

Mở sẵn README ở chế độ Preview, ứng dụng đang chạy, trình soạn thảo mã nguồn và SSMS. Chuẩn bị hai phiên trình duyệt riêng: một phiên có quyền khách hàng, một phiên có quyền quản trị viên.

| Dữ liệu | Mục đích |
|---|---|
| Giỏ nháp có một sách, số lượng 2; sách còn đủ kho để tăng lên 3 | Tạo giỏ A trực tiếp, sau đó xem, sửa, hủy và xóa |
| Giỏ B đang Chờ duyệt, các sách còn đủ kho | Minh họa duyệt giỏ và kiểm tra tồn kho |
| Giỏ C đang Chờ duyệt, nếu có thêm thời gian | Minh họa từ chối và lưu lý do |

Ghi lại mã giỏ B, C và số lượng tồn trước khi demo. Giỏ A sẽ được cấp mã lúc gửi; ghi lại mã này để dùng xuyên suốt phần CRUD. Dùng dữ liệu riêng cho buổi trình bày.

| Phần README | Nội dung trình bày | Thời gian dự kiến |
|---|---|---|
| 01 | Chào hội đồng, giới thiệu nhóm và phạm vi CRUD | 1 phút |
| 02 | Kiến trúc MVC và luồng xử lý | 1 phút |
| 03 | Cấu trúc `GIO_HANG`, `MUA` và các liên kết | 1 phút |
| 04–05 | Quy tắc nghiệp vụ và route | 1 phút |
| 06 | Cách kiểm thử | 30 giây |
| 07 | Demo Create, Read, Update, Delete và trạng thái | 4–5 phút |
| 08 | Cách cài đặt và chạy module | 30 giây |
| 09 | Kết quả, hạn chế và hướng phát triển | 1 phút |

## 1. Mở đầu và giới thiệu phạm vi — README mục 01

**Thao tác:** mở đầu README và bảng bốn thao tác CRUD.

> Kính thưa quý thầy cô trong hội đồng. Em xin đại diện Nhóm 04, lớp DA23TTA, trình bày đề tài CRUD Giỏ hàng được xây dựng bằng Symfony MVC và Microsoft SQL Server.
>
> Mục tiêu của nhóm là thực hiện bốn thao tác tạo, xem, cập nhật và xóa giỏ hàng. Đối tượng quản lý chính là bảng GIO_HANG; thông tin sách và số lượng của mỗi giỏ được lưu trong bảng MUA.
>
> Khách hàng thực hiện thao tác trên giỏ của mình. Quản trị viên được xem và xử lý các giỏ theo trạng thái. Phần trình bày của em sẽ đi từ thiết kế, cách xử lý dữ liệu đến minh họa trực tiếp từng thao tác CRUD.
>
> Nhóm phân công các công việc theo chức năng tạo, xem, cập nhật, xóa giỏ, cùng với thiết kế cơ sở dữ liệu, giao diện, kiểm thử và biên soạn tài liệu. Chi tiết thành viên và nhiệm vụ được trình bày trong bảng này.

## 2. Giải thích kiến trúc — README mục 02

**Thao tác:** mở ảnh kiến trúc; chỉ vào phần Controller, Service và tầng dữ liệu.

> Về công nghệ, nhóm sử dụng PHP và Symfony để xử lý ứng dụng, Twig để hiển thị giao diện, Doctrine DBAL để truy vấn SQL Server.
>
> Khi người dùng thực hiện một thao tác, Controller nhận yêu cầu và kiểm tra biểu mẫu. CartService xử lý các quy tắc như số lượng, tồn kho, quyền sở hữu và trạng thái giỏ. CartRepository đảm nhiệm các truy vấn đọc danh sách và chi tiết; các thao tác ghi hiện được thực hiện trong CartService thông qua kết nối DBAL.
>
> Giỏ có hai giai đoạn lưu. Khi đang soạn, sách và số lượng được lưu tạm trong session. Khi gửi giỏ, ứng dụng lưu thông tin chung vào GIO_HANG và các dòng chi tiết vào MUA. Nhờ vậy, giỏ đã gửi có thể được xem lại từ cơ sở dữ liệu.

Nếu cần chỉ mã nguồn, mở [GioHangController.php](../src/Controller/GioHangController.php), [CartService.php](../src/Service/CartService.php) và [CartRepository.php](../src/Repository/CartRepository.php).

Phần kiến thức để học và trả lời câu hỏi: [Symfony và kiến trúc MVC trong module](SYMFONY_MVC.md). Tài liệu giải thích từng thành phần, luồng xem/sửa giỏ, Dependency Injection và vai trò của `CustomExceptionListener`.

## 3. Trình bày cơ sở dữ liệu — README mục 03

**Thao tác:** mở ERD rồi quay về bảng cấu trúc `GIO_HANG`. Khi cần đối chiếu cột triển khai, dùng [sơ đồ vật lý](diagrams/database-erd.png).

> Bảng GIO_HANG lưu mã giỏ, mã thành viên sở hữu, quản trị viên xử lý, thời gian tạo, trạng thái, ghi chú và lý do từ chối.
>
> Mã giỏ là khóa chính kiểu INT. Mã thành viên trong giỏ là khóa ngoại kiểu VARCHAR(10), cùng kiểu với khóa được tham chiếu ở bảng THANH_VIEN. Thông tin quản trị viên có thể để trống khi giỏ chưa được xử lý.
>
> Một giỏ có thể chứa nhiều dòng sách, nên nhóm tách các dòng này vào bảng MUA. Bảng MUA có khóa chính ghép gồm mã thành viên, mã sách và mã giỏ, kèm theo số lượng mua.
>
> Các khóa ngoại liên kết giỏ với chủ giỏ, người xử lý và sách tương ứng. Những trường cần lưu nội dung tiếng Việt như trạng thái và ghi chú sử dụng NVARCHAR.

## 4. Quy tắc xử lý và route — README mục 04–05

**Thao tác:** chỉ vào các quy tắc tạo, sửa, hủy, xóa và bảng route.

> Các thao tác CRUD được kiểm tra ở phía server. Ví dụ, số lượng phải là số nguyên dương và không vượt tồn kho. Giá được lấy từ cơ sở dữ liệu để tính tổng.
>
> Khách hàng chỉ được xem hoặc thao tác trên giỏ của mình. Giỏ đã gửi chỉ được sửa hoặc hủy khi đang Chờ duyệt. Quản trị viên duyệt giỏ mới trừ tồn kho; từ chối cần có lý do riêng.
>
> Hủy giỏ là đổi trạng thái sang Đã hủy và giữ lịch sử. Xóa giỏ là xóa các dòng MUA cùng bản ghi GIO_HANG, và chỉ áp dụng với giỏ Đã hủy hoặc Từ chối.
>
> Về route, yêu cầu GET dùng để mở danh sách, chi tiết và biểu mẫu. Các thao tác làm thay đổi dữ liệu dùng POST và có kiểm tra CSRF. Khi tạo hoặc xóa một giỏ, những bước ghi liên quan được thực hiện trong cùng transaction: tất cả thành công mới lưu, có lỗi thì rollback.

## 5. Giới thiệu kiểm thử — README mục 06

**Thao tác:** mở [test-cases.md](test-cases.md) tại nhóm kiểm thử giỏ hàng hoặc log tương ứng.

> Nhóm kiểm thử cả các trường hợp thành công và dữ liệu không hợp lệ. Các trường hợp tiêu biểu gồm tạo giỏ có nhiều dòng, cập nhật số lượng, truy cập giỏ không thuộc quyền sở hữu, thao tác sai trạng thái và lỗi ghi dữ liệu cần rollback.
>
> PHPUnit kiểm tra nghiệp vụ và luồng HTTP với kết nối giả lập. Các kiểm thử tích hợp dùng SQL Server thật, tạo dữ liệu tạm trong transaction rồi hoàn tác. Kết quả và log của từng đợt được lưu trong tài liệu kiểm thử.

Khi được hỏi số lượng test, mở log và nêu đúng phạm vi của lần chạy đó. Các tổng số trong tài liệu kiểm thử bao gồm nhiều nhóm kiểm tra của repository.

## 6. Demo trực tiếp — README mục 07

### 6.1. Create — Tạo giỏ A

**Thao tác:** ở phiên khách hàng, mở `/gio-hang` với giỏ nháp đã chuẩn bị, có một sách và số lượng 2.

> Đây là giỏ đang soạn. Hiện tại dữ liệu này nằm trong session, chưa tạo bản ghi giỏ đã gửi trong cơ sở dữ liệu. Em sẽ gửi giỏ để minh họa thao tác Create.

**Thao tác:** mở **Hoặc gửi nhanh giỏ hàng**, nhập ghi chú `Giỏ minh họa CRUD – Nhóm 04`, nhấn **Gửi giỏ hàng**. Sau thông báo thành công, mở `/gio-hang/lich-su` và ghi lại mã giỏ A mới tạo.

> Khi gửi thành công, hệ thống tạo một bản ghi GIO_HANG ở trạng thái Chờ duyệt và một dòng MUA chứa mã sách, chủ giỏ, mã giỏ và số lượng 2. Hai phần được lưu trong cùng transaction. Sau khi lưu thành công, giỏ nháp được xóa khỏi session. Tồn kho chưa bị trừ ở bước này.

### 6.2. Read — Xem danh sách và chi tiết

**Thao tác:** tại `/gio-hang/lich-su`, chọn bộ lọc **Chờ duyệt**, nhấn **Lọc giỏ hàng**, rồi mở **Xem chi tiết** của giỏ A.

> Tiếp theo là thao tác Read. Danh sách hiển thị các giỏ thuộc người dùng hiện tại và có thể lọc theo trạng thái. Khi mở giỏ A, hệ thống hiển thị mã giỏ, thời gian, trạng thái, ghi chú và các dòng sách.
>
> Tổng tiền được tính từ số lượng của từng dòng và giá sách đang có trong cơ sở dữ liệu. Các truy vấn của khách hàng có điều kiện theo chủ giỏ để giới hạn dữ liệu được xem.

### 6.3. Update — Sửa số lượng và ghi chú

**Thao tác:** nhấn **Sửa giỏ hàng** của giỏ A. Nhập số lượng `0`, nhấn **Lưu thay đổi** để minh họa báo lỗi; sau đó đổi thành `3`, sửa ghi chú thành `Đã cập nhật giỏ minh họa`, nhấn **Lưu thay đổi**.

> Trước tiên, em nhập số lượng bằng 0. Hệ thống báo dữ liệu không hợp lệ và không lưu thay đổi này.
>
> Khi đổi sang số lượng 3, vẫn trong giới hạn tồn kho, đồng thời cập nhật ghi chú, hệ thống lưu thành công. Trạng thái giỏ vẫn là Chờ duyệt; dữ liệu số lượng và ghi chú được thay đổi, tổng tiền được tính lại.
>
> Nếu đơn giá đang hiển thị là P thì trước khi sửa, dòng này có thành tiền 2 nhân P; sau khi sửa là 3 nhân P. Việc sửa giỏ cũng chưa làm giảm tồn kho.

**Thao tác:** tải lại trang chi tiết để cho thấy dữ liệu cập nhật được đọc lại từ database.

### 6.4. Delete — Hủy và xóa giỏ A

**Thao tác:** ở chi tiết giỏ A, đánh dấu ô **Tôi xác nhận hủy giỏ**, nhấn **Hủy giỏ hàng**. Quan sát trạng thái **Đã hủy**.

> Đây là bước hủy giỏ. Hệ thống đổi trạng thái sang Đã hủy, nhưng vẫn giữ bản ghi và các dòng chi tiết để lưu lịch sử. Vì vậy, bước này chưa phải là xóa dữ liệu khỏi cơ sở dữ liệu.

**Thao tác:** chuyển sang phiên Admin, mở `/admin/gio-hang`, tìm giỏ A theo mã, mở chi tiết, đánh dấu ô **Tôi xác nhận xóa vĩnh viễn giỏ**, rồi nhấn **Xóa vĩnh viễn**.

> Với quyền quản trị viên, em thực hiện xóa giỏ đã hủy. Ứng dụng xóa các dòng MUA trước rồi xóa GIO_HANG trong cùng transaction để bảo đảm ràng buộc khóa ngoại. Sau khi hoàn tất, giỏ A không còn trong danh sách và dữ liệu liên quan đã được xóa.

### 6.5. Minh họa cập nhật trạng thái với giỏ B

**Thao tác:** mở giỏ B đang Chờ duyệt ở phiên Admin, nhấn **Duyệt** và đối chiếu tồn kho với số đã ghi trước demo.

> Việc duyệt là một thao tác cập nhật trạng thái. Hệ thống kiểm tra lại trạng thái và tồn kho, sau đó trừ kho và chuyển giỏ sang Đã duyệt trong cùng transaction. Giỏ đã duyệt được bảo vệ khỏi các thao tác sửa, hủy hoặc xóa theo quy tắc hiện tại.

Nếu còn thời gian, mở giỏ C, nhập lý do và nhấn **Từ chối**:

> Với trường hợp từ chối, hệ thống lưu lý do vào cột riêng, giữ nguyên ghi chú của khách và không trừ kho. Giỏ Từ chối thuộc nhóm trạng thái mà quản trị viên được phép xóa.

## 7. Giới thiệu cách chạy dự án — README mục 08

**Thao tác:** trở lại README mục 08; chỉ vào cấu hình `DATABASE_URL` và lệnh khởi động.

> Để chạy module trên máy mới, cần chuẩn bị PHP, Composer, SQL Server và driver kết nối phù hợp. Sau khi cài thư viện, cấu hình DATABASE_URL trong tệp .env.local và tạo cơ sở dữ liệu theo tệp SQL của dự án.
>
> Tiếp theo là kiểm tra kết nối, trạng thái migration, cài tài nguyên giao diện và chạy PHP server. Phần hướng dẫn trong README có các lệnh cụ thể để người khác có thể thực hiện lại.

Lệnh có thể chỉ trên màn hình:

```powershell
php bin/console doctrine:query:sql "SELECT DB_NAME() AS database_name"
php bin/console doctrine:migrations:status
php -S 127.0.0.1:8000 -t public public/index.php
```

## 8. Đánh giá và kết thúc — README mục 09

**Thao tác:** mở phần kết quả và hướng phát triển.

> Qua phần minh họa, nhóm đã thực hiện các thao tác tạo, xem, cập nhật và xóa giỏ hàng, kết hợp kiểm tra dữ liệu, quyền sở hữu và trạng thái xử lý. Các thao tác ghi nhiều bước sử dụng transaction để giữ dữ liệu nhất quán.
>
> Module còn một số hạn chế. Tổng tiền hiện được tính theo giá sách hiện tại; giỏ nháp phụ thuộc session; lịch sử các lần thay đổi nội dung giỏ chưa được lưu riêng.
>
> Hướng phát triển của nhóm là lưu đơn giá tại thời điểm tạo giỏ trên từng dòng MUA, lưu giỏ nháp trong database, bổ sung lịch sử cập nhật và kiểm thử nhiều yêu cầu đồng thời để cải tiến cơ chế cấp mã và khóa dữ liệu.
>
> Phần trình bày của Nhóm 04 đến đây là kết thúc. Em xin cảm ơn quý thầy cô đã theo dõi. Nhóm em xin tiếp nhận các câu hỏi và góp ý của hội đồng.

## Câu hỏi hội đồng có thể hỏi

### 1. Đối tượng CRUD chính của nhóm là gì?

> Đối tượng chính là giỏ hàng trong bảng GIO_HANG. Bảng MUA lưu các dòng sách thuộc giỏ và được cập nhật cùng với giỏ. Ví dụ, tạo một giỏ có hai sách sẽ tạo một dòng GIO_HANG và hai dòng MUA.

### 2. Tại sao phải tách GIO_HANG và MUA?

> GIO_HANG lưu thông tin chung của giỏ; MUA lưu từng sách và số lượng. Một giỏ có nhiều dòng sách nên tách hai bảng giúp lưu nhiều dòng mà không lặp lại thời gian, trạng thái và ghi chú ở mỗi dòng.

### 3. Giỏ trong session khác giỏ trong database thế nào?

> Session lưu giỏ đang soạn trong phiên hiện tại. Sau khi gửi thành công, dữ liệu được lưu vào GIO_HANG và MUA để có thể truy xuất lại. Session không phải nơi lưu lịch sử giỏ đã gửi.

### 4. Hủy giỏ có phải là thao tác DELETE trong SQL không?

> Không. Hủy thực hiện UPDATE trạng thái sang Đã hủy và giữ dữ liệu. Thao tác DELETE thực sự do Admin thực hiện trên giỏ Đã hủy hoặc Từ chối, xóa cả các dòng MUA và bản ghi GIO_HANG.

### 5. Vì sao xóa MUA trước GIO_HANG?

> MUA có khóa ngoại tham chiếu GIO_HANG. Schema hiện tại không tự xóa lan truyền nên ứng dụng phải xóa các dòng con trước, rồi xóa giỏ trong cùng transaction.

### 6. Nếu đã lưu GIO_HANG nhưng ghi một dòng MUA bị lỗi thì sao?

> Hai bước nằm trong cùng transaction nên ứng dụng rollback. Kết quả là không giữ lại một giỏ mới bị thiếu dòng chi tiết. Giỏ nháp chỉ bị xóa sau khi transaction tạo giỏ đã thành công.

### 7. Nếu sửa mã giỏ trên URL để xem giỏ của người khác thì sao?

> Ứng dụng kiểm tra quyền ở phía server. Truy vấn dành cho khách hàng có điều kiện chủ giỏ; việc biết mã giỏ không đủ để truy cập hoặc thay đổi giỏ không thuộc quyền sở hữu.

Giải thích chi tiết kèm các đoạn mã Controller, Repository, Service và kiểm thử: [Cách giới hạn quyền xem giỏ hàng theo chủ sở hữu](CART_AUTHORIZATION.md).

### 8. Vì sao QTV_USER_ADMIN được để NULL?

> Giỏ mới đang Chờ duyệt có thể chưa được quản trị viên xử lý. Khi duyệt hoặc từ chối, hệ thống ghi nhận quản trị viên xử lý vào trường này.

### 9. MVC nằm ở đâu khi thư mục Entity không có model nghiệp vụ?

> Controller xử lý yêu cầu HTTP, Twig đảm nhiệm giao diện. Phần dữ liệu và nghiệp vụ của module được tổ chức trong Service, Repository và các bảng SQL Server. Module dùng Doctrine DBAL để truy vấn, không sử dụng Entity ORM cho nghiệp vụ giỏ hàng.

### 10. Người dùng bấm gửi hoặc duyệt lại có tạo trùng hay trừ kho hai lần không?

> Khi gửi giỏ, hệ thống đối chiếu nonce của giỏ nháp; sau khi gửi thành công, giỏ nháp được xóa. Cơ chế này bảo vệ việc gửi lại trong phạm vi phiên đang dùng. Khi duyệt, hệ thống khóa dữ liệu và kiểm tra lại trạng thái Chờ duyệt trong transaction; giỏ đã xử lý không được duyệt lần nữa.

### 11. Cấp mã MAX+1 có bị trùng khi nhiều người cùng tạo giỏ không?

> Với luồng tạo giỏ hiện tại, các yêu cầu được tuần tự hóa tại bước cấp mã nhờ khóa độc quyền trên bảng GIO_HANG trong transaction. Người lấy được khóa trước sẽ tính mã và lưu giỏ; yêu cầu còn lại cũng cần khóa này nên phải chờ. Khi transaction trước hoàn tất, yêu cầu sau mới tính mã theo dữ liệu lúc đó. Nhờ vậy, hai lần tạo giỏ qua luồng này không cùng cấp một mã để lưu thành hai giỏ.
>
> Đổi lại, khóa bảng có thể làm các yêu cầu phải chờ. Nhóm chưa đo tải nhiều kết nối đồng thời; đây là điểm cần kiểm thử và cải tiến khi mở rộng.

**Mã nguồn:** phương thức `submit()` trong [CartService.php](../src/Service/CartService.php). Đoạn SQL cấp mã đang được thực hiện bên trong `$this->db->transactional(...)`:

```sql
SELECT COALESCE(MAX(GIO_HANG_ID_GIO), 0) + 1
FROM dbo.GIO_HANG WITH (TABLOCKX, HOLDLOCK)
```

**Ý nghĩa từng phần:**

- `MAX(GIO_HANG_ID_GIO) + 1`: lấy mã lớn nhất đang có trong bảng rồi cộng 1.
- `COALESCE(..., 0)`: khi bảng chưa có giỏ, giá trị MAX là NULL; thay bằng 0 để mã đầu tiên là 1.
- `TABLOCKX`: yêu cầu khóa độc quyền ở cấp bảng. Hai phiên cùng yêu cầu khóa độc quyền trên bảng này không thể giữ khóa đồng thời.
- `HOLDLOCK`: áp dụng mức bảo vệ tương đương SERIALIZABLE cho bảng được chỉ định trong transaction.
- `transactional(...)`: bao quanh bước tính mã, kiểm tra dữ liệu và ghi cả `GIO_HANG`, `MUA`. Khóa được giữ trong quá trình này; sau commit hoặc rollback, phiên tạo giỏ khác mới có thể lấy khóa tương ứng.

Ngữ nghĩa các hint được mô tả trong [tài liệu Table Hints của SQL Server](https://learn.microsoft.com/en-us/sql/t-sql/queries/hints-transact-sql-table?view=sql-server-2017). Việc chờ khi hai phiên yêu cầu các khóa xung đột được giải thích trong [tài liệu SQL Server Blocking](https://learn.microsoft.com/en-us/troubleshoot/sql/database-engine/performance/understand-resolve-blocking).

**Ví dụ hai người cùng gửi giỏ:** giả sử mã lớn nhất đang có là 100 và phiên A lấy được khóa trước. Đây là ví dụ suy ra từ luồng code, không phải kết quả đo tải thực tế.

| Thời điểm | Phiên A | Phiên B |
|---|---|---|
| 1 | Mở transaction, lấy khóa độc quyền trên `GIO_HANG` | Bắt đầu yêu cầu tạo giỏ |
| 2 | Đọc MAX = 100, tính mã mới = 101 | Đến câu SELECT có `TABLOCKX`, chờ khóa |
| 3 | Kiểm tra sách, ghi giỏ 101 và các dòng `MUA` | Tiếp tục chờ |
| 4 | Commit, giải phóng khóa | Được lấy khóa sau khi A hoàn tất |
| 5 | Giỏ 101 đã được lưu | Đọc MAX = 101, tính mã mới = 102 |
| 6 | — | Ghi giỏ 102, các dòng `MUA`, rồi commit |

Điểm quyết định là B phải lấy khóa **trước khi đọc MAX**. B không được tính sẵn 101 rồi chờ đến bước INSERT. Thứ tự cấp mã phụ thuộc vào việc lấy khóa tại SQL Server.

**Nếu chỉ dùng MAX+1 mà không có khóa phù hợp:** A và B có thể cùng đọc MAX = 100 và cùng tính 101. Một lần INSERT có thể thành công; lần còn lại bị khóa chính từ chối vì trùng mã. Vì vậy, phép cộng MAX+1 tự nó không giải quyết việc cấp mã đồng thời.

**Nếu A tạo giỏ thất bại:** khi có ngoại lệ khiến transaction rollback, giỏ 101 và các dòng đã ghi trong transaction đó không được giữ lại. Sau khi lấy được khóa, B có thể vẫn đọc MAX = 100 và sử dụng 101. Đây là dùng một mã chưa được lưu thành công.

**Khóa chính là lớp ràng buộc bổ sung.** Trong [BanSachDB_Full.sql](../database/BanSachDB_Full.sql), bảng giỏ có:

```sql
CONSTRAINT PK_GIO_HANG PRIMARY KEY CLUSTERED (GIO_HANG_ID_GIO)
```

Ràng buộc này ngăn hai bản ghi đang tồn tại có cùng mã. Nếu một đường ghi khác cố chèn trùng mã, SQL Server báo lỗi; nó không tự chọn mã khác để chèn lại.

**Phân biệt với nonce và khóa session:** nonce kiểm tra việc gửi lại giỏ nháp; khóa session tuần tự hóa yêu cầu trong cùng phiên. Việc nhiều người với các phiên khác nhau cùng tạo giỏ được điều phối bằng khóa tại SQL Server như trên.

**Giới hạn của cách hiện tại:**

- Các yêu cầu tạo giỏ có thể phải chờ nhau đến khi transaction giữ khóa kết thúc. Chưa có số đo thời gian chờ, tải đồng thời và deadlock trong đợt kiểm thử được lưu tại [test-cases.md](test-cases.md#chưa-kiểm-thử-trong-đợt-này).
- Việc không lưu trùng mã không đồng nghĩa mọi yêu cầu đều chắc chắn thành công: thao tác vẫn có thể thất bại do dữ liệu, kết nối hoặc lỗi database.
- MAX+1 dựa trên dữ liệu đang có, nên nếu giỏ mang mã lớn nhất bị xóa, mã đó có thể được cấp lại. Cơ chế này không bảo đảm mã không bao giờ được sử dụng lại trong suốt lịch sử hệ thống.
- Hướng cải tiến là dùng cơ chế cấp số của SQL Server như [IDENTITY](https://learn.microsoft.com/en-us/sql/t-sql/statements/create-table-transact-sql-identity-property?view=sql-server-ver17) hoặc [SEQUENCE](https://learn.microsoft.com/en-us/sql/t-sql/statements/create-sequence-transact-sql?view=sql-server-ver17), kết hợp khóa chính và migration phù hợp. Các cơ chế cấp số này vẫn có thể tạo khoảng trống trong dãy mã.

### 12. Vì sao tổng tiền giỏ cũ có thể thay đổi?

> MUA hiện chưa lưu đơn giá tại thời điểm gửi. Tổng tiền được tính bằng số lượng nhân với giá sách hiện tại trong SACH. Nhóm xác định đây là hạn chế và đề xuất bổ sung đơn giá vào từng dòng để giữ giá trị lịch sử.

### 13. THANH_VIEN_TEN_DANG_NHAP có phải username đang dùng không?

> Trong schema hiện tại, trường này đóng vai trò mã định danh thành viên kiểu VARCHAR(10), được tham chiếu từ GIO_HANG và MUA. Giá trị username dùng trên giao diện được lưu ở một cột riêng. Với module giỏ hàng, các liên kết được xác định bằng mã thành viên.

### 14. Vì sao schema cho phép chủ giỏ NULL nhưng giỏ mới vẫn phải có chủ?

> Cột chủ giỏ cho phép NULL để tương thích dữ liệu lịch sử. Khi tạo giỏ bằng ứng dụng, CartService lấy chủ giỏ từ người dùng hiện tại và gán vào bản ghi; người gửi không tự chọn chủ giỏ qua biểu mẫu.

### 15. Vì sao sử dụng Doctrine DBAL? Có lợi gì so với PDO thuần hoặc ORM?

> DBAL phù hợp với cách nhóm đang truy vấn SQL trực tiếp trên schema GIO_HANG và MUA. So với PDO thuần, DBAL có các hàm lấy dữ liệu gọn và được sử dụng qua cơ chế dịch vụ của Symfony. Nhóm cũng thuận tiện điều phối transaction và viết SQL có khóa dữ liệu riêng của SQL Server.
>
> So với ORM, module chưa cần xây dựng ánh xạ Entity và quản lý trạng thái đối tượng, nhưng nhóm phải tự viết SQL và kiểm tra nghiệp vụ. PDO và ORM cũng hỗ trợ tham số và transaction; nhóm lựa chọn DBAL theo cách tổ chức của module, không dựa trên khẳng định công cụ này luôn nhanh hoặc an toàn hơn.

Giải thích, bảng so sánh và ví dụ mã: [Vì sao sử dụng Doctrine DBAL trong module](SYMFONY_MVC.md#vì-sao-sử-dụng-doctrine-dbal-trong-module).

## Câu lệnh SQL đối chiếu khi hội đồng yêu cầu

Trong SSMS, thay `0` bằng mã giỏ A vừa demo. Trước khi xóa có thể xem giỏ và các dòng; sau khi xóa, hai câu SELECT này không còn trả về bản ghi của giỏ A.

```sql
USE [BanSachDB];
GO

DECLARE @MaGio INT = 0; -- Thay 0 bang ma gio vua demo

SELECT GIO_HANG_ID_GIO,
       THANH_VIEN_TEN_DANG_NHAP,
       QTV_USER_ADMIN,
       GIO_HANG_THOI_GIAN,
       GIO_HANG_TRANG_THAI,
       GIO_HANG_GHI_CHU,
       GIO_HANG_LY_DO_TU_CHOI
FROM dbo.GIO_HANG
WHERE GIO_HANG_ID_GIO = @MaGio;

SELECT GIO_HANG_ID_GIO,
       THANH_VIEN_TEN_DANG_NHAP,
       SACH_ID_SACH,
       MUA_SO_LUONG_MUA
FROM dbo.MUA
WHERE GIO_HANG_ID_GIO = @MaGio;
```

## Bản nhắc nhanh khi thuyết trình

**Giới thiệu nhóm → bốn thao tác CRUD → MVC → GIO_HANG/MUA → quy tắc trạng thái → kiểm thử → tạo A → xem A → sửa A → hủy A → Admin xóa A → duyệt B → cách chạy → hạn chế và hướng phát triển → cảm ơn hội đồng.**
