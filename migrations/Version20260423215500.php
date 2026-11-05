<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260423215500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reclamation (id INT AUTO_INCREMENT NOT NULL, sujet VARCHAR(100) NOT NULL, message LONGTEXT NOT NULL, statut VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, date_traitement DATETIME DEFAULT NULL, reponse LONGTEXT DEFAULT NULL, user_id INT NOT NULL, trajet_concerne_id INT DEFAULT NULL, utilisateur_concerne_id INT DEFAULT NULL, INDEX IDX_CE606404A76ED395 (user_id), INDEX IDX_CE60640494168B62 (trajet_concerne_id), INDEX IDX_CE60640490E0F362 (utilisateur_concerne_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, date_reservation DATETIME NOT NULL, nb_places INT NOT NULL, statut VARCHAR(20) NOT NULL, prix_total DOUBLE PRECISION DEFAULT NULL, date_annulation DATETIME DEFAULT NULL, passager_id INT NOT NULL, trajet_id INT NOT NULL, INDEX IDX_42C8495571A51189 (passager_id), INDEX IDX_42C84955D12A823 (trajet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE trajet (id INT AUTO_INCREMENT NOT NULL, ville_depart VARCHAR(100) NOT NULL, destination VARCHAR(100) NOT NULL, date_depart DATETIME NOT NULL, date_arrivee DATETIME NOT NULL, prix DOUBLE PRECISION NOT NULL, places_disponibles INT NOT NULL, statut VARCHAR(20) NOT NULL, description LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, conducteur_id INT NOT NULL, vehicule_id INT NOT NULL, INDEX IDX_2B5BA98CF16F4AC6 (conducteur_id), INDEX IDX_2B5BA98C4A4A3511 (vehicule_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, telephone VARCHAR(20) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, statut VARCHAR(20) NOT NULL, date_inscription DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vehicule (id INT AUTO_INCREMENT NOT NULL, marque VARCHAR(50) NOT NULL, modele VARCHAR(50) NOT NULL, immatriculation VARCHAR(10) NOT NULL, nb_places INT NOT NULL, couleur VARCHAR(20) DEFAULT NULL, date_ajout DATETIME NOT NULL, proprietaire_id INT NOT NULL, INDEX IDX_292FFF1D76C50E4A (proprietaire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE60640494168B62 FOREIGN KEY (trajet_concerne_id) REFERENCES trajet (id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE60640490E0F362 FOREIGN KEY (utilisateur_concerne_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495571A51189 FOREIGN KEY (passager_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955D12A823 FOREIGN KEY (trajet_id) REFERENCES trajet (id)');
        $this->addSql('ALTER TABLE trajet ADD CONSTRAINT FK_2B5BA98CF16F4AC6 FOREIGN KEY (conducteur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE trajet ADD CONSTRAINT FK_2B5BA98C4A4A3511 FOREIGN KEY (vehicule_id) REFERENCES vehicule (id)');
        $this->addSql('ALTER TABLE vehicule ADD CONSTRAINT FK_292FFF1D76C50E4A FOREIGN KEY (proprietaire_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404A76ED395');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE60640494168B62');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE60640490E0F362');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495571A51189');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955D12A823');
        $this->addSql('ALTER TABLE trajet DROP FOREIGN KEY FK_2B5BA98CF16F4AC6');
        $this->addSql('ALTER TABLE trajet DROP FOREIGN KEY FK_2B5BA98C4A4A3511');
        $this->addSql('ALTER TABLE vehicule DROP FOREIGN KEY FK_292FFF1D76C50E4A');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE trajet');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE vehicule');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
