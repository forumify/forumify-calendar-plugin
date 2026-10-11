<?php

declare(strict_types=1);

namespace ForumifyCalendarMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261011120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add calendar reminders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE calendar_reminder (minutes_before INT NOT NULL, id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, calendar_id INT NOT NULL, UNIQUE INDEX calendar_reminder_uniq (user_id, calendar_id, minutes_before), INDEX IDX_45556F28A76ED395 (user_id), INDEX IDX_45556F28A40A2C8 (calendar_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE calendar_reminder_cursor (id INT NOT NULL, processed_until DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE calendar_reminder ADD CONSTRAINT FK_45556F28A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE calendar_reminder ADD CONSTRAINT FK_45556F28A40A2C8 FOREIGN KEY (calendar_id) REFERENCES calendar (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE calendar_reminder DROP FOREIGN KEY FK_45556F28A76ED395');
        $this->addSql('ALTER TABLE calendar_reminder DROP FOREIGN KEY FK_45556F28A40A2C8');
        $this->addSql('DROP TABLE calendar_reminder');
        $this->addSql('DROP TABLE calendar_reminder_cursor');
    }
}
