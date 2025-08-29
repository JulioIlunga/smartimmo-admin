<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250824162758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE preference ADD lead_cost INT NOT NULL');
        $this->addSql('ALTER TABLE property CHANGE title title VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE subscriptions DROP FOREIGN KEY subscriptions___fk___user');
        $this->addSql('DROP INDEX subscriptions___fk___user ON subscriptions');
        $this->addSql('ALTER TABLE subscriptions DROP agent_id');
        $this->addSql('ALTER TABLE subscriptions RENAME INDEX subscriptions___fk__membership_plan TO IDX_4778A01E899029B');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649CDEADB2A FOREIGN KEY (agency_id) REFERENCES agency (id)');
        $this->addSql('ALTER TABLE user RENAME INDEX user___fk___subscriptions TO IDX_8D93D649688E3B5D');
        $this->addSql('ALTER TABLE user RENAME INDEX user___fk___credit_wallet TO IDX_8D93D649B0EFD6E1');
        $this->addSql('ALTER TABLE user RENAME INDEX user___fk__agent_coverages TO IDX_8D93D6491BC7A1A5');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE preference DROP lead_cost');
        $this->addSql('ALTER TABLE property CHANGE title title TEXT NOT NULL');
        $this->addSql('ALTER TABLE subscriptions ADD agent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions___fk___user FOREIGN KEY (agent_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX subscriptions___fk___user ON subscriptions (agent_id)');
        $this->addSql('ALTER TABLE subscriptions RENAME INDEX idx_4778a01e899029b TO subscriptions___fk__membership_plan');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649CDEADB2A');
        $this->addSql('ALTER TABLE `user` RENAME INDEX idx_8d93d649688e3b5d TO user___fk___subscriptions');
        $this->addSql('ALTER TABLE `user` RENAME INDEX idx_8d93d649b0efd6e1 TO user___fk___credit_wallet');
        $this->addSql('ALTER TABLE `user` RENAME INDEX idx_8d93d6491bc7a1a5 TO user___fk__agent_coverages');
    }
}
