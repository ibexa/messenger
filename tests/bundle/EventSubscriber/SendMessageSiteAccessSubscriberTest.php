<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Messenger\EventSubscriber;

use Ibexa\Bundle\Messenger\EventSubscriber\SendMessageSiteAccessSubscriber;
use Ibexa\Contracts\Messenger\Stamp\SiteAccessStamp;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

final class SendMessageSiteAccessSubscriberTest extends TestCase
{
    private SiteAccessServiceInterface & MockObject $siteAccessService;

    private SendMessageSiteAccessSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->siteAccessService = $this->createMock(SiteAccessServiceInterface::class);
        $this->subscriber = new SendMessageSiteAccessSubscriber($this->siteAccessService);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->siteAccessService->expects(self::never())->method(self::anything());

        $subscribedEvents = SendMessageSiteAccessSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(SendMessageToTransportsEvent::class, $subscribedEvents);
        self::assertSame('onSendMessageToTransport', $subscribedEvents[SendMessageToTransportsEvent::class]);
    }

    public function testOnSendMessageToTransportAddsSiteAccessStamp(): void
    {
        $siteAccess = new SiteAccess('my_site');
        $envelope = new Envelope(new \stdClass());
        $event = new SendMessageToTransportsEvent($envelope, []);

        $this->siteAccessService
            ->expects(self::once())
            ->method('getCurrent')
            ->willReturn($siteAccess);

        $this->subscriber->onSendMessageToTransport($event);

        $updatedEnvelope = $event->getEnvelope();
        $stamp = $updatedEnvelope->last(SiteAccessStamp::class);

        self::assertNotNull($stamp);
        self::assertSame('my_site', $stamp->siteAccess);
    }

    public function testOnSendMessageToTransportDoesNothingWhenNoSiteAccess(): void
    {
        $envelope = new Envelope(new \stdClass());
        $event = new SendMessageToTransportsEvent($envelope, []);

        $this->siteAccessService
            ->expects(self::once())
            ->method('getCurrent')
            ->willReturn(null);

        $this->subscriber->onSendMessageToTransport($event);

        $updatedEnvelope = $event->getEnvelope();

        self::assertNull($updatedEnvelope->last(SiteAccessStamp::class));
        self::assertSame($envelope, $updatedEnvelope);
    }
}
