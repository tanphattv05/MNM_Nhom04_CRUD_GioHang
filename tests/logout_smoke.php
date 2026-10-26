<?php
ob_start();
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', true);
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$client->request('GET', '/dang-nhap');
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
    ++$checks; echo "PASS: $label\n";
}
check((int)$db->fetchOne('SELECT 1') === 1, 'SQL Server connected');
$accounts = new App\Repository\AccountRepository($db);
$userId = $db->fetchOne('SELECT TOP 1 THANH_VIEN_TEN_DANG_NHAP FROM dbo.THANH_VIEN ORDER BY THANH_VIEN_TEN_DANG_NHAP');
$adminId = $db->fetchOne('SELECT TOP 1 QTV_USER_ADMIN FROM dbo.QTV ORDER BY QTV_USER_ADMIN');
check($userId !== false && $adminId !== false, 'Existing user and admin available');
foreach ([[false, 0], [true, 0], [true, 1]] as [$admin, $button]) {
    $kernel->shutdown();
    $kernel = new App\Kernel('dev', true);
    $client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
    $client->disableReboot();
    $client->request('GET', '/dang-nhap');
    $user = $accounts->byId((string)($admin ? $adminId : $userId), $admin);
    $session = new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage());
    foreach ($client->getCookieJar()->all() as $cookie) {
        if ($cookie->getName() === $session->getName()) { $session->setId($cookie->getValue()); }
    }
    $session->set('_security_main', serialize(new Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken($user, 'main', $user->getRoles())));
    $session->set('logout_test_marker', bin2hex(random_bytes(8)));
    $session->save();
    $client->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId()));
    $page = $admin ? '/admin/dashboard' : '/trang-chu';
    $login = '/dang-nhap';
    $crawler = $client->request('GET', $page);
    check($client->getResponse()->isSuccessful(), 'Authenticated page');
    $forms = $crawler->filter('form[action="/dang-xuat"]');
    check($forms->count() === ($admin ? 2 : 1), 'All logout buttons present');
    foreach ($forms as $node) { check(strtolower($node->getAttribute('method')) === 'post', 'Logout form uses POST'); }
    $token = $forms->eq($button)->filter('input[name="_csrf_token"]')->attr('value');
    check($token !== '', 'Logout token generated');
    foreach ([['_csrf_token'=>'invalid'], []] as $post) {
        $client->request('POST', '/dang-xuat', $post);
        check($client->getResponse()->getStatusCode() === 403, 'Invalid or missing token rejected');
        $client->request('GET', $page);
        check($client->getResponse()->isSuccessful(), 'Rejected logout preserves authentication');
    }
    $client->request('GET', '/dang-xuat', ['_csrf_token'=>$token]);
    check($client->getResponse()->getStatusCode() === 405, 'GET logout rejected even with token');
    $client->request('GET', $page);
    check($client->getResponse()->isSuccessful(), 'GET does not log out');
    $oldCookies = clone $client->getCookieJar();
    $crawler = $client->request('GET', $page);
    $form = $crawler->filter('form[action="/dang-xuat"]')->eq($button)->form();
    $client->submit($form);
    check($client->getResponse()->isRedirect($login), 'Valid POST redirects to matching login');
    $client->request('GET', $page);
    check($client->getResponse()->isRedirect($login), 'Protected page blocked after logout');
    foreach ($oldCookies->all() as $cookie) { $client->getCookieJar()->set($cookie); }
    $client->request('GET', $page);
    check($client->getResponse()->isRedirect($login), 'Old session cookie cannot restore authentication');
    $request = $client->getRequest();
    check(!$request->getSession()->has('logout_test_marker'), 'Session data invalidated');
    $request->getSession()->save();
}
$kernel->shutdown();
echo "$checks checks passed; no database writes.\n";
