<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store Admin rejection reason separately from the customer cart note.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SQLServerPlatform, 'SQL Server only.');
        // No backfill: old overwritten notes cannot be reliably reconstructed.
        $this->addSql('ALTER TABLE dbo.GIO_HANG ADD GIO_HANG_LY_DO_TU_CHOI NVARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Dropping this column would destroy Admin rejection reasons.');
    }
}
