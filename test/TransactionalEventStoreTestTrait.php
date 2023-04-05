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
use Exception;
use Phayne\EventStore\EventStore;
use Phayne\EventStore\Exception\StreamNotFound;
use Phayne\EventStore\Exception\TransactionAlreadyStarted;
use Phayne\EventStore\Exception\TransactionNotStarted;
use Phayne\EventStore\Stream;
use Phayne\EventStore\StreamName;
use Phayne\EventStore\TransactionalEventStore;
use PhayneTest\EventStore\Mock\UsernameChanged;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Trait TransactionalEventStoreTestTrait
 *
 * @package PhayneTest\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
trait TransactionalEventStoreTestTrait
{
    use EventStoreTestStreamTrait;
    use ProphecyTrait;

    protected $eventStore;

    public function testWorksTransactional(): void
    {
        $streamName = new StreamName('Phayne\Model\User');
        $stream = new Stream($streamName, new ArrayIterator(), ['foo' => 'bar']);

        $this->eventStore->beginTransaction();

        $this->eventStore->create($stream);

        $this->eventStore->commit();

        $this->assertTrue($this->eventStore->hasStream($streamName));
    }

    public function testWrapsUpCodeInTransactionProperly(): void
    {
        $transactionResult = $this->eventStore->transactional(function (EventStore $eventStore): string {
            $this->eventStore->create($this->getTestStream());
            $this->assertSame($this->eventStore, $eventStore);

            return 'Result';
        });

        $this->assertSame('Result', $transactionResult);

        $secondStreamEvent = UsernameChanged::with(
            ['new_name' => 'John Doe'],
            2
        );

        $transactionResult = $this->eventStore->transactional(
            function (EventStore $eventStore) use ($secondStreamEvent): string {
                $this->eventStore->appendTo(
                    new StreamName('Phayne\Model\User'),
                    new ArrayIterator([$secondStreamEvent])
                );
                $this->assertSame($this->eventStore, $eventStore);

                return 'Second Result';
            }
        );

        $this->assertSame('Second Result', $transactionResult);

        $streamEvents = $this->eventStore->load(new StreamName('Phayne\Model\User'), 1);

        $this->assertCount(2, $streamEvents);
    }

    public function testRollsBackTransaction(): void
    {
        $streamName = new StreamName('test');

        $stream = new Stream($streamName, new ArrayIterator(), ['foo' => 'bar']);

        $this->eventStore->beginTransaction();

        $this->assertTrue($this->eventStore->inTransaction());

        $this->eventStore->create($stream);

        $this->eventStore->rollback();

        $this->assertFalse($this->eventStore->hasStream($streamName));
    }

    public function testShouldRollbackAndThrowExceptionInCaseOfTransactionFail(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Transaction failed');

        $eventStore = $this->eventStore;

        $this->eventStore->transactional(function (EventStore $es) use ($eventStore): void {
            $this->assertSame($es, $eventStore);
            throw new Exception('Transaction failed');
        });
    }

    public function testShouldReturnTrueByDefaultIfTransactionIsUsed(): void
    {
        $transactionResult = $this->eventStore->transactional(function (EventStore $eventStore): void {
            $this->eventStore->create($this->getTestStream());
            $this->assertSame($this->eventStore, $eventStore);
        });
        $this->assertTrue($transactionResult);
    }

    public function testThrowsExceptionWhenTransactionAlreadyStarted(): void
    {
        $this->expectException(TransactionAlreadyStarted::class);

        $this->eventStore->beginTransaction();
        $this->eventStore->beginTransaction();
    }

    public function testCanCommitEmptyTransaction(): void
    {
        $this->eventStore->beginTransaction();
        $this->eventStore->commit();

        $this->assertFalse($this->eventStore->inTransaction());
    }

    public function testCannotCommitTwice(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->eventStore->beginTransaction();
        $this->eventStore->commit();
        $this->eventStore->commit();
    }

    public function testCanRollbackEmptyTransaction(): void
    {
        $this->assertFalse($this->eventStore->inTransaction());
        $this->eventStore->beginTransaction();
        $this->assertTrue($this->eventStore->inTransaction());
        $this->eventStore->rollback();
        $this->assertFalse($this->eventStore->inTransaction());
    }

    public function testCannotRollbackTwice(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->eventStore->beginTransaction();
        $this->eventStore->rollback();
        $this->eventStore->rollback();
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

    public function testLoadsAndSavesWithinOneTransaction(): void
    {
        $testStream = $this->getTestStream();

        $this->eventStore->beginTransaction();

        $streamNotFound = false;

        try {
            $this->eventStore->load($testStream->streamName);
        } catch (StreamNotFound) {
            $streamNotFound = true;
        }

        $this->assertTrue($streamNotFound);

        $this->eventStore->create($testStream);

        $this->eventStore->commit();
    }
}
