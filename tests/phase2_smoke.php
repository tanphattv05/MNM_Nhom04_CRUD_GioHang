<?php
ob_start();
// Integration checks against the configured development SQL Server.
// All fixture writes and application writes are rolled back in one transaction.
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', true);
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$client->setServerParameter('REMOTE_ADDR', '127.0.'.random_int(1,254).'.'.random_int(1,254));
$client->catchExceptions(true);
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks;
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
    ++$checks; echo "PASS: $label\n";
}
function page(string $url): Symfony\Component\DomCrawler\Crawler {
    global $client;
    return $client->request('GET',$url);
}
function login(string $url,string $name,string $password): void {
    global $client;
    $crawler = page($url);
    $client->submit($crawler->selectButton('Đăng nhập')->form(['_username'=>$name,'_password'=>$password]));
}
page('/dang-nhap');
$db = $kernel->getContainer()->get('doctrine')->getConnection();
check((int)$db->fetchOne('SELECT 1') === 1, 'SQL Server connected');
$tables = ['THANH_VIEN','QTV','GIO_HANG','MUA','SACH','LOAI_SACH'];
$before = [];
$fingerprint = static function (string $table) use ($db): string {
    $rows = array_map('serialize', $db->fetchAllAssociative('SELECT * FROM dbo.'.$table));
    sort($rows); return hash('sha256', serialize($rows));
};
foreach ($tables as $table) { $before[$table] = $fingerprint($table); }
$db->beginTransaction();
try {
    // Exercise the actual migration inside the same rollback as the fixtures.
    if (!$db->fetchOne("SELECT COL_LENGTH('dbo.THANH_VIEN', 'THANH_VIEN_USERNAME')")) {
        require dirname(__DIR__).'/migrations/Version20261026000100.php';
        $migration = new DoctrineMigrations\Version20261026000100($db, new Psr\Log\NullLogger());
        $migration->up(new Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) { $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes()); }
        check((int)$db->fetchOne("SELECT COUNT(*) FROM dbo.THANH_VIEN WHERE THANH_VIEN_USERNAME <> 'user_' + CONVERT(varchar(11), THANH_VIEN_TEN_DANG_NHAP)") === 0, 'Migration backfills unique temporary usernames');
    }
    check((int)$db->fetchOne('SELECT COUNT(*) - COUNT(DISTINCT THANH_VIEN_USERNAME) FROM dbo.THANH_VIEN') === 0, 'All usernames unique and non-NULL');
    check($db->fetchOne("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='THANH_VIEN' AND COLUMN_NAME='THANH_VIEN_TEN_DANG_NHAP'") === 'int', 'Integer primary key preserved');
    $suffix = bin2hex(random_bytes(6));
    $username = 'test_'.$suffix;
    $email = 'phase2-'.$suffix.'@example.test';
    $password = bin2hex(random_bytes(10));
    $admin = 'staff_'.$suffix;
    $db->insert('dbo.QTV',['QTV_USER_ADMIN'=>$admin,'QTV_HO_TEN'=>'Kiểm thử','QTV_MAT_KHAU'=>password_hash($password,PASSWORD_BCRYPT)]);
    page('/admin/dang-nhap'); check($client->getResponse()->isRedirect('/dang-nhap'), 'Legacy admin login redirects to shared login');
    $client->request('POST', '/admin/dang-nhap', ['_username'=>$admin, '_password'=>$password]);
    check($client->getResponse()->isRedirect('/dang-nhap'), 'Legacy admin POST only redirects');
    $register = function (array $override = []) use ($email,$password,$username) {
        global $client;
        $crawler = page('/dang-ky');
        $values = ['account[username]'=>$username,'account[name]'=>'Người kiểm thử','account[phone]'=>'0901234567','account[address]'=>'Địa chỉ kiểm thử','account[email]'=>$email,'account[password][first]'=>$password,'account[password][second]'=>$password];
        $form = $crawler->selectButton('Đăng ký')->form(array_replace($values,$override));
        $form->disableValidation(); $client->submit($form);
    };
    $crawler = page('/dang-ky'); $client->submit($crawler->selectButton('Đăng ký')->form(['account[_token]'=>'invalid']));
    check($client->getResponse()->getStatusCode()===422,'CSRF đăng ký sai bị chặn');
    $register(['account[email]'=>'bad-email']); check($client->getResponse()->getStatusCode()===422,'Email không hợp lệ');
    $register(['account[password][second]'=>'mismatch']); check($client->getResponse()->getStatusCode()===422,'Xác nhận mật khẩu sai');
    $register(['account[password][first]'=>'short','account[password][second]'=>'short']); check($client->getResponse()->getStatusCode()===422,'Mật khẩu ngắn bị chặn');
    $register(['account[username]'=>strtoupper($admin)]);
    check($client->getResponse()->getStatusCode()===422, 'Cannot register reserved admin name');
    foreach (['', 'abc', str_repeat('a',31), 'bad@email.test'] as $invalid) {
        $register(['account[username]'=>$invalid]); check($client->getResponse()->getStatusCode()===422, 'Invalid username rejected');
    }
    $register(); check($client->getResponse()->isRedirect('/dang-nhap'),'Đăng ký hợp lệ');
    $row = $db->fetchAssociative('SELECT * FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email]);
    check($row && password_verify($password,$row['THANH_VIEN_MAT_KHAU']),'Mật khẩu SQL đã hash');
    $register(['account[username]'=>strtoupper($username), 'account[email]'=>'other-'.$email]);
    check($client->getResponse()->getStatusCode()===422, 'Duplicate username rejected case insensitively');
    $register(['account[username]'=>$username.'_2']);
    check($client->getResponse()->isRedirect('/dang-nhap'), 'Contact email may be shared by distinct usernames');
    $repository = new App\Repository\AccountRepository($db);
    check($repository->byLogin($email, false) === null, 'Email is never a customer login');
    check($repository->byLogin(strtoupper($username), false)?->id === (string)$row['THANH_VIEN_TEN_DANG_NHAP'], 'Username lookup preserves integer identity');
    // Simulate an existing cross-table collision without changing stored accounts.
    $collisionPassword = bin2hex(random_bytes(12));
    $db->insert('dbo.QTV', ['QTV_USER_ADMIN'=>$username, 'QTV_HO_TEN'=>'Collision fixture', 'QTV_MAT_KHAU'=>password_hash($collisionPassword, PASSWORD_BCRYPT)]);
    check($repository->byUnifiedLogin(strtoupper($username))?->admin === true, 'Collision resolves to admin independent of case');
    login('/dang-nhap', $username, $password);
    check($client->getResponse()->isRedirect('/dang-nhap'), 'Collision rejects customer password without fallback');
    page('/admin/dashboard'); check($client->getResponse()->isRedirect('/dang-nhap'), 'Collision never elevates customer privileges');
    login('/dang-nhap', $username, $collisionPassword);
    check($client->getResponse()->isRedirect('/admin/dashboard'), 'Collision accepts only admin credentials');
    $crawler = page('/admin/dashboard');
    $client->submit($crawler->selectButton('Đăng xuất')->form());
    check($client->getResponse()->isRedirect('/dang-nhap'), 'Admin logout uses shared login');
    $db->delete('dbo.QTV', ['QTV_USER_ADMIN'=>$username]);
    $client->setServerParameter('REMOTE_ADDR', '127.0.'.random_int(1,254).'.'.random_int(1,254));
    $db->update('dbo.THANH_VIEN',['THANH_VIEN_MAT_KHAU'=>$password],['THANH_VIEN_TEN_DANG_NHAP'=>$row['THANH_VIEN_TEN_DANG_NHAP']]);
    login('/dang-nhap',$username,$password); check($client->getResponse()->isRedirect('/dang-nhap'),'Không chấp nhận mật khẩu văn bản cũ');
    $application = new Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
    $command = new Symfony\Component\Console\Tester\CommandTester($application->find('app:account:password'));
    $command->execute(['kind'=>'user','id'=>(string)$row['THANH_VIEN_TEN_DANG_NHAP'],'--convert-legacy'=>true,'--apply'=>true]);
    $converted = $db->fetchOne('SELECT THANH_VIEN_MAT_KHAU FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email]);
    check($command->getStatusCode()===0 && password_verify($password,$converted),'Lệnh chuyển đổi mật khẩu mẫu');
    $command->execute(['kind'=>'user','id'=>(string)$row['THANH_VIEN_TEN_DANG_NHAP'],'--convert-legacy'=>true,'--apply'=>true]);
    check($converted === $db->fetchOne('SELECT THANH_VIEN_MAT_KHAU FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email]),'Không hash lại mật khẩu đã hash');
    $register(); check($client->getResponse()->getStatusCode()===422,'Trùng tên đăng nhập');
    page('/gio-hang'); check($client->getResponse()->isRedirect('/dang-nhap'),'Khách chưa đăng nhập bị chặn');
    page('/admin/dashboard'); check($client->getResponse()->isRedirect('/dang-nhap'),'Khách Admin chưa đăng nhập bị chặn');
    login('/dang-nhap',$username,'wrong-password'); check($client->getResponse()->isRedirect('/dang-nhap'),'Sai mật khẩu khách hàng');
    $wrongError = $client->followRedirect()->filter('.error')->text();
    login('/dang-nhap',$email,$password); check($client->getResponse()->isRedirect('/dang-nhap'), 'Email login rejected over HTTP');
    check($client->followRedirect()->filter('.error')->text() === $wrongError, 'Login error does not disclose account existence');
    $client->setServerParameter('REMOTE_ADDR', '127.0.'.random_int(1,254).'.'.random_int(1,254));
    $crawler = page('/dang-nhap'); $client->submit($crawler->selectButton('Đăng nhập')->form(['_username'=>$username,'_password'=>$password,'_csrf_token'=>'invalid']));
    page('/tai-khoan'); check($client->getResponse()->isRedirect('/dang-nhap'),'CSRF đăng nhập sai bị chặn');
    login('/dang-nhap',$username,$password); check($client->getResponse()->isRedirect('/trang-chu'),'Đăng nhập khách hàng');
    page('/admin/dashboard'); check($client->getResponse()->getStatusCode()===403,'Khách hàng bị từ chối Admin');
    $crawler = page('/tai-khoan'); $client->submit($crawler->selectButton('Lưu thông tin')->form(['account[name]'=>'Tên đã cập nhật']));
    check($client->getResponse()->isRedirect('/tai-khoan') && $db->fetchOne('SELECT THANH_VIEN_HO_TEN FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email])==='Tên đã cập nhật','Cập nhật hồ sơ của chính mình');
    $crawler = page('/tai-khoan');
    $client->submit($crawler->selectButton('Lưu thông tin')->form(['account[username]'=>$admin]));
    check($client->getResponse()->getStatusCode() === 422, 'Profile cannot claim reserved admin name');
    $crawler = page('/tai-khoan');
    $client->submit($crawler->selectButton('Lưu thông tin')->form(['account[username]'=>$username.'_2']));
    check($client->getResponse()->getStatusCode() === 422, 'Profile rejects another customers username');
    $oldUsername = $username;
    $username .= '_new';
    $crawler = page('/tai-khoan');
    $client->submit($crawler->selectButton('Lưu thông tin')->form(['account[username]'=>$username]));
    check($client->getResponse()->isRedirect('/tai-khoan'), 'Customer can rename temporary username');
    check($repository->byLogin($oldUsername, false) === null && $repository->byLogin($username, false)?->id === (string)$row['THANH_VIEN_TEN_DANG_NHAP'], 'Renaming retains immutable ID');
    page('/gio-hang/lich-su'); check($client->getResponse()->isSuccessful(), 'Renamed account session retains cart access');
    $crawler = page('/tai-khoan/doi-mat-khau'); $client->submit($crawler->selectButton('Đổi mật khẩu và đăng nhập lại')->form(['change_password[current]'=>'wrong','change_password[password][first]'=>$password.'x','change_password[password][second]'=>$password.'x']));
    check($client->getResponse()->getStatusCode()===422,'Đổi mật khẩu: mật khẩu cũ sai');
    $crawler = page('/tai-khoan'); $client->submit($crawler->selectButton('Đăng xuất')->form(['_csrf_token'=>'invalid']));
    page('/tai-khoan'); check($client->getResponse()->getStatusCode()===200,'CSRF đăng xuất sai giữ phiên đăng nhập');
    $crawler = page('/tai-khoan'); $client->submit($crawler->selectButton('Đăng xuất')->form()); page('/tai-khoan'); check($client->getResponse()->isRedirect('/dang-nhap'),'Đăng xuất khách hàng');
    login('/dang-nhap',$admin,'wrong-password'); check($client->getResponse()->isRedirect('/dang-nhap'),'Sai mật khẩu Admin');
    login('/dang-nhap',$admin,$password); check($client->getResponse()->isRedirect('/admin/dashboard'),'Đăng nhập Admin');
    $crawler = page('/admin/dashboard'); check($client->getResponse()->getStatusCode()===200,'Dashboard Admin');
    page('/tai-khoan'); check($client->getResponse()->getStatusCode()===403,'Admin không truy cập hồ sơ khách hàng');
    $crawler = page('/admin/dashboard'); $client->submit($crawler->selectButton('Đăng xuất')->form()); page('/admin/dashboard'); check($client->getResponse()->isRedirect('/dang-nhap'),'Đăng xuất Admin');
    login('/dang-nhap',$username,$password);
    $crawler = page('/tai-khoan/doi-mat-khau'); $client->submit($crawler->selectButton('Đổi mật khẩu và đăng nhập lại')->form(['change_password[current]'=>$password,'change_password[password][first]'=>$password.'x','change_password[password][second]'=>$password.'x']));
    check($client->getResponse()->isRedirect('/dang-nhap') && password_verify($password.'x',$db->fetchOne('SELECT THANH_VIEN_MAT_KHAU FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email])),'Đổi mật khẩu: mật khẩu cũ đúng');
    page('/tai-khoan'); check($client->getResponse()->isRedirect('/dang-nhap'),'Đổi mật khẩu vô hiệu hóa phiên');
    for ($i=0;$i<6;++$i) { login('/dang-nhap',$username,'wrong-password'); }
    login('/dang-nhap',$username,$password);
    check($client->getResponse()->isRedirect('/dang-nhap'),'Giới hạn đăng nhập sau nhiều lần sai');
} finally {
    if ($db->isTransactionActive()) { $db->rollBack(); }
    foreach ($tables as $table) { check($before[$table] === $fingerprint($table),'Giữ nguyên toàn bộ dữ liệu '.$table); }
    $kernel->shutdown();
}
echo "$checks checks passed. All writes rolled back.\n";
