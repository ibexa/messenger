<?php

namespace Ibexa\Bundle\Messenger\EventSubscriber;

use Ibexa\Bundle\Messenger\Stamp\SudoStamp;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;

final class RemoveSudoStampOnSendMessageSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => 'removeSudoStamp',
        ];
    }

    public function removeSudoStamp(SendMessageToTransportsEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $envelope = $envelope->withoutStampsOfType(SudoStamp::class);
        $event->setEnvelope($envelope);
    }
}
