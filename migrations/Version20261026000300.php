<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make QTV_USER_ADMIN nullable and add THANH_VIEN_TEN_DANG_NHAP customer reference to GIO_HANG with backfill from MUA.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLServerPlatform, 'SQL Server only.');

        // Allow QTV_USER_ADMIN to be NULL for carts awaiting review
        $this->addSql('ALTER TABLE dbo.GIO_HANG ALTER COLUMN QTV_USER_ADMIN VARCHAR(60) NULL');

        // Add THANH_VIEN_TEN_DANG_NHAP column if not already present
        $this->addSql("IF NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_NAME = 'GIO_HANG' AND COLUMN_NAME = 'THANH_VIEN_TEN_DANG_NHAP'
        )
        BEGIN
            ALTER TABLE dbo.GIO_HANG ADD THANH_VIEN_TEN_DANG_NHAP INT NULL;
        END");

        // Backfill existing cart owners from MUA lines
        $this->addSql('UPDATE g
            SET g.THANH_VIEN_TEN_DANG_NHAP = m.THANH_VIEN_TEN_DANG_NHAP
            FROM dbo.GIO_HANG g
            INNER JOIN (
                SELECT GIO_HANG_ID_GIO, MIN(THANH_VIEN_TEN_DANG_NHAP) AS THANH_VIEN_TEN_DANG_NHAP
                FROM dbo.MUA
                GROUP BY GIO_HANG_ID_GIO
            ) m ON m.GIO_HANG_ID_GIO = g.GIO_HANG_ID_GIO
            WHERE g.THANH_VIEN_TEN_DANG_NHAP IS NULL');

        // Add foreign key constraint if not already present
        $this->addSql("IF NOT EXISTS (
            SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_GIO_HANG_THANH_VIEN'
        )
        BEGIN
            ALTER TABLE dbo.GIO_HANG ADD CONSTRAINT FK_GIO_HANG_THANH_VIEN
                FOREIGN KEY (THANH_VIEN_TEN_DANG_NHAP)
                REFERENCES dbo.THANH_VIEN (THANH_VIEN_TEN_DANG_NHAP);
        END");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing cart owner reference would break customer cart associations.');
    }
}
