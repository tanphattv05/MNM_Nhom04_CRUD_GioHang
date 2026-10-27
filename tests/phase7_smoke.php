<?php
// SQL Server integration: only temporary carts are deleted; all writes roll back.
ob_start();
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', true);
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$client->request('GET', '/dang-nhap');
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$accounts = new App\Repository\AccountRepository($db);
$books = new App\Repository\BookRepository($db);
$repo = new App\Repository\CartRepository($db);
$user = $accounts->byId((string)$db->fetchOne('SELECT TOP 1 THANH_VIEN_TEN_DANG_NHAP FROM dbo.THANH_VIEN ORDER BY THANH_VIEN_TEN_DANG_NHAP'), false);
$other = $accounts->byId((string)$db->fetchOne('SELECT TOP 1 THANH_VIEN_TEN_DANG_NHAP FROM dbo.THANH_VIEN WHERE THANH_VIEN_TEN_DANG_NHAP <> ? ORDER BY THANH_VIEN_TEN_DANG_NHAP', [$user->id]), false);
$admin = $accounts->byId((string)$db->fetchOne('SELECT TOP 1 QTV_USER_ADMIN FROM dbo.QTV ORDER BY QTV_USER_ADMIN'), true);
$available = $db->fetchAllAssociative('SELECT TOP 2 * FROM dbo.SACH WHERE SACH_SO_LUONG >= 5 AND SACH_GIA_BAN IS NOT NULL ORDER BY SACH_ID_SACH');
if (count($available) !== 2 || !$other) { throw new RuntimeException('Need two stocked books and two existing customers.'); }
$a = (int)$available[0]['SACH_ID_SACH']; $b = (int)$available[1]['SACH_ID_SACH'];
$stack = new Symfony\Component\HttpFoundation\RequestStack();
$request = Symfony\Component\HttpFoundation\Request::create('/');
$request->setSession(new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
$stack->push($request);
$service = new App\Service\CartService($stack, $books, $db);
$checks = 0;
function check7(bool $ok, string $label): void { global $checks; if (!$ok) { throw new RuntimeException('FAIL: '.$label); } ++$checks; echo "PASS: $label\n"; }
function as7(App\Security\Account $user): void {
    global $client;
    $session = new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage());
    foreach ($client->getCookieJar()->all() as $cookie) { if ($cookie->getName() === $session->getName()) { $session->setId($cookie->getValue()); } }
    $session->set('_security_main', serialize(new Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken($user, 'main', $user->getRoles())));
    $session->save();
    $client->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId()));
}
function post7(string $page, string $button, array $values = []): array {
    global $client;
    $crawler = $client->request('GET', $page);
    $form = $crawler->selectButton($button)->form($values);
    $payload = $form->getPhpValues();
    $client->submit($form);
    return [$form->getUri(), $payload];
}
function preview7(string $name): void {
    global $client;
    $dir = getenv('PHASE7_UI_DIR');
    if (!$dir) { return; }
    // Optional local browser snapshots of real rendered SQL-backed pages.
    $html = str_replace('<head>', '<head><base href="http://127.0.0.1:8000/">', $client->getResponse()->getContent());
    file_put_contents($dir.DIRECTORY_SEPARATOR.$name.'.html', $html);
}
$fingerprint = static function (string $table) use ($db): string { $rows = array_map('serialize', $db->fetchAllAssociative('SELECT * FROM dbo.'.$table)); sort($rows); return hash('sha256', serialize($rows)); };
$before = []; foreach (['SACH','LOAI_SACH','THANH_VIEN','QTV','GIO_HANG','MUA','doctrine_migration_versions'] as $table) { $before[$table] = $fingerprint($table); }
$create = function () use ($service, $user, $a, $b): int {
    $service->modify($user, $a, 'add', 1); $service->modify($user, $b, 'add', 1);
    return $service->submit($user, $service->nonce($user), 'Ghi chú kiểm thử giai đoạn 7');
};
$db->beginTransaction();
try {
    $id = $create();
    as7($user);
    $client->request('GET', '/danh-muc'); preview7('catalog');
    $client->request('GET', '/gio-hang/lich-su/'.$id); check7($client->getResponse()->isSuccessful(), 'Read own created cart');
    preview7('detail');
    $client->request('GET', '/gio-hang/lich-su/'.$id.'/sua'); check7($client->getResponse()->isSuccessful(), 'Edit page renders');
    check7($client->getCrawler()->filter('.customer-nav a[href="/gio-hang/lich-su"].active[aria-current="page"]')->count() === 1, 'History navigation stays active on edit');
    preview7('edit');
    $client->request('GET', '/gio-hang/lich-su/9999999999'); check7($client->getResponse()->getStatusCode() === 404, 'Out-of-range SQL INT cart ID returns 404');
    $client->request('POST', '/gio-hang/lich-su/'.$id.'/sua', ['_token'=>'bad']); check7($client->getResponse()->getStatusCode() === 403, 'Edit rejects bad CSRF');
    // Values come from the real rendered form, not a synthetic route.
    $crawler = $client->request('GET', '/gio-hang/lich-su/'.$id.'/sua');
    $form = $crawler->filter('form[method="post"][action*="/sua"]')->form();
    $values = $form->getPhpValues(); $values['items'] = [$a=>'2', $b=>'1']; $values['note'] = 'Đã sửa ghi chú';
    $invalid = $values; $invalid['items'] = ['99999999999999999999'=>'1'];
    $client->request('POST', $form->getUri(), $invalid); check7($client->getResponse()->getStatusCode() === 422, 'Oversized book ID returns validation error');
    $client->request('POST', $form->getUri(), $values);
    check7($client->getResponse()->getStatusCode() === 303 && $repo->detail($id, $user->id)['cart']['GIO_HANG_GHI_CHU'] === 'Đã sửa ghi chú', 'Update submitted cart on SQL Server');
    as7($other);
    foreach (['/gio-hang/lich-su/'.$id, '/gio-hang/lich-su/'.$id.'/sua'] as $url) { $client->request('GET', $url); check7($client->getResponse()->getStatusCode() === 404, 'Foreign cart hidden '.$url); }
    as7($user); $client->request('POST', '/admin/gio-hang/'.$id.'/approve'); check7($client->getResponse()->getStatusCode() === 403, 'Customer cannot approve');
    as7($admin);
    $client->request('GET', '/admin/gio-hang/'.$id); preview7('admin');
    $client->request('GET', '/admin/gio-hang?q='.$id.'&status='.urlencode('Chờ duyệt')); check7($client->getResponse()->isSuccessful() && str_contains($client->getResponse()->getContent(), '#'.$id), 'Search and status filter render actual cart');
    $client->request('POST', '/admin/gio-hang/'.$id.'/approve', ['_token'=>'bad']); check7($client->getResponse()->getStatusCode() === 403, 'Approve rejects bad CSRF');
    $stockA = (int)$books->find($a)['SACH_SO_LUONG']; $stockB = (int)$books->find($b)['SACH_SO_LUONG'];
    [$uri, $payload] = post7('/admin/gio-hang/'.$id, 'Duyệt');
    check7($client->getResponse()->getStatusCode() === 303 && (int)$books->find($a)['SACH_SO_LUONG'] === $stockA-2 && (int)$books->find($b)['SACH_SO_LUONG'] === $stockB-1, 'Approve deducts exact quantities');
    $client->request('POST', $uri, $payload);
    check7((int)$books->find($a)['SACH_SO_LUONG'] === $stockA-2 && $repo->detail($id, null)['cart']['GIO_HANG_TRANG_THAI'] === 'Đã duyệt', 'Repeated approval never deducts twice');
    as7($user); $client->request('GET', '/gio-hang/lich-su/'.$id.'/sua'); check7($client->getResponse()->getStatusCode() === 403, 'Approved cart cannot be edited');
    try { $service->delete($admin, $id); throw new LogicException('Approved deletion accepted'); } catch (DomainException) { check7($repo->detail($id, null) !== null, 'Approved cart cannot be deleted'); }

    $reject = $create(); as7($admin);
    post7('/admin/gio-hang/'.$reject, 'Từ chối', ['reason'=>'Không đáp ứng yêu cầu']);
    $data = $repo->detail($reject, null)['cart'];
    check7($data['GIO_HANG_TRANG_THAI'] === 'Từ chối' && $data['GIO_HANG_LY_DO_TU_CHOI'] === 'Không đáp ứng yêu cầu' && $data['GIO_HANG_GHI_CHU'] === 'Ghi chú kiểm thử giai đoạn 7', 'Rejection keeps separate reason and note');
    check7((int)$books->find($a)['SACH_SO_LUONG'] === $stockA-2, 'Rejection never deducts stock');
    $client->request('POST', '/admin/gio-hang/'.$reject.'/xoa', ['_token'=>'bad']); check7($client->getResponse()->getStatusCode() === 403, 'Delete rejects bad CSRF');
    post7('/admin/gio-hang/'.$reject, 'Xóa vĩnh viễn');
    check7($client->getResponse()->getStatusCode() === 303 && !$repo->detail($reject, null) && !$db->fetchOne('SELECT COUNT(*) FROM dbo.MUA WHERE GIO_HANG_ID_GIO = ?', [$reject]), 'Delete rejected cart and child lines');

    $cancel = $create(); as7($user);
    $client->request('POST', '/gio-hang/lich-su/'.$cancel.'/huy', ['_token'=>'bad']); check7($client->getResponse()->getStatusCode() === 403, 'Cancel rejects bad CSRF');
    post7('/gio-hang/lich-su/'.$cancel, 'Hủy giỏ hàng');
    check7($repo->detail($cancel, $user->id)['cart']['GIO_HANG_TRANG_THAI'] === 'Đã hủy' && (int)$books->find($a)['SACH_SO_LUONG'] === $stockA-2, 'Cancel retains history and stock');
    as7($admin); post7('/admin/gio-hang/'.$cancel, 'Xóa vĩnh viễn'); check7(!$repo->detail($cancel, null), 'Delete cancelled cart');

    $failed = $create();
    // Only mutate a newly created temporary line, never a pre-existing cart.
    $db->executeStatement('UPDATE dbo.MUA SET MUA_SO_LUONG_MUA = ? WHERE GIO_HANG_ID_GIO = ? AND SACH_ID_SACH = ?', [(int)$stockB+1, $failed, $b]);
    $stockBeforeFailure = $books->find($a)['SACH_SO_LUONG'];
    try { $service->review($admin, $failed, 'approve'); throw new LogicException('Expected stock failure'); } catch (DomainException) {
        check7($books->find($a)['SACH_SO_LUONG'] === $stockBeforeFailure && $repo->detail($failed, null)['cart']['GIO_HANG_TRANG_THAI'] === 'Chờ duyệt', 'SQL transaction rolls back earlier stock deduction on later failure');
    }
} finally {
    if ($db->isTransactionActive()) { $db->rollBack(); }
    foreach ($before as $table=>$hash) { check7($hash === $fingerprint($table), 'Original data unchanged '.$table); }
    $kernel->shutdown();
}
echo "$checks SQL Server checks passed; all writes rolled back.\n";
