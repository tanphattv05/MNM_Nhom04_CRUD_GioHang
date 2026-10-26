<?php
ob_start();
// Integration checks against the configured development SQL Server.
// All fixture writes and application writes are rolled back in one transaction.
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', false);
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
$tables = ['THANH_VIEN','QTV','GIO_HANG','MUA','SACH','LOAI_SACH'];
$before = [];
$fingerprint = static function (string $table) use ($db): string {
    $rows = array_map('serialize', $db->fetchAllAssociative('SELECT * FROM dbo.'.$table));
    sort($rows); return hash('sha256', serialize($rows));
};
foreach ($tables as $table) { $before[$table] = $fingerprint($table); }
$db->beginTransaction();
try {
    $suffix = bin2hex(random_bytes(6));
    $email = 'phase2-'.$suffix.'@example.test';
    $password = bin2hex(random_bytes(10));
    $admin = 'test_'.$suffix;
    $db->insert('dbo.QTV',['QTV_USER_ADMIN'=>$admin,'QTV_HO_TEN'=>'Kiểm thử','QTV_MAT_KHAU'=>password_hash($password,PASSWORD_BCRYPT)]);
    $register = function (array $override = []) use ($email,$password) {
        global $client;
        $crawler = page('/dang-ky');
        $values = ['account[name]'=>'Người kiểm thử','account[phone]'=>'0901234567','account[address]'=>'Địa chỉ kiểm thử','account[email]'=>$email,'account[password][first]'=>$password,'account[password][second]'=>$password];
        $form = $crawler->selectButton('Đăng ký')->form(array_replace($values,$override));
        $form->disableValidation(); $client->submit($form);
    };
    $crawler = page('/dang-ky'); $client->submit($crawler->selectButton('Đăng ký')->form(['account[_token]'=>'invalid']));
    check($client->getResponse()->getStatusCode()===422,'CSRF đăng ký sai bị chặn');
    $register(['account[email]'=>'bad-email']); check($client->getResponse()->getStatusCode()===422,'Email không hợp lệ');
    $register(['account[password][second]'=>'mismatch']); check($client->getResponse()->getStatusCode()===422,'Xác nhận mật khẩu sai');
    $register(['account[password][first]'=>'short','account[password][second]'=>'short']); check($client->getResponse()->getStatusCode()===422,'Mật khẩu ngắn bị chặn');
    $register(); check($client->getResponse()->isRedirect('/dang-nhap'),'Đăng ký hợp lệ');
    $row = $db->fetchAssociative('SELECT * FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email]);
    check($row && password_verify($password,$row['THANH_VIEN_MAT_KHAU']),'Mật khẩu SQL đã hash');
    $db->update('dbo.THANH_VIEN',['THANH_VIEN_MAT_KHAU'=>$password],['THANH_VIEN_TEN_DANG_NHAP'=>$row['THANH_VIEN_TEN_DANG_NHAP']]);
    login('/dang-nhap',$email,$password); check($client->getResponse()->isRedirect('/dang-nhap'),'Không chấp nhận mật khẩu văn bản cũ');
    $application = new Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
    $command = new Symfony\Component\Console\Tester\CommandTester($application->find('app:account:password'));
    $command->execute(['kind'=>'user','id'=>(string)$row['THANH_VIEN_TEN_DANG_NHAP'],'--convert-legacy'=>true,'--apply'=>true]);
    $converted = $db->fetchOne('SELECT THANH_VIEN_MAT_KHAU FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email]);
    check($command->getStatusCode()===0 && password_verify($password,$converted),'Lệnh chuyển đổi mật khẩu mẫu');
    $command->execute(['kind'=>'user','id'=>(string)$row['THANH_VIEN_TEN_DANG_NHAP'],'--convert-legacy'=>true,'--apply'=>true]);
    check($converted === $db->fetchOne('SELECT THANH_VIEN_MAT_KHAU FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email]),'Không hash lại mật khẩu đã hash');
    $register(); check($client->getResponse()->getStatusCode()===422,'Trùng email');
    page('/gio-hang'); check($client->getResponse()->isRedirect('/dang-nhap'),'Khách chưa đăng nhập bị chặn');
    page('/admin/dashboard'); check($client->getResponse()->isRedirect('/admin/dang-nhap'),'Khách Admin chưa đăng nhập bị chặn');
    login('/dang-nhap',$email,'wrong-password'); check($client->getResponse()->isRedirect('/dang-nhap'),'Sai mật khẩu khách hàng');
    $crawler = page('/dang-nhap'); $client->submit($crawler->selectButton('Đăng nhập')->form(['_username'=>$email,'_password'=>$password,'_csrf_token'=>'invalid']));
    page('/tai-khoan'); check($client->getResponse()->isRedirect('/dang-nhap'),'CSRF đăng nhập sai bị chặn');
    login('/dang-nhap',$email,$password); check($client->getResponse()->isRedirect('/trang-chu'),'Đăng nhập khách hàng');
    page('/admin/dashboard'); check($client->getResponse()->getStatusCode()===403,'Khách hàng bị từ chối Admin');
    $crawler = page('/tai-khoan'); $client->submit($crawler->selectButton('Lưu thông tin')->form(['account[name]'=>'Tên đã cập nhật']));
    check($client->getResponse()->isRedirect('/tai-khoan') && $db->fetchOne('SELECT THANH_VIEN_HO_TEN FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email])==='Tên đã cập nhật','Cập nhật hồ sơ của chính mình');
    $crawler = page('/tai-khoan/doi-mat-khau'); $client->submit($crawler->selectButton('Đổi mật khẩu và đăng nhập lại')->form(['change_password[current]'=>'wrong','change_password[password][first]'=>$password.'x','change_password[password][second]'=>$password.'x']));
    check($client->getResponse()->getStatusCode()===422,'Đổi mật khẩu: mật khẩu cũ sai');
    $crawler = page('/tai-khoan'); $client->submit($crawler->selectButton('Đăng xuất')->form(['_csrf_token'=>'invalid']));
    page('/tai-khoan'); check($client->getResponse()->getStatusCode()===200,'CSRF đăng xuất sai giữ phiên đăng nhập');
    $crawler = page('/tai-khoan'); $client->submit($crawler->selectButton('Đăng xuất')->form()); page('/tai-khoan'); check($client->getResponse()->isRedirect('/dang-nhap'),'Đăng xuất khách hàng');
    login('/admin/dang-nhap',$admin,'wrong-password'); check($client->getResponse()->isRedirect('/admin/dang-nhap'),'Sai mật khẩu Admin');
    login('/admin/dang-nhap',$admin,$password); check($client->getResponse()->isRedirect('/admin/dashboard'),'Đăng nhập Admin');
    $crawler = page('/admin/dashboard'); check($client->getResponse()->getStatusCode()===200,'Dashboard Admin');
    page('/tai-khoan'); check($client->getResponse()->getStatusCode()===403,'Admin không truy cập hồ sơ khách hàng');
    $crawler = page('/admin/dashboard'); $client->submit($crawler->selectButton('Đăng xuất')->form()); page('/admin/dashboard'); check($client->getResponse()->isRedirect('/admin/dang-nhap'),'Đăng xuất Admin');
    login('/dang-nhap',$email,$password);
    $crawler = page('/tai-khoan/doi-mat-khau'); $client->submit($crawler->selectButton('Đổi mật khẩu và đăng nhập lại')->form(['change_password[current]'=>$password,'change_password[password][first]'=>$password.'x','change_password[password][second]'=>$password.'x']));
    check($client->getResponse()->isRedirect('/dang-nhap') && password_verify($password.'x',$db->fetchOne('SELECT THANH_VIEN_MAT_KHAU FROM dbo.THANH_VIEN WHERE THANH_VIEN_EMAIL = ?',[$email])),'Đổi mật khẩu: mật khẩu cũ đúng');
    page('/tai-khoan'); check($client->getResponse()->isRedirect('/dang-nhap'),'Đổi mật khẩu vô hiệu hóa phiên');
    for ($i=0;$i<6;++$i) { login('/dang-nhap',$email,'wrong-password'); }
    login('/dang-nhap',$email,$password);
    check($client->getResponse()->isRedirect('/dang-nhap'),'Giới hạn đăng nhập sau nhiều lần sai');
} finally {
    if ($db->isTransactionActive()) { $db->rollBack(); }
    foreach ($tables as $table) { check($before[$table] === $fingerprint($table),'Giữ nguyên toàn bộ dữ liệu '.$table); }
    $kernel->shutdown();
}
echo "$checks checks passed. All writes rolled back.\n";
