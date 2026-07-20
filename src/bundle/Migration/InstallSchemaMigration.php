<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Migration;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;

final class InstallSchemaMigration extends AbstractMigration implements IbexaMigrationInterface
{
    public function getDescription(): string
    {
        return 'Creates the ibexa/messenger database schema';
    }

    public static function getTargetVersion(): string
    {
        return '5.0.0';
    }

    public static function getCreationDate(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-20 00:00:00');
    }

    public function up(Schema $schema): void
    {
        if ($this->platform instanceof AbstractMySQLPlatform) {
            $this->addSql('CREATE TABLE ibexa_messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX ibexa_messenger_created_at_idx (created_at), INDEX ibexa_messenger_available_at_idx (available_at), INDEX ibexa_messenger_delivered_at_idx (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE TABLE ibexa_messenger_lock_keys (key_id VARCHAR(64) NOT NULL, key_token VARCHAR(44) NOT NULL, key_expiration INT UNSIGNED NOT NULL, PRIMARY KEY(key_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        } elseif ($this->platform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE ibexa_messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
            $this->addSql('CREATE INDEX ibexa_messenger_created_at_idx ON ibexa_messenger_messages (created_at)');
            $this->addSql('CREATE INDEX ibexa_messenger_available_at_idx ON ibexa_messenger_messages (available_at)');
            $this->addSql('CREATE INDEX ibexa_messenger_delivered_at_idx ON ibexa_messenger_messages (delivered_at)');
            $this->addSql('COMMENT ON COLUMN ibexa_messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
            $this->addSql('COMMENT ON COLUMN ibexa_messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
            $this->addSql('COMMENT ON COLUMN ibexa_messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');
            $this->addSql('CREATE TABLE ibexa_messenger_lock_keys (key_id VARCHAR(64) NOT NULL, key_token VARCHAR(44) NOT NULL, key_expiration INT NOT NULL, PRIMARY KEY(key_id))');
        } elseif ($this->platform instanceof SqlitePlatform) {
            $this->addSql(<<<'SQL'
CREATE TABLE ibexa_messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
, available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
, delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
)
SQL);
            $this->addSql('CREATE INDEX ibexa_messenger_created_at_idx ON ibexa_messenger_messages (created_at)');
            $this->addSql('CREATE INDEX ibexa_messenger_available_at_idx ON ibexa_messenger_messages (available_at)');
            $this->addSql('CREATE INDEX ibexa_messenger_delivered_at_idx ON ibexa_messenger_messages (delivered_at)');
            $this->addSql('CREATE TABLE ibexa_messenger_lock_keys (key_id VARCHAR(64) NOT NULL, key_token VARCHAR(44) NOT NULL, key_expiration INTEGER UNSIGNED NOT NULL, PRIMARY KEY(key_id))');
        }
    }
}
