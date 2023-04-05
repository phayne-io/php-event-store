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

use Phayne\EventStore\ActionEventEmitterEventStore;
use Phayne\EventStore\InMemoryEventStore;
use Phayne\Messaging\Event\PhayneActionEventEmitter;
use PHPUnit\Framework\TestCase;

/**
 * Class ActionEventEmitterEventStoreTestCase
 *
 * @package PhayneTest\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
abstract class ActionEventEmitterEventStoreTestCase extends TestCase
{
    protected ActionEventEmitterEventStore $eventStore;

    protected function setUp(): void
    {
        $eventEmitter = new PhayneActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS);
        $this->eventStore = new ActionEventEmitterEventStore(new InMemoryEventStore(), $eventEmitter);
    }
}
