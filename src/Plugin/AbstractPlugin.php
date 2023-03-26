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

namespace Phayne\EventStore\Plugin;

use Phayne\EventStore\ActionEventEmitterEventStore;

/**
 * Class AbstractPlugin
 *
 * @package Phayne\EventStore\Plugin
 * @author Julien Guittard <julien@phayne.com>
 */
abstract class AbstractPlugin implements Plugin
{
    protected array $listenerHandlers = [];

    public function detachFromEventStore(ActionEventEmitterEventStore $eventStore): void
    {
        foreach ($this->listenerHandlers as $listenerHandler) {
            $eventStore->detach($listenerHandler);
        }

        $this->listenerHandlers = [];
    }
}
