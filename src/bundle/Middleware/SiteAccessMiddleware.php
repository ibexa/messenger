<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Middleware;

use Ibexa\Contracts\Messenger\Stamp\SiteAccessStamp;
use Ibexa\Core\MVC\Symfony\Event\ScopeChangeEvent;
use Ibexa\Core\MVC\Symfony\MVCEvents;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SiteAccessMiddleware implements MiddlewareInterface
{
    private SiteAccessServiceInterface $siteAccessService;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        SiteAccessServiceInterface $siteAccessService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->siteAccessService = $siteAccessService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $stamp = $envelope->last(SiteAccessStamp::class);
        if ($stamp !== null) {
            $siteAccess = $this->siteAccessService->get($stamp->siteAccess);
            $this->eventDispatcher->dispatch(new ScopeChangeEvent($siteAccess), MVCEvents::CONFIG_SCOPE_CHANGE);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
