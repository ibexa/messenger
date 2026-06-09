<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Messenger;

use Ibexa\Bundle\Messenger\Stamp\DeduplicateStamp;
use Ibexa\Bundle\Messenger\Stamp\SiteAccessStamp;
use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use Ibexa\Core\MVC\Symfony\Event\ScopeChangeEvent;
use Ibexa\Core\MVC\Symfony\MVCEvents;
use Ibexa\Core\MVC\Symfony\SiteAccess;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessService;
use Ibexa\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface;
use Ibexa\Tests\Integration\Messenger\Stubs\FooMessage;
use Ibexa\Tests\Integration\Messenger\Stubs\FooMessageHandler;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

final class MessageBusTest extends IbexaKernelTestCase
{
    private MessageBusInterface $bus;

    private ReceiverInterface $receiver;

    private FooMessageHandler $fooHandler;

    private SiteAccessService $siteAccessService;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $core = self::getIbexaTestCore();
        $this->bus = $core->getServiceByClassName(MessageBusInterface::class, 'ibexa.messenger.bus');
        $this->receiver = $core->getServiceByClassName(ReceiverInterface::class, 'ibexa.messenger.transport');
        $this->fooHandler = $core->getServiceByClassName(FooMessageHandler::class);

        $siteAccessService = $core->getServiceByClassName(SiteAccessServiceInterface::class);
        self::assertInstanceOf(SiteAccessService::class, $siteAccessService);
        $this->siteAccessService = $siteAccessService;

        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
        $this->eventDispatcher = $eventDispatcher;

        // Clear any SiteAccess set by the kernel to prevent the subscriber from stamping
        // envelopes with a SiteAccess name that may not exist in the provider.
        $this->siteAccessService->setSiteAccess(null);
    }

    public function testBus(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->bus->dispatch(new FooMessage());
        }

        $messages = $this->getMessagesFromReceiver();

        self::assertCount(5, $messages);
        self::assertCount(0, $this->fooHandler->getHandledMessages());
    }

    public function testDeduplication(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->bus->dispatch(new Envelope(new FooMessage(), [
                new DeduplicateStamp('foo-message'),
            ]));
        }

        $receivedMessages = $this->getMessagesFromReceiver();

        self::assertCount(1, $receivedMessages);
        self::assertCount(0, $this->fooHandler->getHandledMessages());

        $message = $receivedMessages[0];
        $message = Envelope::wrap($message, [new ReceivedStamp('test')]);

        // Removes deduplication lock from non-transactional stores
        $this->bus->dispatch($message);
        self::assertCount(1, $this->fooHandler->getHandledMessages());
    }

    public function testHandlingReceivedMessages(): void
    {
        // Symfony\Component\Messenger\Worker adds ReceivedStamp to prevent re-sending messages back to the queue.
        $message = Envelope::wrap(new FooMessage(), [
            new ReceivedStamp('test'),
        ]);

        $this->bus->dispatch($message);
        self::assertCount(1, $this->fooHandler->getHandledMessages());
    }

    public function testHandlingMessagesUsingCommand(): void
    {
        $this->bus->dispatch(new Envelope(new FooMessage()));

        $kernel = self::getContainer()->get('kernel');
        self::assertInstanceOf(AbstractTestKernel::class, $kernel);
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            '--bus' => 'ibexa.messenger.bus',
            // --limit is another option, but in cases where messages remain in queue between tests this may cause
            // confusion, and it is actually a failure state.
            '--time-limit' => 3,
            'command' => 'messenger:consume',
            'receivers' => ['ibexa.messenger.transport'],
        ]);

        $application->run($input, new NullOutput());
        self::assertCount(1, $this->fooHandler->getHandledMessages());
    }

    /**
     * Tests that dispatching a message while a SiteAccess is active automatically
     * adds a SiteAccessStamp to the envelope in the transport.
     */
    public function testDispatchingMessageAddsSiteAccessStamp(): void
    {
        $siteAccess = new SiteAccess('__default_site_access__');
        $this->siteAccessService->setSiteAccess($siteAccess);

        $this->bus->dispatch(new FooMessage());

        $messages = $this->getMessagesFromReceiver();
        self::assertCount(1, $messages);

        $envelope = $messages[0];
        $stamp = $envelope->last(SiteAccessStamp::class);

        self::assertNotNull($stamp);
        self::assertSame('__default_site_access__', $stamp->siteAccess);
    }

    /**
     * Tests that dispatching a message when no SiteAccess is set does not
     * add a SiteAccessStamp to the envelope.
     */
    public function testDispatchingMessageWithoutSiteAccessDoesNotAddStamp(): void
    {
        $this->siteAccessService->setSiteAccess(null);

        $this->bus->dispatch(new FooMessage());

        $messages = $this->getMessagesFromReceiver();
        self::assertCount(1, $messages);

        $envelope = $messages[0];
        self::assertNull($envelope->last(SiteAccessStamp::class));
    }

    /**
     * Tests that consuming a message with a SiteAccessStamp triggers a
     * CONFIG_SCOPE_CHANGE event with the correct SiteAccess.
     */
    public function testConsumingMessageWithSiteAccessStampTriggersScopeChange(): void
    {
        $capturedEvents = [];
        $this->eventDispatcher->addListener(
            MVCEvents::CONFIG_SCOPE_CHANGE,
            static function (ScopeChangeEvent $event) use (&$capturedEvents): void {
                $capturedEvents[] = $event;
            },
        );

        $envelope = new Envelope(new FooMessage(), [
            new ReceivedStamp('test'),
            new SiteAccessStamp('__default_site_access__'),
        ]);

        $this->bus->dispatch($envelope);

        self::assertCount(1, $capturedEvents);
        self::assertSame('__default_site_access__', $capturedEvents[0]->getSiteAccess()->name);
    }

    /**
     * Tests that consuming a message without a SiteAccessStamp does not
     * trigger a CONFIG_SCOPE_CHANGE event.
     */
    public function testConsumingMessageWithoutSiteAccessStampDoesNotTriggerScopeChange(): void
    {
        $scopeChangeTriggered = false;
        $this->eventDispatcher->addListener(
            MVCEvents::CONFIG_SCOPE_CHANGE,
            static function () use (&$scopeChangeTriggered): void {
                $scopeChangeTriggered = true;
            },
        );

        $envelope = new Envelope(new FooMessage(), [
            new ReceivedStamp('test'),
        ]);

        $this->bus->dispatch($envelope);

        self::assertFalse($scopeChangeTriggered);
    }

    /**
     * Tests the full round-trip: setting a SiteAccess on dispatch, retrieving
     * the stamped message from the transport, and re-dispatching it as received
     * to trigger the scope change.
     */
    public function testFullSiteAccessRoundTrip(): void
    {
        // 1. Set current SiteAccess and dispatch
        $siteAccess = new SiteAccess('__default_site_access__');
        $this->siteAccessService->setSiteAccess($siteAccess);

        $this->bus->dispatch(new FooMessage());

        // 2. Retrieve from transport and verify stamp
        $messages = $this->getMessagesFromReceiver();
        self::assertCount(1, $messages);

        $envelope = $messages[0];
        $stamp = $envelope->last(SiteAccessStamp::class);
        self::assertNotNull($stamp);
        self::assertSame('__default_site_access__', $stamp->siteAccess);

        // 3. Clear current SiteAccess to simulate worker context
        $this->siteAccessService->setSiteAccess(null);
        self::assertNull($this->siteAccessService->getCurrent());

        // 4. Re-dispatch as received and verify scope change event fires
        $capturedEvents = [];
        $this->eventDispatcher->addListener(
            MVCEvents::CONFIG_SCOPE_CHANGE,
            static function (ScopeChangeEvent $event) use (&$capturedEvents): void {
                $capturedEvents[] = $event;
            },
        );

        $envelope = Envelope::wrap($envelope, [new ReceivedStamp('test')]);
        $this->bus->dispatch($envelope);

        self::assertCount(1, $capturedEvents);
        self::assertSame('__default_site_access__', $capturedEvents[0]->getSiteAccess()->name);
    }

    /**
     * @return array<\Symfony\Component\Messenger\Envelope>
     */
    private function getMessagesFromReceiver(): array
    {
        $receivedMessages = [];
        do {
            $messages = $this->receiver->get();
            $messageFound = false;

            foreach ($messages as $message) {
                $receivedMessages[] = $message;

                // Acknowledging the message early to remove it from Redis queue
                $this->receiver->ack($message);
                $messageFound = true;
            }
        } while ($messageFound);

        return $receivedMessages;
    }
}
