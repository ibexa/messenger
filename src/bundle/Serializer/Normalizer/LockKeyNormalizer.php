<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Serializer\Normalizer;

use Closure;
use ReflectionClass;
use Symfony\Component\Lock\Key;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Backport of Symfony normalizer for Lock keys.
 */
final class LockKeyNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize($data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof Key);

        return Closure::bind(fn () => array_intersect_key(
            get_object_vars($this),
            /** @phpstan-ignore-next-line argument.type */
            array_flip($this->__sleep())
        ), $data, Key::class)();
    }

    public function supportsNormalization($data, ?string $format = null): bool
    {
        return $data instanceof Key;
    }

    /**
     * @throws \ReflectionException
     */
    public function denormalize($data, string $type, ?string $format = null, array $context = []): Key
    {
        $key = (new ReflectionClass(Key::class))->newInstanceWithoutConstructor();
        $setter = Closure::bind(
            fn (string $field) => $this->$field = $data[$field],
            $key,
            Key::class,
        );
        foreach ($key->__sleep() as $serializedField) {
            $setter($serializedField);
        }

        return $key;
    }

    public function supportsDenormalization($data, string $type, ?string $format = null): bool
    {
        return $type === Key::class;
    }
}
