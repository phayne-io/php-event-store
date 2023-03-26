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

namespace Phayne\EventStore;

use Iterator;
use Phayne\EventStore\Exception\StreamExistsAlready;
use Phayne\EventStore\Exception\StreamNotFound;

use function iterator_to_array;

/**
 * Class NonTransactionalInMemoryEventStore
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
class NonTransactionalInMemoryEventStore implements EventStore
{
    use ProvidesInMemoryEventStore;

    public function create(Stream $stream): void
    {
        $streamName = $stream->streamName;
        $streamNameString = (string)$streamName;

        if (
            isset($this->streams[$streamNameString]) ||
            isset($this->cachedStreams[$streamNameString])
        ) {
            throw StreamExistsAlready::with($streamName);
        }

        $this->streams[$streamNameString]['events'] = iterator_to_array($stream->streamEvents);
        $this->streams[$streamNameString]['metadata'] = $stream->metadata;
    }

    public function appendTo(StreamName $streamName, Iterator $streamEvents): void
    {
        $streamNameString = (string)$streamName;

        if (
            ! isset($this->streams[$streamNameString]) &&
            ! isset($this->cachedStreams[$streamNameString])
        ) {
            throw StreamNotFound::with($streamName);
        }

        foreach ($streamEvents as $streamEvent) {
            $this->streams[$streamNameString]['events'][] = $streamEvent;
        }
    }
}
