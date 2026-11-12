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
use PHPUnit\Framework\Attributes\DataProvider;
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
    private SiteAccessServiceInterface & MockObject $siteAccessService;

    private EventDispatcherInterface & MockObject $eventDispatcher;

    private StackInterface & MockObject $stack;

    private SiteAccessMiddleware $middleware;

    private function setUpMocks(): void
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
        $this->setUpMocks();

        $stamp = new SiteAccessStamp('my_site');
        $envelope = new Envelope(new stdClass(), [$stamp]);
        $siteAccess = new SiteAccess('my_site');

        $this->siteAccessService
            ->expects($this->once())
            ->method('get')
            ->with('my_site')
            ->willReturn($siteAccess);

        $this->eventDispatcher
            ->expects($this->once())
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
            ->expects($this->once())
            ->method('handle')
            ->willReturnArgument(0);

        $this->stack
            ->expects($this->once())
            ->method('next')
            ->willReturn($nextMiddleware);

        $this->middleware->handle($envelope, $this->stack);
    }

    public function testHandleDoesNotDispatchScopeChangeWhenNoSiteAccessStamp(): void
    {
        $this->setUpMocks();

        $envelope = new Envelope(new stdClass());

        $this->siteAccessService
            ->expects($this->never())
            ->method('get');

        $this->eventDispatcher
            ->expects($this->never())
            ->method('dispatch');

        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects($this->once())
            ->method('handle')
            ->willReturnArgument(0);

        $this->stack
            ->expects($this->once())
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

    #[DataProvider('providerForTestNextMessageRunsInPreviousSiteAccess')]
    public function testNextMessageRunsInPreviousSiteAccess(
        bool $firstHandlerThrows,
        bool $scopeChangeListenerThrows
    ): void {
        $original = new SiteAccess('original');
        $stamped = new SiteAccess('stamped');

        $provider = self::createStub(SiteAccessProviderInterface::class);
        $provider->method('isDefined')->willReturn(true);
        $provider->method('getSiteAccess')->willReturnMap([['stamped', $stamped]]);

        $siteAccessService = new SiteAccessService($provider, self::createStub(ConfigResolverInterface::class));
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

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function providerForTestRestoreFailure(): iterable
    {
        yield 'handler failure is kept' => [true, 'Handler failed'];
        yield 'restore failure is thrown when handler succeeded' => [false, 'Restore failed'];
    }

    #[DataProvider('providerForTestRestoreFailure')]
    public function testRestoreFailure(
        bool $handlerThrows,
        string $expectedMessage
    ): void {
        $provider = self::createStub(SiteAccessProviderInterface::class);
        $provider->method('isDefined')->willReturn(true);
        $provider->method('getSiteAccess')->willReturn(new SiteAccess('stamped'));

        $siteAccessService = new SiteAccessService($provider, self::createStub(ConfigResolverInterface::class));
        $siteAccessService->setSiteAccess(new SiteAccess('original'));

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber($siteAccessService);
        $eventDispatcher->addListener(MVCEvents::CONFIG_SCOPE_RESTORE, static function (): void {
            throw new RuntimeException('Restore failed');
        });

        $handler = static function () use ($handlerThrows): void {
            if ($handlerThrows) {
                throw new RuntimeException('Handler failed');
            }
        };

        $bus = new MessageBus([
            new SiteAccessMiddleware($siteAccessService, $eventDispatcher),
            new HandleMessageMiddleware(new HandlersLocator([stdClass::class => [$handler]])),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        $bus->dispatch(new Envelope(new stdClass(), [new SiteAccessStamp('stamped')]));
    }
}
