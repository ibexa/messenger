<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;

/**
 * Backport of Symfony's ReleaseDeduplicationLockOnFailureListener (introduced in Symfony 8.1).
 * The DeduplicateMiddleware keeps the lock held when a handler throws, so a message still being
 * retried cannot be enqueued again. Once the retry flow has decided not to retry, the lock must
 * be released to unblock future messages sharing the same key.
 *
 * Original code: https://github.com/symfony/symfony/blob/8.1/src/Symfony/Component/Messenger/EventListener/ReleaseDeduplicationLockOnFailureListener.php
 *
 * @todo Remove this backport once the minimum Symfony version is >= 8.1.
 */
final class ReleaseDeduplicationLockOnFailureListener implements EventSubscriberInterface
{
    public function __construct(private LockFactory $lockFactory) {}

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $stamp = $event->getEnvelope()->last(DeduplicateStamp::class);
        if ($stamp === null) {
            return;
        }

        if ($stamp->onlyDeduplicateInQueue()) {
            return;
        }

        $this->lockFactory->createLockFromKey($stamp->getKey())->release();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Must have lower priority than SendFailedMessageForRetryListener (100) so willRetry() is already set.
            WorkerMessageFailedEvent::class => ['onMessageFailed', -10],
        ];
    }
}
