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

use Assert\Assertion;
use Iterator;
use Phayne\EventStore\Exception\ConcurrencyException;
use Phayne\EventStore\Exception\StreamExistsAlready;
use Phayne\EventStore\Exception\StreamNotFound;
use Phayne\EventStore\Metadata\MetadataMatcher;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\Messaging\Event\ActionEventEmitter;
use Phayne\Messaging\Event\ListenerHandler;

/**
 * Class ActionEventEmitterEventStore
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
class ActionEventEmitterEventStore implements EventStoreDecorator
{
    public const EVENT_APPEND_TO = 'appendTo';
    public const EVENT_CREATE = 'create';
    public const EVENT_LOAD = 'load';
    public const EVENT_LOAD_REVERSE = 'loadReverse';
    public const EVENT_DELETE = 'delete';
    public const EVENT_HAS_STREAM = 'hasStream';
    public const EVENT_FETCH_STREAM_METADATA = 'fetchStreamMetadata';
    public const EVENT_UPDATE_STREAM_METADATA = 'updateStreamMetadata';
    public const EVENT_FETCH_STREAM_NAMES = 'fetchStreamNames';
    public const EVENT_FETCH_STREAM_NAMES_REGEX = 'fetchStreamNamesRegex';
    public const EVENT_FETCH_CATEGORY_NAMES = 'fetchCategoryNames';
    public const EVENT_FETCH_CATEGORY_NAMES_REGEX = 'fetchCategoryNamesRegex';

    public const ALL_EVENTS = [
        self::EVENT_APPEND_TO,
        self::EVENT_CREATE,
        self::EVENT_LOAD,
        self::EVENT_LOAD_REVERSE,
        self::EVENT_DELETE,
        self::EVENT_HAS_STREAM,
        self::EVENT_FETCH_STREAM_METADATA,
        self::EVENT_UPDATE_STREAM_METADATA,
        self::EVENT_FETCH_STREAM_NAMES,
        self::EVENT_FETCH_STREAM_NAMES_REGEX,
        self::EVENT_FETCH_CATEGORY_NAMES,
        self::EVENT_FETCH_CATEGORY_NAMES_REGEX,
    ];

    public function __construct(
        protected readonly EventStore $eventStore,
        protected readonly ActionEventEmitter $actionEventEmitter
    ) {
        $this->actionEventEmitter->attachListener(
            self::EVENT_CREATE,
            function (ActionEvent $event): void {
                $stream = $event->param('stream');

                try {
                    $this->eventStore->create($stream);
                } catch (StreamExistsAlready $exception) {
                    $event->setParam('streamExistsAlready', $exception);
                }
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_APPEND_TO,
            function (ActionEvent $event): void {
                $streamName = $event->param('streamName');
                $streamEvents = $event->param('streamEvents');

                try {
                    $this->eventStore->appendTo($streamName, $streamEvents);
                } catch (StreamNotFound $exception) {
                    $event->setParam('streamNotFound', $exception);
                } catch (ConcurrencyException $exception) {
                    $event->setParam('concurrencyException', $exception);
                }
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_LOAD,
            function (ActionEvent $event): void {
                $streamName = $event->param('streamName');
                $fromNumber = $event->param('fromNumber');
                $count = $event->param('count');
                $metadataMatcher = $event->param('metadataMatcher');

                try {
                    $streamEvents = $this->eventStore->load($streamName, $fromNumber, $count, $metadataMatcher);
                    $event->setParam('streamEvents', $streamEvents);
                } catch (StreamNotFound $exception) {
                    $event->setParam('streamNotFound', $exception);
                }
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_LOAD_REVERSE,
            function (ActionEvent $event): void {
                $streamName = $event->param('streamName');
                $fromNumber = $event->param('fromNumber');
                $count = $event->param('count');
                $metadataMatcher = $event->param('metadataMatcher');

                try {
                    $streamEvents = $this->eventStore->loadReverse($streamName, $fromNumber, $count, $metadataMatcher);
                    $event->setParam('streamEvents', $streamEvents);
                } catch (StreamNotFound $exception) {
                    $event->setParam('streamNotFound', $exception);
                }
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_DELETE,
            function (ActionEvent $event): void {
                $streamName = $event->param('streamName');

                try {
                    $this->eventStore->delete($streamName);
                } catch (StreamNotFound $exception) {
                    $event->setParam('streamNotFound', $exception);
                }
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_HAS_STREAM,
            function (ActionEvent $event): void {
                $streamName = $event->param('streamName');

                $event->setParam('result', $this->eventStore->hasStream($streamName));
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_FETCH_STREAM_METADATA,
            function (ActionEvent $event): void {
                $streamName = $event->param('streamName');

                try {
                    $metadata = $this->eventStore->fetchStreamMetadata($streamName);
                    $event->setParam('metadata', $metadata);
                } catch (StreamNotFound $exception) {
                    $event->setParam('streamNotFound', $exception);
                }
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_UPDATE_STREAM_METADATA,
            function (ActionEvent $event): void {
                $streamName = $event->param('streamName');
                $metadata = $event->param('metadata');

                try {
                    $this->eventStore->updateStreamMetadata($streamName, $metadata);
                } catch (StreamNotFound $exception) {
                    $event->setParam('streamNotFound', $exception);
                }
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_FETCH_STREAM_NAMES,
            function (ActionEvent $event): void {
                $filter = $event->param('filter');
                $metadataMatcher = $event->param('metadataMatcher');
                $limit = $event->param('limit');
                $offset = $event->param('offset');

                $streamNames = $this->eventStore->fetchStreamNames($filter, $metadataMatcher, $limit, $offset);
                $event->setParam('streamNames', $streamNames);
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_FETCH_STREAM_NAMES_REGEX,
            function (ActionEvent $event): void {
                $filter = $event->param('filter');
                $metadataMatcher = $event->param('metadataMatcher');
                $limit = $event->param('limit');
                $offset = $event->param('offset');

                $streamNames = $this->eventStore->fetchStreamNamesRegex($filter, $metadataMatcher, $limit, $offset);
                $event->setParam('streamNames', $streamNames);
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_FETCH_CATEGORY_NAMES,
            function (ActionEvent $event): void {
                $filter = $event->param('filter');
                $limit = $event->param('limit');
                $offset = $event->param('offset');

                $streamNames = $this->eventStore->fetchCategoryNames($filter, $limit, $offset);
                $event->setParam('categoryNames', $streamNames);
            }
        );

        $this->actionEventEmitter->attachListener(
            self::EVENT_FETCH_CATEGORY_NAMES_REGEX,
            function (ActionEvent $event): void {
                $filter = $event->param('filter');
                $limit = $event->param('limit');
                $offset = $event->param('offset');

                $streamNames = $this->eventStore->fetchCategoryNamesRegex($filter, $limit, $offset);
                $event->setParam('categoryNames', $streamNames);
            }
        );
    }

    public function updateStreamMetadata(StreamName $streamName, array $newMetadata): void
    {
        $event = $this->actionEventEmitter->getNewActionEvent(
            self::EVENT_UPDATE_STREAM_METADATA,
            $this,
            [
                'streamName' => $streamName,
                'metadata' => $newMetadata,
            ]
        );

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('streamNotFound', false)) {
            throw $exception;
        }
    }

    public function create(Stream $stream): void
    {
        $argv = ['stream' => $stream];

        $event = $this->actionEventEmitter->getNewActionEvent(self::EVENT_CREATE, $this, $argv);

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('streamExistsAlready', false)) {
            throw $exception;
        }
    }

    public function appendTo(StreamName $streamName, Iterator $streamEvents): void
    {
        $argv = ['streamName' => $streamName, 'streamEvents' => $streamEvents];

        $event = $this->actionEventEmitter->getNewActionEvent(self::EVENT_APPEND_TO, $this, $argv);

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('streamNotFound', false)) {
            throw $exception;
        }

        if ($exception = $event->param('concurrencyException', false)) {
            throw $exception;
        }
    }

    public function delete(StreamName $streamName): void
    {
        $event = $this->actionEventEmitter->getNewActionEvent(self::EVENT_DELETE, $this, ['streamName' => $streamName]);

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('streamNotFound', false)) {
            throw $exception;
        }
    }

    public function getInnerEventStore(): EventStore
    {
        return $this->eventStore;
    }

    public function fetchStreamMetadata(StreamName $streamName): array
    {
        $event = $this->actionEventEmitter->getNewActionEvent(
            self::EVENT_FETCH_STREAM_METADATA,
            $this,
            ['streamName' => $streamName]
        );

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('streamNotFound', false)) {
            throw $exception;
        }

        $metadata = $event->param('metadata', false);

        if (! \is_array($metadata)) {
            throw StreamNotFound::with($streamName);
        }

        return $metadata;
    }

    public function hasStream(StreamName $streamName): bool
    {
        $event = $this->actionEventEmitter->getNewActionEvent(
            self::EVENT_HAS_STREAM,
            $this,
            ['streamName' => $streamName]
        );

        $this->actionEventEmitter->dispatch($event);

        return false !== $event->param('result', false);
    }

    public function load(
        StreamName $streamName,
        int $fromNumber = 1,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator {
        Assertion::greaterOrEqualThan($fromNumber, 1);
        Assertion::nullOrGreaterOrEqualThan($count, 1);

        $argv = [
            'streamName' => $streamName,
            'fromNumber' => $fromNumber,
            'count' => $count,
            'metadataMatcher' => $metadataMatcher,
        ];

        $event = $this->actionEventEmitter->getNewActionEvent(self::EVENT_LOAD, $this, $argv);

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('streamNotFound', false)) {
            throw $exception;
        }

        $stream = $event->param('streamEvents', false);

        if (! $stream instanceof Iterator) {
            throw StreamNotFound::with($streamName);
        }

        return $stream;
    }

    public function loadReverse(
        StreamName $streamName,
        int $fromNumber = null,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator {
        Assertion::nullOrGreaterOrEqualThan($fromNumber, 1);
        Assertion::nullOrGreaterOrEqualThan($count, 1);

        $argv = [
            'streamName' => $streamName,
            'fromNumber' => $fromNumber,
            'count' => $count,
            'metadataMatcher' => $metadataMatcher,
        ];

        $event = $this->actionEventEmitter->getNewActionEvent(self::EVENT_LOAD_REVERSE, $this, $argv);

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('streamNotFound', false)) {
            throw $exception;
        }

        $stream = $event->param('streamEvents', false);

        if (! $stream instanceof Iterator) {
            throw StreamNotFound::with($streamName);
        }

        return $stream;
    }

    public function fetchStreamNames(
        ?string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array {
        $event = $this->actionEventEmitter->getNewActionEvent(
            self::EVENT_FETCH_STREAM_NAMES,
            $this,
            [
                'filter' => $filter,
                'metadataMatcher' => $metadataMatcher,
                'limit' => $limit,
                'offset' => $offset,
            ]
        );

        $this->actionEventEmitter->dispatch($event);

        return $event->param('streamNames', []);
    }

    public function fetchStreamNamesRegex(
        string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array {
        $event = $this->actionEventEmitter->getNewActionEvent(
            self::EVENT_FETCH_STREAM_NAMES_REGEX,
            $this,
            [
                'filter' => $filter,
                'metadataMatcher' => $metadataMatcher,
                'limit' => $limit,
                'offset' => $offset,
            ]
        );

        $this->actionEventEmitter->dispatch($event);

        return $event->param('streamNames', []);
    }

    public function fetchCategoryNames(?string $filter, int $limit = 20, int $offset = 0): array
    {
        $event = $this->actionEventEmitter->getNewActionEvent(
            self::EVENT_FETCH_CATEGORY_NAMES,
            $this,
            [
                'filter' => $filter,
                'limit' => $limit,
                'offset' => $offset,
            ]
        );

        $this->actionEventEmitter->dispatch($event);

        return $event->param('categoryNames', []);
    }

    public function fetchCategoryNamesRegex(string $filter, int $limit = 20, int $offset = 0): array
    {
        $event = $this->actionEventEmitter->getNewActionEvent(
            self::EVENT_FETCH_CATEGORY_NAMES_REGEX,
            $this,
            [
                'filter' => $filter,
                'limit' => $limit,
                'offset' => $offset,
            ]
        );

        $this->actionEventEmitter->dispatch($event);

        return $event->param('categoryNames', []);
    }

    public function attach(string $eventName, callable $listener, int $priority = 0): ListenerHandler
    {
        return $this->actionEventEmitter->attachListener($eventName, $listener, $priority);
    }

    public function detach(ListenerHandler $handler): void
    {
        $this->actionEventEmitter->detachListener($handler);
    }
}
