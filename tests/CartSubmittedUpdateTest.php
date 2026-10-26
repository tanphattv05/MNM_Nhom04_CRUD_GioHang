<?php
namespace App\Tests;

use App\Repository\BookRepository;
use App\Security\Account;
use App\Service\CartService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

/** Isolated tests: never connects to the configured database. */
final class CartSubmittedUpdateTest extends TestCase
{
    private function service(Connection $db): CartService
    {
        return new CartService(new RequestStack(), new BookRepository($db), $db);
    }

    public static function invalidItems(): iterable
    {
        yield 'empty' => [[]];
        foreach (['0', '-1', '1.5', '1e2', '1000000000', '', ['2']] as $quantity) {
            yield [ [1=>$quantity] ];
        }
        yield 'invalid book ID' => [['1 OR 1=1'=>'1']];
        yield 'book ID exceeds SQL int' => [['2147483648'=>'1']];
        yield 'zero book ID' => [[0=>'1']];
        yield 'too many books' => [array_fill(1, 101, '1')];
    }

    public function testAdminCannotUpdateCustomerCart(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('transactional');
        $this->expectException(\DomainException::class);
        $this->service($db)->updateSubmitted(new Account('1', true, ''), 7, [1=>'1'], '');
    }

    public function testLongNoteIsRejectedBeforeTransaction(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('transactional');
        $this->expectException(\DomainException::class);
        $this->service($db)->updateSubmitted(new Account('1', false, ''), 7, [1=>'1'], str_repeat('á', 256));
    }

    #[DataProvider('invalidItems')]
    public function testInvalidItemsNeverStartTransaction(array $items): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('transactional');
        $this->expectException(\DomainException::class);
        $this->service($db)->updateSubmitted(new Account('1', false, ''), 7, $items, '');
    }

    public static function forbiddenCarts(): iterable
    {
        yield 'missing' => [false];
        yield 'foreign' => [['THANH_VIEN_TEN_DANG_NHAP'=>'2', 'GIO_HANG_TRANG_THAI'=>'Chờ duyệt']];
        foreach (['Đã duyệt', 'Từ chối', '', null] as $status) {
            yield [['THANH_VIEN_TEN_DANG_NHAP'=>'1', 'GIO_HANG_TRANG_THAI'=>$status]];
        }
    }

    #[DataProvider('forbiddenCarts')]
    public function testLockedCartIsCheckedBeforeAnyWrite(array|false $cart): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('transactional')->willReturnCallback(static fn($callback) => $callback($db));
        $db->expects(self::once())->method('fetchAssociative')->with(self::callback(static fn($sql) => str_contains($sql, 'UPDLOCK, HOLDLOCK')), [7])->willReturn($cart);
        $db->expects(self::never())->method('executeStatement');
        $db->expects(self::never())->method('insert');
        $this->expectException(\DomainException::class);
        $this->service($db)->updateSubmitted(new Account('1', false, ''), 7, [1=>'2'], '');
    }

    public function testStockIsRecheckedBeforeDeletingLines(): void
    {
        $db = $this->createMock(Connection::class);
        $db->method('transactional')->willReturnCallback(static fn($callback) => $callback($db));
        $db->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            ['THANH_VIEN_TEN_DANG_NHAP'=>'1', 'GIO_HANG_TRANG_THAI'=>'Chờ duyệt'],
            ['SACH_SO_LUONG'=>1, 'SACH_GIA_BAN'=>'354000.00'],
        );
        $db->expects(self::never())->method('executeStatement');
        $this->expectException(\DomainException::class);
        $this->service($db)->updateSubmitted(new Account('1', false, ''), 7, [1=>'2'], '');
    }

    public function testSuccessfulUpdateOnlyWritesLinesAndNoteInsideTransaction(): void
    {
        $db = $this->createMock(Connection::class);
        $inside = false;
        $db->expects(self::once())->method('transactional')->willReturnCallback(static function ($callback) use ($db, &$inside) {
            $inside = true;
            try { return $callback($db); } finally { $inside = false; }
        });
        $db->method('fetchAssociative')->willReturnOnConsecutiveCalls(
            ['THANH_VIEN_TEN_DANG_NHAP'=>'1', 'GIO_HANG_TRANG_THAI'=>'Chờ duyệt'],
            ['SACH_SO_LUONG'=>5, 'SACH_GIA_BAN'=>'354000.00'],
        );
        $writes = [];
        $db->expects(self::exactly(2))->method('executeStatement')->willReturnCallback(static function ($sql, $params) use (&$writes, &$inside) {
            self::assertTrue($inside);
            $writes[] = [$sql, $params];
            return 1;
        });
        $db->expects(self::once())->method('insert')->with('dbo.MUA', ['THANH_VIEN_TEN_DANG_NHAP'=>1, 'SACH_ID_SACH'=>2, 'GIO_HANG_ID_GIO'=>7, 'MUA_SO_LUONG_MUA'=>3])->willReturnCallback(static function () use (&$inside) { self::assertTrue($inside); return 1; });
        $this->service($db)->updateSubmitted(new Account('1', false, ''), 7, [2=>'3'], 'Giao buổi sáng');
        self::assertSame([
            ['DELETE FROM dbo.MUA WHERE GIO_HANG_ID_GIO = ?', [7]],
            ['UPDATE dbo.GIO_HANG SET GIO_HANG_GHI_CHU = ? WHERE GIO_HANG_ID_GIO = ?', ['Giao buổi sáng', 7]],
        ], $writes);
    }
}
