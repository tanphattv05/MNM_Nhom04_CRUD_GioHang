<?php
// SQL Server + real Symfony forms. Every fixture/write is rolled back.
ob_start();
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
if ($_SERVER['APP_ENV'] !== 'dev') { throw new RuntimeException('Run only in the development environment.'); }
$kernel = new App\Kernel('dev', true);
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$client->request('GET', '/dang-nhap');
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$accounts = new App\Repository\AccountRepository($db);
$checks = 0;
$verify = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
    ++$checks; echo 'PASS: ', $label, "\n";
};
$fingerprint = static function (string $table) use ($db): string {
    $rows = array_map('serialize', $db->fetchAllAssociative('SELECT * FROM dbo.'.$table));
    sort($rows); return hash('sha256', serialize($rows));
};
$before = [];
foreach (['SACH','LOAI_SACH','THANH_VIEN','QTV','GIO_HANG','MUA','doctrine_migration_versions'] as $table) {
    $before[$table] = $fingerprint($table);
}
$preview = static function (string $name) use ($client): void {
    $dir = getenv('CART_DEMO_DIR');
    if (!$dir) { return; }
    if (!is_dir($dir)) { throw new RuntimeException('Create CART_DEMO_DIR before running.'); }
    // Only the temporary demo account appears in these local snapshots.
    $html = str_replace('<head>', '<head><base href="http://127.0.0.1:8000/">', $client->getResponse()->getContent());
    file_put_contents($dir.DIRECTORY_SEPARATOR.$name.'.html', $html);
};
$db->beginTransaction();
try {
    $username = 'report_'.bin2hex(random_bytes(6));
    $accounts->register(['username'=>$username, 'name'=>'Khách hàng Demo', 'email'=>'demo@example.com', 'phone'=>'0901234567', 'address'=>'12 Đường Sách, TP.HCM'], password_hash(bin2hex(random_bytes(20)), PASSWORD_BCRYPT));
    $user = $accounts->byLogin($username, false);
    $session = new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage());
    foreach ($client->getCookieJar()->all() as $cookie) {
        if ($cookie->getName() === $session->getName()) { $session->setId($cookie->getValue()); }
    }
    $session->set('_security_main', serialize(new Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken($user, 'main', $user->getRoles())));
    $session->save();
    $client->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId()));

    $book = $db->fetchAssociative('SELECT TOP 1 * FROM dbo.SACH WHERE SACH_SO_LUONG >= 5 AND SACH_GIA_BAN IS NOT NULL ORDER BY SACH_ID_SACH');
    if (!$book) { throw new RuntimeException('Need one priced book with at least five copies.'); }
    $bookId = (int)$book['SACH_ID_SACH'];
    $page = $client->request('GET', '/danh-muc');
    $verify($client->getResponse()->isSuccessful(), 'Catalog renders real SQL Server data');
    $preview('catalog');
    $token = $page->filter('input[name="_token"]')->first()->attr('value');
    foreach (['1', '2'] as $quantity) {
        $client->request('POST', '/gio-hang/thay-doi', ['_token'=>$token, 'book_id'=>(string)$bookId, 'quantity'=>$quantity, 'action'=>'add']);
        $verify($client->getResponse()->isRedirect('/gio-hang'), 'Add book quantity '.$quantity);
    }
    $page = $client->request('GET', '/gio-hang');
    $verify($page->filter('.cart-line')->count() === 1 && $page->filter('#cart-quantity-'.$bookId)->attr('value') === '3', 'Duplicate book merges and persists across requests');
    $preview('add-to-cart');
    $client->submit($page->selectButton('Cập nhật')->form(['quantity'=>'4']));
    $page = $client->request('GET', '/gio-hang');
    $verify($page->filter('#cart-quantity-'.$bookId)->attr('value') === '4', 'Quantity update persists');
    $preview('update-cart');

    $page = $client->request('GET', '/gio-hang/thanh-toan');
    $verify($client->getResponse()->isSuccessful(), 'Valid cart enters checkout');
    $preview('checkout');
    $form = $page->selectButton('Xác nhận đặt hàng')->form(['recipient_phone'=>'.........']);
    $client->submit($form);
    $verify($client->getResponse()->getStatusCode() === 422 && str_contains($client->getResponse()->getContent(), 'Số điện thoại phải'), 'Invalid phone rejected by server');
    $preview('cart-validation');
    $page = $client->request('GET', '/gio-hang/thanh-toan');
    $form = $page->selectButton('Xác nhận đặt hàng')->form(['note'=>'Gọi trước khi giao']);
    $post = $form->getPhpValues();
    $forged = $post; $forged['shipping_method'] = 'express';
    $client->request('POST', '/gio-hang/thanh-toan', $forged);
    $verify($client->getResponse()->getStatusCode() === 422, 'Unsupported express shipping rejected');
    $forged = $post; $forged['recipient_address'] = str_repeat('Đ', 200);
    $client->request('POST', '/gio-hang/thanh-toan', $forged);
    $verify($client->getResponse()->getStatusCode() === 422, 'Overlong combined recipient data rejected without truncation');
    $db->executeStatement('UPDATE dbo.SACH SET SACH_SO_LUONG = 1 WHERE SACH_ID_SACH = ?', [$bookId]);
    $client->request('GET', '/gio-hang/thanh-toan');
    $verify($client->getResponse()->isRedirect('/gio-hang'), 'Changed stock blocks checkout GET');
    $client->request('POST', '/gio-hang/thanh-toan', $post);
    $verify($client->getResponse()->getStatusCode() === 422, 'Changed stock blocks checkout POST');
    $db->executeStatement('UPDATE dbo.SACH SET SACH_SO_LUONG = ? WHERE SACH_ID_SACH = ?', [$book['SACH_SO_LUONG'], $bookId]);
    $post['price'] = '1'; $post['status'] = 'Đã duyệt'; $post['THANH_VIEN_TEN_DANG_NHAP'] = '999';
    $client->request('POST', '/gio-hang/thanh-toan', $post);
    $verify($client->getResponse()->getStatusCode() === 303, 'COD checkout succeeds with PRG redirect');
    $id = (int)$db->fetchOne('SELECT MAX(GIO_HANG_ID_GIO) FROM dbo.GIO_HANG');
    $saved = (new App\Repository\CartRepository($db))->detail($id, $user->id);
    $verify($saved !== null && $saved['cart']['GIO_HANG_TRANG_THAI'] === 'Chờ duyệt', 'Server assigns owner and pending status');
    $verify(count($saved['lines']) === 1 && (int)$saved['lines'][0]['MUA_SO_LUONG_MUA'] === 4, 'Real MUA line stores requested quantity');
    $verify(App\Repository\BookRepository::cents((string)$saved['cart']['current_total']) === 4 * App\Repository\BookRepository::cents((string)$book['SACH_GIA_BAN']), 'Total uses database price instead of client input');
    $verify(str_contains($saved['cart']['GIO_HANG_GHI_CHU'], '12 Đường Sách, TP.HCM') && str_contains($saved['cart']['GIO_HANG_GHI_CHU'], 'Gọi trước khi giao'), 'Full address and message stored');
    $verify($book['SACH_SO_LUONG'] === $db->fetchOne('SELECT SACH_SO_LUONG FROM dbo.SACH WHERE SACH_ID_SACH = ?', [$bookId]), 'Submitting checkout does not deduct stock');
    $client->request('GET', '/gio-hang/lich-su/'.$id); $preview('order-detail');
    $count = $db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG');
    $client->request('POST', '/gio-hang/thanh-toan', $post);
    $verify($client->getResponse()->isRedirect('/gio-hang') && $count === $db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG'), 'Replay does not create a second cart');
} finally {
    if ($db->isTransactionActive()) { $db->rollBack(); }
    foreach ($before as $table=>$hash) { $verify($hash === $fingerprint($table), 'Unchanged after rollback: '.$table); }
    $kernel->shutdown();
}
echo $checks, " checks passed; all writes rolled back.\n";
