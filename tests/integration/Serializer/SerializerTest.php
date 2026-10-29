<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Messenger\Serializer;

use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use Ibexa\Tests\Integration\Messenger\Stubs\FooMessage;
use LogicException;
use RuntimeException;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Throwable;

final class SerializerTest extends IbexaKernelTestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = self::getIbexaTestCore()->getServiceByClassName(
            SerializerInterface::class,
            'ibexa.messenger.serializer',
        );
    }

    /**
     * Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener adds ErrorDetailsStamp
     * to a failed message before sending it back to the transport for a retry.
     *
     * @dataProvider provideForDecodingErrorDetailsStamp
     *
     * @param list<array{class-string<Throwable>, string}> $expectedExceptionChain
     */
    public function testDecodesErrorDetailsStamp(
        Throwable $exception,
        array $expectedExceptionChain
    ): void {
        $envelope = new Envelope(new FooMessage(), [ErrorDetailsStamp::create($exception)]);

        $decodedEnvelope = $this->serializer->decode($this->serializer->encode($envelope));

        self::assertInstanceOf(FooMessage::class, $decodedEnvelope->getMessage());

        $stamp = $decodedEnvelope->last(ErrorDetailsStamp::class);
        self::assertInstanceOf(ErrorDetailsStamp::class, $stamp);
        self::assertSame(get_class($exception), $stamp->getExceptionClass());
        self::assertSame($exception->getMessage(), $stamp->getExceptionMessage());

        $actualExceptionChain = [];
        $flattenException = $stamp->getFlattenException();
        self::assertInstanceOf(FlattenException::class, $flattenException);
        while ($flattenException !== null) {
            $actualExceptionChain[] = [$flattenException->getClass(), $flattenException->getMessage()];
            $flattenException = $flattenException->getPrevious();
        }

        self::assertSame($expectedExceptionChain, $actualExceptionChain);
    }

    /**
     * @return iterable<string, array{Throwable, list<array{class-string<Throwable>, string}>}>
     */
    public static function provideForDecodingErrorDetailsStamp(): iterable
    {
        yield 'single exception' => [
            new RuntimeException('Outer failure'),
            [
                [RuntimeException::class, 'Outer failure'],
            ],
        ];

        yield 'exception with previous exception' => [
            new RuntimeException('Outer failure', 0, new LogicException('Inner failure')),
            [
                [RuntimeException::class, 'Outer failure'],
                [LogicException::class, 'Inner failure'],
            ],
        ];
    }
}
