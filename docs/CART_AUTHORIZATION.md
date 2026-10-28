# Làm thế nào để khách hàng không xem được giỏ hàng của người khác?

Module kiểm tra quyền sử dụng chức năng và giới hạn dữ liệu theo **chủ giỏ** ở phía server. Với khách hàng, truy vấn chi tiết phải thỏa đồng thời hai điều kiện: đúng mã giỏ được yêu cầu và đúng mã thành viên đang đăng nhập.

## 1. Những file cần mở khi giải thích

| File | Phần mã liên quan | Vai trò |
|---|---|---|
| [security.yaml](../config/packages/security.yaml) | `access_control` | Giới hạn quyền truy cập đường dẫn khách hàng và Admin |
| [CartReadController.php](../src/Controller/CartReadController.php) | `history()`, `listing()`, `detail()`, `show()` | Lấy mã người dùng từ phiên xác thực và truyền phạm vi chủ giỏ vào Repository |
| [CartRepository.php](../src/Repository/CartRepository.php) | `search()`, `statuses()`, `detail()` | Thêm điều kiện chủ giỏ vào truy vấn SQL |
| [Account.php](../src/Security/Account.php), [AccountProvider.php](../src/Security/AccountProvider.php) | `id`, `getUserIdentifier()`, `refreshUser()` | Biểu diễn người dùng được xác thực và nạp lại theo mã định danh |
| [CartEditController.php](../src/Controller/CartEditController.php) | `edit()` | Kiểm tra chủ giỏ trước khi hiển thị biểu mẫu hoặc lưu sửa |
| [CartDeleteController.php](../src/Controller/CartDeleteController.php) | `cancel()` | Kiểm tra chủ giỏ trước khi hủy |
| [CartService.php](../src/Service/CartService.php) | `updateSubmitted()`, `cancel()` | Kiểm tra lại chủ giỏ trong transaction khi thay đổi dữ liệu |

## 2. Kiểm tra quyền truy cập chức năng

Trong `CartReadController`, route xem chi tiết của khách hàng được khai báo:

```php
#[Route('/gio-hang/lich-su/{id}', name: 'app_cart_detail', requirements: ['id'=>'\d{1,10}'], methods: ['GET'])]
#[IsGranted('ROLE_USER')]
public function detail(int $id, CartRepository $carts): Response
{
    return $this->show($id, $carts, false);
}
```

`IsGranted('ROLE_USER')` yêu cầu quyền khách hàng. Giá trị `false` được Controller truyền vào để xử lý theo phạm vi khách hàng.

Đây là kiểm tra ở cấp chức năng. Hai khách hàng đều có `ROLE_USER`, nên cần kiểm tra tiếp chủ sở hữu của giỏ được yêu cầu.

## 3. Lấy mã chủ giỏ từ người dùng đã xác thực

Trong phương thức `show()` của `CartReadController`:

```php
$data = $carts->detail($id, $admin ? null : $this->getUser()->id);
if (!$data) { throw $this->createNotFoundException('Không tìm thấy giỏ hàng.'); }
return $this->render('gio_hang/detail.html.twig', $data + ['admin'=>$admin]);
```

Ở route khách hàng, `$admin` là `false`, nên giá trị tham số thứ hai của `detail()` chính là `$this->getUser()->id`.

- `$id`: mã giỏ lấy từ URL; khách hàng có thể tự thay đổi giá trị này.
- `$this->getUser()->id`: mã thành viên của người dùng đang được Symfony xác thực.
- Tham số `$owner` của Repository: nhận mã thành viên này để giới hạn dữ liệu được xem.

Mã thành viên được lấy từ đối tượng người dùng phía server. Controller không lấy mã chủ giỏ từ tham số `owner` hoặc `THANH_VIEN_TEN_DANG_NHAP` do trình duyệt gửi lên.

Trong `Account`, `id` là thuộc tính chuỗi chỉ đọc. `AccountProvider` nạp người dùng theo mã định danh đã lưu trong phiên. Khi giải thích quyền sở hữu, cần dùng mã `id` này để đối chiếu với cột `GIO_HANG.THANH_VIEN_TEN_DANG_NHAP`.

## 4. Repository kiểm tra đồng thời mã giỏ và chủ giỏ

Trong `CartRepository::detail()`, các dòng quyết định quyền xem là:

```php
$params = [$id];
if ($owner !== null) { $params[] = $owner; }
$cart = $this->db->fetchAssociative('SELECT '.self::FIELDS.' FROM dbo.GIO_HANG g'.self::JOINS.self::TOTALS.' WHERE g.GIO_HANG_ID_GIO = ?'.($owner !== null ? ' AND g.THANH_VIEN_TEN_DANG_NHAP = ?' : ''), $params);
if (!$cart) { return null; }
```

Với khách hàng, `$owner` có giá trị. Điều kiện SQL tương đương ở phần kiểm tra quyền là:

```sql
WHERE g.GIO_HANG_ID_GIO = ?
  AND g.THANH_VIEN_TEN_DANG_NHAP = ?
```

Hai giá trị được truyền lần lượt là `[mã_giỏ, mã_thành_viên_đang_đăng_nhập]`. Truy vấn thực tế còn lấy tên, trạng thái, tổng tiền và các dữ liệu cần hiển thị.

### Ví dụ minh họa

Giả sử khách A có mã `tv00000001`, khách B có mã `tv00000002`, và giỏ `125` thuộc B. Các mã trong ví dụ chỉ để giải thích.

Khi A nhập URL `/gio-hang/lich-su/125`, điều kiện truy vấn trở thành:

```sql
WHERE g.GIO_HANG_ID_GIO = 125
  AND g.THANH_VIEN_TEN_DANG_NHAP = 'tv00000001'
```

Giỏ 125 thuộc `tv00000002` nên không thỏa điều kiện thứ hai. Repository trả `null`; Controller tạo lỗi **404 – Không tìm thấy giỏ hàng** và không render dữ liệu của giỏ B.

Phương thức `detail()` chỉ truy vấn để trả các dòng `MUA` sau khi đã tìm thấy một giỏ phù hợp với phạm vi được xem. Khi bản ghi giỏ không hợp lệ với chủ sở hữu, phương thức kết thúc trước bước lấy các dòng chi tiết đó.

## 5. Danh sách, tổng số và bộ lọc cũng được giới hạn

Trong `CartReadController::listing()`:

```php
$owner = $admin ? null : $this->getUser()->id;
```

Controller truyền `$owner` cho `search()` và `statuses()`. Trong `CartRepository::search()`:

```php
$where = []; $params = [];
if ($owner !== null) { $where[] = 'g.THANH_VIEN_TEN_DANG_NHAP = ?'; $params[] = $owner; }
```

Điều kiện này được sử dụng cho cả truy vấn đếm và truy vấn lấy danh sách. Vì vậy, khách hàng chỉ nhận các giỏ của mình và tổng số giỏ phù hợp để phân trang.

`statuses($owner)` cũng lọc theo chủ giỏ khi lấy những trạng thái đang có. Việc thay đổi bộ lọc trạng thái hoặc số trang không làm mất điều kiện chủ sở hữu.

## 6. Vì sao Admin có thể xem nhiều giỏ?

Các action `admin()` và `adminDetail()` trong `CartReadController` có `IsGranted('ROLE_ADMIN')`. Khi vào nhánh quản trị hợp lệ, Controller truyền `true` vào các hàm xử lý chung, khiến `$owner` được đặt thành `null` và Repository không giới hạn theo một chủ giỏ.

Giá trị `$admin` này do các action trong code truyền vào. Thêm `?admin=true` hoặc `?owner=...` vào URL khách hàng không thay đổi giá trị đó. `config/packages/security.yaml` cũng yêu cầu `ROLE_ADMIN` cho các đường dẫn quản trị được bảo vệ.

## 7. Sửa, hủy và giỏ nháp được kiểm tra thế nào?

### Sửa hoặc hủy giỏ đã gửi

Trong `CartEditController::edit()`:

```php
$data = $carts->detail($id, $this->getUser()->id);
if (!$data) { throw $this->createNotFoundException('Không tìm thấy giỏ hàng.'); }
```

`CartDeleteController::cancel()` cũng gọi `detail()` với mã người dùng hiện tại trước khi xử lý hủy. Khách hàng không được mở biểu mẫu hoặc thực hiện hủy đối với giỏ của người khác.

Trong transaction của `CartService::updateSubmitted()` và `cancel()`, Service kiểm tra lại bản ghi đã khóa:

```php
if (!$cart || (string)$cart['THANH_VIEN_TEN_DANG_NHAP'] !== $user->id) {
    throw new \DomainException('Không tìm thấy giỏ hàng của bạn.');
}
```

Khi hủy, câu lệnh UPDATE còn có điều kiện mã giỏ, mã chủ giỏ và trạng thái Chờ duyệt. Kiểm tra này bảo vệ thao tác ghi ngay tại tầng nghiệp vụ.

### Giỏ nháp trong session

`CartService` đặt khóa session theo mã thành viên:

```php
private function key(Account $user): string { return 'draft_cart_'.$user->id; }
```

Các action giỏ nháp truyền người dùng hiện tại từ `getUser()` vào Service. Mã người dùng để chọn khóa giỏ nháp không được lấy từ URL hoặc biểu mẫu.

### Phân biệt các cơ chế kiểm tra

| Cơ chế | Công việc |
|---|---|
| Role | Xác định được dùng chức năng khách hàng hoặc quản trị |
| Chủ giỏ | Xác định người dùng được đọc hoặc thay đổi giỏ cụ thể nào |
| CSRF | Kiểm tra token trên các yêu cầu ghi từ biểu mẫu |
| Tham số SQL | Tách giá trị đầu vào khỏi câu lệnh SQL |
| Trạng thái giỏ | Xác định thao tác được phép tại thời điểm xử lý |

Với câu hỏi khách hàng có xem được giỏ của người khác hay không, điều kiện chủ giỏ trong truy vấn là phần quyết định. Các lớp kiểm tra còn lại thực hiện những trách nhiệm bổ sung.

## 8. Những kiểm thử đã có trong repository

| File | Kiểm tra được khai báo |
|---|---|
| [phase4_smoke.php](../tests/phase4_smoke.php) | Danh sách và tổng số giỏ theo chủ; xem giỏ của mình; yêu cầu giỏ khác trả 404; khách vào khu vực Admin trả 403 |
| [CartDeleteWebTest.php](../tests/CartDeleteWebTest.php) | Trong `testOwnershipAndAdminAuthorization()`, thay chủ giỏ rồi dùng biểu mẫu có token CSRF hợp lệ vẫn bị từ chối và không ghi dữ liệu |

Ví dụ assertion trong `phase4_smoke.php`:

```php
$client->request('GET', '/gio-hang/lich-su/'.$other); check($client->getResponse()->getStatusCode() === 404, 'Foreign cart hidden');
```

Đây là các kiểm tra có sẵn trong mã nguồn. Kết quả từng đợt đã lưu nằm trong [test-cases.md](test-cases.md).

## 9. Cách trình bày ngắn trước hội đồng

> Hệ thống kiểm tra quyền sử dụng chức năng, sau đó kiểm tra quyền sở hữu giỏ. Mã khách hàng được lấy từ người dùng đang đăng nhập ở phía server. Khi truy vấn một giỏ, Repository yêu cầu đồng thời đúng mã giỏ và đúng mã chủ giỏ. Vì vậy, nếu khách hàng thay mã giỏ trên URL sang giỏ của người khác, truy vấn không trả dữ liệu và Controller phản hồi 404. Danh sách giỏ cũng được lọc theo chủ sở hữu; khi sửa hoặc hủy, Service tiếp tục kiểm tra lại quyền này trong transaction.
