<?php
// SQL Server schema/identity regression. All fixture and rehearsal writes roll back.
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/migrations/Version20261028000100.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
if ($_SERVER['APP_ENV'] !== 'dev') { throw new RuntimeException('Development database only.'); }
$kernel = new App\Kernel('dev', false); $kernel->boot();
$db = $kernel->getContainer()->get('doctrine')->getConnection();
$rehearsal = in_array('--rehearsal', $argv, true);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) { throw new RuntimeException('FAIL: '.$label); }
    ++$checks; echo 'PASS: ', $label, "\n";
};
$fingerprint = static function (string $table) use ($db): string {
    $rows = array_map('serialize', $db->fetchAllAssociative('SELECT * FROM dbo.'.$table)); sort($rows);
    return hash('sha256',serialize($rows));
};
$before = [];
foreach (['THANH_VIEN','QTV','SACH','LOAI_SACH','GIO_HANG','MUA','doctrine_migration_versions'] as $table) { $before[$table] = $fingerprint($table); }
$originalType = $db->fetchOne("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='THANH_VIEN' AND COLUMN_NAME='THANH_VIEN_TEN_DANG_NHAP'");
$db->beginTransaction();
try {
    if ($rehearsal) {
        $check($originalType === 'int', 'Rehearsal starts from original integer schema');
        // Only this test transaction changes invalid fixtures. Rollback restores them.
        $db->executeStatement("UPDATE dbo.THANH_VIEN SET THANH_VIEN_SO_DIEN_THOAI = '0000000000' WHERE THANH_VIEN_SO_DIEN_THOAI IS NOT NULL AND (DATALENGTH(THANH_VIEN_SO_DIEN_THOAI)<>10 OR THANH_VIEN_SO_DIEN_THOAI COLLATE Latin1_General_100_BIN2 LIKE '%[^0-9]%')");
        $migration = new DoctrineMigrations\Version20261028000100($db, new Psr\Log\NullLogger());
        $migration->up(new Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) { $db->executeStatement($query->getStatement(),$query->getParameters(),$query->getTypes()); }
        $check(true, 'Migration statements execute on real SQL Server within rollback transaction');
    }
    foreach (['THANH_VIEN','GIO_HANG','MUA'] as $table) {
        $column = $db->fetchAssociative("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME=? AND COLUMN_NAME='THANH_VIEN_TEN_DANG_NHAP'", [$table]);
        $check($column['DATA_TYPE'] === 'varchar' && (int)$column['CHARACTER_MAXIMUM_LENGTH'] === 10, $table.' member key is VARCHAR(10)');
    }
    $phone = $db->fetchAssociative("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='THANH_VIEN' AND COLUMN_NAME='THANH_VIEN_SO_DIEN_THOAI'");
    $check($phone['DATA_TYPE'] === 'varchar' && (int)$phone['CHARACTER_MAXIMUM_LENGTH'] === 10, 'Phone storage is VARCHAR(10)');
    $check((int)$db->fetchOne("SELECT COUNT(*) FROM sys.foreign_keys WHERE parent_object_id IN (OBJECT_ID('dbo.SACH'),OBJECT_ID('dbo.MUA'),OBJECT_ID('dbo.GIO_HANG')) AND is_disabled=0 AND is_not_trusted=0") === 6, 'All six foreign keys remain enabled and trusted');
    $check((int)$db->fetchOne("SELECT COUNT(*) FROM sys.index_columns ic JOIN sys.indexes i ON i.object_id=ic.object_id AND i.index_id=ic.index_id WHERE i.object_id=OBJECT_ID('dbo.MUA') AND i.is_primary_key=1") === 3, 'MUA composite primary key is preserved');

    $repo = new App\Repository\AccountRepository($db);
    $login = 'schema_'.bin2hex(random_bytes(5));
    $repo->register(['username'=>$login,'name'=>'Kiểm thử schema','email'=>'schema@example.com','phone'=>'0901234567','address'=>'Địa chỉ kiểm thử'],password_hash(bin2hex(random_bytes(12)),PASSWORD_BCRYPT));
    $user = $repo->byLogin($login,false);
    $check($user !== null && strlen($user->id) === 10 && str_starts_with($user->id,'tv'), 'New customer receives an alphanumeric string key');
    $check($repo->byId($user->id,false)?->id === $user->id, 'Repository reloads nonnumeric member identity');
    $check((new App\Security\AccountProvider($repo))->loadUserByIdentifier('user:'.$user->id)->id === $user->id, 'Security provider preserves string identity');
    $check($user->profile['THANH_VIEN_SO_DIEN_THOAI'] === '0901234567', 'Leading zero in phone is preserved');

    foreach (['090123456','09012345678','09012a4567'] as $invalidPhone) {
        try {
            $db->transactional(static function () use ($db,$user,$invalidPhone) { $db->executeStatement('UPDATE dbo.THANH_VIEN SET THANH_VIEN_SO_DIEN_THOAI=? WHERE THANH_VIEN_TEN_DANG_NHAP=?',[$invalidPhone,$user->id]); });
            throw new LogicException('Invalid phone accepted');
        } catch (Doctrine\DBAL\Exception) { $check(true, 'Database rejects invalid phone length/characters'); }
    }

    $stack = new Symfony\Component\HttpFoundation\RequestStack();
    $request = Symfony\Component\HttpFoundation\Request::create('/');
    $request->setSession(new Symfony\Component\HttpFoundation\Session\Session(new Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()));
    $stack->push($request);
    $books = new App\Repository\BookRepository($db);
    $cart = new App\Service\CartService($stack,$books,$db);
    $book = $db->fetchAssociative('SELECT TOP 1 * FROM dbo.SACH WHERE SACH_SO_LUONG>=3 AND SACH_GIA_BAN IS NOT NULL ORDER BY SACH_ID_SACH');
    if (!$book) { throw new RuntimeException('Need one stocked book.'); }
    $bookId = (int)$book['SACH_ID_SACH'];
    $cart->modify($user,$bookId,'add',1);
    $id = $cart->submit($user,$cart->nonce($user),'String member key');
    $check($db->fetchOne('SELECT THANH_VIEN_TEN_DANG_NHAP FROM dbo.GIO_HANG WHERE GIO_HANG_ID_GIO=?',[$id]) === $user->id, 'Cart header stores full string owner');
    $check($db->fetchOne('SELECT THANH_VIEN_TEN_DANG_NHAP FROM dbo.MUA WHERE GIO_HANG_ID_GIO=?',[$id]) === $user->id, 'Cart detail stores full string owner');
    $cart->updateSubmitted($user,$id,[$bookId=>'2'],'Updated string owner');
    $check((int)$db->fetchOne('SELECT MUA_SO_LUONG_MUA FROM dbo.MUA WHERE GIO_HANG_ID_GIO=?',[$id]) === 2, 'Submitted cart update supports string member key');
    $cart->cancel($user,$id);
    $check($db->fetchOne('SELECT GIO_HANG_TRANG_THAI FROM dbo.GIO_HANG WHERE GIO_HANG_ID_GIO=?',[$id]) === 'Đã hủy', 'Owner cancellation supports string key');
} finally {
    if ($db->isTransactionActive()) { $db->rollBack(); }
    foreach ($before as $table=>$hash) { $check($hash === $fingerprint($table), 'Unchanged after rollback: '.$table); }
    $check($originalType === $db->fetchOne("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='dbo' AND TABLE_NAME='THANH_VIEN' AND COLUMN_NAME='THANH_VIEN_TEN_DANG_NHAP'"), 'Schema restored/preserved after rollback');
    $kernel->shutdown();
}
echo $checks," checks passed; all writes rolled back.\n";
