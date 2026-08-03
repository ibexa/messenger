<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Messenger\Middleware;

use Ibexa\Bundle\Messenger\Middleware\SiteAccessMiddleware;
use Ibexa\Contracts\Messenger\Stamp\SiteAccessStamp;
use Ibexa\Core\MVC\Symfony\Event\ScopeChangeEvent;
use Ibexa\Core\MVC\Symfony\MVCEvents;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SiteAccessMiddlewareTest extends TestCase
{
    /** @var \Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface&\PHPUnit\Framework\MockObject\MockObject */
    private SiteAccessServiceInterface $siteAccessService;

    /** @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface&\PHPUnit\Framework\MockObject\MockObject */
    private EventDispatcherInterface $eventDispatcher;

    /** @var \Symfony\Component\Messenger\Middleware\StackInterface&\PHPUnit\Framework\MockObject\MockObject */
    private StackInterface $stack;

    private SiteAccessMiddleware $middleware;

    protected function setUp(): void
    {
        $this->siteAccessService = $this->createMock(SiteAccessServiceInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->stack = $this->createMock(StackInterface::class);
        $this->middleware = new SiteAccessMiddleware(
            $this->siteAccessService,
            $this->eventDispatcher,
        );
    }

    public function testHandleDispatchesScopeChangeWhenSiteAccessStampExists(): void
    {
        $stamp = new SiteAccessStamp('my_site');
        $envelope = new Envelope(new \stdClass(), [$stamp]);
        $siteAccess = new SiteAccess('my_site');

        $this->siteAccessService
            ->expects(self::once())
            ->method('get')
            ->with('my_site')
            ->willReturn($siteAccess);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                self::callback(static function (ScopeChangeEvent $event) use ($siteAccess): bool {
                    self::assertSame($siteAccess, $event->getSiteAccess());

                    return true;
                }),
                MVCEvents::CONFIG_SCOPE_CHANGE,
            );

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects(self::once())
            ->method('handle')
            ->willReturnArgument(0);

        $this->stack
            ->expects(self::once())
            ->method('next')
            ->willReturn($nextMiddleware);

        $this->middleware->handle($envelope, $this->stack);
    }

    public function testHandleDoesNotDispatchScopeChangeWhenNoSiteAccessStamp(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->siteAccessService
            ->expects(self::never())
            ->method('get');

        $this->eventDispatcher
            ->expects(self::never())
            ->method('dispatch');

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects(self::once())
            ->method('handle')
            ->willReturnArgument(0);

        $this->stack
            ->expects(self::once())
            ->method('next')
            ->willReturn($nextMiddleware);

        $result = $this->middleware->handle($envelope, $this->stack);

        self::assertSame($envelope->getMessage(), $result->getMessage());
    }
}
