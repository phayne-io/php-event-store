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

namespace PhayneTest\EventStore;

use ArrayIterator;
use Phayne\EventStore\Exception\TransactionAlreadyStarted;
use Phayne\EventStore\Exception\TransactionNotStarted;
use Phayne\EventStore\InMemoryEventStore;
use Phayne\EventStore\Stream;
use Phayne\EventStore\StreamName;
use Phayne\EventStore\TransactionalActionEventEmitterEventStore;
use Phayne\Messaging\Event\PhayneActionEventEmitter;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Class TransactionalActionEventEmitterEventStoreTest
 *
 * @package PhayneTest\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
class TransactionalActionEventEmitterEventStoreTest extends TestCase
{
    use EventStoreTestStreamTrait;
    use ProphecyTrait;

    protected TransactionalActionEventEmitterEventStore $eventStore;

    protected function setUp(): void
    {
        $eventEmitter = new PhayneActionEventEmitter(TransactionalActionEventEmitterEventStore::ALL_EVENTS);
        $this->eventStore = new TransactionalActionEventEmitterEventStore(new InMemoryEventStore(), $eventEmitter);
    }

    public function testWorksTransactional(): void
    {
        $streamName = new StreamName('test');
        $stream = new Stream($streamName, new ArrayIterator(), ['foo' => 'bar']);

        $this->eventStore->beginTransaction();

        $this->eventStore->create($stream);

        $this->assertFalse($this->eventStore->hasStream($streamName));

        $this->eventStore->commit();

        $this->assertTrue($this->eventStore->hasStream($streamName));
    }

    public function testRollsBackTransaction(): void
    {
        $streamName = new StreamName('test');
        $stream = new Stream($streamName, new ArrayIterator(), ['foo' => 'bar']);

        $this->eventStore->beginTransaction();

        $this->assertTrue($this->eventStore->inTransaction());

        $this->eventStore->create($stream);

        $this->assertFalse($this->eventStore->hasStream($streamName));

        $this->eventStore->rollback();

        $this->assertFalse($this->eventStore->hasStream($streamName));
    }

    public function testThrowsExceptionWhenNoTransactionStartedOnCommit(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->eventStore->commit();
    }

    public function testThrowsExceptionWhenNoTransactionStartedOnRollback(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->eventStore->rollback();
    }

    public function testThrowsExceptionWhenTransactionAlreadyStarted(): void
    {
        $this->expectException(TransactionAlreadyStarted::class);

        $this->eventStore->beginTransaction();
        $this->eventStore->beginTransaction();
    }

    public function testWrapsUpCodeInTransactionProperly(): void
    {
        $transactionResult = $this->eventStore->transactional(function (): string {
            $this->eventStore->create($this->getTestStream());

            return 'Result';
        });

        $this->assertSame('Result', $transactionResult);
    }
}
