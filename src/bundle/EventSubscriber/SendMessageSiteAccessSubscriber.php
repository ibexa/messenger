<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\EventSubscriber;

use Ibexa\Contracts\Messenger\Stamp\SiteAccessStamp;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

final class SendMessageSiteAccessSubscriber implements EventSubscriberInterface
{
    private SiteAccessServiceInterface $siteAccessService;

    public function __construct(
        SiteAccessServiceInterface $siteAccessService
    ) {
        $this->siteAccessService = $siteAccessService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => 'onSendMessageToTransport',
        ];
    }

    public function onSendMessageToTransport(SendMessageToTransportsEvent $event): void
    {
        $siteAccess = $this->siteAccessService->getCurrent();
        if ($siteAccess === null) {
            return;
        }

        $envelope = $event->getEnvelope();
        $envelope = $envelope->with(new SiteAccessStamp($siteAccess->name));
        $event->setEnvelope($envelope);
    }
}
