<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Messenger\Middleware;

use Ibexa\Bundle\Messenger\Middleware\UserPermissionMiddleware;
use Ibexa\Contracts\Messenger\Stamp\UserPermissionStamp;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\Values\User\UserReference as APIUserReference;
use Ibexa\Core\Repository\Values\User\UserReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Throwable;

final class UserPermissionMiddlewareTest extends TestCase
{
    private const PREVIOUS_USER_ID = 14;
    private const STAMP_USER_ID = 42;

    private MockObject & PermissionResolver $permissionResolver;

    private MockObject & StackInterface $stack;

    private UserPermissionMiddleware $middleware;

    protected function setUp(): void
    {
        $this->permissionResolver = $this->createMock(PermissionResolver::class);
        $this->stack = $this->createMock(StackInterface::class);
        $this->middleware = new UserPermissionMiddleware($this->permissionResolver);
    }

    #[DataProvider('provideForTestHandle')]
    public function testHandle(
        ?UserPermissionStamp $stamp,
        int $expectedUserIdInNext,
        ?Throwable $exception
    ): void {
        // A stateful resolver: getCurrentUserReference() reflects the latest setCurrentUserReference().
        $previousUserReference = new UserReference(self::PREVIOUS_USER_ID);
        $currentUserReference = $previousUserReference;

        // With a stamp the middleware reads the current reference once and the next middleware
        // reads it again; without a stamp only the next middleware reads it.
        $this->permissionResolver
            ->expects(null !== $stamp ? self::exactly(2) : self::once())
            ->method('getCurrentUserReference')
            ->willReturnCallback(static function () use (&$currentUserReference): APIUserReference {
                return $currentUserReference;
            });
        // With a stamp the reference is set to the stamp user and then restored in the finally block;
        // without a stamp it is never touched.
        $this->permissionResolver
            ->expects(null !== $stamp ? self::exactly(2) : self::never())
            ->method('setCurrentUserReference')
            ->willReturnCallback(static function (APIUserReference $reference) use (&$currentUserReference): void {
                $currentUserReference = $reference;
            });

        // Capture the user id visible to the next middleware at invocation time.
        $userIdSeenByNext = null;
        $nextMiddleware = $this->createMock(MiddlewareInterface::class);
        $nextMiddleware
            ->expects(self::once())
            ->method('handle')
            ->willReturnCallback(function (Envelope $envelope) use (&$userIdSeenByNext, $exception): Envelope {
                $userIdSeenByNext = $this->permissionResolver->getCurrentUserReference()->getUserId();
                if ($exception !== null) {
                    throw $exception;
                }

                return $envelope;
            });

        $this->stack
            ->expects(self::once())
            ->method('next')
            ->willReturn($nextMiddleware);

        $envelope = new Envelope(new \stdClass(), $stamp !== null ? [$stamp] : []);

        try {
            $this->middleware->handle($envelope, $this->stack);
            self::assertNull($exception, 'Expected the next middleware exception to propagate.');
        } catch (Throwable $caught) {
            self::assertSame($exception, $caught);
        }

        self::assertSame(
            $expectedUserIdInNext,
            $userIdSeenByNext,
            'Next middleware was called with an unexpected current user reference.'
        );
        self::assertSame(
            $previousUserReference,
            $currentUserReference,
            'The previous user reference was not restored after handling.'
        );
    }

    /**
     * @return iterable<string, array{UserPermissionStamp|null, int, Throwable|null}>
     */
    public static function provideForTestHandle(): iterable
    {
        yield 'no stamp, next succeeds' => [
            null,
            self::PREVIOUS_USER_ID,
            null,
        ];

        yield 'no stamp, next throws' => [
            null,
            self::PREVIOUS_USER_ID,
            new RuntimeException('Next middleware failed'),
        ];

        yield 'with stamp, next succeeds' => [
            new UserPermissionStamp(self::STAMP_USER_ID),
            self::STAMP_USER_ID,
            null,
        ];

        yield 'with stamp, next throws' => [
            new UserPermissionStamp(self::STAMP_USER_ID),
            self::STAMP_USER_ID,
            new RuntimeException('Next middleware failed'),
        ];
    }
}
