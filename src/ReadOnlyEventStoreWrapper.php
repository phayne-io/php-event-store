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
use Phayne\EventStore\Metadata\MetadataMatcher;

/**
 * Class ReadOnlyEventStoreWrapper
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
class ReadOnlyEventStoreWrapper implements ReadOnlyEventStore
{
    public function __construct(private readonly EventStore $eventStore)
    {
    }

    public function fetchStreamMetadata(StreamName $streamName): array
    {
        return $this->eventStore->fetchStreamMetadata($streamName);
    }

    public function hasStream(StreamName $streamName): bool
    {
        return $this->eventStore->hasStream($streamName);
    }

    public function load(
        StreamName $streamName,
        int $fromNumber = 1,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator {
        return $this->eventStore->load($streamName, $fromNumber, $count, $metadataMatcher);
    }

    public function loadReverse(
        StreamName $streamName,
        int $fromNumber = null,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator {
        return $this->eventStore->loadReverse($streamName, $fromNumber, $count, $metadataMatcher);
    }

    public function fetchStreamNames(
        ?string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array {
        return $this->eventStore->fetchStreamNames($filter, $metadataMatcher, $limit, $offset);
    }

    public function fetchStreamNamesRegex(
        string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array {
        return $this->eventStore->fetchStreamNamesRegex($filter, $metadataMatcher, $limit, $offset);
    }

    public function fetchCategoryNames(?string $filter, int $limit = 20, int $offset = 0): array
    {
        return $this->eventStore->fetchCategoryNames($filter, $limit, $offset);
    }

    public function fetchCategoryNamesRegex(string $filter, int $limit = 20, int $offset = 0): array
    {
        return $this->eventStore->fetchCategoryNamesRegex($filter, $limit, $offset);
    }
}
