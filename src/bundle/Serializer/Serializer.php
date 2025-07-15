<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Serializer;

use Ibexa\Bundle\Messenger\Serializer\Normalizer\DeduplicateStampNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

final class Serializer extends \Symfony\Component\Messenger\Transport\Serialization\Serializer
{
    public static function create(): self
    {
        $encoders = [new XmlEncoder(), new JsonEncoder()];
        $normalizers = [
            new DeduplicateStampNormalizer(),
            new DateTimeNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(),
        ];
        $serializer = new SymfonySerializer($normalizers, $encoders);

        return new self($serializer);
    }
}
