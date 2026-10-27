# Kịch bản kiểm thử thủ công giai đoạn 6

## Chuẩn bị

- Khởi động web theo README và chạy đủ migration.
- Dùng tài khoản khách A, khách B và Admin trên database phát triển.
- Tạo giỏ thử nghiệm mới; ghi lại mã giỏ, chủ sở hữu, số lượng từng sách và tồn kho trước khi thao tác.
- Mở khách và Admin bằng hai hồ sơ trình duyệt hoặc một cửa sổ riêng tư để không dùng chung phiên đăng nhập.
- Không dùng giỏ cần giữ lại vì xóa của Admin là vĩnh viễn.
- Mở `phase6_delete_checks.sql` trong SSMS, điền `@CartId` bằng mã giỏ thử nghiệm.

## TC01 — Khách hủy giỏ của mình

1. Khách A tạo và gửi một giỏ có ít nhất hai đầu sách.
2. Mở Lịch sử giỏ → Chi tiết, kiểm tra trạng thái Chờ duyệt.
3. Đánh dấu xác nhận và nhấn Hủy giỏ hàng.
4. Tải lại trang chi tiết và trang lịch sử.

Mong đợi: trạng thái Đã hủy; giữ nguyên chủ sở hữu, thời gian gửi, ghi chú và các dòng sách. Không còn nút sửa/hủy. Tồn kho từng sách không đổi.

## TC02 — Admin xóa giỏ đã hủy

1. Admin tìm đúng mã giỏ trong TC01 và mở chi tiết.
2. Đánh dấu xác nhận, nhấn Xóa vĩnh viễn.
3. Kiểm tra quay về danh sách và tìm lại mã giỏ.
4. Chạy SQL kiểm tra với cùng `@CartId`.

Mong đợi: không còn giỏ trong danh sách hoặc bảng GIO_HANG; không còn dòng MUA của giỏ. Các giỏ khác và tồn kho không đổi. Đường dẫn chi tiết cũ trả về 404.

## TC03 — Admin xóa giỏ từ chối

1. Khách A gửi giỏ mới.
2. Admin từ chối giỏ, nhập lý do hợp lệ.
3. Kiểm tra lý do từ chối và ghi chú khách được lưu riêng.
4. Admin xóa giỏ này và chạy SQL kiểm tra.

Mong đợi: xóa cả giỏ và chi tiết; không thay đổi tồn kho. Chỉ xóa đúng mã giỏ đã chọn.

## TC04 — Chặn thao tác sai quyền hoặc trạng thái

| Tình huống | Kết quả mong đợi |
|---|---|
| Khách B mở chi tiết giỏ của A | 404, không lộ chi tiết giỏ |
| Khách gọi URL xóa của Admin | 403 |
| Admin gọi URL hủy dành cho khách | 403 |
| Khách mở giỏ Đã duyệt/Từ chối/Đã hủy | Không có nút hủy hoặc sửa |
| Admin mở giỏ Chờ duyệt/Đã duyệt/Đang xử lý | Không có biểu mẫu xóa |
| Chưa đăng nhập gọi chức năng hủy/xóa | Chuyển đến đăng nhập |
| Gọi GET tới URL hủy/xóa | 405, dữ liệu không đổi |

Không chỉ kiểm tra ẩn nút: bộ `CartDeleteWebTest` còn gửi POST với token hợp lệ để xác nhận server vẫn chặn sai trạng thái.

## TC05 — Hai phiên khách và Admin cùng xử lý

1. Khách A mở biểu mẫu hủy của giỏ Chờ duyệt nhưng chưa gửi.
2. Admin duyệt giỏ đó ở phiên khác.
3. Khách A gửi biểu mẫu hủy đã mở trước đó.

Mong đợi: hủy bị từ chối, trạng thái vẫn Đã duyệt. Tồn kho chỉ giảm đúng một lần do duyệt.

Thử chiều ngược lại: khách hủy trước rồi Admin gửi biểu mẫu duyệt cũ. Duyệt phải bị chặn và tồn kho không giảm. Kiểm thử này xác nhận thay đổi trạng thái giữa hai yêu cầu; kiểm thử khóa đồng thời thực sự cần hai kết nối database chạy cùng lúc.

## TC06 — Gửi lặp và biểu mẫu hết hiệu lực

1. Mở giỏ được phép xóa trên hai tab Admin.
2. Xóa ở tab thứ nhất rồi gửi biểu mẫu cũ ở tab thứ hai.
3. Thử gửi POST với `_token` rỗng hoặc bị sửa bằng công cụ kiểm thử HTTP.

Mong đợi: lần xóa thứ hai trả về 404; token không hợp lệ trả về 403. Không có thay đổi thêm ở giỏ khác.

## Ghi nhận kết quả

Với mỗi ca, ghi ngày kiểm thử, mã giỏ, tài khoản/vai trò, kết quả thực tế và đạt/chưa đạt. Có thể lưu ảnh giao diện cùng kết quả SQL trước/sau. Không đưa mật khẩu hoặc cookie phiên vào ảnh/báo cáo.

Kết quả smoke test hoặc database giả lập không thay thế bằng chứng kiểm thử khóa ngoại và đồng thời trên SQL Server thật.
