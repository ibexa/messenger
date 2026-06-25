<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Stamp;

use Ibexa\Contracts\Messenger\Stamp\DeduplicateStamp as ContractsDeduplicateStamp;

/**
 * @deprecated since Ibexa 5.0.9, use {@see ContractsDeduplicateStamp} instead.
 */
class DeduplicateStamp extends ContractsDeduplicateStamp
{
    public function __construct(
        string $key,
        ?float $ttl = 300.0,
        bool $onlyDeduplicateInQueue = false
    ) {
        trigger_deprecation(
            'ibexa/messenger',
            '5.0.9',
            'The "%s" class is deprecated, use "%s" instead.',
            self::class,
            ContractsDeduplicateStamp::class,
        );

        parent::__construct($key, $ttl, $onlyDeduplicateInQueue);
    }
}
