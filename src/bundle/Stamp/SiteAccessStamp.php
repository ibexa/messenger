<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @readonly
 */
final class SiteAccessStamp implements StampInterface
{
    public string $siteAccess;

    public function __construct(
        string $siteAccess
    ) {
        $this->siteAccess = $siteAccess;
    }

    public function getSiteAccess(): string
    {
        return $this->siteAccess;
    }
}
