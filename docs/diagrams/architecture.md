# Kiến trúc module giỏ hàng

![Kiến trúc module giỏ hàng](architecture.png)

[Ảnh PNG](architecture.png) · [SVG vector](architecture.svg)

Twig/Form gửi request đến Controller. Controller kiểm tra HTTP, CSRF, quyền truy cập và điều phối Service hoặc Repository. CartService xử lý nghiệp vụ, đọc sách qua BookRepository và ghi SQL qua Doctrine DBAL Connection. CheckoutService kiểm tra thông tin nhận hàng.

Giỏ nháp nằm trong Symfony Session; giỏ đã gửi nằm trong GIO_HANG và MUA trên SQL Server. Các thao tác ghi sử dụng transaction. Luồng đọc danh mục/lịch sử có thể gọi trực tiếp Repository từ Controller.

Tạo lại ảnh bằng `python scripts/render_cart_diagrams.py` (cần matplotlib). Công cụ chỉ xuất tài liệu, không kết nối hoặc thay đổi database.
