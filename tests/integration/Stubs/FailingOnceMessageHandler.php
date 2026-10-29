<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Messenger\Stubs;

use LogicException;
use RuntimeException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Fails on the first attempt, with a chained exception, and succeeds on the retry.
 */
final class FailingOnceMessageHandler implements MessageHandlerInterface
{
    private int $attempts = 0;

    public function __invoke(FailingOnceMessage $message): void
    {
        ++$this->attempts;

        if ($this->attempts === 1) {
            throw new RuntimeException('First attempt failure', 0, new LogicException('Inner failure'));
        }
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }
}
