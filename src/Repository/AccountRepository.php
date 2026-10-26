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
        if (!$admin && !ctype_digit($id)) { return null; }
        return $this->hydrate($this->db->fetchAssociative($admin ? 'SELECT * FROM dbo.QTV WHERE QTV_USER_ADMIN = ?' : 'SELECT * FROM dbo.THANH_VIEN WHERE THANH_VIEN_TEN_DANG_NHAP = ?', [$id]), $admin);
    }
    public function byLogin(string $login, bool $admin): ?Account
    {
        $rows = $this->db->fetchAllAssociative($admin ? 'SELECT * FROM dbo.QTV WHERE QTV_USER_ADMIN = ?' : 'SELECT * FROM dbo.THANH_VIEN WHERE LOWER(LTRIM(RTRIM(THANH_VIEN_EMAIL))) = ?', [$admin ? $login : strtolower(trim($login))]);
        return count($rows) === 1 ? $this->hydrate($rows[0], $admin) : null;
    }
    public function register(array $data, string $hash): void
    {
        $this->db->transactional(function () use ($data, $hash) {
            // Serialize allocation and email checks, including when the table is empty.
            $id = (int)$this->db->fetchOne('SELECT COALESCE(MAX(THANH_VIEN_TEN_DANG_NHAP), 1000) + 1 FROM dbo.THANH_VIEN WITH (TABLOCKX, HOLDLOCK)');
            if ($this->db->fetchOne('SELECT COUNT(*) FROM dbo.THANH_VIEN WHERE LOWER(LTRIM(RTRIM(THANH_VIEN_EMAIL))) = ?', [$data['email']])) { throw new \DomainException('Email đã được sử dụng.'); }
            $this->db->insert('dbo.THANH_VIEN', ['THANH_VIEN_TEN_DANG_NHAP'=>$id, 'THANH_VIEN_HO_TEN'=>$data['name'], 'THANH_VIEN_EMAIL'=>$data['email'], 'THANH_VIEN_SO_DIEN_THOAI'=>$data['phone'], 'THANH_VIEN_DIA_CHI'=>$data['address'], 'THANH_VIEN_MAT_KHAU'=>$hash]);
        });
    }
    public function updateProfile(Account $user, array $data): void
    {
        $this->db->update('dbo.THANH_VIEN', ['THANH_VIEN_HO_TEN'=>$data['name'], 'THANH_VIEN_SO_DIEN_THOAI'=>$data['phone'], 'THANH_VIEN_DIA_CHI'=>$data['address']], ['THANH_VIEN_TEN_DANG_NHAP'=>$user->id]);
    }
    public function changePassword(Account $user, string $hash): void
    {
        $this->db->update($user->admin ? 'dbo.QTV' : 'dbo.THANH_VIEN', [$user->admin ? 'QTV_MAT_KHAU' : 'THANH_VIEN_MAT_KHAU'=>$hash], [$user->admin ? 'QTV_USER_ADMIN' : 'THANH_VIEN_TEN_DANG_NHAP'=>$user->id]);
    }
}
