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

use Phayne\EventStore\Exception\TransactionAlreadyStarted;
use Phayne\EventStore\Exception\TransactionNotStarted;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\Messaging\Event\ActionEventEmitter;

/**
 * Class TransactionalActionEventEmitterEventStore
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
class TransactionalActionEventEmitterEventStore extends ActionEventEmitterEventStore implements TransactionalEventStore
{
    public const EVENT_BEGIN_TRANSACTION = 'beginTransaction';
    public const EVENT_COMMIT = 'commit';
    public const EVENT_ROLLBACK = 'rollback';

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
        self::EVENT_BEGIN_TRANSACTION,
        self::EVENT_COMMIT,
        self::EVENT_ROLLBACK,
    ];

    public function __construct(TransactionalEventStore $eventStore, ActionEventEmitter $actionEventEmitter)
    {
        parent::__construct($eventStore, $actionEventEmitter);

        $actionEventEmitter->attachListener(self::EVENT_BEGIN_TRANSACTION, function (ActionEvent $event): void {
            try {
                $this->eventStore->beginTransaction();
            } catch (TransactionAlreadyStarted $exception) {
                $event->setParam('transactionAlreadyStarted', $exception);
            }
        });

        $actionEventEmitter->attachListener(self::EVENT_COMMIT, function (ActionEvent $event): void {
            try {
                $this->eventStore->commit();
            } catch (TransactionNotStarted $exception) {
                $event->setParam('transactionNotStarted', $exception);
            }
        });

        $actionEventEmitter->attachListener(self::EVENT_ROLLBACK, function (ActionEvent $event): void {
            try {
                $this->eventStore->rollback();
            } catch (TransactionNotStarted $exception) {
                $event->setParam('transactionNotStarted', $exception);
            }
        });
    }

    public function beginTransaction(): void
    {
        $event = $this->actionEventEmitter->getNewActionEvent(self::EVENT_BEGIN_TRANSACTION, $this);

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('transactionAlreadyStarted', false)) {
            throw $exception;
        }
    }

    public function commit(): void
    {
        $event = $this->actionEventEmitter->getNewActionEvent(self::EVENT_COMMIT, $this);

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('transactionNotStarted', false)) {
            throw $exception;
        }
    }

    public function rollback(): void
    {
        $event = $this->actionEventEmitter->getNewActionEvent(self::EVENT_ROLLBACK, $this);

        $this->actionEventEmitter->dispatch($event);

        if ($exception = $event->param('transactionNotStarted', false)) {
            throw $exception;
        }
    }

    public function inTransaction(): bool
    {
        return $this->eventStore->inTransaction();
    }

    public function transactional(callable $callable): mixed
    {
        return $this->eventStore->transactional($callable);
    }
}
