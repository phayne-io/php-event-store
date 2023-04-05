<?php

/**
 * This file is part of phayne-io/php-event-store package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see       https://github.com/phayne-io/php-event-store for the canonical source repository
 * @copyright Copyright (c) 2023 Phayne. (https://phayne.io)
 */

declare(strict_types=1);

namespace PhayneTest\EventStore;

use DG\BypassFinals;
use PHPUnit\Runner\BeforeTestHook;
use ReflectionClass;

/**
 * Class PHPUnitUtil
 *
 * @package PhayneTest\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
class PHPUnitUtil implements BeforeTestHook
{
    public function executeBeforeTest(string $test): void
    {
        BypassFinals::enable();
    }

    public static function callMethod(object $obj, string $name, array $args, bool $static = false)
    {
        $class = new ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($static ? null : $obj, $args);
    }

    public static function getProperty(object $obj, string $propertyName)
    {
        $class = new ReflectionClass($obj);
        $property = $class->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }
}
