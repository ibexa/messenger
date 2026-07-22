<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Messenger\Stamp;

use Symfony\Component\Lock\Key;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp as SymfonyDeduplicateStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * (c) Fabien Potencier <fabien@symfony.com>.
 *
 * Original code: https://github.com/symfony/symfony/blob/7.3/src/Symfony/Component/Messenger/Stamp/DeduplicateStamp.php
 *
 * @deprecated since Ibexa 5.0.9. Starting from Ibexa 6.0, the native {@see SymfonyDeduplicateStamp}
 * will be used instead. Ibexa 5.0 is not prepared to handle the Symfony stamp yet, so keep using
 * this class until you upgrade.
 */
class DeduplicateStamp implements StampInterface
{
    private Key $key;

    private ?float $ttl;

    private bool $onlyDeduplicateInQueue;

    public function __construct(
        string $key,
        ?float $ttl = 300.0,
        bool $onlyDeduplicateInQueue = false
    ) {
        trigger_deprecation(
            'ibexa/messenger',
            '5.0.9',
            'The "%s" class is deprecated, starting from Ibexa 6.0 the native "%s" will be used instead.',
            self::class,
            SymfonyDeduplicateStamp::class,
        );

        if (!class_exists(Key::class)) {
            throw new LogicException(sprintf(
                'You cannot use the "%s" as the Lock component is not installed. Try running "composer require symfony/lock".',
                self::class,
            ));
        }

        $this->key = new Key($key);
        $this->ttl = $ttl;
        $this->onlyDeduplicateInQueue = $onlyDeduplicateInQueue;
    }

    public function onlyDeduplicateInQueue(): bool
    {
        return $this->onlyDeduplicateInQueue;
    }

    public function getKey(): Key
    {
        return $this->key;
    }

    public function getTtl(): ?float
    {
        return $this->ttl;
    }
}
