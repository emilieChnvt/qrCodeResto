<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250714173631 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE menu_category (id SERIAL NOT NULL, restaurant_id INT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2A1D5C57B1E7706E ON menu_category (restaurant_id)');
        $this->addSql('CREATE TABLE restaurant (id SERIAL NOT NULL, of_user_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, create_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EB95123F5A1B2224 ON restaurant (of_user_id)');
        $this->addSql('COMMENT ON COLUMN restaurant.create_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE menu_category ADD CONSTRAINT FK_2A1D5C57B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F5A1B2224 FOREIGN KEY (of_user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE menu_category DROP CONSTRAINT FK_2A1D5C57B1E7706E');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F5A1B2224');
        $this->addSql('DROP TABLE menu_category');
        $this->addSql('DROP TABLE restaurant');
    }
}
