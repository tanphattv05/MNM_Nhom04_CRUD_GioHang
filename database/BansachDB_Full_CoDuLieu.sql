/*==============================================================*/
/* DBMS name:      Microsoft SQL Server 2017                    */
/* Created on:     10/24/2026 2:52:42 PM                        */
/*==============================================================*/
IF DB_ID(N'BanSachDB') IS NULL
BEGIN
    CREATE DATABASE BanSachDB;
END
GO

USE BanSachDB;
GO

if exists (select 1
   from sys.sysreferences r join sys.sysobjects o on (o.id = r.constid and o.type = 'F')
   where r.fkeyid = object_id('GIO_HANG') and o.name = 'FK_GIO_HANG_DUYET_QTV')
alter table GIO_HANG
   drop constraint FK_GIO_HANG_DUYET_QTV
go

if exists (select 1
   from sys.sysreferences r join sys.sysobjects o on (o.id = r.constid and o.type = 'F')
   where r.fkeyid = object_id('MUA') and o.name = 'FK_MUA_MUA_THANH_VI')
alter table MUA
   drop constraint FK_MUA_MUA_THANH_VI
go

if exists (select 1
   from sys.sysreferences r join sys.sysobjects o on (o.id = r.constid and o.type = 'F')
   where r.fkeyid = object_id('MUA') and o.name = 'FK_MUA_MUA2_SACH')
alter table MUA
   drop constraint FK_MUA_MUA2_SACH
go

if exists (select 1
   from sys.sysreferences r join sys.sysobjects o on (o.id = r.constid and o.type = 'F')
   where r.fkeyid = object_id('MUA') and o.name = 'FK_MUA_MUA3_GIO_HANG')
alter table MUA
   drop constraint FK_MUA_MUA3_GIO_HANG
go

if exists (select 1
   from sys.sysreferences r join sys.sysobjects o on (o.id = r.constid and o.type = 'F')
   where r.fkeyid = object_id('SACH') and o.name = 'FK_SACH_THUOC_LOAI_SAC')
alter table SACH
   drop constraint FK_SACH_THUOC_LOAI_SAC
go

if exists (select 1
            from  sysindexes
           where  id    = object_id('GIO_HANG')
            and   name  = 'DUYET_FK'
            and   indid > 0
            and   indid < 255)
   drop index GIO_HANG.DUYET_FK
go

if exists (select 1
            from  sysobjects
           where  id = object_id('GIO_HANG')
            and   type = 'U')
   drop table GIO_HANG
go

if exists (select 1
            from  sysobjects
           where  id = object_id('LOAI_SACH')
            and   type = 'U')
   drop table LOAI_SACH
go

if exists (select 1
            from  sysindexes
           where  id    = object_id('MUA')
            and   name  = 'MUA3_FK'
            and   indid > 0
            and   indid < 255)
   drop index MUA.MUA3_FK
go

if exists (select 1
            from  sysindexes
           where  id    = object_id('MUA')
            and   name  = 'MUA2_FK'
            and   indid > 0
            and   indid < 255)
   drop index MUA.MUA2_FK
go

if exists (select 1
            from  sysindexes
           where  id    = object_id('MUA')
            and   name  = 'MUA_FK'
            and   indid > 0
            and   indid < 255)
   drop index MUA.MUA_FK
go

if exists (select 1
            from  sysobjects
           where  id = object_id('MUA')
            and   type = 'U')
   drop table MUA
go

if exists (select 1
            from  sysobjects
           where  id = object_id('QTV')
            and   type = 'U')
   drop table QTV
go

if exists (select 1
            from  sysindexes
           where  id    = object_id('SACH')
            and   name  = 'THUOC_FK'
            and   indid > 0
            and   indid < 255)
   drop index SACH.THUOC_FK
go

if exists (select 1
            from  sysobjects
           where  id = object_id('SACH')
            and   type = 'U')
   drop table SACH
go

if exists (select 1
            from  sysobjects
           where  id = object_id('THANH_VIEN')
            and   type = 'U')
   drop table THANH_VIEN
go

/*==============================================================*/
/* Table: GIO_HANG                                              */
/*==============================================================*/
create table GIO_HANG (
   GIO_HANG_ID_GIO      int                  not null,
   QTV_USER_ADMIN       varchar(60)          not null,
   GIO_HANG_THOI_GIAN   datetime             null,
   GIO_HANG_TRANG_THAI  varchar(30)          null,
   GIO_HANG_GHI_CHU     varchar(255)         null,
   constraint PK_GIO_HANG primary key (GIO_HANG_ID_GIO)
)
go

/*==============================================================*/
/* Index: DUYET_FK                                              */
/*==============================================================*/




create nonclustered index DUYET_FK on GIO_HANG (QTV_USER_ADMIN ASC)
go

/*==============================================================*/
/* Table: LOAI_SACH                                             */
/*==============================================================*/
create table LOAI_SACH (
   LOAI_SACH_MA_LOAI    int                  not null,
   LOAI_SACH_TEN_LOAI   varchar(100)         null,
   constraint PK_LOAI_SACH primary key (LOAI_SACH_MA_LOAI)
)
go

/*==============================================================*/
/* Table: MUA                                                   */
/*==============================================================*/
create table MUA (
   THANH_VIEN_TEN_DANG_NHAP int                  not null,
   SACH_ID_SACH         int                  not null,
   GIO_HANG_ID_GIO      int                  not null,
   MUA_SO_LUONG_MUA     int                  not null,
   constraint PK_MUA primary key (THANH_VIEN_TEN_DANG_NHAP, SACH_ID_SACH, GIO_HANG_ID_GIO)
)
go

/*==============================================================*/
/* Index: MUA_FK                                                */
/*==============================================================*/




create nonclustered index MUA_FK on MUA (THANH_VIEN_TEN_DANG_NHAP ASC)
go

/*==============================================================*/
/* Index: MUA2_FK                                               */
/*==============================================================*/




create nonclustered index MUA2_FK on MUA (SACH_ID_SACH ASC)
go

/*==============================================================*/
/* Index: MUA3_FK                                               */
/*==============================================================*/




create nonclustered index MUA3_FK on MUA (GIO_HANG_ID_GIO ASC)
go

/*==============================================================*/
/* Table: QTV                                                   */
/*==============================================================*/
create table QTV (
   QTV_USER_ADMIN       varchar(60)          not null,
   QTV_HO_TEN           varchar(120)         null,
   QTV_EMAIL            varchar(150)         null,
   QTV_MAT_KHAU         varchar(255)         not null,
   constraint PK_QTV primary key (QTV_USER_ADMIN)
)
go

/*==============================================================*/
/* Table: SACH                                                  */
/*==============================================================*/
create table SACH (
   SACH_ID_SACH         int                  not null,
   LOAI_SACH_MA_LOAI    int                  not null,
   SACH_TEN_SACH        varchar(200)         null,
   SACH_TAC_GIA         varchar(150)         null,
   SACH_GIA_BAN         numeric(12,2)        null,
   SACH_SO_LUONG        int                  null,
   constraint PK_SACH primary key (SACH_ID_SACH)
)
go

/*==============================================================*/
/* Index: THUOC_FK                                              */
/*==============================================================*/




create nonclustered index THUOC_FK on SACH (LOAI_SACH_MA_LOAI ASC)
go

/*==============================================================*/
/* Table: THANH_VIEN                                            */
/*==============================================================*/
create table THANH_VIEN (
   THANH_VIEN_TEN_DANG_NHAP int                  not null,
   THANH_VIEN_HO_TEN    varchar(120)         null,
   THANH_VIEN_EMAIL     varchar(150)         null,
   THANH_VIEN_SO_DIEN_THOAI varchar(20)          null,
   THANH_VIEN_DIA_CHI   varchar(255)         null,
   THANH_VIEN_HINH_DAI_DIEN varchar(255)         null,
   THANH_VIEN_MAT_KHAU  varchar(255)         not null,
   constraint PK_THANH_VIEN primary key (THANH_VIEN_TEN_DANG_NHAP)
)
go

alter table GIO_HANG
   add constraint FK_GIO_HANG_DUYET_QTV foreign key (QTV_USER_ADMIN)
      references QTV (QTV_USER_ADMIN)
go

alter table MUA
   add constraint FK_MUA_MUA_THANH_VI foreign key (THANH_VIEN_TEN_DANG_NHAP)
      references THANH_VIEN (THANH_VIEN_TEN_DANG_NHAP)
go

alter table MUA
   add constraint FK_MUA_MUA2_SACH foreign key (SACH_ID_SACH)
      references SACH (SACH_ID_SACH)
go

alter table MUA
   add constraint FK_MUA_MUA3_GIO_HANG foreign key (GIO_HANG_ID_GIO)
      references GIO_HANG (GIO_HANG_ID_GIO)
go

alter table SACH
   add constraint FK_SACH_THUOC_LOAI_SAC foreign key (LOAI_SACH_MA_LOAI)
      references LOAI_SACH (LOAI_SACH_MA_LOAI)
go



-- DU LIEU MAU CHO BansachDB.sql - Microsoft SQL Server 2017+
-- Chay sau khi chay file tao bang BansachDB.sql (trong SSMS, co ho tro GO).
-- 10 loai, 50 dau sach (100 quyen/dau sach = 5000 quyen ton kho ban dau),
-- 5 thanh vien, 1 quan tri vien, 8 gio hang, 17 dong mua.
-- Toan bo thong tin nhan vat, lien he va mat khau la gia lap phuc vu demo.
-- Canh bao: mat khau demo khong duoc su dung trong ung dung that;
-- he thong that phai hash mat khau (Argon2id/bcrypt) tai tang ung dung.
-- Luu y: COT THANH_VIEN_TEN_DANG_NHAP la INT theo schema goc,
-- nen dung ID 1001..1005, khong the dung username chuoi.
-- Chuyen cac cot hien thi sang NVARCHAR de luu tieng Viet co dau.

ALTER TABLE dbo.LOAI_SACH ALTER COLUMN LOAI_SACH_TEN_LOAI NVARCHAR(100) NULL;
GO
ALTER TABLE dbo.SACH ALTER COLUMN SACH_TEN_SACH NVARCHAR(200) NULL;
GO
ALTER TABLE dbo.SACH ALTER COLUMN SACH_TAC_GIA NVARCHAR(150) NULL;
GO
ALTER TABLE dbo.QTV ALTER COLUMN QTV_HO_TEN NVARCHAR(120) NULL;
GO
ALTER TABLE dbo.THANH_VIEN ALTER COLUMN THANH_VIEN_HO_TEN NVARCHAR(120) NULL;
GO
ALTER TABLE dbo.THANH_VIEN ALTER COLUMN THANH_VIEN_DIA_CHI NVARCHAR(255) NULL;
GO
ALTER TABLE dbo.GIO_HANG ALTER COLUMN GIO_HANG_TRANG_THAI NVARCHAR(30) NULL;
GO
ALTER TABLE dbo.GIO_HANG ALTER COLUMN GIO_HANG_GHI_CHU NVARCHAR(255) NULL;
GO

-- 1. LOAI SACH
INSERT INTO dbo.LOAI_SACH (LOAI_SACH_MA_LOAI, LOAI_SACH_TEN_LOAI) VALUES
    (1, N'Văn học Việt Nam'),
    (2, N'Văn học nước ngoài'),
    (3, N'Kinh tế - Kinh doanh'),
    (4, N'Kỹ năng sống'),
    (5, N'Công nghệ thông tin'),
    (6, N'Khoa học - Khám phá'),
    (7, N'Lịch sử - Văn hóa'),
    (8, N'Tâm lý học'),
    (9, N'Thiếu nhi'),
    (10, N'Ngoại ngữ');
GO

-- 2. QUAN TRI VIEN
INSERT INTO dbo.QTV (QTV_USER_ADMIN,QTV_HO_TEN,QTV_EMAIL,QTV_MAT_KHAU) VALUES
    ('admin', N'Nguyễn Minh Quản', 'admin@nhasach-demo.example', 'Demo@2026!');
GO

-- 3. THANH VIEN
INSERT INTO dbo.THANH_VIEN (THANH_VIEN_TEN_DANG_NHAP,THANH_VIEN_HO_TEN,THANH_VIEN_EMAIL,THANH_VIEN_SO_DIEN_THOAI,THANH_VIEN_DIA_CHI,THANH_VIEN_HINH_DAI_DIEN,THANH_VIEN_MAT_KHAU) VALUES
    (1001, N'Nguyễn Văn An', 'an.nguyen@nhasach-demo.example', '0901000001', N'12 Nguyễn Trãi, Phường Bến Thành, TP. Hồ Chí Minh', '/images/avatars/an.jpg', 'Demo@2026!'),
    (1002, N'Trần Thị Bình', 'binh.tran@nhasach-demo.example', '0901000002', N'45 Lê Lợi, TP. Đà Nẵng', '/images/avatars/binh.jpg', 'Demo@2026!'),
    (1003, N'Lê Hoàng Cường', 'cuong.le@nhasach-demo.example', '0901000003', N'78 Trần Phú, TP. Nha Trang', '/images/avatars/cuong.jpg', 'Demo@2026!'),
    (1004, N'Phạm Ngọc Dung', 'dung.pham@nhasach-demo.example', '0901000004', N'23 Lạch Tray, TP. Hải Phòng', '/images/avatars/dung.jpg', 'Demo@2026!'),
    (1005, N'Đỗ Minh Em', 'em.do@nhasach-demo.example', '0901000005', N'91 Cầu Giấy, TP. Hà Nội', '/images/avatars/em.jpg', 'Demo@2026!');
GO

-- 4. SACH: moi loai 5 dau sach, so luong nhap kho ban dau = 100
INSERT INTO dbo.SACH (SACH_ID_SACH,LOAI_SACH_MA_LOAI,SACH_TEN_SACH,SACH_TAC_GIA,SACH_GIA_BAN,SACH_SO_LUONG) VALUES
    (1, 1, N'Dế Mèn phiêu lưu ký', N'Tô Hoài', 85000, 100),
    (2, 1, N'Số đỏ', N'Vũ Trọng Phụng', 98000, 100),
    (3, 1, N'Chí Phèo và những truyện ngắn', N'Nam Cao', 89000, 100),
    (4, 1, N'Tắt đèn', N'Ngô Tất Tố', 92000, 100),
    (5, 1, N'Những ngày thơ ấu', N'Nguyên Hồng', 88000, 100),
    (6, 2, N'Nhà giả kim', N'Paulo Coelho', 109000, 100),
    (7, 2, N'Hoàng tử bé', N'Antoine de Saint-Exupéry', 95000, 100),
    (8, 2, N'Ông già và biển cả', N'Ernest Hemingway', 105000, 100),
    (9, 2, N'Kiêu hãnh và định kiến', N'Jane Austen', 165000, 100),
    (10, 2, N'Không gia đình', N'Hector Malot', 149000, 100),
    (11, 3, N'Cha giàu cha nghèo', N'Robert T. Kiyosaki', 139000, 100),
    (12, 3, N'Quốc gia khởi nghiệp', N'Dan Senor và Saul Singer', 159000, 100),
    (13, 3, N'Từ tốt đến vĩ đại', N'Jim Collins', 189000, 100),
    (14, 3, N'Khởi nghiệp tinh gọn', N'Eric Ries', 175000, 100),
    (15, 3, N'7 thói quen hiệu quả', N'Stephen R. Covey', 169000, 100),
    (16, 4, N'Đắc nhân tâm', N'Dale Carnegie', 115000, 100),
    (17, 4, N'Quẳng gánh lo đi và vui sống', N'Dale Carnegie', 119000, 100),
    (18, 4, N'Atomic Habits - Thói quen tí hon', N'James Clear', 179000, 100),
    (19, 4, N'Deep Work - Làm ra làm, chơi ra chơi', N'Cal Newport', 159000, 100),
    (20, 4, N'Đi tìm lẽ sống', N'Viktor E. Frankl', 129000, 100),
    (21, 5, N'Clean Code', N'Robert C. Martin', 425000, 100),
    (22, 5, N'The Pragmatic Programmer', N'Andrew Hunt và David Thomas', 465000, 100),
    (23, 5, N'Design Patterns', N'Erich Gamma và cộng sự', 520000, 100),
    (24, 5, N'Grokking Algorithms', N'Aditya Y. Bhargava', 385000, 100),
    (25, 5, N'Python Crash Course', N'Eric Matthes', 450000, 100),
    (26, 6, N'Lược sử thời gian', N'Stephen Hawking', 169000, 100),
    (27, 6, N'Vũ trụ trong vỏ hạt dẻ', N'Stephen Hawking', 185000, 100),
    (28, 6, N'Sapiens - Lược sử loài người', N'Yuval Noah Harari', 249000, 100),
    (29, 6, N'Lược sử vạn vật', N'Bill Bryson', 215000, 100),
    (30, 6, N'Gen vị kỷ', N'Richard Dawkins', 229000, 100),
    (31, 7, N'Đại Việt sử ký toàn thư - Tuyển chọn', N'Ngô Sĩ Liên và sử thần triều Lê', 269000, 100),
    (32, 7, N'Việt Nam sử lược', N'Trần Trọng Kim', 179000, 100),
    (33, 7, N'Súng, vi trùng và thép', N'Jared Diamond', 289000, 100),
    (34, 7, N'Lịch sử thế giới', N'E. H. Gombrich', 219000, 100),
    (35, 7, N'Homo Deus - Lược sử tương lai', N'Yuval Noah Harari', 259000, 100),
    (36, 8, N'Tư duy nhanh và chậm', N'Daniel Kahneman', 229000, 100),
    (37, 8, N'Sức mạnh của thói quen', N'Charles Duhigg', 159000, 100),
    (38, 8, N'Trí tuệ xúc cảm', N'Daniel Goleman', 199000, 100),
    (39, 8, N'Tâm lý học về tiền', N'Morgan Housel', 169000, 100),
    (40, 8, N'Phi lý trí', N'Dan Ariely', 189000, 100),
    (41, 9, N'Doraemon - Tập 1', N'Fujiko F. Fujio', 25000, 100),
    (42, 9, N'Conan - Tập 1', N'Gosho Aoyama', 26000, 100),
    (43, 9, N'Harry Potter và Hòn đá Phù thủy', N'J. K. Rowling', 189000, 100),
    (44, 9, N'Alice ở xứ sở diệu kỳ', N'Lewis Carroll', 99000, 100),
    (45, 9, N'Pippi Tất Dài', N'Astrid Lindgren', 115000, 100),
    (46, 10, N'English Grammar in Use', N'Raymond Murphy', 245000, 100),
    (47, 10, N'Oxford Word Skills Basic', N'Ruth Gairns và Stuart Redman', 229000, 100),
    (48, 10, N'Essential Words for the TOEIC', N'Lin Lougheed', 199000, 100),
    (49, 10, N'4000 Essential English Words 1', N'Paul Nation', 185000, 100),
    (50, 10, N'Minna no Nihongo I', N'3A Corporation', 215000, 100);
GO

-- 5. GIO HANG: schema goc bat buoc lien ket 1 admin duyet
INSERT INTO dbo.GIO_HANG (GIO_HANG_ID_GIO,QTV_USER_ADMIN,GIO_HANG_THOI_GIAN,GIO_HANG_TRANG_THAI,GIO_HANG_GHI_CHU) VALUES
    (1, 'admin', '2026-10-02T09:15:00', N'Đã duyệt', N'Đơn đã thanh toán và bàn giao vận chuyển'),
    (2, 'admin', '2026-10-03T11:35:00', N'Đã duyệt', N'Giao hàng tiêu chuẩn'),
    (3, 'admin', '2026-10-04T14:20:00', N'Đang xử lý', N'Đang chuẩn bị đóng gói'),
    (4, 'admin', '2026-10-06T16:05:00', N'Đã duyệt', N'Khách chọn giao giờ hành chính'),
    (5, 'admin', '2026-10-08T08:45:00', N'Chờ duyệt', N'Khách yêu cầu kiểm tra địa chỉ'),
    (6, 'admin', '2026-10-11T10:10:00', N'Đã duyệt', N'Đã xác nhận thanh toán'),
    (7, 'admin', '2026-10-12T19:30:00', N'Đang xử lý', N'Chờ đơn vị vận chuyển đến lấy hàng'),
    (8, 'admin', '2026-10-15T13:55:00', N'Chờ duyệt', N'Khách ghi chú gọi trước khi giao');
GO

-- 6. MUA: lien ket thanh vien, sach va gio hang; moi so luong > 0
INSERT INTO dbo.MUA (THANH_VIEN_TEN_DANG_NHAP,SACH_ID_SACH,GIO_HANG_ID_GIO,MUA_SO_LUONG_MUA) VALUES
    (1001, 1, 1, 2),
    (1001, 16, 1, 1),
    (1002, 11, 2, 1),
    (1002, 26, 2, 2),
    (1003, 21, 3, 1),
    (1003, 22, 3, 1),
    (1004, 36, 4, 2),
    (1004, 39, 4, 1),
    (1005, 41, 5, 3),
    (1005, 43, 5, 1),
    (1001, 31, 6, 1),
    (1001, 32, 6, 1),
    (1002, 46, 7, 1),
    (1002, 47, 7, 2),
    (1003, 6, 8, 1),
    (1003, 8, 8, 1),
    (1003, 50, 8, 1);
GO

-- 7. KIEM TRA KET QUA
SELECT 'LOAI_SACH' AS TenBang, COUNT(*) AS SoBanGhi FROM dbo.LOAI_SACH
UNION ALL SELECT 'SACH', COUNT(*) FROM dbo.SACH
UNION ALL SELECT 'THANH_VIEN', COUNT(*) FROM dbo.THANH_VIEN
UNION ALL SELECT 'QTV', COUNT(*) FROM dbo.QTV
UNION ALL SELECT 'GIO_HANG', COUNT(*) FROM dbo.GIO_HANG
UNION ALL SELECT 'MUA', COUNT(*) FROM dbo.MUA;

SELECT LOAI_SACH_MA_LOAI, COUNT(*) AS SoDauSach, SUM(SACH_SO_LUONG) AS TongSoQuyen
FROM dbo.SACH GROUP BY LOAI_SACH_MA_LOAI ORDER BY LOAI_SACH_MA_LOAI;


-- Thêm thông tin chủ sở hữu giỏ hàng
IF COL_LENGTH('dbo.GIO_HANG', 'THANH_VIEN_TEN_DANG_NHAP') IS NULL
BEGIN
    ALTER TABLE dbo.GIO_HANG
    ADD THANH_VIEN_TEN_DANG_NHAP INT NULL;
END
GO

-- Cho phép giỏ hàng chưa có Admin duyệt
ALTER TABLE dbo.GIO_HANG
ALTER COLUMN QTV_USER_ADMIN VARCHAR(60) NULL;
GO

-- Gán chủ sở hữu cho các giỏ hàng mẫu cũ
UPDATE gh
SET THANH_VIEN_TEN_DANG_NHAP = (
    SELECT MIN(m.TANH_VIEN_ID)
    FROM (
        SELECT
            GIO_HANG_ID_GIO,
            THANH_VIEN_TEN_DANG_NHAP AS TANH_VIEN_ID
        FROM dbo.MUA
    ) m
    WHERE m.GIO_HANG_ID_GIO = gh.GIO_HANG_ID_GIO
)
FROM dbo.GIO_HANG gh
WHERE gh.THANH_VIEN_TEN_DANG_NHAP IS NULL;
GO

-- Tạo khóa ngoại tới bảng thành viên
IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_GIO_HANG_THANH_VIEN'
)
BEGIN
    ALTER TABLE dbo.GIO_HANG
    ADD CONSTRAINT FK_GIO_HANG_THANH_VIEN
    FOREIGN KEY (THANH_VIEN_TEN_DANG_NHAP)
    REFERENCES dbo.THANH_VIEN(THANH_VIEN_TEN_DANG_NHAP);
END
GO
