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

use Closure;
use Phayne\EventStore\EventStore;
use Phayne\EventStore\EventStoreDecorator;
use Phayne\EventStore\Exception\StreamNotFound;
use Phayne\EventStore\InMemoryEventStore;
use Phayne\EventStore\Metadata\MetadataMatcher;
use Phayne\EventStore\NonTransactionalInMemoryEventStore;
use Phayne\EventStore\StreamIterator\MergedStreamIterator;
use Phayne\EventStore\StreamName;
use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Messaging\Message;
use ReflectionProperty;

use function array_keys;
use function array_merge;
use function array_values;
use function get_class;
use function is_array;
use function is_callable;
use function is_string;
use function pcntl_signal_dispatch;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Class InMemoryEventStoreQuery
 *
 * @package Phayne\EventStore\Projection
 * @author Julien Guittard <julien@phayne.com>
 */
final class InMemoryEventStoreQuery implements Query
{
    private EventStore $innerEventStore;

    private array $streamPositions = [];

    private array $state = [];

    private ?Closure $initCallback = null;

    private ?Closure $handler = null;

    private array $handlers = [];

    private bool $isStopped = false;

    private ?string $currentStreamName = null;

    private ?array $query = null;

    private ?MetadataMatcher $metadataMatcher = null;

    public function __construct(
        private readonly EventStore $eventStore,
        private readonly bool $triggerPcntlSignalDispatch = false
    ) {
        while ($eventStore instanceof EventStoreDecorator) {
            $eventStore = $eventStore->getInnerEventStore();
        }

        if (
            ! $eventStore instanceof InMemoryEventStore && ! $eventStore instanceof NonTransactionalInMemoryEventStore
        ) {
            throw new InvalidArgumentException('Unknown event store instance given');
        }

        $this->innerEventStore = $eventStore;
    }

    public function init(Closure $callback): Query
    {
        if (null !== $this->initCallback) {
            throw new RuntimeException('Query is already initialized');
        }

        $callback = Closure::bind($callback, $this->createHandlerContext($this->currentStreamName));

        $result = $callback();

        if (is_array($result)) {
            $this->state = $result;
        }

        $this->initCallback = $callback;

        return $this;
    }

    public function fromStream(string $streamName, MetadataMatcher $metadataMatcher = null): Query
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        $this->query['streams'][] = $streamName;
        $this->metadataMatcher = $metadataMatcher;

        return $this;
    }

    public function fromStreams(string ...$streamNames): Query
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        foreach ($streamNames as $streamName) {
            $this->query['streams'][] = $streamName;
        }

        return $this;
    }

    public function fromCategory(string $name): Query
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        $this->query['categories'][] = $name;

        return $this;
    }

    public function fromCategories(string ...$names): Query
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        foreach ($names as $name) {
            $this->query['categories'][] = $name;
        }

        return $this;
    }

    public function fromAll(): Query
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        $this->query['all'] = true;

        return $this;
    }

    public function when(array $handlers): Query
    {
        if (null !== $this->handler || $this->handlers !== []) {
            throw new RuntimeException('When was already called');
        }

        foreach ($handlers as $eventName => $handler) {
            if (! is_string($eventName)) {
                throw new InvalidArgumentException('Invalid event name given, string expected');
            }

            if (! $handler instanceof Closure) {
                throw new InvalidArgumentException('Invalid handler given, Closure expected');
            }

            $this->handlers[$eventName] =
                Closure::bind($handler, $this->createHandlerContext($this->currentStreamName));
        }

        return $this;
    }

    public function whenAny(Closure $closure): Query
    {
        if (null !== $this->handler || $this->handlers !== []) {
            throw new RuntimeException('When was already called');
        }

        $this->handler = Closure::bind($closure, $this->createHandlerContext($this->currentStreamName));

        return $this;
    }

    public function reset(): void
    {
        $this->streamPositions = [];

        $callback = $this->initCallback;

        if (is_callable($callback)) {
            $result = $callback();

            if (is_array($result)) {
                $this->state = $result;

                return;
            }
        }

        $this->state = [];
    }

    public function run(): void
    {
        if (
            null === $this->query ||
            (null === $this->handler && $this->handlers === [])
        ) {
            throw new RuntimeException('No handlers configured');
        }

        $this->prepareStreamPositions();
        $singleHandler = null !== $this->handler;

        $eventStreams = [];

        foreach ($this->streamPositions as $streamName => $position) {
            try {
                $eventStreams[$streamName] =
                    $this->eventStore->load(new StreamName($streamName), $position + 1, null, $this->metadataMatcher);
            } catch (StreamNotFound) {
                continue;
            }
        }

        $streamEvents = new MergedStreamIterator(array_keys($eventStreams), ...array_values($eventStreams));

        if ($singleHandler) {
            $this->handleStreamWithSingleHandler($streamEvents);
        } else {
            $this->handleStreamWithHandlers($streamEvents);
        }
    }

    public function stop(): void
    {
        $this->isStopped = true;
    }

    public function state(): array
    {
        return $this->state;
    }

    private function handleStreamWithSingleHandler(MergedStreamIterator $events): void
    {
        $handler = $this->handler;

        /* @var Message $event */
        foreach ($events as $event) {
            if ($this->triggerPcntlSignalDispatch) {
                pcntl_signal_dispatch();
            }

            $this->currentStreamName = $events->streamName();
            $this->streamPositions[$this->currentStreamName]++;

            $result = $handler($this->state, $event);

            if (is_array($result)) {
                $this->state = $result;
            }

            if ($this->isStopped) {
                break;
            }
        }
    }

    private function handleStreamWithHandlers(MergedStreamIterator $events): void
    {
        /* @var Message $event */
        foreach ($events as $event) {
            if ($this->triggerPcntlSignalDispatch) {
                pcntl_signal_dispatch();
            }

            $this->currentStreamName = $events->streamName();
            $this->streamPositions[$this->currentStreamName]++;

            if (! isset($this->handlers[$event->messageName()])) {
                continue;
            }

            $handler = $this->handlers[$event->messageName()];
            $result = $handler($this->state, $event);

            if (is_array($result)) {
                $this->state = $result;
            }

            if ($this->isStopped) {
                break;
            }
        }
    }

    private function createHandlerContext(?string &$streamName): object
    {
        return new class ($this, $streamName) {
            private Query $query;

            private ?string $streamName = null;

            public function __construct(Query $query, ?string &$streamName)
            {
                $this->query = $query;
                $this->streamName = &$streamName;
            }

            public function stop(): void
            {
                $this->query->stop();
            }

            public function streamName(): ?string
            {
                return $this->streamName;
            }
        };
    }

    private function prepareStreamPositions(): void
    {
        $reflectionProperty = new ReflectionProperty(get_class($this->innerEventStore), 'streams');
        $reflectionProperty->setAccessible(true);

        $streamPositions = [];
        $streams = array_keys($reflectionProperty->getValue($this->eventStore));

        if (isset($this->query['all'])) {
            foreach ($streams as $stream) {
                if (str_starts_with($stream, '$')) {
                    // ignore internal streams
                    continue;
                }
                $streamPositions[$stream] = 0;
            }

            $this->streamPositions = array_merge($streamPositions, $this->streamPositions);

            return;
        }

        if (isset($this->query['categories'])) {
            foreach ($streams as $stream) {
                foreach ($this->query['categories'] as $category) {
                    if (substr($stream, 0, strlen($category) + 1) === $category . '-') {
                        $streamPositions[$stream] = 0;
                        break;
                    }
                }
            }

            $this->streamPositions = array_merge($streamPositions, $this->streamPositions);

            return;
        }

        // stream names given
        foreach ($this->query['streams'] as $stream) {
            $streamPositions[$stream] = 0;
        }

        $this->streamPositions = array_merge($streamPositions, $this->streamPositions);
    }
}
