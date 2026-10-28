/* BanSachDB: fresh-install schema for the completed Symfony application.
   Microsoft SQL Server 2017+; run the ENTIRE file in SSMS, normal query mode.
   This is not an upgrade/reset script. Existing databases are always refused.
   Includes the four migrations available on 2026-10-28.
   Six business tables + Doctrine migration metadata; no personal/demo data.
   Book/cart INT keys are not IDENTITY; member keys are VARCHAR(10).
   No cascade deletes. Member phones are NULL or exactly ten ASCII digits.

   Change only the following database-name literal for a separate installation.
   If BanSachDB already exists, this script stops without modifying it.
*/
USE [master];
SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @DatabaseName sysname = N'BanSachDB';
IF @@TRANCOUNT <> 0
    THROW 51000, N'Run fresh installation outside an existing transaction.', 1;
IF @DatabaseName IS NULL OR LEN(@DatabaseName) = 0
   OR @DatabaseName COLLATE Latin1_General_100_BIN2 LIKE N'%[^A-Za-z0-9_]%'
   OR LOWER(@DatabaseName) IN (N'master', N'model', N'msdb', N'tempdb')
    THROW 51001, N'Choose a new non-system database name (letters, digits, underscore).', 1;
IF DB_ID(@DatabaseName) IS NOT NULL
    THROW 51002, N'Target database already exists. Nothing was changed; choose a NEW name.', 1;

-- CREATE DATABASE cannot run inside a user transaction. TRY/CATCH also
-- prevents schema execution if creation fails (including a concurrent create).
BEGIN TRY
    DECLARE @CreateSql nvarchar(max) = N'CREATE DATABASE ' + QUOTENAME(@DatabaseName)
        + N' COLLATE SQL_Latin1_General_CP1_CI_AS;';
    EXEC sys.sp_executesql @CreateSql;

    -- One dynamic batch keeps the chosen database explicit throughout import.
    -- DDL + migration baseline commit together, or both roll back.
    DECLARE @SchemaSql nvarchar(max) = N'USE __TARGET_DATABASE__;
SET XACT_ABORT ON;
BEGIN TRY
    BEGIN TRANSACTION;

    CREATE TABLE dbo.THANH_VIEN (
        THANH_VIEN_TEN_DANG_NHAP VARCHAR(10) COLLATE Latin1_General_100_CI_AS NOT NULL,
        THANH_VIEN_HO_TEN NVARCHAR(120) NULL,
        THANH_VIEN_EMAIL VARCHAR(150) NULL,
        THANH_VIEN_SO_DIEN_THOAI VARCHAR(10) NULL,
        THANH_VIEN_DIA_CHI NVARCHAR(255) NULL,
        THANH_VIEN_HINH_DAI_DIEN VARCHAR(255) NULL,
        THANH_VIEN_MAT_KHAU VARCHAR(255) NOT NULL,
        THANH_VIEN_USERNAME VARCHAR(30) COLLATE Latin1_General_100_CI_AS NOT NULL,
        CONSTRAINT PK_THANH_VIEN PRIMARY KEY CLUSTERED (THANH_VIEN_TEN_DANG_NHAP),
        CONSTRAINT UQ_THANH_VIEN_USERNAME UNIQUE NONCLUSTERED (THANH_VIEN_USERNAME),
        CONSTRAINT CK_THANH_VIEN_USERNAME CHECK (
            DATALENGTH(THANH_VIEN_USERNAME) BETWEEN 4 AND 30
            AND THANH_VIEN_USERNAME COLLATE Latin1_General_100_BIN2 NOT LIKE ''%[^a-z0-9_.-]%''
        ),
        CONSTRAINT CK_THANH_VIEN_PHONE CHECK (
            THANH_VIEN_SO_DIEN_THOAI IS NULL OR
            (DATALENGTH(THANH_VIEN_SO_DIEN_THOAI) = 10
             AND THANH_VIEN_SO_DIEN_THOAI COLLATE Latin1_General_100_BIN2 NOT LIKE ''%[^0-9]%'')
        )
    );

    CREATE TABLE dbo.QTV (
        QTV_USER_ADMIN VARCHAR(60) NOT NULL,
        QTV_HO_TEN NVARCHAR(120) NULL,
        QTV_EMAIL VARCHAR(150) NULL,
        QTV_MAT_KHAU VARCHAR(255) NOT NULL,
        CONSTRAINT PK_QTV PRIMARY KEY CLUSTERED (QTV_USER_ADMIN)
    );

    CREATE TABLE dbo.LOAI_SACH (
        LOAI_SACH_MA_LOAI INT NOT NULL,
        LOAI_SACH_TEN_LOAI NVARCHAR(100) NULL,
        CONSTRAINT PK_LOAI_SACH PRIMARY KEY CLUSTERED (LOAI_SACH_MA_LOAI)
    );

    CREATE TABLE dbo.SACH (
        SACH_ID_SACH INT NOT NULL,
        LOAI_SACH_MA_LOAI INT NOT NULL,
        SACH_TEN_SACH NVARCHAR(200) NULL,
        SACH_TAC_GIA NVARCHAR(150) NULL,
        SACH_GIA_BAN NUMERIC(12,2) NULL,
        SACH_SO_LUONG INT NULL,
        CONSTRAINT PK_SACH PRIMARY KEY CLUSTERED (SACH_ID_SACH),
        CONSTRAINT FK_SACH_THUOC_LOAI_SAC FOREIGN KEY (LOAI_SACH_MA_LOAI)
            REFERENCES dbo.LOAI_SACH (LOAI_SACH_MA_LOAI)
    );
    CREATE NONCLUSTERED INDEX THUOC_FK ON dbo.SACH (LOAI_SACH_MA_LOAI);

    CREATE TABLE dbo.GIO_HANG (
        GIO_HANG_ID_GIO INT NOT NULL,
        QTV_USER_ADMIN VARCHAR(60) NULL,
        GIO_HANG_THOI_GIAN DATETIME NULL,
        GIO_HANG_TRANG_THAI NVARCHAR(30) NULL,
        GIO_HANG_GHI_CHU NVARCHAR(255) NULL,
        THANH_VIEN_TEN_DANG_NHAP VARCHAR(10) COLLATE Latin1_General_100_CI_AS NULL,
        GIO_HANG_LY_DO_TU_CHOI NVARCHAR(255) NULL,
        CONSTRAINT PK_GIO_HANG PRIMARY KEY CLUSTERED (GIO_HANG_ID_GIO),
        CONSTRAINT FK_GIO_HANG_DUYET_QTV FOREIGN KEY (QTV_USER_ADMIN)
            REFERENCES dbo.QTV (QTV_USER_ADMIN),
        CONSTRAINT FK_GIO_HANG_THANH_VIEN FOREIGN KEY (THANH_VIEN_TEN_DANG_NHAP)
            REFERENCES dbo.THANH_VIEN (THANH_VIEN_TEN_DANG_NHAP)
    );
    CREATE NONCLUSTERED INDEX DUYET_FK ON dbo.GIO_HANG (QTV_USER_ADMIN);

    CREATE TABLE dbo.MUA (
        THANH_VIEN_TEN_DANG_NHAP VARCHAR(10) COLLATE Latin1_General_100_CI_AS NOT NULL,
        SACH_ID_SACH INT NOT NULL,
        GIO_HANG_ID_GIO INT NOT NULL,
        MUA_SO_LUONG_MUA INT NOT NULL,
        CONSTRAINT PK_MUA PRIMARY KEY CLUSTERED
            (THANH_VIEN_TEN_DANG_NHAP, SACH_ID_SACH, GIO_HANG_ID_GIO),
        CONSTRAINT FK_MUA_MUA_THANH_VI FOREIGN KEY (THANH_VIEN_TEN_DANG_NHAP)
            REFERENCES dbo.THANH_VIEN (THANH_VIEN_TEN_DANG_NHAP),
        CONSTRAINT FK_MUA_MUA2_SACH FOREIGN KEY (SACH_ID_SACH)
            REFERENCES dbo.SACH (SACH_ID_SACH),
        CONSTRAINT FK_MUA_MUA3_GIO_HANG FOREIGN KEY (GIO_HANG_ID_GIO)
            REFERENCES dbo.GIO_HANG (GIO_HANG_ID_GIO)
    );
    CREATE NONCLUSTERED INDEX MUA_FK ON dbo.MUA (THANH_VIEN_TEN_DANG_NHAP);
    CREATE NONCLUSTERED INDEX MUA2_FK ON dbo.MUA (SACH_ID_SACH);
    CREATE NONCLUSTERED INDEX MUA3_FK ON dbo.MUA (GIO_HANG_ID_GIO);

    -- Doctrine metadata is infrastructure, not a seventh business table.
    -- Baseline: all four migrations are already incorporated in the DDL above.
    CREATE TABLE dbo.doctrine_migration_versions (
        version NVARCHAR(191) NOT NULL,
        executed_at DATETIME2(6) NULL,
        execution_time INT NULL,
        CONSTRAINT PK_doctrine_migration_versions PRIMARY KEY CLUSTERED (version)
    );
    INSERT INTO dbo.doctrine_migration_versions (version, executed_at, execution_time)
    VALUES
        (N''DoctrineMigrations\Version20261026000100'', SYSDATETIME(), NULL),
        (N''DoctrineMigrations\Version20261026000200'', SYSDATETIME(), NULL),
        (N''DoctrineMigrations\Version20261026000300'', SYSDATETIME(), NULL),
        (N''DoctrineMigrations\Version20261028000100'', SYSDATETIME(), NULL);

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF XACT_STATE() <> 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;

SELECT DB_NAME() AS created_database,
       (SELECT COUNT(*) FROM sys.tables WHERE schema_id = SCHEMA_ID(N''dbo'')
           AND name IN (N''THANH_VIEN'', N''QTV'', N''LOAI_SACH'', N''SACH'', N''GIO_HANG'', N''MUA'')) AS business_tables,
       (SELECT COUNT(*) FROM dbo.doctrine_migration_versions) AS baselined_migrations;';
    SET @SchemaSql = REPLACE(@SchemaSql, N'__TARGET_DATABASE__', QUOTENAME(@DatabaseName));
    EXEC sys.sp_executesql @SchemaSql;
END TRY
BEGIN CATCH
    -- On a schema error a NEW empty database may remain for inspection.
    -- Do not delete databases automatically; no existing database is reset.
    THROW;
END CATCH;
