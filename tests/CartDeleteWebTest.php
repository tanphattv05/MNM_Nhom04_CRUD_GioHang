<?php
namespace App\Tests;

use App\Security\Account;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Real Symfony security, routing, CSRF and Twig; database is replaced before use. */
final class CartDeleteWebTest extends WebTestCase
{
    private array $cart;
    private array $writes;
    private bool $failDelete;

    private function browser(?bool $admin = false): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $this->cart = [
            'GIO_HANG_ID_GIO'=>7, 'THANH_VIEN_TEN_DANG_NHAP'=>'1', 'GIO_HANG_TRANG_THAI'=>'Chờ duyệt',
            'GIO_HANG_THOI_GIAN'=>null, 'GIO_HANG_GHI_CHU'=>'Ghi chú khách', 'GIO_HANG_LY_DO_TU_CHOI'=>null,
            'QTV_USER_ADMIN'=>null, 'QTV_HO_TEN'=>null, 'THANH_VIEN_HO_TEN'=>'Khách thử', 'current_total'=>'0.00', 'line_count'=>0,
        ];
        $this->writes = []; $this->failDelete = false;
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturnCallback(function ($sql, $params) {
            if (str_contains($sql, 'SELECT * FROM dbo.THANH_VIEN')) {
                return ['THANH_VIEN_TEN_DANG_NHAP'=>'1', 'THANH_VIEN_HO_TEN'=>'Khách thử', 'THANH_VIEN_MAT_KHAU'=>''];
            }
            if (str_contains($sql, 'SELECT * FROM dbo.QTV')) {
                return ['QTV_USER_ADMIN'=>'admin04', 'QTV_HO_TEN'=>'Admin thử', 'QTV_MAT_KHAU'=>''];
            }
            if (!$this->cart || (count($params) === 2 && $params[1] !== $this->cart['THANH_VIEN_TEN_DANG_NHAP'])) { return false; }
            return $this->cart;
        });
        $db->method('fetchAllAssociative')->willReturn([]);
        $db->method('transactional')->willReturnCallback(static fn($callback) => $callback($db));
        $db->method('executeStatement')->willReturnCallback(function ($sql, $params) {
            if ($this->failDelete) {
                throw new class('Simulated database constraint failure') extends \RuntimeException implements \Doctrine\DBAL\Exception {};
            }
            $this->writes[] = [$sql, $params];
            if (str_starts_with($sql, 'UPDATE')) { $this->cart['GIO_HANG_TRANG_THAI'] = $params[0]; }
            if (str_starts_with($sql, 'DELETE FROM dbo.GIO_HANG')) { $this->cart = []; }
            return 1;
        });
        self::getContainer()->set('doctrine.dbal.default_connection', $db);
        if ($admin !== null) { $client->loginUser(new Account($admin ? 'admin04' : '1', $admin, '')); }
        return $client;
    }

    public function testCancelFormAndHistoryState(): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', '/gio-hang/lich-su/7');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Hủy giỏ hàng')->form());
        self::assertResponseRedirects('/gio-hang/lich-su/7', 303);
        self::assertSame('Đã hủy', $this->cart['GIO_HANG_TRANG_THAI']);
        self::assertCount(1, $this->writes);
        $client->followRedirect();
        self::assertSelectorTextContains('.status-pill', 'Đã hủy');
        self::assertSelectorNotExists('form[action$="/huy"]');
        self::assertSelectorNotExists('a[href$="/sua"]');
        self::assertSelectorTextContains('.cart-read-note', 'Ghi chú khách');
    }

    public function testAdminDeleteFormAndRedirect(): void
    {
        $client = $this->browser(true); $this->cart['GIO_HANG_TRANG_THAI'] = 'Từ chối';
        $crawler = $client->request('GET', '/admin/gio-hang/7');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Xóa vĩnh viễn')->form());
        self::assertResponseRedirects('/admin/gio-hang', 303);
        self::assertSame([], $this->cart);
        self::assertCount(2, $this->writes);
    }

    public function testOwnershipAndAdminAuthorization(): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', '/gio-hang/lich-su/7');
        $form = $crawler->selectButton('Hủy giỏ hàng')->form();
        $this->cart['THANH_VIEN_TEN_DANG_NHAP'] = '2';
        $client->request('GET', '/gio-hang/lich-su/7');
        self::assertResponseStatusCodeSame(404);
        // A valid token still cannot give access to another customer's cart.
        $client->submit($form);
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', '/admin/gio-hang/7/xoa');
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->writes);
    }

    public function testAnonymousMutationIsBlocked(): void
    {
        $client = $this->browser(null);
        $client->request('POST', '/gio-hang/lich-su/7/huy');
        self::assertResponseRedirects('/dang-nhap');
        $client->request('POST', '/admin/gio-hang/7/xoa');
        self::assertResponseRedirects('/dang-nhap');
        self::assertSame([], $this->writes);
    }

    public function testCsrfAndHttpMethods(): void
    {
        $client = $this->browser();
        $client->request('POST', '/gio-hang/lich-su/7/huy', ['_token'=>'invalid']);
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/gio-hang/lich-su/7/huy');
        self::assertResponseStatusCodeSame(405);
        $client->loginUser(new Account('admin04', true, ''));
        $client->request('POST', '/admin/gio-hang/7/xoa', ['_token'=>'invalid']);
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/admin/gio-hang/7/xoa');
        self::assertResponseStatusCodeSame(405);
        self::assertSame([], $this->writes);
    }

    public function testStatusChangedAfterFormWasOpened(): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', '/gio-hang/lich-su/7');
        $form = $crawler->selectButton('Hủy giỏ hàng')->form();
        $this->cart['GIO_HANG_TRANG_THAI'] = 'Đã duyệt';
        $client->submit($form);
        self::assertResponseRedirects('/gio-hang/lich-su/7', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Chỉ được hủy');
        self::assertSame([], $this->writes);
    }

    public static function protectedStatuses(): iterable
    {
        yield 'pending' => ['Chờ duyệt'];
        yield 'approved' => ['Đã duyệt'];
        yield 'legacy processing' => ['Đang xử lý'];
        yield 'unknown' => ['Trạng thái khác'];
        yield 'empty' => [''];
        yield 'null' => [null];
    }

    #[DataProvider('protectedStatuses')]
    public function testAdminCannotDeleteProtectedStatusUsingAnOldForm(?string $status): void
    {
        $client = $this->browser(true);
        $this->cart['GIO_HANG_TRANG_THAI'] = 'Đã hủy';
        $crawler = $client->request('GET', '/admin/gio-hang/7');
        $form = $crawler->selectButton('Xóa vĩnh viễn')->form();

        // Keep a real, valid CSRF token while changing database state.
        $this->cart['GIO_HANG_TRANG_THAI'] = $status;
        $before = $this->cart;
        $client->submit($form);
        self::assertResponseRedirects('/admin/gio-hang/7', 303);
        self::assertSame($before, $this->cart);
        self::assertSame([], $this->writes);

        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Chỉ được xóa');
        self::assertSelectorNotExists('form[action$="/xoa"]');
    }

    public function testDeleteHandlesCartRemovedAfterOpeningForm(): void
    {
        $client = $this->browser(true);
        $this->cart['GIO_HANG_TRANG_THAI'] = 'Từ chối';
        $crawler = $client->request('GET', '/admin/gio-hang/7');
        $form = $crawler->selectButton('Xóa vĩnh viễn')->form();
        $this->cart = [];

        $client->submit($form);
        self::assertResponseStatusCodeSame(404);
        self::assertSame([], $this->writes);
    }

    public function testResubmittingDeleteDoesNotWriteTwice(): void
    {
        $client = $this->browser(true);
        $this->cart['GIO_HANG_TRANG_THAI'] = 'Đã hủy';
        $crawler = $client->request('GET', '/admin/gio-hang/7');
        $form = $crawler->selectButton('Xóa vĩnh viễn')->form();

        $client->submit($form);
        self::assertResponseRedirects('/admin/gio-hang', 303);
        $firstWrites = $this->writes;
        $client->submit($form);
        self::assertResponseStatusCodeSame(404);
        self::assertSame($firstWrites, $this->writes);
    }

    public function testAdminCannotUseCustomerCancellationEndpoint(): void
    {
        $client = $this->browser();
        $crawler = $client->request('GET', '/gio-hang/lich-su/7');
        $form = $crawler->selectButton('Hủy giỏ hàng')->form();
        $before = $this->cart;
        $client->loginUser(new Account('admin04', true, ''));

        $client->submit($form);
        self::assertResponseStatusCodeSame(403);
        self::assertSame($before, $this->cart);
        self::assertSame([], $this->writes);
    }

    public function testDatabaseErrorIsShownWithoutDeletingCart(): void
    {
        $client = $this->browser(true); $this->cart['GIO_HANG_TRANG_THAI'] = 'Đã hủy';
        $crawler = $client->request('GET', '/admin/gio-hang/7');
        $this->failDelete = true;
        $client->submit($crawler->selectButton('Xóa vĩnh viễn')->form());
        self::assertResponseRedirects('/admin/gio-hang/7', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'hoàn tác');
        self::assertNotEmpty($this->cart);
    }
}
