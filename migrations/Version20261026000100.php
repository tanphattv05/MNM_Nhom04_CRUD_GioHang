<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique customer username; preserve integer IDs, passwords and cart relationships.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLServerPlatform, 'SQL Server only.');
        // Keep the lock until this transactional migration commits.
        $this->addSql('SELECT COUNT(*) FROM dbo.THANH_VIEN WITH (TABLOCKX, HOLDLOCK)');
        $this->addSql('ALTER TABLE dbo.THANH_VIEN ADD THANH_VIEN_USERNAME VARCHAR(30) COLLATE Latin1_General_100_CI_AS NULL');
        $this->addSql("UPDATE dbo.THANH_VIEN SET THANH_VIEN_USERNAME = 'user_' + CONVERT(varchar(11), THANH_VIEN_TEN_DANG_NHAP)");
        $this->addSql('ALTER TABLE dbo.THANH_VIEN ALTER COLUMN THANH_VIEN_USERNAME VARCHAR(30) COLLATE Latin1_General_100_CI_AS NOT NULL');
        $this->addSql('ALTER TABLE dbo.THANH_VIEN ADD CONSTRAINT UQ_THANH_VIEN_USERNAME UNIQUE (THANH_VIEN_USERNAME)');
        $this->addSql("ALTER TABLE dbo.THANH_VIEN ADD CONSTRAINT CK_THANH_VIEN_USERNAME CHECK (
            DATALENGTH(THANH_VIEN_USERNAME) BETWEEN 4 AND 30
            AND THANH_VIEN_USERNAME COLLATE Latin1_General_100_BIN2 NOT LIKE '%[^a-z0-9_.-]%'
        )");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing usernames would destroy login data.');
    }
}
