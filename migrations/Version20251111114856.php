<?php

declare(strict_types=1);

namespace ForumifyCalendarMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251111114856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add calendars';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('calendar')) {
            // This plugin was extracted from forumify-platform in 1.0
            // So it's possible that these tables already exist and were created under a different doctrine namespace.
            return;
        }

        $this->addSql('CREATE TABLE calendar (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, color CHAR(7) NOT NULL, UNIQUE INDEX UNIQ_6EA9A146989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE calendar_event (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, start DATETIME NOT NULL, end DATETIME DEFAULT NULL, `repeat` VARCHAR(255) DEFAULT NULL, repeat_end DATETIME DEFAULT NULL, content LONGTEXT NOT NULL, banner VARCHAR(255) DEFAULT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, calendar_id INT DEFAULT NULL, created_by INT DEFAULT NULL, updated_by INT DEFAULT NULL, UNIQUE INDEX UNIQ_57FA09C9989D9B62 (slug), INDEX IDX_57FA09C98B8E8428 (created_at), INDEX IDX_57FA09C943625D9F (updated_at), INDEX IDX_57FA09C9A40A2C8 (calendar_id), INDEX IDX_57FA09C9DE12AB56 (created_by), INDEX IDX_57FA09C916FE72E1 (updated_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9A40A2C8 FOREIGN KEY (calendar_id) REFERENCES calendar (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C9DE12AB56 FOREIGN KEY (created_by) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE calendar_event ADD CONSTRAINT FK_57FA09C916FE72E1 FOREIGN KEY (updated_by) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C9A40A2C8');
        $this->addSql('ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C9DE12AB56');
        $this->addSql('ALTER TABLE calendar_event DROP FOREIGN KEY FK_57FA09C916FE72E1');
        $this->addSql('DROP TABLE calendar');
        $this->addSql('DROP TABLE calendar_event');
    }
}
