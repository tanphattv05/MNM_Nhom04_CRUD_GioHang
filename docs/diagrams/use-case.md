# Use case · Module giỏ hàng

![Use Case module giỏ hàng](use-case.png)

[Ảnh PNG](use-case.png) · [SVG vector](use-case.svg). Tạo lại ảnh bằng `python scripts/render_cart_diagrams.py` (cần matplotlib).

Sơ đồ nhóm chức năng theo tác nhân, biểu diễn bằng Mermaid flowchart để GitHub hiển thị trực tiếp.

```mermaid
flowchart LR
    Guest[Khách chưa đăng nhập] --> Register([Đăng ký])
    Guest --> Login([Đăng nhập username])
    Customer[Khách hàng] --> Catalog([Tìm và lọc sách])
    Customer --> Draft([Thêm / sửa số lượng / bỏ sách])
    Customer --> Checkout([Xác nhận COD hoặc gửi nhanh])
    Customer --> History([Xem lịch sử và chi tiết của mình])
    Customer --> Edit([Sửa hoặc hủy giỏ Chờ duyệt])
    Admin[Quản trị viên] --> Search([Tìm và lọc toàn bộ giỏ])
    Admin --> Approve([Duyệt và trừ kho])
    Admin --> Reject([Từ chối kèm lý do])
    Admin --> Delete([Xóa giỏ Đã hủy / Từ chối])
    Checkout -. kiểm tra .-> Validate([Giỏ không rỗng / tồn kho / giá / nonce])
    Approve -. kiểm tra lại .-> Validate
```

Chỉ tài khoản khách hàng có ROLE_USER; Admin có ROLE_ADMIN riêng. Sơ đồ không có tác nhân cổng thanh toán vì chưa tích hợp dịch vụ thu tiền online.
