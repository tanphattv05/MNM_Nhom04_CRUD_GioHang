<?php
namespace App\Tests;

use App\Repository\BookRepository;
use App\Security\Account;
use App\Service\CartService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class CartDraftTest extends TestCase
{
    private function cart(): CartService
    {
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturn(['SACH_ID_SACH'=>1, 'SACH_GIA_BAN'=>'85000.00', 'SACH_SO_LUONG'=>10]);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack();
        $stack->push($request);
        return new CartService($stack, new BookRepository($db), $db);
    }

    public function testDuplicateReloadUpdateAndRemove(): void
    {
        $cart = $this->cart(); $user = new Account('1', false, '');
        $cart->modify($user, 1, 'add', 1);
        $cart->modify($user, 1, 'add', 2);
        $view = $cart->view($user);
        self::assertCount(1, $view['lines']);
        self::assertSame(3, $view['lines'][0]['quantity']);
        self::assertSame(25500000, $view['total']);
        self::assertSame($view, $cart->view($user));
        self::assertSame([], $cart->view(new Account('2', false, ''))['lines']);
        $cart->modify($user, 1, 'update', 4);
        self::assertSame(4, $cart->view($user)['lines'][0]['quantity']);
        $cart->modify($user, 1, 'remove');
        self::assertSame(0, $cart->view($user)['total']);
        self::assertSame([], $cart->view($user)['lines']);
    }

    public static function invalidChanges(): iterable
    {
        yield 'negative addition to existing item' => ['add', -1];
        yield 'zero addition' => ['add', 0];
        yield 'zero update' => ['update', 0];
        yield 'negative update' => ['update', -1];
        yield 'addition exceeds stock' => ['add', 9];
        yield 'update exceeds stock' => ['update', 11];
    }

    #[DataProvider('invalidChanges')]
    public function testRejectedChangeKeepsOriginalDraft(string $action, int $quantity): void
    {
        $cart = $this->cart(); $user = new Account('1', false, '');
        $cart->modify($user, 1, 'add', 3);
        $before = $cart->view($user); $nonce = $cart->nonce($user);
        try {
            $cart->modify($user, 1, $action, $quantity);
            self::fail('Expected invalid quantity to be rejected.');
        } catch (\DomainException) {
            self::assertSame($before, $cart->view($user));
            self::assertSame($nonce, $cart->nonce($user));
        }
    }

    public function testFullSqlServerIntegerBookIdRange(): void
    {
        self::assertSame(2147483647, CartService::bookId('2147483647'));
        $this->expectException(\DomainException::class);
        CartService::bookId('2147483648');
    }
}
