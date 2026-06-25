<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Middleware;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Messenger\Stamp\SudoStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class SudoMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Repository $repository
    ) {}

    public function handle(
        Envelope $envelope,
        StackInterface $stack
    ): Envelope {
        $stamp = $envelope->last(SudoStamp::class);
        if ($stamp === null) {
            return $stack->next()->handle($envelope, $stack);
        }

        return $this->repository->sudo(
            static fn (): Envelope => $stack->next()->handle($envelope, $stack)
        );
    }
}
