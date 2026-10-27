<?php
namespace App\Service;

use App\Repository\BookRepository;
use App\Security\Account;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

final class CartService
{
    public function __construct(private RequestStack $requests, private BookRepository $books, private Connection $db) {}
    private function key(Account $user): string { return 'draft_cart_'.$user->id; }
    private function state(Account $user): array { return $this->requests->getSession()->get($this->key($user), ['items'=>[], 'nonce'=>bin2hex(random_bytes(24))]); }
    private function save(Account $user, array $state): void { $this->requests->getSession()->set($this->key($user), $state); }
    public function nonce(Account $user): string { $state = $this->state($user); $this->save($user, $state); return $state['nonce']; }

    public static function quantity(string $value): int
    {
        if (!preg_match('/^[1-9][0-9]{0,8}$/D', $value)) { throw new \DomainException('Số lượng phải là số nguyên từ 1 trở lên.'); }
        return (int)$value;
    }

    private function validate(array|false $book, int $quantity): void
    {
        if (!$book) { throw new \DomainException('Sách không còn tồn tại.'); }
        if ($quantity < 1 || $quantity > (int)$book['SACH_SO_LUONG']) { throw new \DomainException('Số lượng vượt tồn kho hoặc sách đã hết hàng.'); }
        BookRepository::cents((string)$book['SACH_GIA_BAN']);
    }

    public function modify(Account $user, int $id, string $action, int $quantity = 1): void
    {
        if ($user->admin) { throw new \DomainException('Chỉ khách hàng được tạo giỏ hàng.'); }
        $state = $this->state($user);
        if ($action === 'remove') { unset($state['items'][$id]); }
        else {
            if (!in_array($action, ['add','update'], true)) { throw new \DomainException('Thao tác không hợp lệ.'); }
            $quantity = $action === 'add' ? ($state['items'][$id] ?? 0) + $quantity : $quantity;
            $this->validate($this->books->find($id), $quantity);
            if (!isset($state['items'][$id]) && count($state['items']) >= 100) { throw new \DomainException('Mỗi giỏ tối đa 100 đầu sách.'); }
            $state['items'][$id] = $quantity;
        }
        $state['nonce'] = bin2hex(random_bytes(24)); $this->save($user, $state);
    }

    public function view(Account $user): array
    {
        $lines = []; $total = 0; $valid = true;
        foreach ($this->state($user)['items'] as $id=>$quantity) {
            $book = $this->books->find((int)$id); $error = null; $price = 0;
            try { $this->validate($book, $quantity); } catch (\DomainException $e) { $error = $e->getMessage(); $valid = false; }
            if ($book) { try { $price = BookRepository::cents((string)$book['SACH_GIA_BAN']); } catch (\DomainException) {} }
            $subtotal = $price * $quantity; $total += $subtotal;
            $lines[] = compact('id','book','quantity','price','subtotal','error');
        }
        return compact('lines','total','valid');
    }

    public function submit(Account $user, string $nonce, string $note): int
    {
        if ($user->admin) { throw new \DomainException('Chỉ khách hàng được tạo giỏ hàng.'); }
        // Native session locking serializes submissions from the same session.
        $state = $this->state($user);
        if (!$state['items']) { throw new \DomainException('Giỏ hàng đang trống.'); }
        if (!hash_equals($state['nonce'], $nonce)) { throw new \DomainException('Giỏ hàng đã thay đổi hoặc đã được gửi. Vui lòng tải lại trang.'); }
        if (mb_strlen($note) > 255) { throw new \DomainException('Ghi chú tối đa 255 ký tự.'); }
        $id = $this->db->transactional(function () use ($user, $state, $note): int {
            // Serialize ID allocation, including an empty table, until all lines commit.
            $id = (int)$this->db->fetchOne('SELECT COALESCE(MAX(GIO_HANG_ID_GIO), 0) + 1 FROM dbo.GIO_HANG WITH (TABLOCKX, HOLDLOCK)');
            $items = $state['items']; ksort($items);
            foreach ($items as $bookId=>$quantity) { $this->validate($this->books->find((int)$bookId, true), $quantity); }
            $this->db->executeStatement('INSERT INTO dbo.GIO_HANG (GIO_HANG_ID_GIO, THANH_VIEN_TEN_DANG_NHAP, QTV_USER_ADMIN, GIO_HANG_THOI_GIAN, GIO_HANG_TRANG_THAI, GIO_HANG_GHI_CHU) VALUES (?, ?, NULL, SYSDATETIME(), ?, ?)', [$id, (int)$user->id, 'Chờ duyệt', $note === '' ? null : $note]);
            foreach ($items as $bookId=>$quantity) {
                $this->db->insert('dbo.MUA', ['THANH_VIEN_TEN_DANG_NHAP'=>(int)$user->id, 'SACH_ID_SACH'=>(int)$bookId, 'GIO_HANG_ID_GIO'=>$id, 'MUA_SO_LUONG_MUA'=>$quantity]);
            }
            return $id;
        });
        $this->requests->getSession()->remove($this->key($user));
        return $id;
    }

    /** Update a submitted cart without changing its status, timestamp or stock. */
    public function updateSubmitted(Account $user, int $id, array $items, string $note): void
    {
        if ($user->admin) { throw new \DomainException('Chỉ khách hàng được sửa giỏ của mình.'); }
        if (!$items) { throw new \DomainException('Không thể lưu giỏ hàng rỗng.'); }
        if (count($items) > 100) { throw new \DomainException('Mỗi giỏ tối đa 100 đầu sách.'); }
        if (mb_strlen($note) > 255) { throw new \DomainException('Ghi chú tối đa 255 ký tự.'); }
        $validated = [];
        foreach ($items as $bookId => $quantity) {
            if (!preg_match('/^[1-9][0-9]{0,9}$/D', (string)$bookId) || (int)$bookId > 2147483647) {
                throw new \DomainException('Mã sách không hợp lệ.');
            }
            $bookId = (int)$bookId;
            if (!is_string($quantity)) { throw new \DomainException('Số lượng không hợp lệ.'); }
            $validated[$bookId] = self::quantity($quantity);
        }
        ksort($validated);
        $this->db->transactional(function () use ($user, $id, $validated, $note): void {
            // SQL Server update/range locks last until commit. Future Admin writes
            // must acquire this same cart lock before changing status or lines.
            $cart = $this->db->fetchAssociative('SELECT THANH_VIEN_TEN_DANG_NHAP, GIO_HANG_TRANG_THAI FROM dbo.GIO_HANG WITH (UPDLOCK, HOLDLOCK) WHERE GIO_HANG_ID_GIO = ?', [$id]);
            if (!$cart || (string)$cart['THANH_VIEN_TEN_DANG_NHAP'] !== $user->id) {
                throw new \DomainException('Không tìm thấy giỏ hàng của bạn.');
            }
            if ($cart['GIO_HANG_TRANG_THAI'] !== 'Chờ duyệt') {
                throw new \DomainException('Chỉ được sửa giỏ hàng ở trạng thái Chờ duyệt. Vui lòng tải lại trang.');
            }
            foreach ($validated as $bookId => $quantity) {
                $this->validate($this->books->find($bookId, true), $quantity);
            }
            $this->db->executeStatement('DELETE FROM dbo.MUA WHERE GIO_HANG_ID_GIO = ?', [$id]);
            foreach ($validated as $bookId => $quantity) {
                $this->db->insert('dbo.MUA', ['THANH_VIEN_TEN_DANG_NHAP'=>(int)$user->id, 'SACH_ID_SACH'=>$bookId, 'GIO_HANG_ID_GIO'=>$id, 'MUA_SO_LUONG_MUA'=>$quantity]);
            }
            $this->db->executeStatement('UPDATE dbo.GIO_HANG SET GIO_HANG_GHI_CHU = ? WHERE GIO_HANG_ID_GIO = ?', [$note === '' ? null : $note, $id]);
        });
    }
    /** Cancellation retains history and never changes stock (only approval deducts it). */
    public function cancel(Account $user, int $id): void
    {
        if ($user->admin) { throw new \DomainException('Chỉ khách hàng được hủy giỏ của mình.'); }
        $this->db->transactional(function () use ($user, $id): void {
            $cart = $this->db->fetchAssociative('SELECT THANH_VIEN_TEN_DANG_NHAP, GIO_HANG_TRANG_THAI FROM dbo.GIO_HANG WITH (UPDLOCK, HOLDLOCK) WHERE GIO_HANG_ID_GIO = ?', [$id]);
            if (!$cart || (string)$cart['THANH_VIEN_TEN_DANG_NHAP'] !== $user->id) {
                throw new \DomainException('Không tìm thấy giỏ hàng của bạn.');
            }
            if ($cart['GIO_HANG_TRANG_THAI'] !== 'Chờ duyệt') {
                throw new \DomainException('Chỉ được hủy giỏ hàng ở trạng thái Chờ duyệt. Vui lòng tải lại trang.');
            }
            $changed = $this->db->executeStatement('UPDATE dbo.GIO_HANG SET GIO_HANG_TRANG_THAI = ? WHERE GIO_HANG_ID_GIO = ? AND THANH_VIEN_TEN_DANG_NHAP = ? AND GIO_HANG_TRANG_THAI = ?', ['Đã hủy', $id, $user->id, 'Chờ duyệt']);
            if ($changed !== 1) { throw new \DomainException('Giỏ đã thay đổi. Vui lòng tải lại trang.'); }
        });
    }

    /** Delete only inactive, unfulfilled carts; preserve approved stock movements. */
    public function delete(Account $admin, int $id): void
    {
        if (!$admin->admin) { throw new \DomainException('Chỉ Admin được xóa giỏ hàng.'); }
        $this->db->transactional(function () use ($id): void {
            $cart = $this->db->fetchAssociative('SELECT GIO_HANG_TRANG_THAI FROM dbo.GIO_HANG WITH (UPDLOCK, HOLDLOCK) WHERE GIO_HANG_ID_GIO = ?', [$id]);
            if (!$cart) { throw new \DomainException('Không tìm thấy giỏ hàng.'); }
            if (!in_array($cart['GIO_HANG_TRANG_THAI'], ['Đã hủy', 'Từ chối'], true)) {
                throw new \DomainException('Chỉ được xóa giỏ hàng Đã hủy hoặc Từ chối. Giỏ chờ duyệt và đã duyệt phải được giữ lại.');
            }
            // Delete children first. Any FK/write failure rolls back both statements.
            $this->db->executeStatement('DELETE FROM dbo.MUA WHERE GIO_HANG_ID_GIO = ?', [$id]);
            $changed = $this->db->executeStatement('DELETE FROM dbo.GIO_HANG WHERE GIO_HANG_ID_GIO = ? AND GIO_HANG_TRANG_THAI = ?', [$id, $cart['GIO_HANG_TRANG_THAI']]);
            if ($changed !== 1) { throw new \DomainException('Giỏ đã thay đổi. Vui lòng tải lại trang.'); }
        });
    }

    /** Lock order: cart, lines, then books in ascending ID order. */
    public function review(Account $admin, int $id, string $action, string $reason = ''): void
    {
        if (!$admin->admin) { throw new \DomainException('Chỉ Admin được xử lý giỏ hàng.'); }
        if (!in_array($action, ['approve', 'reject'], true)) { throw new \DomainException('Thao tác không hợp lệ.'); }
        $reason = trim($reason);
        if ($action === 'reject' && ($reason === '' || mb_strlen($reason) > 255)) {
            throw new \DomainException('Lý do từ chối bắt buộc nhập và tối đa 255 ký tự.');
        }
        $this->db->transactional(function () use ($admin, $id, $action, $reason): void {
            $cart = $this->db->fetchAssociative('SELECT GIO_HANG_TRANG_THAI FROM dbo.GIO_HANG WITH (UPDLOCK, HOLDLOCK) WHERE GIO_HANG_ID_GIO = ?', [$id]);
            if (!$cart) { throw new \DomainException('Không tìm thấy giỏ hàng.'); }
            if ($cart['GIO_HANG_TRANG_THAI'] !== 'Chờ duyệt') {
                throw new \DomainException('Giỏ đã được xử lý hoặc không ở trạng thái Chờ duyệt.');
            }
            if ($action === 'approve') {
                $lines = $this->db->fetchAllAssociative('SELECT SACH_ID_SACH, MUA_SO_LUONG_MUA FROM dbo.MUA WITH (UPDLOCK, HOLDLOCK) WHERE GIO_HANG_ID_GIO = ? ORDER BY SACH_ID_SACH', [$id]);
                if (!$lines) { throw new \DomainException('Không thể duyệt giỏ hàng rỗng.'); }
                // Aggregate legacy duplicate lines so stock is checked against the full quantity.
                $items = [];
                foreach ($lines as $line) {
                    $quantity = self::quantity((string)$line['MUA_SO_LUONG_MUA']);
                    $bookId = (int)$line['SACH_ID_SACH'];
                    $items[$bookId] = ($items[$bookId] ?? 0) + $quantity;
                }
                ksort($items);
                foreach ($items as $bookId => $quantity) {
                    $book = $this->db->fetchAssociative('SELECT * FROM dbo.SACH WITH (UPDLOCK, HOLDLOCK) WHERE SACH_ID_SACH = ?', [$bookId]);
                    $this->validate($book, $quantity);
                    $changed = $this->db->executeStatement('UPDATE dbo.SACH SET SACH_SO_LUONG = SACH_SO_LUONG - ? WHERE SACH_ID_SACH = ? AND SACH_SO_LUONG >= ?', [$quantity, $bookId, $quantity]);
                    if ($changed !== 1) { throw new \DomainException('Tồn kho đã thay đổi. Vui lòng thử lại.'); }
                }
            }
            $changed = $this->db->executeStatement('UPDATE dbo.GIO_HANG SET GIO_HANG_TRANG_THAI = ?, QTV_USER_ADMIN = ?, GIO_HANG_LY_DO_TU_CHOI = ? WHERE GIO_HANG_ID_GIO = ? AND GIO_HANG_TRANG_THAI = ?', [$action === 'approve' ? 'Đã duyệt' : 'Từ chối', $admin->id, $action === 'reject' ? $reason : null, $id, 'Chờ duyệt']);
            if ($changed !== 1) { throw new \DomainException('Giỏ đã thay đổi. Vui lòng tải lại trang.'); }
        });
    }
}
