# Giai đoạn 6 — D: Delete

## Quy định nghiệp vụ

- Khách hàng chỉ hủy giỏ **của mình**, ở trạng thái **Chờ duyệt**. Hủy chuyển trạng thái thành **Đã hủy**, giữ nguyên các dòng `MUA`, ghi chú và thời gian gửi để xem lịch sử. Giỏ đã hủy không được sửa, duyệt hay từ chối lại.
- Admin chỉ xóa vĩnh viễn giỏ **Đã hủy** hoặc **Từ chối**, bao gồm các dòng `MUA` của giỏ đó. Không xóa giỏ Chờ duyệt, Đã duyệt, trạng thái rỗng hoặc chưa xác định.
- Không thay đổi tồn kho khi hủy/xóa: giỏ Chờ duyệt chưa trừ kho; giỏ Đã duyệt đã trừ kho nên được giữ lại.
- Phạm vi xóa của giai đoạn này là giỏ hàng và các dòng mua thuộc giỏ; không xóa sách, tài khoản hoặc loại sách.

## Cách sử dụng

- Khách: **Lịch sử giỏ → Xem chi tiết → Hủy giỏ hàng**, đánh dấu xác nhận và gửi.
- Admin: **Quản lý giỏ hàng → Xem chi tiết → Xóa vĩnh viễn**, đánh dấu xác nhận và gửi. Chỉ giỏ đủ điều kiện mới có biểu mẫu xóa.
- Hai thao tác đều dùng POST và CSRF token riêng theo thao tác/mã giỏ. Kiểm tra quyền và trạng thái tại server; xác nhận trên giao diện giúp tránh bấm nhầm.
- Khóa `UPDLOCK, HOLDLOCK` trên giỏ trong transaction đồng bộ với sửa/duyệt. Trạng thái và chủ sở hữu được kiểm tra lại sau khi khóa, tránh hủy/xóa trong lúc duyệt.
- Xóa `MUA` trước `GIO_HANG`; nếu lỗi khóa ngoại, lỗi ghi hoặc bản ghi thay đổi, toàn bộ transaction được hoàn tác và giao diện báo lỗi.

## Cài đặt và kiểm thử

Không cần migration mới: cột trạng thái hiện có hỗ trợ Unicode và giá trị `Đã hủy`. Cần cài database/migration của các giai đoạn trước theo README.

```powershell
php tests/phase6_smoke.php
php tests/phase5_smoke.php
php vendor/phpunit/phpunit/phpunit
php bin/console lint:twig templates
php bin/console debug:router
```

Smoke test độc lập kiểm tra quyền, chủ sở hữu, các trạng thái bị chặn, giỏ không tồn tại, hủy lặp, chặn sửa/duyệt sau hủy, thứ tự xóa khóa ngoại và hoàn tác khi lỗi. Test không kết nối database thật. Khi kiểm thử thủ công, dùng giỏ thử nghiệm vì thao tác Admin xóa là vĩnh viễn.

`CartDeleteWebTest` chạy qua Symfony với kết nối DBAL giả lập để kiểm tra biểu mẫu Twig, phân quyền, giỏ của người khác, CSRF, POST-only, chuyển hướng, thay đổi trạng thái sau khi mở biểu mẫu và hiển thị lỗi database. Cần chạy `composer install` (bao gồm `importmap:install`) trước khi chạy bộ PHPUnit. Kiểm thử này không xác nhận hành vi khóa hoặc khóa ngoại trên SQL Server thật.
