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
use Phayne\EventStore\Exception\TransactionAlreadyStarted;
use Phayne\EventStore\Exception\TransactionNotStarted;
use Throwable;

use function iterator_to_array;

/**
 * Class InMemoryEventStore
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
final class InMemoryEventStore implements TransactionalEventStore
{
    use ProvidesInMemoryEventStore;

    private bool $inTransaction = false;

    public function create(Stream $stream): void
    {
        $streamName = $stream->streamName;
        $streamNameString = $streamName->toString();

        if (
            isset($this->streams[$streamNameString]) ||
            isset($this->cachedStreams[$streamNameString])
        ) {
            throw StreamExistsAlready::with($streamName);
        }

        if ($this->inTransaction) {
            $this->cachedStreams[$streamNameString]['events'] = iterator_to_array($stream->streamEvents);
            $this->cachedStreams[$streamNameString]['metadata'] = $stream->metadata;
        } else {
            $this->streams[$streamNameString]['events'] = iterator_to_array($stream->streamEvents);
            $this->streams[$streamNameString]['metadata'] = $stream->metadata;
        }
    }

    public function appendTo(StreamName $streamName, Iterator $streamEvents): void
    {
        $streamNameString = $streamName->toString();

        if (
            ! isset($this->streams[$streamNameString]) &&
            ! isset($this->cachedStreams[$streamNameString])
        ) {
            throw StreamNotFound::with($streamName);
        }

        if ($this->inTransaction) {
            if (! isset($this->cachedStreams[$streamNameString])) {
                $this->cachedStreams[$streamNameString]['events'] = [];
            }

            foreach ($streamEvents as $streamEvent) {
                $this->cachedStreams[$streamNameString]['events'][] = $streamEvent;
            }
        } else {
            foreach ($streamEvents as $streamEvent) {
                $this->streams[$streamNameString]['events'][] = $streamEvent;
            }
        }
    }

    public function beginTransaction(): void
    {
        if ($this->inTransaction) {
            throw new TransactionAlreadyStarted();
        }

        $this->inTransaction = true;
    }

    public function commit(): void
    {
        if (! $this->inTransaction) {
            throw new TransactionNotStarted();
        }

        foreach ($this->cachedStreams as $streamName => $data) {
            if (isset($data['metadata'])) {
                $this->streams[$streamName] = $data;
            } else {
                foreach ($data['events'] as $streamEvent) {
                    $this->streams[$streamName]['events'][] = $streamEvent;
                }
            }
        }

        $this->cachedStreams = [];
        $this->inTransaction = false;
    }

    public function rollback(): void
    {
        if (! $this->inTransaction) {
            throw new TransactionNotStarted();
        }

        $this->cachedStreams = [];
        $this->inTransaction = false;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function transactional(callable $callable): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callable($this);
            $this->commit();
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }

        return $result ?: true;
    }
}
