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

namespace Phayne\EventStore\Projection;

use Phayne\EventStore\EventStore;
use Phayne\EventStore\EventStoreDecorator;
use Phayne\EventStore\Exception\ProjectionNotFound;
use Phayne\EventStore\InMemoryEventStore;
use Phayne\EventStore\NonTransactionalInMemoryEventStore;
use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\OutOfBoundsException;
use Phayne\Exception\RuntimeException;
use ReflectionProperty;

use function array_keys;
use function array_slice;
use function get_class;
use function preg_grep;
use function restore_error_handler;
use function set_error_handler;
use function sort;
use function sprintf;

use const SORT_STRING;

/**
 * Class InMemoryProjectionManager
 *
 * @package Phayne\EventStore\Projection
 * @author Julien Guittard <julien@phayne.com>
 */
class InMemoryProjectionManager implements ProjectionManager
{
    private array $projectors = [];

    public function __construct(private readonly EventStore $eventStore)
    {
        while ($this->eventStore instanceof EventStoreDecorator) {
            $eventStore = $eventStore->getInnerEventStore();
        }

        if (
            ! $eventStore instanceof InMemoryEventStore && ! $eventStore instanceof NonTransactionalInMemoryEventStore
        ) {
            throw new InvalidArgumentException('Unknown event store instance given');
        }
    }

    public function createQuery(array $options = null): Query
    {
        return new InMemoryEventStoreQuery(
            $this->eventStore,
            $options[Query::OPTION_PCNTL_DISPATCH] ?? Query::DEFAULT_PCNTL_DISPATCH
        );
    }

    public function createProjection(string $name, array $options = []): Projector
    {
        $projector = new InMemoryEventStoreProjector(
            $this->eventStore,
            $name,
            $options[Projector::OPTION_CACHE_SIZE] ?? Projector::DEFAULT_CACHE_SIZE,
            $options[Projector::OPTION_SLEEP] ?? Projector::DEFAULT_SLEEP,
            $options[Projector::OPTION_PCNTL_DISPATCH] ?? Projector::DEFAULT_PCNTL_DISPATCH,
        );

        if (! isset($this->projectors[$name])) {
            $this->projectors[$name] = $projector;
        }

        return $projector;
    }

    public function createReadModelProjection(
        string $name,
        ReadModel $readModel,
        array $options = []
    ): ReadModelProjector {
        $projector = new InMemoryEventStoreReadModelProjector(
            $this->eventStore,
            $name,
            $readModel,
            $options[Projector::OPTION_CACHE_SIZE] ?? Projector::DEFAULT_CACHE_SIZE,
            $options[Projector::OPTION_PERSIST_BLOCK_SIZE] ?? Projector::DEFAULT_PERSIST_BLOCK_SIZE,
            $options[Projector::OPTION_SLEEP] ?? Projector::DEFAULT_SLEEP,
            $options[Projector::OPTION_PCNTL_DISPATCH] ?? Projector::DEFAULT_PCNTL_DISPATCH,
        );

        if (! isset($this->projectors[$name])) {
            $this->projectors[$name] = $projector;
        }

        return $projector;
    }

    public function deleteProjection(string $name, bool $deleteEmittedEvents): void
    {
        throw new RuntimeException('Deleting a projection is not supported in ' . get_class($this));
    }

    public function resetProjection(string $name): void
    {
        throw new RuntimeException('Resetting a projection is not supported in ' . get_class($this));
    }

    public function stopProjection(string $name): void
    {
        throw new RuntimeException('Stopping a projection is not supported in ' . get_class($this));
    }

    public function fetchProjectionNames(?string $filter, int $limit = 20, int $offset = 0): array
    {
        if (1 > $limit) {
            throw new OutOfBoundsException(sprintf(
                'Invalid limit "%d" given. Must be greater than 0.',
                $limit
            ));
        }

        if (0 > $offset) {
            throw new OutOfBoundsException(sprintf(
                'Invalid offset "%d" given. Must be greater or equal than 0.',
                $offset
            ));
        }

        if (null === $filter) {
            $result = array_keys($this->projectors);
            sort($result, SORT_STRING);

            return array_slice($result, $offset, $limit);
        }

        if (isset($this->projectors[$filter])) {
            return [$filter];
        }

        return [];
    }

    public function fetchProjectionNamesRegex(string $regex, int $limit = 20, int $offset = 0): array
    {
        if (1 > $limit) {
            throw new OutOfBoundsException(sprintf(
                'Invalid limit "%d" given. Must be greater than 0.',
                $limit
            ));
        }

        if (0 > $offset) {
            throw new OutOfBoundsException(sprintf(
                'Invalid offset "%d" given. Must be greater or equal than 0.',
                $offset
            ));
        }

        set_error_handler(function ($errorNo, $errorMsg): void {
            throw new RuntimeException($errorMsg);
        });

        try {
            $result = preg_grep("/$regex/", array_keys($this->projectors));
            sort($result, SORT_STRING);

            return array_slice($result, $offset, $limit);
        } catch (RuntimeException $e) {
            throw new InvalidArgumentException('Invalid regex pattern given', 0, $e);
        } finally {
            restore_error_handler();
        }
    }

    public function fetchProjectionStatus(string $name): ProjectionStatus
    {
        if (! isset($this->projectors[$name])) {
            throw ProjectionNotFound::withName($name);
        }

        $projector = $this->projectors[$name];

        $ref = new ReflectionProperty(get_class($projector), 'status');
        $ref->setAccessible(true);

        return ProjectionStatus::from($ref->getValue($projector));
    }

    public function fetchProjectionStreamPositions(string $name): array
    {
        if (! isset($this->projectors[$name])) {
            throw ProjectionNotFound::withName($name);
        }

        $projector = $this->projectors[$name];

        $ref = new ReflectionProperty(get_class($projector), 'streamPositions');
        $ref->setAccessible(true);
        $value = $ref->getValue($projector);

        return (null === $value) ? [] : $value;
    }

    public function fetchProjectionState(string $name): array
    {
        if (! isset($this->projectors[$name])) {
            throw ProjectionNotFound::withName($name);
        }

        return $this->projectors[$name]->state();
    }
}
