# Symfony và kiến trúc MVC trong module CRUD Giỏ hàng

Tài liệu giải thích cách tổ chức mã nguồn của Nhóm 04, dùng để học, viết báo cáo và trả lời câu hỏi khi thuyết trình. Phạm vi là CRUD của `GIO_HANG` và các dòng `MUA`. [README](../README.md) trình bày tổng quan; [PRESENTATION.md](PRESENTATION.md) là kịch bản nói và demo.

## 1. Symfony là gì?

Symfony là framework PHP cung cấp các thành phần để xây dựng ứng dụng web, như định tuyến, xử lý yêu cầu HTTP, quản lý dịch vụ và tích hợp giao diện. Một framework tạo bộ khung để tổ chức ứng dụng và các cơ chế dùng chung; nhóm vẫn phải viết quy tắc nghiệp vụ CRUD của mình.

Trong Symfony, Controller nhận thông tin từ `Request` và trả về `Response`. Response có thể chứa HTML hoặc yêu cầu chuyển hướng. Đây cũng là cách các màn hình giỏ hàng của dự án hoạt động. [Tài liệu Controller của Symfony 7.4](https://symfony.com/doc/7.4/controller.html).

Dự án đang khóa `symfony/framework-bundle` ở phiên bản **7.4.21** trong [composer.lock](../composer.lock). Các thư viện được cài bằng Composer theo cấu hình của dự án.

## 2. Symfony được dùng để làm gì trong hệ thống?

| Thành phần | Vai trò trong CRUD giỏ hàng | Mã nguồn hoặc cấu hình |
|---|---|---|
| Front Controller và Kernel | Điểm vào HTTP, khởi động ứng dụng và điều phối quá trình xử lý yêu cầu | [public/index.php](../public/index.php), [src/Kernel.php](../src/Kernel.php) |
| Routing | Ghép URL và phương thức HTTP với action xử lý | Thuộc tính `#[Route]` trong các Controller; [routes.yaml](../config/routes.yaml) |
| Request / Response | Đọc bộ lọc, dữ liệu biểu mẫu; trả HTML, mã trạng thái hoặc chuyển hướng | Các Controller trong [src/Controller](../src/Controller/) |
| Service Container | Cấp các đối tượng mà Controller, Service và Repository cần sử dụng | [services.yaml](../config/services.yaml) |
| Twig | Kết hợp dữ liệu với template để tạo giao diện giỏ hàng | [templates/gio_hang](../templates/gio_hang/) |
| Security và CSRF | Kiểm tra quyền thao tác và token của biểu mẫu ghi dữ liệu | `#[IsGranted]`, `isCsrfTokenValid()` và [security.yaml](../config/packages/security.yaml) |
| Session | Giữ giỏ đang soạn theo mã thành viên trong phiên làm việc | `RequestStack` được truyền vào [CartService](../src/Service/CartService.php) |
| Event Listener | Xử lý một số ngoại lệ bằng trang lỗi phù hợp | [CustomExceptionListener.php](../src/EventListener/CustomExceptionListener.php) |

Doctrine DBAL là thư viện được tích hợp cùng Symfony để truy cập SQL Server; Twig là công cụ template được tích hợp để hiển thị HTML. Cấu hình kết nối database nằm trong [doctrine.yaml](../config/packages/doctrine.yaml), lấy URL từ biến môi trường `DATABASE_URL`.

## 3. MVC là gì và được áp dụng thế nào?

MVC là cách chia trách nhiệm của ứng dụng thành **Model, View, Controller**:

| Thành phần | Trách nhiệm | Cách triển khai trong dự án |
|---|---|---|
| **Model** | Biểu diễn, xử lý dữ liệu và quy tắc nghiệp vụ | `CartService`, `CartRepository`, kết nối DBAL; dữ liệu bền vững được lưu trong SQL Server |
| **View** | Trình bày dữ liệu và các điều khiển để người dùng thao tác | Các template Twig trong `templates/gio_hang/` |
| **Controller** | Nhận yêu cầu HTTP, điều phối xử lý và quyết định phản hồi | `GioHangController`, `CartReadController`, `CartEditController`, `CartReviewController`, `CartDeleteController` |

**Model bao gồm cả dữ liệu và cách xử lý nghiệp vụ.** Trong module này, Model được tổ chức thành Service và Repository thay vì một lớp tên `GioHang` trong thư mục `Entity`.

Đây là cách nhóm tổ chức module theo MVC, kết hợp tách Service và Repository. Symfony cung cấp cơ chế Controller, template và dịch vụ; quy tắc như “chỉ sửa giỏ Chờ duyệt” hoặc “xóa dòng con trước khi xóa giỏ” được nhóm triển khai trong mã nguồn.

## 4. Model trong dự án

### CartService: quy tắc nghiệp vụ và thao tác ghi

[CartService.php](../src/Service/CartService.php) đảm nhiệm:

- Kiểm tra số lượng, sách còn tồn tại, giá và tồn kho.
- Quản lý nội dung và nonce của giỏ nháp trong session.
- Tạo `GIO_HANG` và các dòng `MUA` khi gửi giỏ.
- Cập nhật giỏ đã gửi, hủy giỏ, duyệt hoặc từ chối theo trạng thái.
- Xóa giỏ và các dòng liên quan trong transaction.

Ví dụ, `updateSubmitted()` kiểm tra chủ giỏ và trạng thái **Chờ duyệt** trong transaction trước khi lưu nội dung mới. Quy tắc này được kiểm tra phía server, kể cả khi người gửi thay đổi dữ liệu biểu mẫu.

### CartRepository: truy vấn dữ liệu đọc

[CartRepository.php](../src/Repository/CartRepository.php) chứa các phương thức:

| Phương thức | Công việc |
|---|---|
| `search()` | Lấy danh sách theo chủ giỏ, trạng thái hoặc từ khóa của Admin; thực hiện phân trang |
| `detail()` | Lấy thông tin chung và các dòng chi tiết của một giỏ |
| `statuses()` | Lấy các trạng thái có trong dữ liệu phù hợp |
| `pendingCount()` | Đếm giỏ đang Chờ duyệt |

Trong cách triển khai hiện tại, **CartService cũng ghi SQL trực tiếp qua DBAL Connection**. CartRepository chủ yếu tập trung các truy vấn đọc.

### Doctrine DBAL và cơ sở dữ liệu

DBAL cung cấp các phương thức truy vấn như `fetchAssociative()`, `fetchAllAssociative()`, `executeStatement()` và `insert()`. Khi dùng placeholder và truyền tham số riêng, giá trị đầu vào được tách khỏi câu lệnh SQL. [Tài liệu truy vấn của Doctrine DBAL](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/data-retrieval-and-manipulation.html).

Module sử dụng DBAL và SQL để thao tác trên `GIO_HANG`, `MUA` và các bảng được tham chiếu. Nghiệp vụ giỏ hàng hiện không sử dụng Entity ORM hoặc thao tác `persist()`/`flush()`.

### Vì sao sử dụng Doctrine DBAL trong module?

**DBAL** là viết tắt của **Database Abstraction Layer**, tức lớp trừu tượng truy cập cơ sở dữ liệu. Trong module, mã PHP sử dụng đối tượng `Doctrine\DBAL\Connection` để thực hiện truy vấn trên SQL Server.

Các điểm phù hợp với thiết kế hiện tại gồm:

| Lợi ích | Áp dụng trong CRUD giỏ hàng |
|---|---|
| Truy vấn trực tiếp trên schema hiện có | Giữ các tên bảng/cột `GIO_HANG`, `MUA` và thao tác với khóa ghép bằng SQL; chưa cần xây dựng ánh xạ Entity cho nghiệp vụ giỏ |
| API truy vấn gọn | `fetchAssociative()` lấy một dòng, `fetchAllAssociative()` lấy nhiều dòng, `executeStatement()` thực hiện lệnh ghi |
| Tách tham số khỏi SQL | Truy vấn giỏ truyền riêng mã giỏ và mã chủ giỏ qua placeholder `?` |
| Điều phối transaction thuận tiện | `CartService` sử dụng `transactional()` để nhóm các bước tạo, sửa, hủy, duyệt hoặc xóa; khi phát sinh ngoại lệ trong quá trình xử lý, các thay đổi được hoàn tác theo transaction |
| Chủ động dùng tính năng SQL Server | Các câu SQL có thể chỉ rõ khóa `UPDLOCK`, `HOLDLOCK`, `TABLOCKX` tại những bước cần kiểm soát cập nhật đồng thời |
| Sử dụng cùng cơ chế dịch vụ của Symfony | `Connection` được truyền vào Service/Repository; cấu hình URL kết nối tập trung trong `doctrine.yaml` và biến môi trường |

Ví dụ, khi tạo giỏ, một transaction ghi cả `GIO_HANG` và các dòng `MUA`. Khi xóa, một transaction xóa các dòng `MUA` rồi xóa `GIO_HANG`. Mã triển khai nằm trong [CartService.php](../src/Service/CartService.php).

### So sánh với PDO thuần và Doctrine ORM

| Tiêu chí | PDO thuần | Doctrine DBAL | Doctrine ORM |
|---|---|---|---|
| Cách làm việc chính | SQL, đối tượng kết nối và statement | SQL và API truy cập database ở mức cao hơn | Entity, quan hệ giữa đối tượng và EntityManager |
| Đọc một dòng có tham số | Thường gọi `prepare()`, `execute()`, rồi `fetch()` | Có thể dùng một lệnh `fetchAssociative()` | Có thể tìm qua EntityManager hoặc Repository của Entity |
| Ánh xạ Entity với bảng | Không yêu cầu | Không yêu cầu | Cần định nghĩa ánh xạ cho các Entity sử dụng |
| Truy vấn và thao tác ghi | Viết SQL trực tiếp | Viết SQL trực tiếp hoặc dùng SQL QueryBuilder | Thao tác Entity, dùng DQL/QueryBuilder; cũng có thể truy cập SQL trực tiếp khi cần |
| Đổi lại khi sử dụng | Tự tổ chức thêm các hàm tiện ích và cấu hình | Vẫn tự viết nhiều truy vấn và xử lý dữ liệu | Cần quản lý mapping, quan hệ và vòng đời Entity |

PDO hỗ trợ cả [prepared statements](https://www.php.net/manual/en/pdo.prepared-statements.php) và [transaction](https://www.php.net/manual/en/pdo.transactions.php). Lợi ích của DBAL so với PDO trong module nằm ở API tiện dụng và cách tích hợp với cấu trúc dịch vụ hiện có.

Doctrine ORM có thể quản lý trạng thái các Entity và đồng bộ thay đổi xuống database khi gọi `flush()`. Đây là hướng tổ chức phù hợp khi ứng dụng làm việc nhiều với mô hình đối tượng và quan hệ giữa các Entity. [Tài liệu làm việc với đối tượng của Doctrine ORM](https://www.doctrine-project.org/projects/doctrine-orm/en/3.6/reference/working-with-objects.html).

Với module hiện tại, lựa chọn DBAL giúp nhóm tập trung vào SQL, các ràng buộc của schema và transaction của CRUD giỏ hàng. Việc sử dụng DBAL không khẳng định PDO hoặc ORM kém hơn trong mọi trường hợp.

### Ví dụ đối chiếu cách đọc một giỏ

Ví dụ sau minh họa API; truy vấn đầy đủ của dự án nằm trong [CartRepository::detail()](../src/Repository/CartRepository.php).

```php
$sql = 'SELECT GIO_HANG_ID_GIO, GIO_HANG_TRANG_THAI
        FROM dbo.GIO_HANG
        WHERE GIO_HANG_ID_GIO = ? AND THANH_VIEN_TEN_DANG_NHAP = ?';
```

Với PDO thuần:

```php
$statement = $pdo->prepare($sql);
$statement->execute([$id, $owner]);
$cart = $statement->fetch(PDO::FETCH_ASSOC);
```

Với DBAL Connection đang được truyền vào Repository:

```php
$cart = $this->db->fetchAssociative($sql, [$id, $owner]);
```

Cả hai cách đều có thể truyền tham số an toàn. Điều kiện `THANH_VIEN_TEN_DANG_NHAP = ?` mới là phần giới hạn quyền xem theo chủ giỏ; DBAL không tự bổ sung điều kiện này.

### Hạn chế cần hiểu khi lựa chọn DBAL

- Người lập trình vẫn viết SQL, xử lý kết quả và kiểm tra nghiệp vụ; DBAL không tự quản lý các quan hệ Entity như ORM.
- Tính đúng đắn của quyền sở hữu, trạng thái và thứ tự các bước ghi phụ thuộc vào mã ứng dụng.
- SQL hiện dùng cú pháp và cơ chế khóa riêng của SQL Server. Đổi `DATABASE_URL` sang hệ quản trị khác vẫn cần rà soát, điều chỉnh các truy vấn tương ứng.
- Chưa có đo lường so sánh hiệu năng giữa các công cụ trong dự án; không thể kết luận DBAL luôn chạy nhanh hơn PDO hoặc ORM.

### Câu trả lời trước hội đồng

> Nhóm em sử dụng Doctrine DBAL vì module đang thao tác trực tiếp trên cơ sở dữ liệu SQL Server với bảng GIO_HANG, MUA và các khóa liên kết đã xác định. DBAL cung cấp các hàm truy vấn có tham số gọn hơn thao tác PDO thuần và thuận tiện sử dụng trong Service, Repository của Symfony.
>
> Nhóm vẫn chủ động viết SQL để kiểm tra chủ giỏ, trạng thái và sử dụng cơ chế khóa của SQL Server. Các thao tác ghi nhiều bước được thực hiện trong transaction. So với ORM, cách này chưa cần xây dựng ánh xạ Entity cho nghiệp vụ giỏ hàng, nhưng nhóm phải tự viết truy vấn và xử lý dữ liệu. Vì vậy, DBAL phù hợp với cách triển khai của module hiện tại.

## 5. View trong dự án

View là các template được Controller truyền dữ liệu để tạo HTML. Symfony tích hợp Twig để thực hiện việc này. [Tài liệu template của Symfony 7.4](https://symfony.com/doc/7.4/templates.html).

| Template | Màn hình |
|---|---|
| [index.html.twig](../templates/gio_hang/index.html.twig) | Giỏ nháp, số lượng, tổng tiền và biểu mẫu gửi giỏ |
| [history.html.twig](../templates/gio_hang/history.html.twig) | Danh sách, bộ lọc trạng thái và phân trang |
| [detail.html.twig](../templates/gio_hang/detail.html.twig) | Chi tiết giỏ, các dòng và nút thao tác theo quyền/trạng thái |
| [edit.html.twig](../templates/gio_hang/edit.html.twig) | Biểu mẫu sửa số lượng, các dòng sách và ghi chú |

Ví dụ hiển thị mã giỏ trong Twig:

```twig
<h1>Giỏ #{{ cart.GIO_HANG_ID_GIO }}</h1>
```

Ở đây, `cart` là dữ liệu được truyền từ Controller. Twig có thể lặp qua các dòng, hiển thị thông báo và ẩn/hiện nút theo trạng thái. Quyết định một thao tác có được thực hiện hay không vẫn phải được kiểm tra phía server.

Các biểu mẫu CRUD trong các template trên gửi dữ liệu HTML qua POST; Controller đọc dữ liệu bằng `Request`.

## 6. Controller trong dự án

| Controller | Vai trò |
|---|---|
| [GioHangController](../src/Controller/GioHangController.php) | Nhận yêu cầu xem, thay đổi và gửi giỏ nháp |
| [CartReadController](../src/Controller/CartReadController.php) | Danh sách và chi tiết giỏ theo quyền |
| [CartEditController](../src/Controller/CartEditController.php) | Mở biểu mẫu sửa, nhận dữ liệu và gọi Service để lưu |
| [CartReviewController](../src/Controller/CartReviewController.php) | Nhận yêu cầu duyệt hoặc từ chối |
| [CartDeleteController](../src/Controller/CartDeleteController.php) | Nhận yêu cầu hủy hoặc xóa giỏ |

Ví dụ thuộc tính route đang dùng trong `CartReadController`:

```php
#[Route('/gio-hang/lich-su/{id}', name: 'app_cart_detail', requirements: ['id'=>'\d{1,10}'], methods: ['GET'])]
#[IsGranted('ROLE_USER')]
```

Route xác định URL, tên route, giới hạn định dạng `id` và phương thức HTTP. `IsGranted` là bước kiểm tra quyền. Điều kiện chủ giỏ được áp dụng tiếp trong quá trình truy vấn dữ liệu.

Đoạn xử lý trong phương thức `show()` của Controller này:

```php
$data = $carts->detail($id, $admin ? null : $this->getUser()->id);
if (!$data) { throw $this->createNotFoundException('Không tìm thấy giỏ hàng.'); }
return $this->render('gio_hang/detail.html.twig', $data + ['admin'=>$admin]);
```

Controller yêu cầu Repository lấy dữ liệu theo phạm vi được phép, xử lý trường hợp không tìm thấy rồi truyền dữ liệu cho View. Kết quả cuối cùng là một `Response` HTML.

## 7. Ví dụ luồng xử lý một chức năng CRUD

### Read: xem chi tiết một giỏ

1. Trình duyệt gửi `GET /gio-hang/lich-su/{id}`.
2. Yêu cầu đi vào `public/index.php`; Kernel và Routing điều phối đến `CartReadController`.
3. Hệ thống kiểm tra quyền. Controller lấy mã người dùng hiện tại và gọi `CartRepository::detail()`.
4. Repository truy vấn SQL Server qua DBAL để lấy `GIO_HANG` và các dòng `MUA` thuộc phạm vi được xem.
5. Controller truyền dữ liệu cho `detail.html.twig`; Twig tạo HTML để trả về trình duyệt.

Luồng Read này gọi Repository trực tiếp từ Controller, vì mục đích là lấy dữ liệu hiển thị.

### Update: sửa giỏ Chờ duyệt

1. Người dùng sửa số lượng hoặc ghi chú rồi nhấn **Lưu thay đổi**.
2. Biểu mẫu gửi POST đến `/gio-hang/lich-su/{id}/sua`.
3. `CartEditController` kiểm tra CSRF, đọc dữ liệu và gọi `CartService::updateSubmitted()`.
4. Service kiểm tra dữ liệu, chủ giỏ và trạng thái; khóa dữ liệu cần thiết và cập nhật `MUA`, `GIO_HANG` trong transaction qua DBAL.
5. Khi thành công, Controller chuyển hướng bằng HTTP 303 về trang chi tiết. Khi dữ liệu nghiệp vụ không hợp lệ và giỏ vẫn ở trạng thái có thể sửa, biểu mẫu được hiển thị lại cùng lỗi với HTTP 422.

## 8. Dependency Injection và autowiring

Dependency Injection là cách cấp cho một đối tượng những dịch vụ nó cần từ bên ngoài. Với autowiring, Symfony dựa vào kiểu tham số để tìm dịch vụ phù hợp trong container. [Tài liệu Service Container của Symfony 7.4](https://symfony.com/doc/7.4/service_container.html).

Trong [services.yaml](../config/services.yaml), dự án bật:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
```

Ví dụ constructor của `CartService` sử dụng các kiểu `RequestStack`, `BookRepository` và `Connection`. Container truyền các đối tượng tương ứng khi tạo Service. Controller cũng nhận `CartService` hoặc `CartRepository` qua tham số action.

`autowire` hỗ trợ cung cấp các phụ thuộc theo kiểu. `autoconfigure` áp dụng cấu hình tích hợp phù hợp cho những lớp dịch vụ được nhận diện, chẳng hạn lớp có thuộc tính listener.

Cách tổ chức này cho phép khi kiểm thử cấp kết nối hoặc phụ thuộc giả lập để kiểm tra nghiệp vụ mà không cần SQL Server thật.

## 9. CustomExceptionListener nằm ở đâu trong kiến trúc?

[CustomExceptionListener](../src/EventListener/CustomExceptionListener.php) là thành phần lắng nghe sự kiện `kernel.exception` của Symfony. Lớp này xử lý các ngoại lệ phù hợp của main request bằng trang Twig cho lỗi **403** hoặc **404** và giữ mã trạng thái HTTP tương ứng.

Listener tham gia vào vòng đời xử lý yêu cầu khi có lỗi. Nó không phải Controller của chức năng CRUD, và không thay thế việc kiểm tra quyền sở hữu, trạng thái hoặc tính hợp lệ của dữ liệu trong Controller/Service.

## 10. Ý nghĩa của cách tổ chức này

- **Dễ tìm mã:** thao tác HTTP nằm trong Controller, giao diện nằm trong Twig, quy tắc giỏ hàng tập trung ở Service.
- **Giảm lặp nghiệp vụ:** cùng một Service được gọi từ các luồng ghi; các quy tắc quan trọng được kiểm tra lại trong Service.
- **Dễ kiểm thử:** có thể kiểm tra Service bằng phụ thuộc giả lập và dùng integration test để kiểm tra database thật.
- **Dễ thay đổi giao diện:** điều chỉnh template mà vẫn sử dụng các phương thức xử lý dữ liệu hiện có.

Điểm có thể cải tiến là tách thêm phần SQL ghi từ `CartService` sang lớp truy cập dữ liệu, trong khi Service tiếp tục điều phối transaction và quy tắc nghiệp vụ.

## 11. Đoạn nói trước hội đồng

> Nhóm em sử dụng Symfony làm bộ khung xử lý ứng dụng PHP. Symfony cung cấp định tuyến, cơ chế Request và Response, quản lý dịch vụ và tích hợp Twig để hiển thị giao diện.
>
> Module được tổ chức theo MVC. Controller nhận yêu cầu và điều phối xử lý; View là các template Twig; phần Model được triển khai qua CartService và CartRepository, kết hợp Doctrine DBAL để truy cập SQL Server. Model ở đây bao gồm dữ liệu và quy tắc nghiệp vụ, chẳng hạn chỉ được sửa giỏ Chờ duyệt.
>
> Ví dụ khi sửa một giỏ, biểu mẫu gửi yêu cầu đến CartEditController. Controller kiểm tra CSRF, nhận dữ liệu rồi gọi CartService. Service kiểm tra chủ giỏ, trạng thái và số lượng, sau đó cập nhật GIO_HANG cùng MUA trong transaction. Khi thành công, Controller chuyển về trang chi tiết để hiển thị kết quả.
>
> Nghiệp vụ giỏ hàng hiện sử dụng DBAL và SQL, không dùng Entity ORM. Việc tách Controller, Service, Repository và View giúp nhóm dễ kiểm tra, bảo trì và mở rộng module.
