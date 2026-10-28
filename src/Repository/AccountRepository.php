<?php
namespace App\Repository;

use App\Security\Account;
use Doctrine\DBAL\Connection;

final class AccountRepository
{
    public function __construct(private Connection $db) {}
    private function hydrate(array|false $row, bool $admin): ?Account
    {
        if (!$row) { return null; }
        $hash = $row[$admin ? 'QTV_MAT_KHAU' : 'THANH_VIEN_MAT_KHAU'];
        unset($row['QTV_MAT_KHAU'], $row['THANH_VIEN_MAT_KHAU']);
        return new Account((string)$row[$admin ? 'QTV_USER_ADMIN' : 'THANH_VIEN_TEN_DANG_NHAP'], $admin, $hash, $row);
    }
    public function byId(string $id, bool $admin): ?Account
    {
        if (!$admin && !preg_match('/^[a-zA-Z0-9_.-]{1,10}$/D', $id)) { return null; }
        return $this->hydrate($this->db->fetchAssociative($admin ? 'SELECT * FROM dbo.QTV WHERE QTV_USER_ADMIN = ?' : 'SELECT * FROM dbo.THANH_VIEN WHERE THANH_VIEN_TEN_DANG_NHAP = ?', [$id]), $admin);
    }
    public function byLogin(string $login, bool $admin): ?Account
    {
        if (!$admin && !preg_match('/^[a-zA-Z0-9_.-]{4,30}$/D', trim($login))) { return null; }
        $rows = $this->db->fetchAllAssociative($admin ? 'SELECT * FROM dbo.QTV WHERE QTV_USER_ADMIN = ?' : 'SELECT * FROM dbo.THANH_VIEN WHERE THANH_VIEN_USERNAME = ?', [$admin ? $login : strtolower(trim($login))]);
        return count($rows) === 1 ? $this->hydrate($rows[0], $admin) : null;
    }

    public function byUnifiedLogin(string $login): ?Account
    {
        // Admin names are reserved. Never fall back to customer credentials
        // when an admin exists, even if its password is wrong or not hashed.
        $admins = $this->db->fetchAllAssociative('SELECT * FROM dbo.QTV WHERE LOWER(LTRIM(RTRIM(QTV_USER_ADMIN))) COLLATE Latin1_General_100_CI_AS = ?', [strtolower(trim($login))]);
        if ($admins) { return count($admins) === 1 ? $this->hydrate($admins[0], true) : null; }
        return $this->byLogin($login, false);
    }
    public function register(array $data, string $hash): void
    {
        $this->db->transactional(function () use ($data, $hash) {
            // Keep customer identity independent of the editable login username.
            $this->db->fetchOne('SELECT COUNT(*) FROM dbo.THANH_VIEN WITH (TABLOCKX, HOLDLOCK)');
            do {
                $id = 'tv'.bin2hex(random_bytes(4));
            } while ($this->db->fetchOne('SELECT COUNT(*) FROM dbo.THANH_VIEN WHERE THANH_VIEN_TEN_DANG_NHAP = ?', [$id]));
            $username = $this->checkedUsername($data['username']);
            $phone = self::phone($data['phone']);
            $this->db->insert('dbo.THANH_VIEN', ['THANH_VIEN_TEN_DANG_NHAP'=>$id, 'THANH_VIEN_USERNAME'=>$username, 'THANH_VIEN_HO_TEN'=>$data['name'], 'THANH_VIEN_EMAIL'=>$data['email'], 'THANH_VIEN_SO_DIEN_THOAI'=>$phone, 'THANH_VIEN_DIA_CHI'=>$data['address'], 'THANH_VIEN_MAT_KHAU'=>$hash]);
        });
    }
    public function updateProfile(Account $user, array $data): void
    {
        $this->db->transactional(function () use ($user, $data): void {
            $this->db->fetchOne('SELECT COUNT(*) FROM dbo.THANH_VIEN WITH (TABLOCKX, HOLDLOCK)');
            $username = $this->checkedUsername($data['username'], $user->id);
            $phone = self::phone($data['phone']);
            $this->db->update('dbo.THANH_VIEN', ['THANH_VIEN_USERNAME'=>$username, 'THANH_VIEN_HO_TEN'=>$data['name'], 'THANH_VIEN_SO_DIEN_THOAI'=>$phone, 'THANH_VIEN_DIA_CHI'=>$data['address']], ['THANH_VIEN_TEN_DANG_NHAP'=>$user->id]);
        });
    }

    public static function phone(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^[0-9]{10}$/D', $value)) {
            throw new \DomainException('Số điện thoại phải gồm đúng 10 chữ số.');
        }
        return $value;
    }

    private function checkedUsername(string $value, ?string $exceptId = null): string
    {
        $username = strtolower(trim($value));
        if (!preg_match('/^[a-z0-9_.-]{4,30}$/D', $username)) { throw new \DomainException('Tên đăng nhập không hợp lệ (4–30 ký tự).'); }
        if ($this->db->fetchOne('SELECT COUNT(*) FROM dbo.QTV WITH (HOLDLOCK) WHERE LOWER(LTRIM(RTRIM(QTV_USER_ADMIN))) COLLATE Latin1_General_100_CI_AS = ?', [$username])) {
            throw new \DomainException('Tên đăng nhập đã được sử dụng. Vui lòng chọn tên khác.');
        }
        $params = [$username];
        if ($exceptId !== null) { $params[] = $exceptId; }
        if ($this->db->fetchOne('SELECT COUNT(*) FROM dbo.THANH_VIEN WHERE THANH_VIEN_USERNAME = ?'.($exceptId !== null ? ' AND THANH_VIEN_TEN_DANG_NHAP <> ?' : ''), $params)) {
            throw new \DomainException('Tên đăng nhập đã được sử dụng. Vui lòng chọn tên khác.');
        }
        return $username;
    }
    public function changePassword(Account $user, string $hash): void
    {
        $this->db->update($user->admin ? 'dbo.QTV' : 'dbo.THANH_VIEN', [$user->admin ? 'QTV_MAT_KHAU' : 'THANH_VIEN_MAT_KHAU'=>$hash], [$user->admin ? 'QTV_USER_ADMIN' : 'THANH_VIEN_TEN_DANG_NHAP'=>$user->id]);
    }
}
