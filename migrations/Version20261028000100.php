<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change member keys and related foreign keys to VARCHAR(10); require ten-digit member phone numbers.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof SQLServerPlatform, 'SQL Server only.');
        // The whole migration is transactional. Lock parents before children.
        $this->addSql('SELECT COUNT(*) FROM dbo.THANH_VIEN WITH (TABLOCKX, HOLDLOCK)');
        $this->addSql('SELECT COUNT(*) FROM dbo.GIO_HANG WITH (TABLOCKX, HOLDLOCK)');
        $this->addSql('SELECT COUNT(*) FROM dbo.MUA WITH (TABLOCKX, HOLDLOCK)');
        $this->addSql("IF EXISTS (SELECT 1 FROM dbo.THANH_VIEN WHERE LEN(CONVERT(varchar(11), THANH_VIEN_TEN_DANG_NHAP)) > 10)
            THROW 51010, 'Member key exceeds VARCHAR(10); no data was truncated.', 1");
        $this->addSql("IF EXISTS (SELECT 1 FROM dbo.THANH_VIEN WHERE THANH_VIEN_SO_DIEN_THOAI IS NOT NULL AND
            (DATALENGTH(THANH_VIEN_SO_DIEN_THOAI) <> 10 OR THANH_VIEN_SO_DIEN_THOAI COLLATE Latin1_General_100_BIN2 LIKE '%[^0-9]%'))
            THROW 51011, 'Correct invalid member phones before migration; exactly ten digits are required.', 1");

        $this->addSql('ALTER TABLE dbo.GIO_HANG DROP CONSTRAINT FK_GIO_HANG_THANH_VIEN');
        $this->addSql('ALTER TABLE dbo.MUA DROP CONSTRAINT FK_MUA_MUA_THANH_VI');
        $this->addSql('ALTER TABLE dbo.MUA DROP CONSTRAINT PK_MUA');
        $this->addSql('DROP INDEX MUA_FK ON dbo.MUA');
        $this->addSql('ALTER TABLE dbo.THANH_VIEN DROP CONSTRAINT PK_THANH_VIEN');
        // Auto-created statistics on the old integer columns can block ALTER.
        // SQL Server recreates these optimizer statistics when needed.
        $this->addSql("DECLARE @dropStatistics nvarchar(max) = N'';
            SELECT @dropStatistics = @dropStatistics + N'DROP STATISTICS ' + QUOTENAME(OBJECT_SCHEMA_NAME(s.object_id)) + N'.' + QUOTENAME(OBJECT_NAME(s.object_id)) + N'.' + QUOTENAME(s.name) + N';'
            FROM sys.stats s WHERE s.auto_created = 1 AND s.object_id IN (OBJECT_ID('dbo.THANH_VIEN'),OBJECT_ID('dbo.GIO_HANG'),OBJECT_ID('dbo.MUA'))
            AND EXISTS (SELECT 1 FROM sys.stats_columns sc JOIN sys.columns c ON c.object_id=sc.object_id AND c.column_id=sc.column_id WHERE sc.object_id=s.object_id AND sc.stats_id=s.stats_id AND c.name='THANH_VIEN_TEN_DANG_NHAP');
            IF @dropStatistics <> N'' EXEC sys.sp_executesql @dropStatistics");
        $this->addSql('ALTER TABLE dbo.THANH_VIEN ALTER COLUMN THANH_VIEN_TEN_DANG_NHAP VARCHAR(10) COLLATE Latin1_General_100_CI_AS NOT NULL');
        $this->addSql('ALTER TABLE dbo.GIO_HANG ALTER COLUMN THANH_VIEN_TEN_DANG_NHAP VARCHAR(10) COLLATE Latin1_General_100_CI_AS NULL');
        $this->addSql('ALTER TABLE dbo.MUA ALTER COLUMN THANH_VIEN_TEN_DANG_NHAP VARCHAR(10) COLLATE Latin1_General_100_CI_AS NOT NULL');
        $this->addSql('ALTER TABLE dbo.THANH_VIEN ALTER COLUMN THANH_VIEN_SO_DIEN_THOAI VARCHAR(10) NULL');
        $this->addSql('ALTER TABLE dbo.THANH_VIEN ADD CONSTRAINT PK_THANH_VIEN PRIMARY KEY CLUSTERED (THANH_VIEN_TEN_DANG_NHAP)');
        $this->addSql('ALTER TABLE dbo.MUA ADD CONSTRAINT PK_MUA PRIMARY KEY CLUSTERED (THANH_VIEN_TEN_DANG_NHAP, SACH_ID_SACH, GIO_HANG_ID_GIO)');
        $this->addSql('CREATE NONCLUSTERED INDEX MUA_FK ON dbo.MUA (THANH_VIEN_TEN_DANG_NHAP)');
        $this->addSql('ALTER TABLE dbo.GIO_HANG WITH CHECK ADD CONSTRAINT FK_GIO_HANG_THANH_VIEN FOREIGN KEY (THANH_VIEN_TEN_DANG_NHAP) REFERENCES dbo.THANH_VIEN (THANH_VIEN_TEN_DANG_NHAP)');
        $this->addSql('ALTER TABLE dbo.MUA WITH CHECK ADD CONSTRAINT FK_MUA_MUA_THANH_VI FOREIGN KEY (THANH_VIEN_TEN_DANG_NHAP) REFERENCES dbo.THANH_VIEN (THANH_VIEN_TEN_DANG_NHAP)');
        $this->addSql("ALTER TABLE dbo.THANH_VIEN WITH CHECK ADD CONSTRAINT CK_THANH_VIEN_PHONE CHECK (
            THANH_VIEN_SO_DIEN_THOAI IS NULL OR (DATALENGTH(THANH_VIEN_SO_DIEN_THOAI) = 10
            AND THANH_VIEN_SO_DIEN_THOAI COLLATE Latin1_General_100_BIN2 NOT LIKE '%[^0-9]%'))");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('String member keys may not convert back to INT; restore a verified backup if required.');
    }
}
