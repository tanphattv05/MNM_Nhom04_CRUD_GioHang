# Kiểm thử và kết quả thực tế

## Kết quả mới nhất: migration mã thành viên và điện thoại

Ngày 28/10/2026 đã áp dụng `Version20261028000100`: mã thành viên ở THANH_VIEN/GIO_HANG/MUA là VARCHAR(10), điện thoại VARCHAR(10) với CHECK đúng 10 chữ số. Đã tạo backup COPY_ONLY, kiểm tra RESTORE VERIFYONLY, chạy thử migration trong transaction rồi rollback trước lần áp dụng thật. Số thử của tài khoản `gggg` được sửa theo xác nhận; fingerprint chuẩn hóa xác nhận các dữ liệu khác giữ nguyên.

Log đợt mới nhất nằm trong [evidence/2026-10-28-member-schema](evidence/2026-10-28-member-schema).

| Nhóm | Kết quả thực tế sau nâng cấp | Bằng chứng |
|---|---|---|
| PHPUnit | **72 tests, 248 assertions**, PASS | [log](evidence/2026-10-28-member-schema/phpunit.log) |
| Twig / YAML / container | 19 template, 24 YAML và DI hợp lệ | [Twig](evidence/2026-10-28-member-schema/twig.log), [YAML](evidence/2026-10-28-member-schema/yaml.log), [container](evidence/2026-10-28-member-schema/container.log) |
| Tài khoản và phone | **63 checks**, PASS, rollback; 9/11 chữ số và ký tự chữ bị từ chối ở form hồ sơ | [log](evidence/2026-10-28-member-schema/phase2_smoke.log) |
| Catalog/tạo giỏ | 32 checks, PASS, rollback | [log](evidence/2026-10-28-member-schema/phase3_smoke.log) |
| Đọc giỏ | 67 checks, PASS, không ghi DB | [log](evidence/2026-10-28-member-schema/phase4_smoke.log) |
| Duyệt/hủy/xóa | 31 checks, PASS, rollback | [log](evidence/2026-10-28-member-schema/phase7_smoke.log) |
| Checkout COD | 25 checks, PASS, rollback | [log](evidence/2026-10-28-member-schema/cart_checkout_smoke.log) |
| Schema/khóa chuỗi | **25 checks**, PASS: 3 cột khóa VARCHAR(10), phone, FK/PK, key có chữ, provider, tạo/sửa/hủy giỏ và rollback | [log](evidence/2026-10-28-member-schema/member_schema_smoke.log) |
| Dịch vụ/tiền/logout/password input | Các nhóm còn lại đều PASS | [phase5](evidence/2026-10-28-member-schema/phase5_smoke.log), [phase6](evidence/2026-10-28-member-schema/phase6_smoke.log), [money](evidence/2026-10-28-member-schema/money_smoke.log), [logout](evidence/2026-10-28-member-schema/logout_smoke.log), [password input](evidence/2026-10-28-member-schema/password_input_smoke.log) |
| Migration / dữ liệu | Executed=4, Available=4, New=0; dữ liệu giữ nguyên ngoài phone đã duyệt | [status](evidence/2026-10-28-member-schema/migrations.log), [integrity](evidence/2026-10-28-member-schema/data-integrity.log), [apply](evidence/2026-10-28-member-schema/migration-apply.log) |
| Cài mới | Đủ 4 baseline, đăng ký và tạo giỏ bằng key chuỗi hoạt động; fixture rollback | [log](evidence/2026-10-28-member-schema/fresh-schema.log) |

`tests/member_schema_smoke.php --rehearsal` đã đạt 27 check trên schema INT cũ trước áp dụng, toàn bộ DDL/fixture rollback. Sau khi đã nâng cấp, chạy lệnh này **không có** `--rehearsal`. File `scripts/check.ps1 -WithDatabase` đã bao gồm test này.

Các kết quả ở phần dưới là **lịch sử trước migration đổi kiểu**, giữ lại cùng log tương ứng; không dùng số 68 test hoặc 3 migration bên dưới để mô tả bản hiện tại.

## Đợt kiểm tra trước migration đổi kiểu

Ngày chạy: **28/10/2026**, múi giờ Asia/Saigon. Thư mục kiểm tra: repository ngoài cùng. Môi trường thực tế: PHP 8.4.26 x64, Symfony 7.4.21, `pdo_sqlsrv` 5.13.3, SQL Server local, database phát triển `BanSachDB`.

## Cách chạy lại

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/check.ps1 -WithDatabase
```

Lệnh thứ hai bổ sung test SQL Server thật, cần schema đúng và dữ liệu mẫu đủ cho từng test. `phase3/phase7` cần hai sách còn hàng; `phase7` cần hai khách hàng và một Admin. Chạy tuần tự trên database phát triển, không thao tác dữ liệu đồng thời. Các test ghi dữ liệu dùng transaction và rollback; không có kết nối thì báo lỗi, không được coi là PASS.

Log chạy lại được lưu tại `var/checks/`. Log đợt kiểm tra này được lưu riêng trong [evidence/2026-10-28](evidence/2026-10-28). `phpunit.log` có đường dẫn workspace của lần chạy; không có mật khẩu/tokens.

## Kết quả theo nhóm

| Lệnh/nhóm | Actual result | Kết quả | Phạm vi và bằng chứng |
|---|---|---|---|
| PHPUnit | **68 tests, 243 assertions** | PASS | Service và HTTP kernel, connection giả lập; [log](evidence/2026-10-28/phpunit.log) |
| `lint:twig templates` | 19 template hợp lệ | PASS | [log](evidence/2026-10-28/twig.log) |
| `lint:yaml config` | 24 file YAML hợp lệ | PASS | [log](evidence/2026-10-28/yaml.log) |
| `lint:container` | Dependency injection hợp lệ | PASS | [log](evidence/2026-10-28/container.log) |
| `phase5_smoke` | 49 service checks | PASS | Connection giả lập, trạng thái/duyệt/rollback/thứ tự khóa; [log](evidence/2026-10-28/phase5_smoke.log) |
| `phase6_smoke` | 47 service checks | PASS | Connection giả lập, hủy/xóa/phân quyền; [log](evidence/2026-10-28/phase6_smoke.log) |
| `money_smoke` | 18 checks | PASS | Định dạng tiền, NULL/biên; [log](evidence/2026-10-28/money_smoke.log) |
| `password_input_smoke` | Retry, whitespace, confirmation, cancellation, no secret output | PASS | Terminal giả lập, không ghi DB; [log](evidence/2026-10-28/password_input_smoke.log) |
| `phase2_smoke` | 60 checks, rollback | PASS | SQL thật, đăng ký/login/hồ sơ/password/throttling; [log](evidence/2026-10-28/phase2_smoke.log) |
| `phase3_smoke` | 32 checks, rollback | PASS | SQL thật, catalog/giỏ session/tạo giỏ, lỗi ghi dòng thứ hai; [log](evidence/2026-10-28/phase3_smoke.log) |
| `phase4_smoke` | 65 checks, không ghi DB | PASS | HTTP đọc SQL thật; thêm case Twig NULL/XSS độc lập; [log](evidence/2026-10-28/phase4_smoke.log) |
| `logout_smoke` | 46 checks, không ghi DB | PASS | HTTP kernel/session/CSRF, tài khoản từ SQL thật; [log](evidence/2026-10-28/logout_smoke.log) |
| `phase7_smoke` | 31 SQL checks, rollback | PASS | CRUD/duyệt/xóa, duyệt lại, rollback sau trừ kho dòng đầu; [log](evidence/2026-10-28/phase7_smoke.log) |
| `cart_checkout_smoke` | 25 SQL checks, rollback | PASS | Form COD, dữ liệu nhận hàng, kho đổi, tổng server, replay; [log](evidence/2026-10-28/cart_checkout_smoke.log) |
| Composer platform | Tất cả yêu cầu được đáp ứng | PASS | [log](evidence/2026-10-28/platform.log) |
| Migration status/dry-run | Executed=3, Available=3, New=0; already latest | PASS | [status](evidence/2026-10-28/migrations.log), [dry-run](evidence/2026-10-28/migrations-dry-run.log) |

Số check trong smoke test đọc có thể thay đổi theo số giỏ đã tồn tại; bảng ghi đúng kết quả của dữ liệu tại lần chạy này. `phase2` có nhánh thử migration username trong transaction nếu cột chưa tồn tại; lần này đủ schema nên không cần nhánh đó.

## Test case đối chiếu checklist và các lỗi bổ sung

| ID | Đầu vào/thao tác | Mong đợi | Actual result | Kết quả | Bằng chứng |
|---|---|---|---|---|---|
| TC01 | Thêm 1 sách còn hàng | Có một dòng trong giỏ | POST redirect về giỏ, sách hiển thị | PASS | `phase3`, `cart_checkout`; [ảnh](screenshots/add-to-cart.png) |
| TC02 | Thêm cùng sách 1 rồi 2 cuốn | Gộp một dòng số lượng 3 | Session giữ 1 dòng, lượng 3 qua GET tiếp theo | PASS | `CartDraftTest`, `cart_checkout`; [ảnh](screenshots/add-to-cart.png) |
| TC03 | Update số lượng 0/âm | Từ chối, giữ giỏ cũ | Giỏ và nonce không đổi | PASS | `CartDraftTest`, `phase3` |
| TC04 | Kho 10, update 11 hoặc add vượt kho | Từ chối | DomainException, không đổi session | PASS | `CartDraftTest`; `phase3` với kho thật |
| TC05 | Bỏ một sách khỏi giỏ | Dòng biến mất, tính lại tổng | Dòng không còn; xóa dòng cuối cho tổng 0 | PASS | `CartDraftTest`, `phase3` |
| TC06 | Giỏ trống vào checkout | Chặn và quay lại giỏ | GET redirect `/gio-hang`, không ghi DB | PASS | `CartCheckoutWebTest` |
| TC07 | Gửi `price=1`, owner khác, `status=Đã duyệt` | Bỏ payload không tin cậy | Tổng từ SACH, owner phiên hiện tại, Chờ duyệt | PASS | `CartCheckoutWebTest`, `cart_checkout` |
| TC08 | Kho giảm sau khi thêm | Kiểm tra lại ở GET/POST checkout | GET bị chuyển về giỏ; POST 422; không tạo giỏ | PASS | `CartCheckoutWebTest`, `cart_checkout` |
| TC09 | Tải lại giỏ bằng GET | Giữ sách/số lượng | Lượng vẫn 3 rồi 4; không ảnh hưởng thành viên khác | PASS | `CartDraftTest`, `cart_checkout`; [ảnh](screenshots/update-cart.png) |
| TC10 | Điện thoại `.........` | Không coi dấu câu là chữ số | 422, thông báo điện thoại, giữ tên/lời nhắn | PASS | `CheckoutServiceTest`, `CartCheckoutWebTest`; [ảnh](screenshots/cart-validation.png) |
| TC11 | Người nhận + địa chỉ + lời nhắn vượt 255 | Báo lỗi, không cắt dữ liệu | 422, không INSERT, có thể rút gọn để gửi lại | PASS | `CheckoutServiceTest`, `cart_checkout` |
| TC12 | Địa chỉ chứa emoji chiếm 2 đơn vị UTF-16 | Kiểm tra sức chứa NVARCHAR đúng | Service từ chối dữ liệu vượt sức chứa | PASS | `CheckoutServiceTest` |
| TC13 | Giả POST `payment_method=vnpay`/shipping express | Không ghi nhận phương thức chưa hỗ trợ | 422, không tạo giỏ | PASS | `CheckoutServiceTest`, `CartCheckoutWebTest`, `cart_checkout` |
| TC14 | Gửi lại POST đã thành công | Không tạo giỏ lần hai | Số giỏ không tăng, giỏ nháp đã rỗng | PASS | `CartCheckoutWebTest`, `cart_checkout` |
| TC15 | Token CSRF sai | Không ghi dữ liệu | HTTP 403 | PASS | `CartCheckoutWebTest`, `CartDeleteWebTest`, `phase3/phase7` |
| TC16 | Khách mở/sửa/hủy giỏ của người khác | Từ chối truy cập | 404 theo chủ giỏ, không ghi | PASS | `CartDeleteWebTest`, `phase7` |
| TC17 | Admin duyệt cùng giỏ hai lần | Trừ kho đúng một lần | Lần hai từ chối, lượng kho giữ nguyên | PASS | `phase7` |
| TC18 | Lỗi ghi dòng thứ hai/lỗi sau trừ kho dòng đầu | Rollback toàn bộ | Fingerprint/giỏ/kho trở về trước thao tác | PASS | `phase3`, `phase7` |
| TC19 | Admin xóa giỏ Chờ duyệt/Đã duyệt | Bảo vệ lịch sử và kho | Không DELETE, thông báo lỗi | PASS | `CartDeleteWebTest`, `phase6/phase7` |
| TC20 | ID sách 2147483647 / 2147483648 | Nhận miền SQL INT, chặn vượt miền | Parser nhận giá trị đầu, từ chối giá trị sau | PASS | `CartDraftTest` (parser); không giả định sách ID này có thật |

## Giao diện và kiểm tra HTTP

Trang `/`, `/dang-nhap` và `/dang-ky` được truy cập qua server PHP thật tại `127.0.0.1:8000`, trả HTTP 200; CSS trả 200 đúng content type, các trang giỏ/Admin chuyển khách chưa đăng nhập tới trang login. Xem [kết quả HTTP](evidence/2026-10-28/http.json). Các trang có đăng nhập được kiểm tra qua KernelBrowser; ảnh Chrome chụp snapshot HTML từ integration test SQL Server.

[Bảng kích thước ảnh](evidence/2026-10-28/screenshots.json) ghi viewport và document width của các trang demo ở desktop 1440px và checkout 390px. Ảnh có CSS thật từ server AssetMapper. Không coi ảnh snapshot là bằng chứng đã click mọi form trong Chrome.

## Chưa kiểm thử trong đợt này

- Tải đồng thời trên nhiều connection/session, deadlock và throughput thực tế.
- Cài mới/import GUI SSMS trên máy khác, môi trường production và HTTPS.
- Thanh toán online, callback ngân hàng, email hoặc giao hàng; các chức năng này chưa triển khai.
- Tính bền vững của giỏ nháp sau hết session/đăng xuất: thiết kế hiện tại không giữ giỏ qua hai sự kiện này.

Không có hàng “chưa kiểm thử” nào được đánh dấu PASS. Các log lịch sử trong README cũ không thay thế kết quả của đợt chạy này.
