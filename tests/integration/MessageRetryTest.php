<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Messenger;

use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use Ibexa\Tests\Integration\Messenger\Stubs\FailingOnceMessage;
use Ibexa\Tests\Integration\Messenger\Stubs\FailingOnceMessageHandler;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class MessageRetryTest extends IbexaKernelTestCase
{
    public function testFailedMessageIsHandledAgainOnRetry(): void
    {
        $core = self::getIbexaTestCore();
        $transport = $core->getServiceByClassName(
            TransportInterface::class,
            'messenger.transport.ibexa_serializer_with_retries',
        );
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $handler = $core->getServiceByClassName(FailingOnceMessageHandler::class);

        $transport->send(new Envelope(new FailingOnceMessage()));

        $kernel = self::getContainer()->get('kernel');
        self::assertInstanceOf(AbstractTestKernel::class, $kernel);
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $exitCode = $application->run(new ArrayInput([
            'command' => 'messenger:consume',
            'receivers' => ['ibexa_serializer_with_retries'],
            '--bus' => 'ibexa.messenger.bus',
            // The message, then its retry.
            '--limit' => 2,
            '--time-limit' => 3,
            // Resetting the services would empty the in-memory transport, with the retried message in it.
            '--no-reset' => true,
        ]), new NullOutput());

        self::assertSame(0, $exitCode, 'The worker stopped on an error, e.g. a retried message that could not be decoded.');
        self::assertSame(2, $handler->getAttempts());
        self::assertCount(0, $transport->get());
    }
}
