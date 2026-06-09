<?php

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
