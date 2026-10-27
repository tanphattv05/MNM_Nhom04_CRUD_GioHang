<?php
/** Isolated business/transaction tests; never connects to the real database. */
namespace Symfony\Component\Security\Core\User {
    interface UserInterface {}
    interface PasswordAuthenticatedUserInterface {}
}
namespace Symfony\Component\HttpFoundation { class RequestStack {} }
namespace Doctrine\DBAL {
    class Connection
    {
        public array $cart = ['THANH_VIEN_TEN_DANG_NHAP'=>'1', 'GIO_HANG_TRANG_THAI'=>'Chờ duyệt', 'GIO_HANG_GHI_CHU'=>'Ghi chú khách'];
        public array $lines = [['SACH_ID_SACH'=>1, 'MUA_SO_LUONG_MUA'=>2]];
        public array $writes = [];
        public bool $inside = false;
        public bool $failFinalWrite = false;
        public bool $zeroFinalWrite = false;
        public function transactional(callable $callback): mixed
        {
            $snapshot = [$this->cart, $this->lines];
            $this->inside = true;
            try { return $callback($this); }
            catch (\Throwable $e) { [$this->cart, $this->lines] = $snapshot; throw $e; }
            finally { $this->inside = false; }
        }
        public function fetchAssociative(string $sql, array $params): array|false
        {
            if (!$this->inside || !str_contains($sql, 'dbo.GIO_HANG WITH (UPDLOCK, HOLDLOCK)') || $params !== [7]) {
                throw new \LogicException('Must lock the selected cart inside the transaction');
            }
            return $this->cart ?: false;
        }
        public function executeStatement(string $sql, array $params): int
        {
            if (!$this->inside) { throw new \LogicException('Write outside transaction'); }
            $this->writes[] = [$sql, $params];
            if ($sql === 'DELETE FROM dbo.MUA WHERE GIO_HANG_ID_GIO = ?') {
                if ($params !== [7]) { throw new \LogicException('Wrong child scope'); }
                $count = count($this->lines); $this->lines = []; return $count;
            }
            if ($this->failFinalWrite) { throw new \RuntimeException('Simulated FK/database failure'); }
            if ($this->zeroFinalWrite) { return 0; }
            if (str_starts_with($sql, 'UPDATE dbo.GIO_HANG SET GIO_HANG_TRANG_THAI = ?')) {
                if ($params !== ['Đã hủy', 7, '1', 'Chờ duyệt']) { throw new \LogicException('Wrong cancellation scope'); }
                $this->cart['GIO_HANG_TRANG_THAI'] = 'Đã hủy'; return 1;
            }
            if ($sql === 'DELETE FROM dbo.GIO_HANG WHERE GIO_HANG_ID_GIO = ? AND GIO_HANG_TRANG_THAI = ?') {
                if ($params !== [7, $this->cart['GIO_HANG_TRANG_THAI']] || $this->lines) { throw new \LogicException('Wrong parent scope/order'); }
                $this->cart = []; return 1;
            }
            throw new \LogicException('Unexpected write (stock must remain unchanged): '.$sql);
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
        try { $callback(); } catch (\DomainException|\RuntimeException) { check(true, $message); return; }
        check(false, $message);
    }
    $user = new \App\Security\Account('1', false, '');
    $foreign = new \App\Security\Account('2', false, '');
    $admin = new \App\Security\Account('admin04', true, '');
    [$db, $service] = fixture();
    $before = [$db->cart, $db->lines];
    fails(fn() => $service->cancel($admin, 7), 'admin cannot cancel as customer');
    fails(fn() => $service->cancel($foreign, 7), 'foreign customer cannot cancel');
    fails(fn() => $service->delete($user, 7), 'customer cannot delete');
    check($db->writes === [], 'unauthorized operations do not write');
    $service->cancel($user, 7);
    check($db->cart === array_replace($before[0], ['GIO_HANG_TRANG_THAI'=>'Đã hủy']) && $db->lines === $before[1], 'cancel retains lines, note and history');
    fails(fn() => $service->cancel($user, 7), 'duplicate cancel rejected');
    fails(fn() => $service->updateSubmitted($user, 7, [1=>'1'], ''), 'cancelled cart cannot be edited');
    fails(fn() => $service->review($admin, 7, 'approve'), 'cancelled cart cannot be approved');
    fails(fn() => $service->review($admin, 7, 'reject', 'Lý do'), 'cancelled cart cannot be rejected');
    foreach (['Đã duyệt', 'Từ chối', 'Đã hủy', null, '', 'Đang giao'] as $status) {
        [$db, $service] = fixture(); $db->cart['GIO_HANG_TRANG_THAI'] = $status;
        fails(fn() => $service->cancel($user, 7), 'cannot cancel non-pending cart');
        check($db->writes === [], 'invalid status does not write');
    }
    foreach (['Chờ duyệt', 'Đã duyệt', null, '', 'Đang giao'] as $status) {
        [$db, $service] = fixture(); $db->cart['GIO_HANG_TRANG_THAI'] = $status;
        fails(fn() => $service->delete($admin, 7), 'cannot delete active/unknown status');
        check($db->writes === [], 'invalid delete retains children');
    }
    foreach (['Đã hủy', 'Từ chối'] as $status) {
        foreach ([false, true] as $empty) {
            [$db, $service] = fixture(); $db->cart['GIO_HANG_TRANG_THAI'] = $status;
            if ($empty) { $db->lines = []; }
            $service->delete($admin, 7);
            check($db->cart === [] && $db->lines === [] && count($db->writes) === 2, 'delete inactive cart children before parent, including empty carts');
        }
    }
    foreach (['cancel', 'delete'] as $operation) {
        [$db, $service] = fixture(); $db->cart = [];
        fails(fn() => $service->$operation($operation === 'cancel' ? $user : $admin, 7), 'missing cart rejected');
        check($db->writes === [], 'missing cart never writes');
        foreach (['failFinalWrite', 'zeroFinalWrite'] as $failure) {
            [$db, $service] = fixture();
            if ($operation === 'delete') { $db->cart['GIO_HANG_TRANG_THAI'] = 'Từ chối'; }
            $db->$failure = true; $before = [$db->cart, $db->lines];
            fails(fn() => $service->$operation($operation === 'cancel' ? $user : $admin, 7), 'write failure detected');
            check([$db->cart, $db->lines] === $before && !$db->inside, 'rollback restores parent and children');
        }
    }
    echo "$checks phase 6 service checks passed; no real database connections.\n";
}
