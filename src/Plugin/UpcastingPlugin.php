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
use Phayne\EventStore\StreamIterator\StreamIterator;
use Phayne\EventStore\Upcasting\Upcaster;
use Phayne\EventStore\Upcasting\UpcastingIterator;
use Phayne\Messaging\Event\ActionEvent;

/**
 * Class UpcastingPlugin
 *
 * @package Phayne\EventStore\Plugin
 * @author Julien Guittard <julien@phayne.com>
 */
final class UpcastingPlugin extends AbstractPlugin
{
    public const ACTION_EVENT_PRIORITY = -1000;

    public function __construct(private readonly Upcaster $upcaster)
    {
    }

    public function attachToEventStore(ActionEventEmitterEventStore $eventStore): void
    {
        $upcaster = function (ActionEvent $actionEvent): void {
            $streamEvents = $actionEvent->param('streamEvents');

            if (! $streamEvents instanceof StreamIterator) {
                return;
            }

            $actionEvent->setParam('streamEvents', new UpcastingIterator($this->upcaster, $streamEvents));
        };

        $eventStore->attach(
            ActionEventEmitterEventStore::EVENT_LOAD,
            $upcaster,
            self::ACTION_EVENT_PRIORITY
        );

        $eventStore->attach(
            ActionEventEmitterEventStore::EVENT_LOAD_REVERSE,
            $upcaster,
            self::ACTION_EVENT_PRIORITY
        );
    }
}
