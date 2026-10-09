<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Ibexa\Bundle\Messenger\Migration\ConvertPostgreSqlSerialColumnsToIdentityMigration;
use Ibexa\Bundle\Messenger\Migration\FixMessengerMessagesIndexesMigration;
use Ibexa\Bundle\Messenger\Migration\InstallSchemaMigration;
use Ibexa\Bundle\Messenger\Migration\RemoveDoctrineTypeCommentsMigration;
use Ibexa\Contracts\DoctrineMigrations\Migrations\IbexaMigrationTag;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services
        ->defaults()
        ->autowire()
        ->autoconfigure(false)
        ->private();

    $services->set(InstallSchemaMigration::class)
        ->arg('$connection', service('ibexa.persistence.connection'))
        ->tag(IbexaMigrationTag::TAG);

    $services->set(FixMessengerMessagesIndexesMigration::class)
        ->arg('$connection', service('ibexa.persistence.connection'))
        ->tag(IbexaMigrationTag::TAG);

    $services->set(RemoveDoctrineTypeCommentsMigration::class)
        ->arg('$connection', service('ibexa.persistence.connection'))
        ->tag(IbexaMigrationTag::TAG);

    $services->set(ConvertPostgreSqlSerialColumnsToIdentityMigration::class)
        ->arg('$connection', service('ibexa.persistence.connection'))
        ->tag(IbexaMigrationTag::TAG);
};
