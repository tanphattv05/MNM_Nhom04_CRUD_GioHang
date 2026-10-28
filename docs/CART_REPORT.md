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

[README mục 08](../README.md#08-cài-đặt-và-chạy-dự-án) là hướng dẫn chính; [README_DATABASE](../database/README_DATABASE.md) phân biệt cài mới/nâng cấp. Môi trường xác minh là PHP 8.4.26, `pdo_sqlsrv` 5.13.3, Symfony 7.4.21 và instance SQL Server mặc định local. Không yêu cầu máy khác dùng cùng tên host.

Trạng thái migration hiện tại ngày 28/10: 4 executed, 4 available, 0 new. Đã sao lưu COPY_ONLY và kiểm tra RESTORE VERIFYONLY trước khi áp dụng migration đổi mã thành viên/điện thoại; chạy thử migration trong transaction rồi rollback trước lần áp dụng thật. Số thử 11 chữ số của tài khoản `gggg` được đổi sang `0000000000` theo xác nhận. Fingerprint chuẩn hóa xác nhận các dữ liệu khác và giá trị mã liên kết được giữ nguyên. `.env.local` sinh riêng cho máy và bị Git bỏ qua.

Phần văn bản có thể đưa trực tiếp vào báo cáo gồm [1.1 Thiết kế GIO_HANG, 1.2 Tạo cơ sở dữ liệu và 1.3 Cấu hình môi trường](DATABASE_REPORT.md).

Tài khoản SQL mẫu cũ có thể chứa mật khẩu văn bản. Không tự reset tài khoản thật để demo. Kiểm tra trước bằng lệnh chỉ đọc:

```powershell
php bin/console app:account:password user 1001
php bin/console app:account:password admin admin
```

Sau sao lưu và chọn đúng tài khoản mẫu được phép, chuyển mật khẩu hiện có thành hash bằng `… user 1001 --convert-legacy --apply` hoặc `… admin admin --convert-legacy --apply`. Lệnh bỏ qua tài khoản đã hash, không in mật khẩu, chỉ ghi trong dev. Đặt mật khẩu riêng bằng `php bin/console app:account:password admin admin --apply` với nhập ẩn. Việc này là thao tác vận hành chủ động, không chạy khi web khởi động.

## 10. Đánh giá và hướng phát triển

Đạt đủ 10 chức năng Cart trong checklist theo kiến trúc session + SQL Server hiện tại. Những cải tiến mới giải quyết sai lệch giữa giao diện checkout và xử lý server, loại bỏ cắt âm thầm dữ liệu nhận hàng và bổ sung bằng chứng có thể chạy lại.

Hạn chế cần nêu rõ:

- Chưa lưu đơn giá lúc đặt; lịch sử thay đổi tổng theo giá hiện tại. Ưu tiên thêm đơn giá bất biến trên dòng, dữ liệu giao nhận riêng và migration giữ tương thích.
- Ghi nhận COD chưa có trạng thái thu tiền/giao hàng độc lập; chưa có gateway/callback, email hoặc phí hỏa tốc.
- Giỏ nháp phụ thuộc session, mất sau logout/hết hạn; chưa đồng bộ nhiều thiết bị.
- `MAX+1` có khóa bảng phù hợp quy mô đồ án nhưng giới hạn throughput; chưa đo tải lớn hoặc nhiều kết nối cạnh tranh.
- Chưa có module quản lý sách/loại sách; chưa xác minh cài mới qua GUI SSMS trên máy khác trong đợt này.

Bài học: mô tả đúng DBAL/session đang dùng; không suy diễn từ kiến trúc tham khảo; kiểm tra giá/kho ở server và kiểm tra lại khi ghi; chỉ đánh dấu PASS khi có lần chạy và log tương ứng.
