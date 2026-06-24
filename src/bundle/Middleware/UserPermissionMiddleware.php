<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Middleware;

use Ibexa\Contracts\Messenger\Stamp\UserPermissionStamp;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Core\Repository\Values\User\UserReference;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class UserPermissionMiddleware implements MiddlewareInterface
{
    public function __construct(private PermissionResolver $permissionResolver) {}

    public function handle(
        Envelope $envelope,
        StackInterface $stack
    ): Envelope {
        $stamp = $envelope->last(UserPermissionStamp::class);
        if ($stamp === null) {
            return $stack->next()->handle($envelope, $stack);
        }

        $previousUserReference = $this->permissionResolver->getCurrentUserReference();
        $this->permissionResolver->setCurrentUserReference(
            new UserReference($stamp->userId)
        );

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->permissionResolver->setCurrentUserReference($previousUserReference);
        }
    }
}
