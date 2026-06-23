<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final readonly class UserPermissionStamp implements StampInterface
{
    public function __construct(public int $userId)
    {
    }
}
