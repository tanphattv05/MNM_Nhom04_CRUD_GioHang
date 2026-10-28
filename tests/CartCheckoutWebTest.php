<?php
namespace App\Tests;

use App\Security\Account;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Form;

/** Real HTTP/security/forms with a fake database; never writes to SQL Server. */
final class CartCheckoutWebTest extends WebTestCase
{
    private array $writes = [];
    private int $stock = 10;

    private function browser(): KernelBrowser
    {
        $client = self::createClient();
        $client->disableReboot();
        $db = $this->createMock(Connection::class);
        $db->method('fetchAssociative')->willReturnCallback(function ($sql) {
            if (str_contains($sql, 'dbo.THANH_VIEN')) {
                return ['THANH_VIEN_TEN_DANG_NHAP'=>'tv1234abcd', 'THANH_VIEN_MAT_KHAU'=>'', 'THANH_VIEN_HO_TEN'=>'Nguyễn An',
                    'THANH_VIEN_SO_DIEN_THOAI'=>'0901234567', 'THANH_VIEN_DIA_CHI'=>'12 Đường Sách'];
            }
            return $this->book();
        });
        $db->method('fetchAllAssociative')->willReturnCallback(fn($sql) => str_contains($sql, 'SELECT s.*') ? [$this->book()] : []);
        $db->method('fetchOne')->willReturn(1);
        $db->method('transactional')->willReturnCallback(static fn($callback) => $callback($db));
        $db->method('executeStatement')->willReturnCallback(function ($sql, $params) { $this->writes[] = [$sql, $params]; return 1; });
        $db->method('insert')->willReturnCallback(function ($table, $data) { $this->writes[] = [$table, $data]; return 1; });
        self::getContainer()->set('doctrine.dbal.default_connection', $db);
        $client->loginUser(new Account('tv1234abcd', false, ''));
        return $client;
    }

    private function book(): array
    {
        return ['SACH_ID_SACH'=>1, 'SACH_TEN_SACH'=>'Sách kiểm thử', 'SACH_TAC_GIA'=>'Tác giả',
            'SACH_GIA_BAN'=>'85000.00', 'SACH_SO_LUONG'=>$this->stock, 'LOAI_SACH_MA_LOAI'=>1, 'LOAI_SACH_TEN_LOAI'=>'Văn học'];
    }

    private function checkoutForm(KernelBrowser $client): Form
    {
        $catalog = $client->request('GET', '/danh-muc');
        $token = $catalog->filter('input[name="_token"]')->first()->attr('value');
        $client->request('POST', '/gio-hang/thay-doi', ['_token'=>$token, 'book_id'=>'1', 'quantity'=>'2', 'action'=>'add']);
        $page = $client->request('GET', '/gio-hang/thanh-toan');
        self::assertResponseIsSuccessful();
        return $page->selectButton('Xác nhận đặt hàng')->form();
    }

    public function testEmptyCartCannotCheckout(): void
    {
        $client = $this->browser();
        $client->request('GET', '/gio-hang/thanh-toan');
        self::assertResponseRedirects('/gio-hang');
        self::assertSame([], $this->writes);
    }

    public function testCheckoutSavesServerOwnedDataAndPreventsReplay(): void
    {
        $client = $this->browser();
        $form = $this->checkoutForm($client);
        self::assertSelectorTextContains('#grand-total-text', '170.000');
        self::assertSelectorNotExists('input[value="vnpay"]');
        $post = $form->getPhpValues() + ['price'=>'1', 'status'=>'Đã duyệt', 'THANH_VIEN_TEN_DANG_NHAP'=>'99'];
        $client->request('POST', '/gio-hang/thanh-toan', $post);
        self::assertResponseRedirects('/gio-hang/lich-su/1', 303);
        self::assertCount(2, $this->writes);
        self::assertSame('tv1234abcd', $this->writes[0][1][1]);
        self::assertSame('tv1234abcd', $this->writes[1][1]['THANH_VIEN_TEN_DANG_NHAP']);
        self::assertSame('Chờ duyệt', $this->writes[0][1][2]);
        self::assertStringContainsString('Nguyễn An - 0901234567', $this->writes[0][1][3]);
        self::assertSame(2, $this->writes[1][1]['MUA_SO_LUONG_MUA']);
        $client->request('POST', '/gio-hang/thanh-toan', $post);
        self::assertResponseRedirects('/gio-hang');
        self::assertCount(2, $this->writes);
    }

    public function testInvalidDetailsPreserveInputWithoutWriting(): void
    {
        $client = $this->browser(); $form = $this->checkoutForm($client);
        $client->submit($form, ['recipient_phone'=>'.........', 'note'=>'Giữ lời nhắn này']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Số điện thoại');
        self::assertInputValueSame('recipient_name', 'Nguyễn An');
        self::assertSelectorTextContains('textarea[name="note"]', 'Giữ lời nhắn này');
        self::assertSame([], $this->writes);
    }

    public function testForgedPaymentIsRejected(): void
    {
        $client = $this->browser(); $post = $this->checkoutForm($client)->getPhpValues();
        $post['payment_method'] = 'vnpay';
        $client->request('POST', '/gio-hang/thanh-toan', $post);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'COD');
        self::assertSame([], $this->writes);
    }

    public function testStockIsCheckedAgainAfterOpeningCheckout(): void
    {
        $client = $this->browser(); $form = $this->checkoutForm($client);
        $this->stock = 1;
        $client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'tồn kho');
        self::assertSame([], $this->writes);
    }

    public function testInvalidCsrfCannotSubmitCheckout(): void
    {
        $client = $this->browser(); $post = $this->checkoutForm($client)->getPhpValues();
        $post['_token'] = 'invalid';
        $client->request('POST', '/gio-hang/thanh-toan', $post);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->writes);
    }
}
