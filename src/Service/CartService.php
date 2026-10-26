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
}
