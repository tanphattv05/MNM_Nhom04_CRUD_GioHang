<?php
ob_start();
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', true);
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$client->request('GET', '/dang-nhap');
$db = $kernel->getContainer()->get('doctrine')->getConnection();
if ((int)$db->fetchOne('SELECT 1') !== 1) { throw new RuntimeException('SQL Server unavailable'); }
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
    ++$checks; echo "PASS: $label\n";
}
function loginAs(App\Security\Account $user): void {
    global $client;
    $session = new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage());
    foreach ($client->getCookieJar()->all() as $cookie) { if ($cookie->getName() === $session->getName()) { $session->setId($cookie->getValue()); } }
    $session->set('_security_main', serialize(new Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken($user, 'main', $user->getRoles())));
    $session->save();
    $client->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId()));
}
$repo = new App\Repository\CartRepository($db);
$accounts = new App\Repository\AccountRepository($db);
$client->request('GET', '/'); check($client->getResponse()->isSuccessful(), 'Website root renders home');
$client->request('GET', '/home'); check($client->getResponse()->isSuccessful(), 'Existing home URL retained');
$userId = (string)$db->fetchOne('SELECT TOP 1 THANH_VIEN_TEN_DANG_NHAP FROM dbo.THANH_VIEN ORDER BY THANH_VIEN_TEN_DANG_NHAP');
$adminId = (string)$db->fetchOne('SELECT TOP 1 QTV_USER_ADMIN FROM dbo.QTV');
$client->request('GET', '/gio-hang/lich-su'); check($client->getResponse()->isRedirect('/dang-nhap'), 'Anonymous history blocked');
$client->request('GET', '/admin/gio-hang'); check($client->getResponse()->isRedirect(), 'Anonymous admin blocked');
loginAs($accounts->byId($userId, false));
$client->request('GET', '/admin/gio-hang'); check($client->getResponse()->getStatusCode() === 403, 'Customer admin blocked');
$client->request('GET', '/admin/gio-hang/1'); check($client->getResponse()->getStatusCode() === 403, 'Customer admin detail blocked');
$client->request('GET', '/gio-hang/lich-su'); check($client->getResponse()->isSuccessful(), 'Customer history rendered');
$own = $repo->search($userId, '', '', 1);
check($own['total'] === (int)$db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG WHERE THANH_VIEN_TEN_DANG_NHAP = ?', [$userId]), 'Owner scoped count');
foreach ($own['carts'] as $cart) {
    check((string)$cart['THANH_VIEN_TEN_DANG_NHAP'] === $userId, 'Owner scoped row');
    $detailPage = $client->request('GET', '/gio-hang/lich-su/'.$cart['GIO_HANG_ID_GIO']); check($client->getResponse()->isSuccessful(), 'Own detail rendered');
    check($detailPage->filter('.customer-nav a[href="/gio-hang/lich-su"].active[aria-current="page"]')->count() === 1, 'History navigation active on detail');
}
$other = $db->fetchOne('SELECT TOP 1 GIO_HANG_ID_GIO FROM dbo.GIO_HANG WHERE THANH_VIEN_TEN_DANG_NHAP <> ? OR THANH_VIEN_TEN_DANG_NHAP IS NULL', [$userId]);
check($other !== false, 'Foreign cart fixture exists');
$client->request('GET', '/gio-hang/lich-su/'.$other); check($client->getResponse()->getStatusCode() === 404, 'Foreign cart hidden');
$client->request('GET', '/gio-hang/lich-su/2147483647'); check($client->getResponse()->getStatusCode() === 404, 'Missing cart hidden');
loginAs($accounts->byId($adminId, true));
$client->request('GET', '/gio-hang/lich-su'); check($client->getResponse()->getStatusCode() === 403, 'Admin customer route blocked');
foreach (['/admin/gio-hang', '/admin/dashboard', '/admin/gio-hang?q=%27%20OR%201%3D1--', '/admin/gio-hang?status=__null', '/admin/gio-hang?page=999999'] as $url) {
    $client->request('GET', $url); check($client->getResponse()->isSuccessful(), 'Rendered '.$url);
}
$all = $repo->search(null, '', '', 1);
check($all['total'] === (int)$db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG'), 'Admin sees all carts');
check($repo->pendingCount() === (int)$db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG WHERE GIO_HANG_TRANG_THAI = ?', ['Chờ duyệt']), 'Dashboard pending count');
check($repo->search(null, '', "' OR 1=1--", 1)['total'] === 0, 'Parameterized search');
check($repo->search(null, '', '', PHP_INT_MAX)['page'] === $all['pages'], 'Page clamped');
foreach ($repo->statuses(null) as $status) {
    $filtered = $repo->search(null, $status, '', 1);
    check($filtered['total'] === (int)$db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG WHERE GIO_HANG_TRANG_THAI = ?', [$status]), 'Status filter');
}
foreach ($all['carts'] as $cart) {
    $id = $cart['GIO_HANG_ID_GIO'];
    $client->request('GET', '/admin/gio-hang/'.$id); check($client->getResponse()->isSuccessful(), 'Admin detail rendered');
    $expected = $db->fetchOne('SELECT CASE WHEN COUNT(*) = COUNT(s.SACH_GIA_BAN) THEN COALESCE(SUM(CAST(s.SACH_GIA_BAN AS decimal(28,2)) * m.MUA_SO_LUONG_MUA), 0) ELSE NULL END FROM dbo.MUA m LEFT JOIN dbo.SACH s ON s.SACH_ID_SACH = m.SACH_ID_SACH WHERE m.GIO_HANG_ID_GIO = ?', [$id]);
    check($expected === $cart['current_total'], 'Exact decimal total');
}
$client->request('POST', '/admin/gio-hang/'.$other); check($client->getResponse()->getStatusCode() === 405, 'Read endpoint rejects POST');
$twig = new Twig\Environment(new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader(['account/layout.html.twig'=>'{% block content %}{% endblock %}']),
    new Twig\Loader\FilesystemLoader(dirname(__DIR__).'/templates'),
]), ['autoescape'=>'html', 'strict_variables'=>true]);
$twig->addFunction(new Twig\TwigFunction('path', static fn(string $route, array $params = []): string => '/'.$route));
$twig->addFunction(new Twig\TwigFunction('asset', static fn(string $path): string => '/'.$path));
// This isolated renderer checks escaping/NULL handling, not CSRF validation.
// Actual HTTP CSRF checks run through Symfony in the integration/web suites.
$twig->addFunction(new Twig\TwigFunction('csrf_token', static fn(string $id): string => 'template-test-token'));
$twig->addExtension(new App\Twig\MoneyExtension());
$emptyCart = ['GIO_HANG_ID_GIO'=>0, 'GIO_HANG_TRANG_THAI'=>null, 'GIO_HANG_THOI_GIAN'=>null,
    'THANH_VIEN_HO_TEN'=>null, 'THANH_VIEN_TEN_DANG_NHAP'=>null, 'QTV_HO_TEN'=>null,
    'QTV_USER_ADMIN'=>null, 'GIO_HANG_GHI_CHU'=>null, 'current_total'=>'0.00'];
$html = $twig->render('gio_hang/detail.html.twig', ['admin'=>true, 'cart'=>$emptyCart, 'lines'=>[]]);
check(str_contains($html, 'Giỏ hàng trống') && str_contains($html, '0 ₫') && str_contains($html, 'Chưa có trạng thái'), 'Empty cart and NULL metadata render');
$emptyCart['current_total'] = null;
$emptyCart['GIO_HANG_GHI_CHU'] = '<script>alert(1)</script>';
$html = $twig->render('gio_hang/detail.html.twig', ['admin'=>true, 'cart'=>$emptyCart,
    'lines'=>[['SACH_ID_SACH'=>0, 'SACH_TEN_SACH'=>null, 'MUA_SO_LUONG_MUA'=>2, 'SACH_GIA_BAN'=>null, 'subtotal'=>null]]]);
check(str_contains($html, 'Chưa có giá') && str_contains($html, 'Chưa đủ dữ liệu giá'), 'NULL price not shown as zero');
check(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;'), 'Notes escaped');
echo "$checks checks passed; no database writes.\n";
$kernel->shutdown();
