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
use Phayne\EventStore\Util\ArrayCache;
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
use function strlen;
use function substr;
use function usleep;

/**
 * Class InMemoryEventStoreReadModelProjector
 *
 * @package Phayne\EventStore\Projection
 * @author Julien Guittard <julien@phayne.com>
 */
final class InMemoryEventStoreReadModelProjector implements ReadModelProjector
{
    private ProjectionStatus $status;

    private EventStore $innerEventStore;

    private int $eventCounter = 0;

    private array $streamPositions = [];

    private array $state = [];

    private ?Closure $initCallback = null;

    private ?Closure $handler = null;

    private array $handlers = [];

    private ArrayCache $cachedStreamNames;

    private bool $isStopped = false;

    private ?string $currentStreamName = null;

    private ?array $query = null;

    private ?MetadataMatcher $metadataMatcher = null;

    public function __construct(
        private readonly EventStore $eventStore,
        private readonly string $name,
        private readonly ReadModel $readModel,
        int $cacheSize,
        private readonly int $persistBlockSize,
        private readonly int $sleep,
        private readonly bool $triggerPcntlSignalDispatch = false
    ) {
        if ($sleep < 1) {
            throw new InvalidArgumentException('sleep must be a positive integer');
        }

        $this->status = ProjectionStatus::IDLE;

        $this->cachedStreamNames = new ArrayCache($cacheSize);

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

    public function init(Closure $callback): ReadModelProjector
    {
        if (null !== $this->initCallback) {
            throw new RuntimeException('Projection already initialized');
        }

        $callback = Closure::bind($callback, $this->createHandlerContext($this->currentStreamName));

        $result = $callback();

        if (is_array($result)) {
            $this->state = $result;
        }

        $this->initCallback = $callback;

        return $this;
    }

    public function fromStream(string $streamName, MetadataMatcher $metadataMatcher = null): ReadModelProjector
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        $this->query['streams'][] = $streamName;
        $this->metadataMatcher = $metadataMatcher;

        return $this;
    }

    public function fromStreams(string ...$streamNames): ReadModelProjector
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        foreach ($streamNames as $streamName) {
            $this->query['streams'][] = $streamName;
        }

        return $this;
    }

    public function fromCategory(string $name): ReadModelProjector
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        $this->query['categories'][] = $name;

        return $this;
    }

    public function fromCategories(string ...$names): ReadModelProjector
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        foreach ($names as $name) {
            $this->query['categories'][] = $name;
        }

        return $this;
    }

    public function fromAll(): ReadModelProjector
    {
        if (null !== $this->query) {
            throw new RuntimeException('From was already called');
        }

        $this->query['all'] = true;

        return $this;
    }

    public function when(array $handlers): ReadModelProjector
    {
        if (null !== $this->handler || ! empty($this->handlers)) {
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

    public function whenAny(Closure $closure): ReadModelProjector
    {
        if (null !== $this->handler || $this->handlers !== []) {
            throw new RuntimeException('When was already called');
        }

        $this->handler = Closure::bind($closure, $this->createHandlerContext($this->currentStreamName));

        return $this;
    }

    public function readModel(): ReadModel
    {
        return $this->readModel;
    }

    public function delete(bool $deleteProjection): void
    {
        if ($deleteProjection) {
            $this->readModel->delete();
        }

        $this->streamPositions = [];
    }

    public function run(bool $keepRunning = true): void
    {
        if (null === $this->query || (null === $this->handler && $this->handlers === [])) {
            throw new RuntimeException('No handlers configured');
        }

        $this->prepareStreamPositions();
        $this->isStopped = false;
        $this->status = ProjectionStatus::RUNNING;

        if (! $this->readModel->isInitialized()) {
            $this->readModel->init();
        }

        do {
            $singleHandler = null !== $this->handler;

            $eventCounter = 0;
            $eventStreams = [];

            foreach ($this->streamPositions as $streamName => $position) {
                try {
                    $eventStreams[$streamName] = $this->eventStore->load(
                        new StreamName($streamName),
                        $position + 1,
                        null,
                        $this->metadataMatcher
                    );
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

            $this->readModel()->persist();

            if (0 === $eventCounter) {
                usleep($this->sleep);
            }

            if ($this->triggerPcntlSignalDispatch) {
                pcntl_signal_dispatch();
            }
        } while ($keepRunning && ! $this->isStopped);

        $this->status = ProjectionStatus::IDLE;
    }

    public function stop(): void
    {
        $this->readModel()->persist();
        $this->isStopped = true;
    }

    public function state(): array
    {
        return $this->state;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function reset(): void
    {
        $this->streamPositions = [];
        $this->state = [];
        $this->readModel->reset();
        $callback = $this->initCallback;

        if (is_callable($callback)) {
            $result = $callback();

            if (is_array($result)) {
                $this->state = $result;
            }
        }
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
            $this->eventCounter++;

            $result = $handler($this->state, $event);

            if (is_array($result)) {
                $this->state = $result;
            }

            if ($this->eventCounter === $this->persistBlockSize) {
                $this->readModel()->persist();
                $this->eventCounter = 0;
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

            $this->eventCounter++;

            $handler = $this->handlers[$event->messageName()];
            $result = $handler($this->state, $event);

            if (is_array($result)) {
                $this->state = $result;
            }

            if ($this->eventCounter === $this->persistBlockSize) {
                $this->readModel()->persist();
                $this->eventCounter = 0;
            }

            if ($this->isStopped) {
                break;
            }
        }
    }

    private function createHandlerContext(?string &$streamName)
    {
        return new class ($this, $streamName) {
            private ReadModelProjector $projector;

            private ?string $streamName = null;

            public function __construct(ReadModelProjector $projector, ?string &$streamName)
            {
                $this->projector = $projector;
                $this->streamName = &$streamName;
            }

            public function stop(): void
            {
                $this->projector->stop();
            }

            public function readModel(): ReadModel
            {
                return $this->projector->readModel();
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
        $streams = array_keys($reflectionProperty->getValue($this->innerEventStore));

        if (isset($this->query['all'])) {
            foreach ($streams as $stream) {
                if (substr($stream, 0, 1) === '$') {
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
