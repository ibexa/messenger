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
use Ibexa\Contracts\DoctrineMigrations\Migrations\SqlPlatform;

/**
 * Removes the Doctrine type comments, such as "(DC2Type:datetime_immutable)" on
 * "ibexa_messenger_messages.available_at", from the messenger columns, as a fresh 6.0 install has
 * none.
 *
 * Doctrine DBAL 4 no longer writes them. The install migrations, which a 6.0 install runs too, are
 * the ones from 4.6 and 5.0, so they created these columns with them, and a database upgraded from
 * 5.0 keeps them. MySQL and MariaDB change a column's comment only with the rest of its definition,
 * so the statements give each column its 6.0 definition again; PostgreSQL drops the comment.
 * SQLite, used only for tests, would need the table rebuilt, so it keeps them. The statements are
 * in sql/remove-doctrine-type-comments-*.sql. Running them on columns that have no comment changes
 * nothing, so there's no check first.
 */
final class RemoveDoctrineTypeCommentsMigration extends AbstractSqlMigration implements IbexaMigrationInterface
{
    public function getDescription(): string
    {
        return 'Removes the Doctrine type comments from the messenger columns';
    }

    public static function getTargetVersion(): string
    {
        return '6.0.0';
    }

    public static function getCreationDate(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-06 00:00:02');
    }

    public function up(Schema $schema): void
    {
        $this->abortIfUnsupportedPlatform(SqlPlatform::MYSQL, SqlPlatform::MARIADB, SqlPlatform::POSTGRESQL, SqlPlatform::SQLITE);

        if ($this->isSqlite()) {
            return;
        }

        if ($this->isMariaDB()) {
            $this->addSqlFile(__DIR__ . '/sql/remove-doctrine-type-comments-mariadb.sql');
        } elseif ($this->isMySQL()) {
            $this->addSqlFile(__DIR__ . '/sql/remove-doctrine-type-comments-mysql.sql');
        } elseif ($this->isPostgreSQL()) {
            $this->addSqlFile(__DIR__ . '/sql/remove-doctrine-type-comments-postgresql.sql');
        }
    }
}
