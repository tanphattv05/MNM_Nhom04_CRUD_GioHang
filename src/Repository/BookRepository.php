<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;

final class BookRepository
{
    public function __construct(private Connection $db) {}

    public static function cents(string $price): int
    {
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $price)) { throw new \DomainException('Giá sách chưa hợp lệ.'); }
        [$whole, $fraction] = array_pad(explode('.', $price), 2, '');
        return (int)$whole * 100 + (int)str_pad($fraction, 2, '0');
    }

    public function find(int $id, bool $lock = false): array|false
    {
        if ($id < 1 || $id > 2147483647) { return false; }
        return $this->db->fetchAssociative('SELECT * FROM dbo.SACH'.($lock ? ' WITH (HOLDLOCK)' : '').' WHERE SACH_ID_SACH = ?', [$id]);
    }

    public function categories(): array
    {
        return $this->db->fetchAllAssociative('SELECT * FROM dbo.LOAI_SACH ORDER BY LOAI_SACH_TEN_LOAI');
    }

    public function catalog(array $filters, int $page): array
    {
        $where = []; $params = [];
        if ($filters['q'] !== '') {
            $where[] = '(s.SACH_TEN_SACH LIKE ? ESCAPE \'!\' OR s.SACH_TAC_GIA LIKE ? ESCAPE \'!\')';
            $term = '%'.strtr($filters['q'], ['!'=>'!!','%'=>'!%','_'=>'!_','['=>'![']).'%';
            array_push($params, $term, $term);
        }
        if ($filters['category'] !== '') { $where[] = 's.LOAI_SACH_MA_LOAI = ?'; $params[] = $filters['category']; }
        foreach (['min'=>'>=', 'max'=>'<='] as $key=>$operator) {
            if ($filters[$key] !== '') { $where[] = 's.SACH_GIA_BAN '.$operator.' ?'; $params[] = $filters[$key]; }
        }
        $clause = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $total = (int)$this->db->fetchOne('SELECT COUNT(*) FROM dbo.SACH s'.$clause, $params);
        $pages = max(1, (int)ceil($total / 12)); $page = min(max(1, $page), $pages);
        $sort = ['price_asc'=>'s.SACH_GIA_BAN ASC','price_desc'=>'s.SACH_GIA_BAN DESC','name'=>'s.SACH_TEN_SACH ASC'][$filters['sort']] ?? 's.SACH_TEN_SACH ASC';
        $books = $this->db->fetchAllAssociative('SELECT s.*, l.LOAI_SACH_TEN_LOAI FROM dbo.SACH s LEFT JOIN dbo.LOAI_SACH l ON l.LOAI_SACH_MA_LOAI = s.LOAI_SACH_MA_LOAI'.$clause.' ORDER BY '.$sort.', s.SACH_ID_SACH ASC OFFSET '.(($page-1)*12).' ROWS FETCH NEXT 12 ROWS ONLY', $params);
        return compact('books','total','pages','page');
    }
}
