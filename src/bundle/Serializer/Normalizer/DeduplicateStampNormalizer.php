<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Serializer\Normalizer;

use Ibexa\Bundle\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @phpstan-type TData array{
 *     key: string,
 *     ttl?: float|null,
 *     only_deduplicate_in_queue?: bool|null,
 * }
 */
final class DeduplicateStampNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    /**
     * @phpstan-param TData $data
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): DeduplicateStamp
    {
        return new DeduplicateStamp(
            $data['key'],
            $data['ttl'] ?? 300.0,
            $data['only_deduplicate_in_queue'] ?? false,
        );
    }

    public function supportsDenormalization($data, string $type, ?string $format = null): bool
    {
        return $type === DeduplicateStamp::class;
    }

    /**
     * @phpstan-return TData
     */
    public function normalize($object, ?string $format = null, array $context = []): array
    {
        assert($object instanceof DeduplicateStamp);

        return [
            'key' => (string)($object->getKey()),
            'ttl' => $object->getTtl(),
            'only_deduplicate_in_queue' => $object->onlyDeduplicateInQueue(),
        ];
    }

    public function supportsNormalization($data, ?string $format = null): bool
    {
        return $data instanceof DeduplicateStamp;
    }
}
