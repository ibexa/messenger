<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Migration;

use DateTimeImmutable;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineMigrations\Migrations\AbstractSqlMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationInterface;
use Ibexa\DoctrineMigrations\Migration\SqlPlatform;
use Symfony\Component\Messenger\Bridge\Doctrine\Transport\Connection;

/**
 * Replaces the 3 separate, explicitly-named single-column indexes the original schema.yaml (and
 * {@see InstallSchemaMigration}) mistakenly created on `ibexa_messenger_messages`
 * (`ibexa_messenger_created_at_idx`/`available_at_idx`/`delivered_at_idx`) with the single
 * composite index this table's actual owner, symfony/doctrine-messenger's own
 * {@see Connection}, has always created:
 * one index over `(queue_name, available_at, delivered_at, id)`.
 *
 * Needed for any install that already ran the old {@see InstallSchemaMigration} (which is
 * guarded against re-running once the table exists) or the old, incorrect schema.yaml via the
 * legacy SchemaBuilderEvent install path, before both were corrected.
 */
final class FixMessengerMessagesIndexesMigration extends AbstractSqlMigration implements IbexaMigrationInterface
{
    public function getDescription(): string
    {
        return 'Replaces the 3 incorrect per-column indexes on ibexa_messenger_messages with the single composite index Symfony\'s own Doctrine Messenger transport actually uses';
    }

    public static function getTargetVersion(): string
    {
        return '5.0.0';
    }

    public static function getCreationDate(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-27 00:00:00');
    }

    public function up(Schema $schema): void
    {
        $this->abortIfUnsupportedPlatform(SqlPlatform::MYSQL, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);

        if ($schema->getTable('ibexa_messenger_messages')->hasIndex('ibexa_messenger_messages_queue_available_delivered_idx')) {
            return;
        }

        if ($this->isMySQL()) {
            $this->addSqlFile(__DIR__ . '/sql/fix-messenger-messages-indexes-mysql.sql');
        } elseif ($this->isPostgreSQL()) {
            $this->addSqlFile(__DIR__ . '/sql/fix-messenger-messages-indexes-postgresql.sql');
        } elseif ($this->isSqlite()) {
            $this->addSqlFile(__DIR__ . '/sql/fix-messenger-messages-indexes-sqlite.sql');
        }
    }
}
