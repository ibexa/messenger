<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Messenger\Middleware;

use Ibexa\Bundle\Messenger\Middleware\SiteAccessMiddleware;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Contracts\Messenger\Stamp\SiteAccessStamp;
use Ibexa\Core\MVC\Symfony\Event\ScopeChangeEvent;
use Ibexa\Core\MVC\Symfony\MVCEvents;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessProviderInterface;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessService;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SiteAccessMiddlewareTest extends TestCase
{
    /** @var SiteAccessServiceInterface&MockObject */
    private SiteAccessServiceInterface $siteAccessService;

    /** @var EventDispatcherInterface&MockObject */
    private EventDispatcherInterface $eventDispatcher;

    /** @var StackInterface&MockObject */
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
        $envelope = new Envelope(new stdClass(), [$stamp]);
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
        $envelope = new Envelope(new stdClass());

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

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function providerForTestNextMessageRunsInPreviousSiteAccess(): iterable
    {
        yield 'first handler succeeds' => [false, false];
        yield 'first handler throws' => [true, false];
        yield 'scope change listener throws' => [false, true];
    }

    /**
     * @dataProvider providerForTestNextMessageRunsInPreviousSiteAccess
     */
    public function testNextMessageRunsInPreviousSiteAccess(
        bool $firstHandlerThrows,
        bool $scopeChangeListenerThrows
    ): void {
        $original = new SiteAccess('original');
        $stamped = new SiteAccess('stamped');

        $provider = $this->createStub(SiteAccessProviderInterface::class);
        $provider->method('isDefined')->willReturn(true);
        $provider->method('getSiteAccess')->willReturnMap([['stamped', $stamped]]);

        $siteAccessService = new SiteAccessService($provider, $this->createStub(ConfigResolverInterface::class));
        $siteAccessService->setSiteAccess($original);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($siteAccessService);
        if ($scopeChangeListenerThrows) {
            // runs after SiteAccessService has already switched
            $eventDispatcher->addListener(MVCEvents::CONFIG_SCOPE_CHANGE, static function (): void {
                throw new RuntimeException('Scope change listener failed');
            }, -10);
        }

        $handledIn = [];
        $handler = static function () use ($siteAccessService, $firstHandlerThrows, &$handledIn): void {
            $handledIn[] = $siteAccessService->getCurrent();
            if ($firstHandlerThrows && count($handledIn) === 1) {
                throw new RuntimeException('Handler failed');
            }
        };

        $bus = new MessageBus([
            new SiteAccessMiddleware($siteAccessService, $eventDispatcher),
            new HandleMessageMiddleware(new HandlersLocator([stdClass::class => [$handler]])),
        ]);

        try {
            $bus->dispatch(new Envelope(new stdClass(), [new SiteAccessStamp('stamped')]));
            self::assertFalse($firstHandlerThrows || $scopeChangeListenerThrows);
        } catch (RuntimeException $e) {
            self::assertTrue($firstHandlerThrows || $scopeChangeListenerThrows);
        }

        $bus->dispatch(new Envelope(new stdClass()));

        self::assertSame($scopeChangeListenerThrows ? [$original] : [$stamped, $original], $handledIn);
    }
}
