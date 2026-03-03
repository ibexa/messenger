<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Transport;

use Doctrine\Persistence\ConnectionRegistry as BaseConnectionRegistry;
use Ibexa\Bundle\Core\ApiLoader\RepositoryConfigurationProvider;

final class ConnectionRegistry implements BaseConnectionRegistry
{
    public const DEFAULT_IBEXA_CONNECTION = 'ibexa.current';

    private BaseConnectionRegistry $registry;

    private RepositoryConfigurationProvider $repositoryConfigurationProvider;

    public function __construct(
        BaseConnectionRegistry $registry,
        RepositoryConfigurationProvider $repositoryConfigurationProvider
    ) {
        $this->registry = $registry;
        $this->repositoryConfigurationProvider = $repositoryConfigurationProvider;
    }

    public function getDefaultConnectionName(): string
    {
        return $this->registry->getDefaultConnectionName();
    }

    /**
     * Cannot declare types to maintain compatibility with doctrine/persistence v2.
     *
     * @param string|null $name
     */
    public function getConnection($name = null): object
    {
        if ($name === self::DEFAULT_IBEXA_CONNECTION) {
            $name = $this->repositoryConfigurationProvider->getStorageConnectionName();
        }

        return $this->registry->getConnection($name);
    }

    public function getConnections(): array
    {
        return $this->registry->getConnections();
    }

    public function getConnectionNames(): array
    {
        return $this->registry->getConnectionNames();
    }
}
