# Quản Lý Sách (CRUD) – Bảng Giỏ Hàng | Nhà Sách Nhóm 04

Ứng dụng web **Quản lý sách (CRUD) – Bảng giỏ hàng (`MNM_Nhom04_CRUD_GioHang`)** được xây dựng theo kiến trúc **Model–View–Controller (MVC)** trên nền tảng **Symfony Framework v7.4.21** kết hợp với hệ quản trị cơ sở dữ liệu **Microsoft SQL Server**. Dự án được thực hiện trong khuôn khổ học phần **Phát triển ứng dụng web với mã nguồn mở** tại Trường Kỹ thuật và Công nghệ – Đại học Trà Vinh.

- **Đơn vị:** Trường Kỹ thuật và Công nghệ – Đại học Trà Vinh (TVU)
- **Học phần:** Phát triển ứng dụng web với mã nguồn mở
- **Lớp – Nhóm thực hiện:** DA23TTA – Nhóm 04
- **Repository GitHub:** [https://github.com/tanphattv05/MNM_Nhom04_CRUD_GioHang.git](https://github.com/tanphattv05/MNM_Nhom04_CRUD_GioHang.git)

---

## 1. 🛠️ Công nghệ và Yêu cầu môi trường

<p>
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Symfony-7.4-000000?logo=symfony&logoColor=white" alt="Symfony 7.4">
  <img src="https://img.shields.io/badge/Microsoft-SQL%20Server-CC2927?logo=microsoftsqlserver&logoColor=white" alt="Microsoft SQL Server">
  <img src="https://img.shields.io/badge/Twig-Template%20Engine-8BC34A?logo=twig&logoColor=white" alt="Twig Template Engine">
  <img src="https://img.shields.io/badge/Composer-2.10%2B-885630?logo=composer&logoColor=white" alt="Composer 2.10+">
</p>

Để cài đặt và vận hành dự án trên máy cục bộ (Localhost), môi trường cần đáp ứng các yêu cầu sau:

| Hạng mục | Yêu cầu |
| --- | --- |
| **💻 Ngôn ngữ lập trình** | PHP `>= 8.2` (khuyến nghị `PHP 8.2.12` CLI x64) |
| **🧩 PHP Extensions bắt buộc** | `pdo_sqlsrv`, `sqlsrv`, `ctype`, `iconv`, `mbstring`, `openssl` |
| **📦 Trình quản lý gói** | Composer `2.10.3` trở lên |
| **🚀 Framework** | Symfony Framework `v7.4.21` (Long-Term Support) |
| **🗄️ Hệ quản trị cơ sở dữ liệu** | Microsoft SQL Server (Tên CSDL: `BanSachDB`) |
| **🔌 Trình điều khiển kết nối CSDL** | Microsoft ODBC Driver 18 for SQL Server |
| **⚙️ Công cụ quản trị CSDL** | SQL Server Management Studio (SSMS) |
| **🎨 Giao diện (Frontend)** | Twig Template Engine, Symfony AssetMapper, HTML5, CSS3, JavaScript |
| **🧑‍💻 Công cụ lập trình khuyến nghị** | Visual Studio Code |

---

## 2. 🚀 Hướng dẫn cài đặt và khởi chạy dự án

> 🧭 **Lộ trình:** Tải mã nguồn → Cài thư viện → Cấu hình CSDL → Khởi chạy → Kiểm thử

### 📥 Bước 1: Tải mã nguồn từ GitHub

Mở Terminal / PowerShell và thực thi lệnh sau để sao chép dự án về máy:

```bash
git clone https://github.com/tanphattv05/MNM_Nhom04_CRUD_GioHang.git
cd MNM_Nhom04_CRUD_GioHang
```

### 📦 Bước 2: Cài đặt các thư viện phụ thuộc

Chạy Composer để tải đầy đủ các gói thư viện của Symfony vào thư mục `vendor/`:

```bash
composer install
```

### 🔐 Bước 3: Cấu hình biến môi trường kết nối CSDL

1. 🔒 Sao chép tệp `.env` thành tệp `.env.local` để cấu hình riêng cho máy cá nhân (không đẩy mật khẩu thực lên GitHub):
   ```bash
   copy .env .env.local
   ```
2. 🔧 Mở tệp `.env.local` và chỉnh sửa chuỗi kết nối `DATABASE_URL` phù hợp với cấu hình Microsoft SQL Server trên máy của bạn:
   ```env
   DATABASE_URL="sqlsrv://sa:YOUR_PASSWORD@127.0.0.1:1433/BanSachDB?TrustServerCertificate=1& encrypt=0"
   ```

### 🗄️ Bước 4: Khởi tạo Cơ sở dữ liệu và chạy Migration

1. 🧱 Mở **SQL Server Management Studio (SSMS)**, khởi tạo cơ sở dữ liệu có tên **`BanSachDB`** và chạy các tập tin kịch bản SQL trong thư mục `database/` để tạo bảng và dữ liệu mẫu.
2. 🔄 Thực thi lệnh Doctrine Migrations để đồng bộ cấu trúc bảng (nếu cần):
   ```bash
   php bin/console doctrine:migrations:migrate
   ```

### ▶️ Bước 5: Khởi chạy máy chủ phát triển (Local Web Server)

Chạy máy chủ tích hợp sẵn của PHP trỏ vào thư mục `public/`:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Sau khi khởi chạy thành công, mở trình duyệt và truy cập vào địa chỉ:

- **Trang chủ ứng dụng:** `http://127.0.0.1:8000`

### 🧪 Bước 6: Kiểm tra giao diện và chạy kiểm thử (Tùy chọn)

Kiểm tra cú pháp các tập tin giao diện Twig và chạy bộ kiểm thử tự động PHPUnit:

```bash
php bin/console lint:twig templates
php bin/phpunit
```

---

## 3. ✨ Các chức năng chính của hệ thống

Hệ thống triển khai đầy đủ quy trình **CRUD** trên bảng **`GIO_HANG`** (kết hợp cùng bảng chi tiết **`MUA`** và bảng **`SACH`**), đi kèm cơ chế xác thực và phân quyền chặt chẽ giữa **Khách hàng (`THANH_VIEN`)** và **Quản trị viên (`QTV`)**:

<p>
  <img src="https://img.shields.io/badge/Create-T%E1%BA%A1o%20gi%E1%BB%8F%20h%C3%A0ng-2E8B57?style=for-the-badge" alt="Create - Tạo giỏ hàng">
  <img src="https://img.shields.io/badge/Read-Xem%20gi%E1%BB%8F%20h%C3%A0ng-1E90FF?style=for-the-badge" alt="Read - Xem giỏ hàng">
  <img src="https://img.shields.io/badge/Update-C%E1%BA%ADp%20nh%E1%BA%ADt-F4A261?style=for-the-badge" alt="Update - Cập nhật">
  <img src="https://img.shields.io/badge/Delete-H%E1%BB%A7y%20v%C3%A0%20x%C3%B3a-E63946?style=for-the-badge" alt="Delete - Hủy và xóa">
</p>

### 🔐 3.1. Xác thực và Phân quyền (Authentication & Authorization)

- 📝 Đăng ký tài khoản thành viên mới và đăng nhập vào hệ thống.
- 👥 Quản lý phiên đăng nhập và phân quyền truy cập theo vai trò:
  - 🛍️ **Khách hàng (`THANH_VIEN`):** Xem sách, tạo giỏ hàng, quản lý các giỏ hàng của cá nhân.
  - 🛡️ **Quản trị viên (`QTV`):** Quản lý toàn bộ giỏ hàng trên hệ thống, duyệt hoặc từ chối giỏ hàng của khách hàng.

### 🛒 3.2. Tạo giỏ hàng mới – Create

- 📚 Hiển thị danh mục sách trực quan, hỗ trợ tìm kiếm theo từ khóa và lọc sách.
- ✅ Cho phép chọn sách đưa vào giỏ hàng, kiểm tra số lượng tồn kho hợp lệ.
- 💰 Tự động tính thành tiền từng đầu sách, tổng tiền toàn bộ giỏ hàng và gửi giỏ hàng ở trạng thái **Chờ duyệt**.

### 📋 3.3. Xem danh sách và Chi tiết giỏ hàng – Read

- 🔎 Hiển thị danh sách giỏ hàng kèm bộ lọc trạng thái và thanh tìm kiếm.
- 👤 Phân tách phạm vi dữ liệu: Khách hàng chỉ xem được giỏ hàng của mình; Quản trị viên xem và tra cứu được toàn bộ giỏ hàng trong hệ thống.
- 🧾 Xem chi tiết từng giỏ hàng: thông tin người mua, thời gian tạo, trạng thái xử lý, danh sách các sách đã chọn trong bảng `MUA`, số lượng, đơn giá và tổng tiền.

### ✏️ 3.4. Cập nhật và Xử lý trạng thái giỏ hàng – Update

- **📝 Cập nhật thông tin giỏ hàng:** Cho phép chỉnh sửa thông tin nhận hàng, thay đổi số lượng sách hoặc thêm/bớt sách khi giỏ hàng đang ở trạng thái cho phép chỉnh sửa.
- **✅ Duyệt / Từ chối giỏ hàng (Dành cho Quản trị viên):** Xác nhận **Duyệt** đơn giỏ hàng hoặc **Từ chối** kèm theo lý do phản hồi cụ thể cho khách hàng.

### 🗑️ 3.5. Hủy và Xóa giỏ hàng – Delete

- 🚫 Cho phép Khách hàng hủy giỏ hàng hoặc xóa bản ghi giỏ hàng theo điều kiện trạng thái nghiệp vụ.
- 🔗 Đảm bảo tính toàn vẹn dữ liệu tham chiếu giữa bảng `GIO_HANG` và bảng chi tiết `MUA` khi thực hiện thao tác xóa.

---

## 4. 📁 Cấu trúc thư mục dự án

```text
MNM_Nhom04_CRUD_GioHang/
├── assets/                 # Tài nguyên Frontend quản lý qua Symfony AssetMapper
├── bin/                    # Tập tin thực thi dòng lệnh (bin/console, bin/phpunit)
├── config/                 # Cấu hình hệ thống, paquetes (packages), routes và bảo mật
├── database/               # Các tập tin script SQL khởi tạo CSDL BanSachDB và dữ liệu mẫu
├── migrations/             # Các lớp quản lý phiên bản cấu trúc cơ sở dữ liệu
├── public/                 # Thư mục gốc Web Server (chứa index.php, CSS, JS, hình ảnh)
├── src/                    # Mã nguồn xử lý chính của ứng dụng theo mô hình MVC
│   ├── Controller/         # Tầng Controller điều phối Request/Response (Route Attributes)
│   ├── Entity/             # Các lớp thực thể ánh xạ dữ liệu
│   ├── Repository/         # Tầng truy xuất dữ liệu SQL Server (Account, Book, Cart)
│   ├── Security/           # Xử lý xác thực đăng nhập và kiểm soát phân quyền
│   ├── Service/            # Tầng xử lý logic nghiệp vụ giỏ hàng (CartService)
│   └── Kernel.php          # Hạt nhân khởi chạy ứng dụng Symfony
├── templates/              # Tầng View chứa các tập tin giao diện Twig (.html.twig)
├── tests/                  # Các kịch bản kiểm thử chức năng tự động
├── var/                    # Chứa bộ nhớ đệm (cache) và nhật ký hệ thống (log)
├── .env                    # Cấu hình biến môi trường mặc định
├── composer.json           # Khai báo các gói thư viện phụ thuộc của PHP
└── README.md               # Tài liệu hướng dẫn tổng quan của dự án
```

---

## 5. 👥 Danh sách thành viên và Phân công nhiệm vụ (Nhóm 04 – DA23TTA)

| STT | Họ và tên | MSSV | Vai trò | Nhiệm vụ phân công |
| :-: | :--- | :---: | :---: | :--- |
| 1 | **Huỳnh Dương Tấn Phát** | 110123038 | Trưởng nhóm | Xây dựng chức năng đăng ký, đăng nhập, phân quyền và tạo giỏ hàng (Create) |
| 2 | **Phạm Bá Hạo Long** | 110123024 | Thư ký | Biên soạn và hoàn thiện tài liệu hướng dẫn kỹ thuật Hands-on |
| 3 | **Cao Á Châu** | 110123206 | Thành viên | Chuẩn bị và cấu hình cơ sở dữ liệu SQL Server |
| 4 | **Trần Hoàng Oanh** | 110123037 | Thành viên | Xây dựng chức năng xem danh sách, tìm kiếm và chi tiết giỏ hàng (Read) |
| 5 | **Võ Thị Kiều Nhi** | 110123033 | Thành viên | Xây dựng chức năng cập nhật, duyệt và từ chối giỏ hàng (Update) |
| 6 | **Trần Vũ Ngọc Huỳnh** | 110123012 | Thành viên | Xây dựng chức năng hủy và xóa giỏ hàng (Delete) |
| 7 | **Nguyễn An Ninh** | 110123036 | Thành viên | Hoàn thiện giao diện và kiểm thử hệ thống |
