-- Giai doan 6: chi doc du lieu, khong huy/xoa/reset bat ky ban ghi nao.
-- Mo trong SSMS hoac: sqlcmd -S . -E -C -d BanSachDB -i database/phase6_delete_checks.sql
USE [BanSachDB];
GO
SET NOCOUNT ON;

-- Dien ma gio thu nghiem de so sanh truoc/sau thao tac tren web.
-- NULL: xem toi da 50 gio gan nhat; nen dien ID khi ghi nhan ket qua.
DECLARE @CartId INT = NULL;

SELECT DB_NAME() AS database_name, SYSDATETIME() AS checked_at;

-- 1. Trang thai va cac thao tac duoc phep theo quy dinh ung dung.
SELECT TOP (50)
    g.GIO_HANG_ID_GIO AS cart_id,
    g.THANH_VIEN_TEN_DANG_NHAP AS customer_id,
    g.GIO_HANG_TRANG_THAI AS cart_status,
    g.GIO_HANG_THOI_GIAN AS submitted_at,
    (SELECT COUNT(*) FROM dbo.MUA m
        WHERE m.GIO_HANG_ID_GIO = g.GIO_HANG_ID_GIO) AS line_count,
    CASE WHEN g.GIO_HANG_TRANG_THAI = N'Chờ duyệt'
        THEN 1 ELSE 0 END AS owner_can_cancel,
    CASE WHEN g.GIO_HANG_TRANG_THAI IN (N'Đã hủy', N'Từ chối')
        THEN 1 ELSE 0 END AS admin_can_delete
FROM dbo.GIO_HANG g
WHERE @CartId IS NULL OR g.GIO_HANG_ID_GIO = @CartId
ORDER BY g.GIO_HANG_ID_GIO DESC;

-- 2. Sau khi huy: giu dong mua; sau khi Admin xoa: khong con dong mua.
SELECT m.GIO_HANG_ID_GIO AS cart_id,
    m.THANH_VIEN_TEN_DANG_NHAP AS customer_id,
    m.SACH_ID_SACH AS book_id,
    m.MUA_SO_LUONG_MUA AS quantity
FROM dbo.MUA m
WHERE @CartId IS NOT NULL AND m.GIO_HANG_ID_GIO = @CartId
ORDER BY m.SACH_ID_SACH, m.THANH_VIEN_TEN_DANG_NHAP;

-- 3. Ket qua mong doi: ca hai so luong mo coi bang 0.
SELECT
    (SELECT COUNT(*) FROM dbo.MUA m
        LEFT JOIN dbo.GIO_HANG g ON g.GIO_HANG_ID_GIO = m.GIO_HANG_ID_GIO
        WHERE g.GIO_HANG_ID_GIO IS NULL) AS orphan_purchase_lines,
    (SELECT COUNT(*) FROM dbo.GIO_HANG g
        LEFT JOIN dbo.THANH_VIEN t
            ON t.THANH_VIEN_TEN_DANG_NHAP = g.THANH_VIEN_TEN_DANG_NHAP
        WHERE g.THANH_VIEN_TEN_DANG_NHAP IS NOT NULL
            AND t.THANH_VIEN_TEN_DANG_NHAP IS NULL) AS orphan_cart_owners;

-- 4. Khoa ngoai lien quan phai dang bat (is_disabled = 0).
SELECT fk.name AS foreign_key_name,
    OBJECT_NAME(fk.parent_object_id) AS child_table,
    OBJECT_NAME(fk.referenced_object_id) AS parent_table,
    fk.is_disabled, fk.is_not_trusted
FROM sys.foreign_keys fk
WHERE fk.parent_object_id IN (OBJECT_ID('dbo.MUA'), OBJECT_ID('dbo.GIO_HANG'))
ORDER BY child_table, foreign_key_name;

-- 5. Ghi nhan ton tung sach truoc/sau huy/xoa, khong chi so sanh tong.
-- Khong thao tac duyet/mua/sua kho o phien khac trong luc so sanh.
SELECT SACH_ID_SACH AS book_id, SACH_SO_LUONG AS stock_quantity
FROM dbo.SACH
ORDER BY SACH_ID_SACH;
GO
