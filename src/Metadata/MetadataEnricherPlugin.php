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

namespace Phayne\EventStore\Metadata;

use ArrayIterator;
use Iterator;
use Phayne\EventStore\ActionEventEmitterEventStore;
use Phayne\EventStore\Plugin\AbstractPlugin;
use Phayne\EventStore\Stream;
use Phayne\Messaging\Event\ActionEvent;

/**
 * Class MetadataEnricherPlugin
 *
 * @package Phayne\EventStore\Metadata
 * @author Julien Guittard <julien@phayne.com>
 */
class MetadataEnricherPlugin extends AbstractPlugin
{
    public const ACTION_EVENT_PRIORITY = 1000;

    public function __construct(private readonly MetadataEnricher $metadataEnricher)
    {
    }

    public function attachToEventStore(ActionEventEmitterEventStore $eventStore): void
    {
        $this->listenerHandlers[] = $eventStore->attach(
            ActionEventEmitterEventStore::EVENT_CREATE,
            function (ActionEvent $createEvent): void {
                $this->onEventStoreCreateStream($createEvent);
            },
            self::ACTION_EVENT_PRIORITY
        );

        $this->listenerHandlers[] = $eventStore->attach(
            ActionEventEmitterEventStore::EVENT_APPEND_TO,
            function (ActionEvent $appendToStreamEvent): void {
                $this->onEventStoreAppendToStream($appendToStreamEvent);
            },
            self::ACTION_EVENT_PRIORITY
        );
    }

    public function onEventStoreCreateStream(ActionEvent $createEvent): void
    {
        $stream = $createEvent->param('stream');

        if (! $stream instanceof Stream) {
            return;
        }

        $streamEvents = $stream->streamEvents;
        $streamEvents = $this->handleRecordedEvents($streamEvents);

        $createEvent->setParam('stream', new Stream($stream->streamName, $streamEvents, $stream->metadata));
    }

    public function onEventStoreAppendToStream(ActionEvent $appendToStreamEvent): void
    {
        $streamEvents = $appendToStreamEvent->param('streamEvents');

        if (! $streamEvents instanceof Iterator) {
            return;
        }

        $streamEvents = $this->handleRecordedEvents($streamEvents);

        $appendToStreamEvent->setParam('streamEvents', $streamEvents);
    }

    private function handleRecordedEvents(Iterator $events): Iterator
    {
        $enrichedEvents = [];

        foreach ($events as $event) {
            $enrichedEvents[] = $this->metadataEnricher->enrich($event);
        }

        return new ArrayIterator($enrichedEvents);
    }
}
