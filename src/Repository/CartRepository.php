<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;

final class CartRepository
{
    public function __construct(private Connection $db) {}

    private const TOTALS = " OUTER APPLY (SELECT COUNT(*) AS line_count,
        SUM(CASE WHEN s.SACH_GIA_BAN IS NULL THEN 1 ELSE 0 END) AS missing_price,
        SUM(CAST(s.SACH_GIA_BAN AS decimal(28,2)) * m.MUA_SO_LUONG_MUA) AS total
        FROM dbo.MUA m LEFT JOIN dbo.SACH s ON s.SACH_ID_SACH = m.SACH_ID_SACH
        WHERE m.GIO_HANG_ID_GIO = g.GIO_HANG_ID_GIO) totals ";
    private const JOINS = ' LEFT JOIN dbo.THANH_VIEN t ON t.THANH_VIEN_TEN_DANG_NHAP = g.THANH_VIEN_TEN_DANG_NHAP
        LEFT JOIN dbo.QTV q ON q.QTV_USER_ADMIN = g.QTV_USER_ADMIN ';
    // g.* includes the independent customer note and nullable Admin rejection reason.
    private const FIELDS = 'g.*, t.THANH_VIEN_HO_TEN, q.QTV_HO_TEN, totals.line_count,
        CASE WHEN totals.missing_price > 0 THEN NULL ELSE COALESCE(totals.total, 0) END AS current_total';

    public function statuses(?string $owner): array
    {
        return $this->db->fetchFirstColumn('SELECT DISTINCT GIO_HANG_TRANG_THAI FROM dbo.GIO_HANG WHERE GIO_HANG_TRANG_THAI IS NOT NULL'.($owner !== null ? ' AND THANH_VIEN_TEN_DANG_NHAP = ?' : '').' ORDER BY GIO_HANG_TRANG_THAI', $owner !== null ? [$owner] : []);
    }

    public function pendingCount(): int
    {
        return (int)$this->db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG WHERE GIO_HANG_TRANG_THAI = ?', ['Chờ duyệt']);
    }

    public function search(?string $owner, string $status, string $q, int $page): array
    {
        $where = []; $params = [];
        if ($owner !== null) { $where[] = 'g.THANH_VIEN_TEN_DANG_NHAP = ?'; $params[] = $owner; }
        if ($status === '__null') { $where[] = 'g.GIO_HANG_TRANG_THAI IS NULL'; }
        elseif ($status !== '') { $where[] = 'g.GIO_HANG_TRANG_THAI = ?'; $params[] = $status; }
        if ($owner === null && $q !== '') {
            $where[] = "(CONVERT(varchar(20), g.GIO_HANG_ID_GIO) LIKE ? ESCAPE '!' OR t.THANH_VIEN_HO_TEN LIKE ? ESCAPE '!' OR CONVERT(varchar(20), g.THANH_VIEN_TEN_DANG_NHAP) LIKE ? ESCAPE '!')";
            $term = '%'.strtr($q, ['!'=>'!!','%'=>'!%','_'=>'!_','['=>'![']).'%';
            array_push($params, $term, $term, $term);
        }
        $clause = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $total = (int)$this->db->fetchOne('SELECT COUNT(*) FROM dbo.GIO_HANG g'.self::JOINS.$clause, $params);
        $pages = max(1, (int)ceil($total / 12)); $page = min(max(1, $page), $pages);
        $carts = $this->db->fetchAllAssociative('SELECT '.self::FIELDS.' FROM dbo.GIO_HANG g'.self::JOINS.self::TOTALS.$clause.' ORDER BY g.GIO_HANG_THOI_GIAN DESC, g.GIO_HANG_ID_GIO DESC OFFSET '.(($page - 1) * 12).' ROWS FETCH NEXT 12 ROWS ONLY', $params);
        return compact('carts', 'total', 'pages', 'page');
    }

    public function detail(int $id, ?string $owner): ?array
    {
        $params = [$id];
        if ($owner !== null) { $params[] = $owner; }
        $cart = $this->db->fetchAssociative('SELECT '.self::FIELDS.' FROM dbo.GIO_HANG g'.self::JOINS.self::TOTALS.' WHERE g.GIO_HANG_ID_GIO = ?'.($owner !== null ? ' AND g.THANH_VIEN_TEN_DANG_NHAP = ?' : ''), $params);
        if (!$cart) { return null; }
        $cart['GIO_HANG_LY_DO_TU_CHOI'] = $cart['GIO_HANG_LY_DO_TU_CHOI'] ?? null;
        $lines = $this->db->fetchAllAssociative('SELECT m.SACH_ID_SACH, m.MUA_SO_LUONG_MUA, s.SACH_TEN_SACH, s.SACH_GIA_BAN,
            CAST(s.SACH_GIA_BAN AS decimal(28,2)) * m.MUA_SO_LUONG_MUA AS subtotal
            FROM dbo.MUA m LEFT JOIN dbo.SACH s ON s.SACH_ID_SACH = m.SACH_ID_SACH WHERE m.GIO_HANG_ID_GIO = ? ORDER BY m.SACH_ID_SACH, m.THANH_VIEN_TEN_DANG_NHAP', [$id]);
        return compact('cart', 'lines');
    }
}
