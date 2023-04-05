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

use ArrayIterator;
use Phayne\EventStore\Stream;
use Phayne\EventStore\StreamName;
use PhayneTest\EventStore\Mock\UserCreated;

/**
 * Trait EventStoreTestStreamTrait
 *
 * @package PhayneTest\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
trait EventStoreTestStreamTrait
{
    protected function getTestStream(): Stream
    {
        $streamEvent = UserCreated::with(
            ['name' => 'John', 'email' => 'john@doe.com'],
            1
        );

        return new Stream(new StreamName('Phayne\Model\User'), new ArrayIterator([$streamEvent]), ['foo' => 'bar']);
    }
}
