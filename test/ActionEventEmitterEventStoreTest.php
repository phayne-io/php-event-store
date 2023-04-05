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
use Phayne\EventStore\ActionEventEmitterEventStore;
use Phayne\EventStore\EventStore;
use Phayne\EventStore\Exception\ConcurrencyException;
use Phayne\EventStore\Exception\StreamExistsAlready;
use Phayne\EventStore\Exception\StreamNotFound;
use Phayne\EventStore\Metadata\MetadataMatcher;
use Phayne\EventStore\Metadata\Operator;
use Phayne\EventStore\StreamName;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\Messaging\Event\PhayneActionEventEmitter;
use PhayneTest\EventStore\Mock\UsernameChanged;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Class ActionEventEmitterEventStoreTest
 *
 * @package PhayneTest\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
class ActionEventEmitterEventStoreTest extends ActionEventEmitterEventStoreTestCase
{
    use EventStoreTestStreamTrait;
    use ProphecyTrait;

    public function testBreaksLoadingAStreamWhenListenerStopsPropagationButDoesNotProvideAStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $this->eventStore->attach(
            'load',
            function (ActionEvent $event): void {
                $event->stopPropagation();
            },
            1000
        );

        $this->eventStore->load(new StreamName('Phayne\Model\User'));
    }

    public function testCannotCreateAStreamWithSameNameTwice(): void
    {
        $this->expectException(StreamExistsAlready::class);

        $stream = $this->getTestStream();

        $this->eventStore->create($stream);
        $this->eventStore->create($stream);
    }

    public function testThrowsExceptionWhenTryingToAppendToNonExistingStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $streamName = new StreamName('test');

        $this->eventStore->appendTo($streamName, new \ArrayIterator());
    }

    public function testThrowsConcurrencyExceptionWhenItHappens(): void
    {
        $this->expectException(ConcurrencyException::class);

        $eventStore = $this->prophesize(EventStore::class);
        $eventEmitter = new PhayneActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS);

        $actionEventStore = new ActionEventEmitterEventStore($eventStore->reveal(), $eventEmitter);

        $streamName = new StreamName('test');
        $events = new ArrayIterator();

        $eventStore->appendTo($streamName, $events)->willThrow(ConcurrencyException::class)->shouldBeCalled();

        $actionEventStore->appendTo($streamName, $events);
    }

    public function testThrowsExceptionWhenTryingToLoadNonExistingStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $streamName = new StreamName('test');

        $this->assertNull($this->eventStore->load($streamName));
    }

    public function testThrowsExceptionWhenTryingToLoadReverseNonExistingStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $streamName = new StreamName('test');

        $this->assertNull($this->eventStore->loadReverse($streamName));
    }

    public function testLoadsEventsInReverseOrder(): void
    {
        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $streamEventVersion2 = UsernameChanged::with(
            ['new_name' => 'John Doe'],
            2
        );

        $streamEventVersion2 = $streamEventVersion2->withAddedMetadata('snapshot', true);

        $streamEventVersion3 = UsernameChanged::with(
            ['new_name' => 'Jane Doe'],
            3
        );

        $streamEventVersion3 = $streamEventVersion3->withAddedMetadata('snapshot', false);

        $streamEventVersion4 = UsernameChanged::with(
            ['new_name' => 'Jane Dole'],
            4
        );

        $streamEventVersion4 = $streamEventVersion4->withAddedMetadata('snapshot', false);

        $this->eventStore->appendTo($stream->streamName, new ArrayIterator([
            $streamEventVersion2,
            $streamEventVersion3,
            $streamEventVersion4,
        ]));

        $loadedEvents = $this->eventStore->loadReverse($stream->streamName, 3, 2);

        $this->assertCount(2, $loadedEvents);

        $loadedEvents->rewind();

        $this->assertFalse($loadedEvents->current()->metadata()['snapshot']);
        $loadedEvents->next();
        $this->assertTrue($loadedEvents->current()->metadata()['snapshot']);
    }

    public function testThrowsExceptionWhenListenerStopsLoadingEventsAndDoesNotProvideLoadedEvents(): void
    {
        $this->expectException(StreamNotFound::class);

        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $this->eventStore->attach(
            'load',
            function (ActionEvent $event): void {
                $event->stopPropagation();
            },
            1000
        );

        $this->eventStore->load($stream->streamName);
    }

    public function testThrowsExceptionWhenListenerStopsLoadingEventsAndDoesNotProvideLoadedEventsReverse(): void
    {
        $this->expectException(StreamNotFound::class);

        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $this->eventStore->attach(
            'loadReverse',
            function (ActionEvent $event): void {
                $event->stopPropagation(true);
            },
            1000
        );

        $this->eventStore->loadReverse($stream->streamName);
    }

    public function testThrowsExceptionWhenTryingToDeleteUnknownStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $streamName = new StreamName('foo');

        $this->eventStore->delete($streamName);
    }

    public function testDoesNotAppendEventsWhenListenerStopsPropagation(): void
    {
        $recordedEvents = [];

        $this->eventStore->attach(
            'create',
            function (ActionEvent $event) use (&$recordedEvents): void {
                foreach ($event->param('recordedEvents', new \ArrayIterator()) as $recordedEvent) {
                    $recordedEvents[] = $recordedEvent;
                }
            },
            -1000
        );

        $this->eventStore->attach(
            'appendTo',
            function (ActionEvent $event) use (&$recordedEvents): void {
                foreach ($event->param('recordedEvents', new \ArrayIterator()) as $recordedEvent) {
                    $recordedEvents[] = $recordedEvent;
                }
            },
            -1000
        );

        $this->eventStore->create($this->getTestStream());

        $this->eventStore->attach(
            'appendTo',
            function (ActionEvent $event): void {
                $event->stopPropagation(true);
            },
            1000
        );

        $secondStreamEvent = UsernameChanged::with(
            ['new_name' => 'John Doe'],
            2
        );

        $this->eventStore->appendTo(new StreamName('Phayne\Model\User'), new ArrayIterator([$secondStreamEvent]));

        $this->assertCount(1, $this->eventStore->load(new StreamName('Phayne\Model\User')));
    }

    public function testUsesStreamProvidedByListenerWhenListenerStopsPropagation(): void
    {
        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $this->eventStore->attach(
            'load',
            function (ActionEvent $event): void {
                $event->setParam('streamEvents', new ArrayIterator());
                $event->stopPropagation(true);
            },
            1000
        );

        $emptyStream = $this->eventStore->load($stream->streamName);

        $this->assertCount(0, $emptyStream);
    }

    public function testReturnsListenerEventsWhenListenerStopsLoadingEventsAndProvideLoadedEvents(): void
    {
        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $streamEventWithMetadata = UsernameChanged::with(
            ['new_name' => 'John Doe'],
            2
        );

        $streamEventWithMetadata = $streamEventWithMetadata->withAddedMetadata('snapshot', true);

        $this->eventStore->appendTo($stream->streamName, new ArrayIterator([$streamEventWithMetadata]));

        $this->eventStore->attach(
            'load',
            function (ActionEvent $event): void {
                $streamEventWithMetadataButOtherUuid = UsernameChanged::with(
                    ['new_name' => 'John Doe'],
                    2
                );

                $streamEventWithMetadataButOtherUuid =
                    $streamEventWithMetadataButOtherUuid->withAddedMetadata('snapshot', true);

                $event->setParam('streamEvents', new ArrayIterator([$streamEventWithMetadataButOtherUuid]));
                $event->stopPropagation(true);
            },
            1000
        );

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('snapshot', Operator::EQUALS, true);

        $loadedEvents = $this->eventStore->load($stream->streamName, 1, null, $metadataMatcher);

        $this->assertCount(1, $loadedEvents);

        $loadedEvents->rewind();

        $this->assertNotEquals(
            $streamEventWithMetadata->uuid()->toString(),
            $loadedEvents->current()->uuid()->toString()
        );
    }

    public function testAppendsEventsToStreamAndRecordsThem(): void
    {
        $recordedEvents = [];

        $this->eventStore->attach(
            'create',
            function (ActionEvent $event) use (&$recordedEvents): void {
                $stream = $event->param('stream');

                foreach ($stream->streamEvents as $recordedEvent) {
                    $recordedEvents[] = $recordedEvent;
                }
            },
            -1000
        );

        $this->eventStore->attach(
            'appendTo',
            function (ActionEvent $event) use (&$recordedEvents): void {
                foreach ($event->param('streamEvents', new \ArrayIterator()) as $recordedEvent) {
                    $recordedEvents[] = $recordedEvent;
                }
            },
            -1000
        );

        $this->eventStore->create($this->getTestStream());

        $secondStreamEvent = UsernameChanged::with(
            ['new_name' => 'John Doe'],
            2
        );

        $this->eventStore->appendTo(new StreamName('Phayne\Model\User'), new ArrayIterator([$secondStreamEvent]));

        $this->assertCount(2, $recordedEvents);
    }

    public function testCreatesANewStreamAndRecordsTheStreamEventsAndDeletes(): void
    {
        $recordedEvents = [];

        $streamName = new StreamName('Phayne\Model\User');

        $this->eventStore->attach(
            'create',
            function (ActionEvent $event) use (&$recordedEvents): void {
                $stream = $event->param('stream');

                foreach ($stream->streamEvents as $recordedEvent) {
                    $recordedEvents[] = $recordedEvent;
                }
            },
            -1000
        );

        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $streamEvents = $this->eventStore->load($streamName);

        $this->assertCount(1, $streamEvents);

        $this->assertCount(1, $recordedEvents);

        $this->assertEquals(
            [
                'foo' => 'bar',
            ],
            $this->eventStore->fetchStreamMetadata($streamName)
        );

        $this->assertTrue($this->eventStore->hasStream($streamName));

        $this->eventStore->delete($streamName);

        $this->assertFalse($this->eventStore->hasStream($streamName));
    }

    public function testThrowsExceptionWhenAskedForUnknownStreamMetadata(): void
    {
        $this->expectException(StreamNotFound::class);

        $streamName = new StreamName('unknown');

        $this->eventStore->fetchStreamMetadata($streamName);
    }

    public function testThrowsExceptionWhenAskedForStreamMetadataAndEventGetsStopped(): void
    {
        $this->expectException(StreamNotFound::class);

        $streamName = new StreamName('test');

        $this->eventStore->attach(
            ActionEventEmitterEventStore::EVENT_FETCH_STREAM_METADATA,
            function (ActionEvent $event): void {
                $event->stopPropagation();
            },
            1000
        );

        $this->eventStore->fetchStreamMetadata($streamName);
    }

    public function testUpdatesStreamMetadata(): void
    {
        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $this->eventStore->updateStreamMetadata($stream->streamName, ['new' => 'values']);

        $this->assertEquals(
            [
                'new' => 'values',
            ],
            $this->eventStore->fetchStreamMetadata($stream->streamName)
        );
    }

    public function testThrowsStreamNotFoundExceptionWhenTryingToUpdateMetadataOnUnknownStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $this->eventStore->updateStreamMetadata(new StreamName('unknown'), []);
    }

    public function testFetchesStreamNames(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->fetchStreamNames('foo', null, 10, 20)->shouldBeCalled();

        $wrapper = new ActionEventEmitterEventStore($eventStore->reveal(), new PhayneActionEventEmitter());

        $wrapper->fetchStreamNames('foo', null, 10, 20);
    }

    public function testFetchesStreamNamesRegex(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->fetchStreamNamesRegex('foo', null, 10, 20)->shouldBeCalled();

        $wrapper = new ActionEventEmitterEventStore($eventStore->reveal(), new PhayneActionEventEmitter());

        $wrapper->fetchStreamNamesRegex('foo', null, 10, 20);
    }

    public function testFetchesCategoryNames(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->fetchCategoryNames('foo', 10, 20)->shouldBeCalled();

        $wrapper = new ActionEventEmitterEventStore($eventStore->reveal(), new PhayneActionEventEmitter());

        $wrapper->fetchCategoryNames('foo', 10, 20);
    }

    public function testFetchesCategoryNamesRegex(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore->fetchCategoryNamesRegex('foo', 10, 20)->shouldBeCalled();

        $wrapper = new ActionEventEmitterEventStore($eventStore->reveal(), new PhayneActionEventEmitter());

        $wrapper->fetchCategoryNamesRegex('foo', 10, 20);
    }

    public function testReturnsInnerEventStore(): void
    {
        $eventStore = $this->prophesize(EventStore::class);
        $eventStore = $eventStore->reveal();

        $wrapper = new ActionEventEmitterEventStore($eventStore, new PhayneActionEventEmitter());

        $this->assertSame($eventStore, $wrapper->getInnerEventStore());
    }
}
