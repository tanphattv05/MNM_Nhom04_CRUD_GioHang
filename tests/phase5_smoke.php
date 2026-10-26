<?php
/** Standalone service tests with in-memory DBAL double; no autoloader or real DB. */
namespace Symfony\Component\Security\Core\User {
    interface UserInterface {}
    interface PasswordAuthenticatedUserInterface {}
}
namespace Symfony\Component\HttpFoundation { class RequestStack {} }
namespace Doctrine\DBAL {
    class Connection
    {
        public array $cart = ['GIO_HANG_TRANG_THAI'=>'Chờ duyệt', 'GIO_HANG_GHI_CHU'=>'Ghi chú khách', 'GIO_HANG_LY_DO_TU_CHOI'=>null, 'THANH_VIEN_TEN_DANG_NHAP'=>'1'];
        public array $lines = [['SACH_ID_SACH'=>1, 'MUA_SO_LUONG_MUA'=>2], ['SACH_ID_SACH'=>2, 'MUA_SO_LUONG_MUA'=>1]];
        public array $books = [1=>['SACH_SO_LUONG'=>5, 'SACH_GIA_BAN'=>'354000.00'], 2=>['SACH_SO_LUONG'=>3, 'SACH_GIA_BAN'=>'100000.00']];
        public bool $inside = false;
        public bool $failFinalWrite = false;
        public bool $zeroStockWrite = false;
        public bool $zeroFinalWrite = false;
        public array $locks = [];
        public function transactional(callable $callback): mixed
        {
            $snapshot = [$this->cart, $this->lines, $this->books];
            $this->inside = true;
            try { return $callback($this); }
            catch (\Throwable $e) { [$this->cart, $this->lines, $this->books] = $snapshot; throw $e; }
            finally { $this->inside = false; }
        }
        public function fetchAssociative(string $sql, array $params): array|false
        {
            if (!$this->inside || !str_contains($sql, 'HOLDLOCK')) { throw new \LogicException('Read without transaction/lock'); }
            if (str_contains($sql, 'dbo.GIO_HANG')) {
                if (!str_contains($sql, 'UPDLOCK')) { throw new \LogicException('Missing cart update lock'); }
                $this->locks[] = 'cart';
                return $this->cart ?: false;
            }
            $this->locks[] = $params[0];
            return $this->books[$params[0]] ?? false;
        }
        public function fetchAllAssociative(string $sql, array $params): array
        {
            if (!$this->inside || !str_contains($sql, 'UPDLOCK, HOLDLOCK')) { throw new \LogicException('Lines not locked'); }
            return $this->lines;
        }
        public function executeStatement(string $sql, array $params): int
        {
            if (!$this->inside) { throw new \LogicException('Write outside transaction'); }
            if (str_starts_with($sql, 'DELETE FROM dbo.MUA')) { $this->lines = []; return 1; }
            if (str_starts_with($sql, 'UPDATE dbo.SACH')) {
                if ($this->zeroStockWrite) { return 0; }
                [$quantity, $id] = $params;
                if ($this->books[$id]['SACH_SO_LUONG'] < $quantity) { return 0; }
                $this->books[$id]['SACH_SO_LUONG'] -= $quantity;
                return 1;
            }
            if ($this->failFinalWrite) { throw new \RuntimeException('Simulated database failure'); }
            if (str_contains($sql, 'SET GIO_HANG_GHI_CHU')) { $this->cart['GIO_HANG_GHI_CHU'] = $params[0]; return 1; }
            if (!str_contains($sql, 'GIO_HANG_LY_DO_TU_CHOI = ?') || str_contains($sql, 'GIO_HANG_GHI_CHU = ?')) {
                throw new \LogicException('Review must not overwrite customer note');
            }
            if ($this->zeroFinalWrite) { return 0; }
            [$status, $admin, $reason] = $params;
            if ($this->cart['GIO_HANG_TRANG_THAI'] !== 'Chờ duyệt') { return 0; }
            $this->cart = array_replace($this->cart, ['GIO_HANG_TRANG_THAI'=>$status, 'QTV_USER_ADMIN'=>$admin, 'GIO_HANG_LY_DO_TU_CHOI'=>$reason]);
            return 1;
        }
        public function insert(string $table, array $data): int
        {
            if (!$this->inside) { throw new \LogicException('Insert outside transaction'); }
            $this->lines[] = $data;
            return 1;
        }
    }
}
namespace {
    require __DIR__.'/../src/Security/Account.php';
    require __DIR__.'/../src/Repository/BookRepository.php';
    require __DIR__.'/../src/Service/CartService.php';
    $checks = 0;
    function check(bool $ok, string $message): void {
        global $checks;
        if (!$ok) { throw new \RuntimeException('FAIL: '.$message); }
        ++$checks;
    }
    function fixture(): array {
        $db = new \Doctrine\DBAL\Connection();
        return [$db, new \App\Service\CartService(new \Symfony\Component\HttpFoundation\RequestStack(), new \App\Repository\BookRepository($db), $db)];
    }
    function fails(callable $callback, string $message): void {
        try { $callback(); } catch (\DomainException|\RuntimeException $e) { check(true, $message); return; }
        check(false, $message);
    }
    $user = new \App\Security\Account('1', false, '');
    $admin = new \App\Security\Account('admin04', true, '');
    [$db, $service] = fixture();
    $service->updateSubmitted($user, 7, [2=>'2'], 'Ghi chú mới');
    check(count($db->lines) === 1 && $db->lines[0]['SACH_ID_SACH'] === 2 && $db->lines[0]['MUA_SO_LUONG_MUA'] === 2, 'replace lines and quantity');
    check($db->cart['GIO_HANG_GHI_CHU'] === 'Ghi chú mới' && $db->books[2]['SACH_SO_LUONG'] === 3, 'edit note without stock deduction');
    $service->updateSubmitted($user, 7, [1=>'1', 2=>'3'], '');
    check(count($db->lines) === 2, 'add book and change quantity');
    foreach ([[], [1=>'0'], [1=>'1.5'], [1=>'6']] as $items) {
        fails(fn() => $service->updateSubmitted($user, 7, $items, ''), 'invalid edit');
    }
    fails(fn() => $service->updateSubmitted(new \App\Security\Account('2', false, ''), 7, [1=>'1'], ''), 'foreign edit');
    fails(fn() => $service->updateSubmitted($admin, 7, [1=>'1'], ''), 'admin edit');
    fails(fn() => $service->review($user, 7, 'approve'), 'customer approval');
    fails(fn() => $service->review($user, 7, 'reject', 'Lý do'), 'customer rejection');
    fails(fn() => $service->review($admin, 7, 'invalid'), 'unknown action');
    [$db, $service] = fixture();
    $service->review($admin, 7, 'approve');
    check($db->books[1]['SACH_SO_LUONG'] === 3 && $db->books[2]['SACH_SO_LUONG'] === 2, 'stock deducted');
    check($db->cart['GIO_HANG_TRANG_THAI'] === 'Đã duyệt' && $db->cart['QTV_USER_ADMIN'] === 'admin04', 'status and string admin ID');
    check($db->cart['GIO_HANG_GHI_CHU'] === 'Ghi chú khách' && $db->cart['GIO_HANG_LY_DO_TU_CHOI'] === null, 'approval preserves customer note');
    check($db->locks === ['cart', 1, 2], 'consistent lock order');
    fails(fn() => $service->review($admin, 7, 'approve'), 'duplicate approval');
    fails(fn() => $service->review($admin, 7, 'reject', 'Lý do'), 'reject after approval');
    fails(fn() => $service->updateSubmitted($user, 7, [1=>'1'], ''), 'edit after approval');
    check($db->books[1]['SACH_SO_LUONG'] === 3, 'duplicate does not deduct');
    [$db, $service] = fixture();
    fails(fn() => $service->review($admin, 7, 'reject', '  '), 'reason required');
    fails(fn() => $service->review($admin, 7, 'reject', str_repeat('á', 256)), 'reason limit');
    $service->review($admin, 7, 'reject', 'Không đáp ứng');
    check($db->cart['GIO_HANG_TRANG_THAI'] === 'Từ chối' && $db->cart['GIO_HANG_LY_DO_TU_CHOI'] === 'Không đáp ứng' && $db->books[1]['SACH_SO_LUONG'] === 5, 'reject with separate reason, stock intact');
    check($db->cart['GIO_HANG_GHI_CHU'] === 'Ghi chú khách' && $db->cart['QTV_USER_ADMIN'] === 'admin04', 'rejection preserves customer note and assigns admin');
    fails(fn() => $service->review($admin, 7, 'approve'), 'approve after rejection');
    fails(fn() => $service->review($admin, 7, 'reject', 'Lần hai'), 'duplicate rejection');
    fails(fn() => $service->updateSubmitted($user, 7, [1=>'1'], ''), 'edit after rejection');
    foreach (['stock', 'missing', 'empty', 'write', 'zero-stock-write', 'zero-final-write'] as $failure) {
        [$db, $service] = fixture();
        if ($failure === 'stock') { $db->books[2]['SACH_SO_LUONG'] = 0; }
        if ($failure === 'missing') { unset($db->books[2]); }
        if ($failure === 'empty') { $db->lines = []; }
        if ($failure === 'write') { $db->failFinalWrite = true; }
        if ($failure === 'zero-stock-write') { $db->zeroStockWrite = true; }
        if ($failure === 'zero-final-write') { $db->zeroFinalWrite = true; }
        $before = [$db->cart, $db->lines, $db->books];
        fails(fn() => $service->review($admin, 7, 'approve'), $failure);
        check($before === [$db->cart, $db->lines, $db->books], 'rollback '.$failure);
    }
    [$db, $service] = fixture();
    $db->failFinalWrite = true;
    $before = [$db->cart, $db->lines, $db->books];
    fails(fn() => $service->updateSubmitted($user, 7, [2=>'2'], 'Mới'), 'edit write failure');
    check($before === [$db->cart, $db->lines, $db->books], 'edit rollback');
    fails(fn() => $service->review($admin, 7, 'reject', 'Lý do'), 'reject write failure');
    check($before === [$db->cart, $db->lines, $db->books], 'reject rollback');
    [$db, $service] = fixture();
    $db->cart = [];
    fails(fn() => $service->review($admin, 7, 'approve'), 'missing cart');
    [$db, $service] = fixture();
    $db->lines[] = ['SACH_ID_SACH'=>1, 'MUA_SO_LUONG_MUA'=>4];
    fails(fn() => $service->review($admin, 7, 'approve'), 'aggregate duplicate lines');
    check($db->books[1]['SACH_SO_LUONG'] === 5, 'duplicate lines leave stock intact');
    foreach ([null, '', str_repeat('á', 255)] as $customerNote) {
        [$db, $service] = fixture();
        $db->cart['GIO_HANG_GHI_CHU'] = $customerNote;
        $reason = str_repeat('ậ', 255);
        $service->review($admin, 7, 'reject', '  '.$reason.'  ');
        check($db->cart['GIO_HANG_GHI_CHU'] === $customerNote && $db->cart['GIO_HANG_LY_DO_TU_CHOI'] === $reason, 'independent nullable/full-length Unicode note and reason');
    }
    echo "$checks service checks passed; no real database connections.\n";
}
