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
use Phayne\EventStore\EventStore;
use Phayne\EventStore\ReadOnlyEventStoreWrapper;
use Phayne\EventStore\StreamName;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Class ReadOnlyEventStoreWrapperTest
 *
 * @package PhayneTest\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
class ReadOnlyEventStoreWrapperTest extends TestCase
{
    use ProphecyTrait;

    public function testDelegatesMethodCallsToInternalEventStore(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->fetchStreamMetadata(Argument::type(StreamName::class))->willReturn([])->shouldBeCalled();
        $eventStore->hasStream(Argument::type(StreamName::class))->willReturn(true)->shouldBeCalled();
        $eventStore->load(Argument::type(StreamName::class), 0, 10, null)
            ->willReturn(new ArrayIterator())->shouldBeCalled();
        $eventStore->loadReverse(Argument::type(StreamName::class), 0, 10, null)
            ->willReturn(new ArrayIterator())->shouldBeCalled();
        $eventStore->fetchStreamNames('foo', null, 0, 10)
            ->willReturn(['foobar', 'foobaz'])->shouldBeCalled();
        $eventStore->fetchStreamNamesRegex('^foo', null, 0, 10)
            ->willReturn(['foobar', 'foobaz'])->shouldBeCalled();
        $eventStore->fetchCategoryNames('foo', 0, 10)
            ->willReturn(['foo-1', 'foo-2'])->shouldBeCalled();
        $eventStore->fetchCategoryNamesRegex('^foo', 0, 10)
            ->willReturn(['foo-1', 'foo-2'])->shouldBeCalled();

        $testStream = new StreamName('foo');

        $readOnlyEventStore = new ReadOnlyEventStoreWrapper($eventStore->reveal());

        $this->assertEmpty($readOnlyEventStore->fetchStreamMetadata($testStream));
        $this->assertTrue($readOnlyEventStore->hasStream($testStream));
        $this->assertInstanceOf(\ArrayIterator::class, $readOnlyEventStore->load($testStream, 0, 10));
        $this->assertInstanceOf(\ArrayIterator::class, $readOnlyEventStore->loadReverse($testStream, 0, 10));
        $this->assertSame(['foobar', 'foobaz'], $readOnlyEventStore->fetchStreamNames('foo', null, 0, 10));
        $this->assertSame(['foobar', 'foobaz'], $readOnlyEventStore->fetchStreamNamesRegex('^foo', null, 0, 10));
        $this->assertSame(['foo-1', 'foo-2'], $readOnlyEventStore->fetchCategoryNames('foo', 0, 10));
        $this->assertSame(['foo-1', 'foo-2'], $readOnlyEventStore->fetchCategoryNamesRegex('^foo', 0, 10));
    }
}
