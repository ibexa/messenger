<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

use Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper;
use Ibexa\Contracts\Test\Core\Bootstrapper\PurgeSearchIndexHook;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use Symfony\Component\HttpKernel\Kernel;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

chdir(dirname(__DIR__, 2));

$kernelClass = (static function (): string {
    if (!isset($_SERVER['KERNEL_CLASS']) && !isset($_ENV['KERNEL_CLASS'])) {
        throw new LogicException(
            'You must set the KERNEL_CLASS environment variable to the fully-qualified class name of your Kernel'
            . ' in phpunit.xml / phpunit.xml.dist.',
        );
    }

    $class = (string)($_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS']);
    if (!class_exists($class)) {
        throw new RuntimeException(sprintf(
            'Class "%s" doesn\'t exist or cannot be autoloaded.'
            . ' Check that the KERNEL_CLASS value in phpunit.xml matches the fully-qualified class name of your Kernel.',
            $class,
        ));
    }

    if (!is_a($class, IbexaTestKernel::class, true)) {
        throw new RuntimeException(sprintf('Class "%s" is not a "%s".', $class, Kernel::class));
    }

    return $class;
})();

// Database preparation, schema import, and fixture import all stay at Bootstrapper's defaults
// (enabled) to match this suite's actual needs: MessageBusTest exercises a real Doctrine messenger
// transport backed by the `ibexa_messenger_messages` table from AbstractTestKernel::getSchemaFiles(),
// so schema import can't be skipped here. Only the search index purge needs to be explicitly
// requested, since PurgeSearchIndexHook defaults to off but the old bootstrap.php always purged.
(new Bootstrapper())->bootstrap($kernelClass, [
    PurgeSearchIndexHook::class => [PurgeSearchIndexHook::OPTION_PURGE_INDEX => true],
]);
