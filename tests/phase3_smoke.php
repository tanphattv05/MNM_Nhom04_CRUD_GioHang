<?php
ob_start();
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('dev', true);
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$client->request('GET','/dang-nhap');
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$books = new App\Repository\BookRepository($db);
$accounts = new App\Repository\AccountRepository($db);
$userId = $db->fetchOne('SELECT TOP 1 THANH_VIEN_TEN_DANG_NHAP FROM dbo.THANH_VIEN ORDER BY THANH_VIEN_TEN_DANG_NHAP');
$adminId = $db->fetchOne('SELECT TOP 1 QTV_USER_ADMIN FROM dbo.QTV ORDER BY QTV_USER_ADMIN');
$user = $accounts->byId((string)$userId,false);
$admin = $accounts->byId((string)$adminId,true);
$checks=0;
function verify(bool $ok, string $label): void { global $checks; if (!$ok) { throw new RuntimeException('FAIL: '.$label); } ++$checks; echo "PASS: $label\n"; }
function getPage(string $url): Symfony\Component\DomCrawler\Crawler { global $client; return $client->request('GET',$url); }
function loginAs(App\Security\Account $user): void {
    global $client,$kernel;
    $session=new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage());
    foreach ($client->getCookieJar()->all() as $cookie) { if ($cookie->getName() === $session->getName()) { $session->setId($cookie->getValue()); } }
    $session->set('_security_main',serialize(new Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken($user,'main',$user->getRoles())));
    $session->save();
    $client->getCookieJar()->set(new Symfony\Component\BrowserKit\Cookie($session->getName(),$session->getId()));
}
function modifyCart(int $id, int|string $quantity, string $action = 'add', bool $csrf = true): void {
    global $client;
    $crawler = getPage('/danh-muc');
    $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
    $client->request('POST','/gio-hang/thay-doi',['_token'=>$csrf ? $token : 'bad','book_id'=>$id,'quantity'=>(string)$quantity,'action'=>$action]);
}
$fingerprint = static function (string $table) use ($db): string { $rows=array_map('serialize',$db->fetchAllAssociative('SELECT * FROM dbo.'.$table)); sort($rows); return hash('sha256',serialize($rows)); };
$before=[]; foreach (['SACH','LOAI_SACH','THANH_VIEN','QTV','GIO_HANG','MUA'] as $table) { $before[$table]=$fingerprint($table); }
$db->beginTransaction();
try {
    $client->request('POST','/gio-hang/gui'); verify($client->getResponse()->isRedirect('/dang-nhap'),'Khách chưa đăng nhập không được gửi');
    loginAs($admin); $client->request('POST','/gio-hang/gui'); verify($client->getResponse()->getStatusCode()===403,'Admin không được tạo giỏ khách');
    loginAs($user);
    $crawler=getPage('/danh-muc'); verify($client->getResponse()->isSuccessful() && $crawler->filter('.product-card')->count()===12,'Danh mục hiển thị 12 sách thật');
    $filters=['q'=>'','category'=>'','min'=>'','max'=>'','sort'=>'name'];
    $all=$books->catalog($filters,1); $page2=$books->catalog($filters,2);
    verify($all['total']==$db->fetchOne('SELECT COUNT(*) FROM dbo.SACH') && $all['books'][0]['SACH_ID_SACH']!==$page2['books'][0]['SACH_ID_SACH'],'Phân trang và tổng sách');
    $sample=$all['books'][0];
    $search=$books->catalog(array_replace($filters,['q'=>$sample['SACH_TEN_SACH']]),1); verify(count($search['books'])>=1,'Tìm theo tên sách');
    $search=$books->catalog(array_replace($filters,['q'=>$sample['SACH_TAC_GIA']]),1); verify(count($search['books'])>=1,'Tìm theo tác giả');
    $filtered=$books->catalog(array_replace($filters,['category'=>(string)$sample['LOAI_SACH_MA_LOAI'],'min'=>'50000','max'=>'200000']),1);
    verify(count($filtered['books'])>0 && count(array_filter($filtered['books'],fn($b)=>$b['LOAI_SACH_MA_LOAI']==$sample['LOAI_SACH_MA_LOAI'] && $b['SACH_GIA_BAN']>=50000 && $b['SACH_GIA_BAN']<=200000))===count($filtered['books']),'Lọc loại sách và khoảng giá');
    foreach (['price_asc','price_desc','name'] as $sort) {
        $result=$books->catalog(array_replace($filters,['sort'=>$sort]),1);
        $expected=$db->fetchOne('SELECT TOP 1 SACH_ID_SACH FROM dbo.SACH ORDER BY '.['price_asc'=>'SACH_GIA_BAN ASC','price_desc'=>'SACH_GIA_BAN DESC','name'=>'SACH_TEN_SACH ASC'][$sort].', SACH_ID_SACH ASC');
        verify($result['books'][0]['SACH_ID_SACH']==$expected,'Sắp xếp '.$sort);
    }
    getPage('/danh-muc?q=%27%20OR%201%3D1--'); verify($client->getResponse()->isSuccessful(),'Tìm kiếm có ký tự SQL được tham số hóa');
    $available=$db->fetchAllAssociative('SELECT TOP 2 * FROM dbo.SACH WHERE SACH_SO_LUONG >= 5 AND SACH_GIA_BAN IS NOT NULL ORDER BY SACH_ID_SACH');
    [$first,$second]=$available; $firstId=(int)$first['SACH_ID_SACH']; $secondId=(int)$second['SACH_ID_SACH'];
    modifyCart($firstId,1,'add',false); verify($client->getResponse()->getStatusCode()===403,'CSRF thêm sách sai bị chặn');
    modifyCart($firstId,1); modifyCart($firstId,2); modifyCart($secondId,1);
    $crawler=getPage('/gio-hang'); verify($crawler->filter('.cart-line')->count()===2 && $crawler->filter('#cart-quantity-'.$firstId)->attr('value')==='3','Nhiều sách và thêm trùng cộng số lượng');
    modifyCart($firstId,4,'update'); $crawler=getPage('/gio-hang'); verify($crawler->filter('#cart-quantity-'.$firstId)->attr('value')==='4','Cập nhật số lượng');
    modifyCart($firstId,0,'update'); $crawler=getPage('/gio-hang'); verify($crawler->filter('#cart-quantity-'.$firstId)->attr('value')==='4','Không nhận số lượng nhỏ hơn 1');
    modifyCart($firstId,(int)$first['SACH_SO_LUONG']+1,'update'); $crawler=getPage('/gio-hang'); verify($crawler->filter('#cart-quantity-'.$firstId)->attr('value')==='4','Không vượt tồn kho');
    modifyCart($secondId,1,'remove'); $crawler=getPage('/gio-hang'); verify($crawler->filter('.cart-line')->count()===1,'Bỏ sách khỏi giỏ tạm');
    modifyCart($secondId,1);
    $crawler=getPage('/gio-hang'); $form=$crawler->selectButton('Gửi giỏ hàng')->form(['note'=>'Ghi chú kiểm thử']);
    $post=$form->getPhpValues(); $post['THANH_VIEN_TEN_DANG_NHAP']='999999'; $post['price']='1'; $post['status']='Đã duyệt';
    $count=(int)$db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG');
    $client->request('POST','/gio-hang/gui',$post); verify($client->getResponse()->isRedirect('/gio-hang'),'Gửi giỏ thành công dùng PRG');
    $header=$db->fetchAssociative('SELECT TOP 1 * FROM dbo.GIO_HANG ORDER BY GIO_HANG_ID_GIO DESC');
    verify($header['THANH_VIEN_TEN_DANG_NHAP']==$userId && $header['QTV_USER_ADMIN']===null && $header['GIO_HANG_TRANG_THAI']==='Chờ duyệt' && $header['GIO_HANG_GHI_CHU']==='Ghi chú kiểm thử','Chủ sở hữu và trạng thái lấy từ server');
    $lines=$db->fetchAllAssociative('SELECT * FROM dbo.MUA WHERE GIO_HANG_ID_GIO = ? ORDER BY SACH_ID_SACH',[$header['GIO_HANG_ID_GIO']]);
    verify(count($lines)===2 && $lines[0]['MUA_SO_LUONG_MUA']==4 && $lines[1]['MUA_SO_LUONG_MUA']==1 && $lines[0]['THANH_VIEN_TEN_DANG_NHAP']==$userId,'Lưu đúng các dòng MUA');
    verify($first['SACH_SO_LUONG']==$books->find($firstId)['SACH_SO_LUONG'],'Chờ duyệt không trừ tồn kho');
    getPage('/gio-hang'); $client->request('POST','/gio-hang/gui',$post);
    verify((int)$db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG')===$count+1 && $client->getResponse()->getStatusCode()===422,'Không gửi giỏ trống hoặc gửi trùng khi refresh/replay');

    // Exercise a real SQL rollback after header and first line were inserted.
    $stack=new Symfony\Component\HttpFoundation\RequestStack(); $request=Symfony\Component\HttpFoundation\Request::create('/');
    $request->setSession(new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage())); $stack->push($request);
    $mockFactory=new class('helper') extends PHPUnit\Framework\TestCase { public function connection(): Doctrine\DBAL\Connection { return $this->createMock(Doctrine\DBAL\Connection::class); } };
    $failing=$mockFactory->connection(); $failing->method('transactional')->willReturnCallback(fn($fn)=>$db->transactional($fn));
    $failing->method('fetchOne')->willReturnCallback(fn(...$args)=>$db->fetchOne(...$args));
    $failing->method('executeStatement')->willReturnCallback(fn(...$args)=>$db->executeStatement(...$args));
    $inserts=0; $failing->method('insert')->willReturnCallback(function (...$args) use ($db,&$inserts) { if (++$inserts===2) { throw new RuntimeException('Injected second-line failure'); } return $db->insert(...$args); });
    $cart=new App\Service\CartService($stack,$books,$failing); $cart->modify($user,$firstId,'add',1); $cart->modify($user,$secondId,'add',1);
    $headerBefore=$fingerprint('GIO_HANG'); $linesBefore=$fingerprint('MUA');
    try { $cart->submit($user,$cart->nonce($user),''); throw new LogicException('Expected failure'); } catch (RuntimeException $e) { verify($e->getMessage()==='Injected second-line failure','Mô phỏng lỗi sau khi đã ghi header và một dòng'); }
    verify($inserts===2 && $headerBefore===$fingerprint('GIO_HANG') && $linesBefore===$fingerprint('MUA'),'Transaction rollback toàn bộ dữ liệu ghi dở');
    verify(count($cart->view($user)['lines'])===2,'Lỗi gửi giữ giỏ tạm để thử lại');
    $request->getSession()->set('draft_cart_'.$user->id,['items'=>[$firstId=>(int)$first['SACH_SO_LUONG']+1],'nonce'=>'stale-stock']);
    $realCart=new App\Service\CartService($stack,$books,$db);
    try { $realCart->submit($user,'stale-stock',''); throw new LogicException('Expected stock rejection'); } catch (DomainException) { verify($headerBefore===$fingerprint('GIO_HANG'),'Kiểm tra tồn kho lại lúc gửi'); }
} finally {
    if ($db->isTransactionActive()) { $db->rollBack(); }
    foreach ($before as $table=>$hash) { verify($hash===$fingerprint($table),'Giữ nguyên dữ liệu cũ '.$table); }
    $kernel->shutdown();
}
echo "$checks checks passed; all writes rolled back.\n";
