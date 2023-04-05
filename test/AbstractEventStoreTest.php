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
use EmptyIterator;
use Phayne\EventStore\EventStore;
use Phayne\EventStore\Exception\StreamExistsAlready;
use Phayne\EventStore\Exception\StreamNotFound;
use Phayne\EventStore\Metadata\FieldType;
use Phayne\EventStore\Metadata\MetadataMatcher;
use Phayne\EventStore\Metadata\Operator;
use Phayne\EventStore\Stream;
use Phayne\EventStore\StreamIterator\StreamIterator;
use Phayne\EventStore\StreamName;
use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\UnexpectedValueException;
use PhayneTest\EventStore\Mock\TestDomainEvent;
use PhayneTest\EventStore\Mock\UserCreated;
use PhayneTest\EventStore\Mock\UsernameChanged;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;

use function uniqid;

/**
 * Class AbstractEventStoreTest
 *
 * @package PhayneTest\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
abstract class AbstractEventStoreTest extends TestCase
{
    use EventStoreTestStreamTrait;
    use ProphecyTrait;

    protected $eventStore;

    public function testCreatesANewStreamAndRecordsTheStreamEventsAndDeletes(): void
    {
        $streamName = new StreamName('Phayne\Model\User');

        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $streamEvents = $this->eventStore->load($streamName);

        $this->assertCount(1, $streamEvents);

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

    public function testAppendsEventsToStreamAndRecordsThem(): void
    {
        $this->eventStore->create($this->getTestStream());

        $secondStreamEvent = UsernameChanged::with(
            ['new_name' => 'John Doe'],
            2
        );

        $this->eventStore->appendTo(new StreamName('Phayne\Model\User'), new ArrayIterator([$secondStreamEvent]));

        $this->assertCount(2, $this->eventStore->load(new StreamName('Phayne\Model\User')));
    }

    public function testCannotCreateAStreamWithSameNameTwice(): void
    {
        $this->expectException(StreamExistsAlready::class);

        $stream = $this->getTestStream();

        $this->eventStore->create($stream);
        $this->eventStore->create($stream);
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

    public function testLoadsEventsFromNumber(): void
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

        $this->eventStore->appendTo(
            $stream->streamName,
            new ArrayIterator([$streamEventVersion2, $streamEventVersion3])
        );

        $loadedEvents = $this->eventStore->load($stream->streamName, 2);

        $this->assertCount(2, $loadedEvents);

        $loadedEvents->rewind();

        $this->assertTrue($loadedEvents->current()->metadata()['snapshot']);
        $loadedEvents->next();
        $this->assertFalse($loadedEvents->current()->metadata()['snapshot']);

        $streamEvents = $this->eventStore->load($stream->streamName, 2);

        $this->assertCount(2, $streamEvents);

        $streamEvents->rewind();

        $this->assertTrue($streamEvents->current()->metadata()['snapshot']);
        $streamEvents->next();
        $this->assertFalse($streamEvents->current()->metadata()['snapshot']);
    }

    public function testLoadsEventsReverseFromNumber(): void
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

        $this->eventStore->appendTo(
            $stream->streamName,
            new ArrayIterator([$streamEventVersion2, $streamEventVersion3])
        );

        $loadedEvents = $this->eventStore->loadReverse($stream->streamName, null, 2);

        $this->assertCount(2, $loadedEvents);

        $loadedEvents->rewind();

        $this->assertFalse($loadedEvents->current()->metadata()['snapshot']);
        $loadedEvents->next();
        $this->assertTrue($loadedEvents->current()->metadata()['snapshot']);

        $streamEvents = $this->eventStore->loadReverse($stream->streamName, null, 2);

        $this->assertCount(2, $streamEvents);

        $streamEvents->rewind();

        $this->assertFalse($streamEvents->current()->metadata()['snapshot']);
        $streamEvents->next();
        $this->assertTrue($streamEvents->current()->metadata()['snapshot']);
    }

    public function testLoadsEventsFromNumberWithCount(): void
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

        $loadedEvents = $this->eventStore->load($stream->streamName, 2, 2);

        $this->assertCount(2, $loadedEvents);

        $loadedEvents->rewind();

        $this->assertTrue($loadedEvents->current()->metadata()['snapshot']);
        $loadedEvents->next();
        $this->assertFalse($loadedEvents->current()->metadata()['snapshot']);

        $loadedEvents = $this->eventStore->load($stream->streamName, 2, 2);

        $this->assertCount(2, $loadedEvents);

        $loadedEvents->rewind();

        $this->assertTrue($loadedEvents->current()->metadata()['snapshot']);
        $loadedEvents->next();
        $this->assertFalse($loadedEvents->current()->metadata()['snapshot']);
    }

    public function testLoadsEventsReverseFromNumberWithCount(): void
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

        $loadedEvents = $this->eventStore->loadReverse($stream->streamName, 3, 2);

        $this->assertCount(2, $loadedEvents);

        $loadedEvents->rewind();

        $this->assertFalse($loadedEvents->current()->metadata()['snapshot']);
        $loadedEvents->next();
        $this->assertTrue($loadedEvents->current()->metadata()['snapshot']);
    }

    /**
     * @dataProvider getMatchingMetadata
     */
    public function testLoadsEventsByMatchingMetadata(array $metadata): void
    {
        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $streamEventWithMetadata = TestDomainEvent::with(
            ['name' => 'John', 'email' => 'john@doe.com'],
            2
        );

        foreach ($metadata as $field => $value) {
            $streamEventWithMetadata = $streamEventWithMetadata->withAddedMetadata($field, $value);
        }

        $this->eventStore->appendTo($stream->streamName, new ArrayIterator([$streamEventWithMetadata]));

        $metadataMatcher = new MetadataMatcher();

        foreach ($metadata as $field => $value) {
            $metadataMatcher = $metadataMatcher->withMetadataMatch($field, Operator::EQUALS, $value);
        }

        $streamEvents = $this->eventStore->load($stream->streamName, 1, null, $metadataMatcher);

        $this->assertCount(1, $streamEvents);

        $streamEvents->rewind();

        $currentMetadata = $streamEvents->current()->metadata();

        foreach ($metadata as $field => $value) {
            $this->assertEquals($value, $currentMetadata[$field]);
        }
    }

    /**
     * @dataProvider getMatchingMetadata
     */
    public function testLoadsEventsReverseByMatchingMetadata(array $metadata): void
    {
        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $streamEventWithMetadata = TestDomainEvent::with(
            ['name' => 'John', 'email' => 'john@doe.com'],
            2
        );

        foreach ($metadata as $field => $value) {
            $streamEventWithMetadata = $streamEventWithMetadata->withAddedMetadata($field, $value);
        }

        $this->eventStore->appendTo($stream->streamName, new ArrayIterator([$streamEventWithMetadata]));

        $metadataMatcher = new MetadataMatcher();

        foreach ($metadata as $field => $value) {
            $metadataMatcher = $metadataMatcher->withMetadataMatch($field, Operator::EQUALS, $value);
        }

        $streamEvents = $this->eventStore->loadReverse($stream->streamName, 2, null, $metadataMatcher);

        $this->assertCount(1, $streamEvents);

        $streamEvents->rewind();

        $currentMetadata = $streamEvents->current()->metadata();

        foreach ($metadata as $field => $value) {
            $this->assertEquals($value, $currentMetadata[$field]);
        }
    }

    public function testReturnsOnlyMatchedMetadata(): void
    {
        $event = UserCreated::with(['name' => 'John'], 1);
        $event = $event->withAddedMetadata('foo', 'bar');
        $event = $event->withAddedMetadata('int', 5);
        $event = $event->withAddedMetadata('int2', 4);
        $event = $event->withAddedMetadata('int3', 6);
        $event = $event->withAddedMetadata('int4', 7);

        $uuid = $event->uuid()->toString();
        $before = $event->createdAt()->modify('-5 secs')->format('Y-m-d\TH:i:s.u');
        $later = $event->createdAt()->modify('+5 secs')->format('Y-m-d\TH:i:s.u');

        $stream = new Stream(new StreamName('Phayne\Model\User'), new ArrayIterator([$event]));

        $this->eventStore->create($stream);

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::EQUALS, 'bar');
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::NOT_EQUALS, 'baz');
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::GREATER_THAN, 4);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int2', Operator::GREATER_THAN_EQUALS, 4);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::IN, [4, 5, 6]);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int3', Operator::LOWER_THAN, 7);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int4', Operator::LOWER_THAN_EQUALS, 7);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::NOT_IN, [4, 6]);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::REGEX, '^b[a]r$');

        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::EQUALS,
            $uuid,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::NOT_EQUALS,
            'baz',
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::GREATER_THAN,
            $before,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::GREATER_THAN_EQUALS,
            $before,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::IN,
            [$uuid, 2, 3],
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::LOWER_THAN,
            $later,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::LOWER_THAN_EQUALS,
            $later,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::NOT_IN,
            [$before, $later],
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'messageName',
            Operator::REGEX,
            '.+UserCreated$',
            FieldType::MESSAGE_PROPERTY
        );

        $streamEvents = $this->eventStore->load($stream->streamName, 1, null, $metadataMatcher);

        $this->assertCount(1, $streamEvents);
    }

    public function testReturnsOnlyMatchedMetadata2(): void
    {
        $event = UserCreated::with(['name' => 'John'], 1);
        $event = $event->withAddedMetadata('foo', 'bar');
        $event = $event->withAddedMetadata('int', 5);
        $event = $event->withAddedMetadata('int2', 4);
        $event = $event->withAddedMetadata('int3', 6);
        $event = $event->withAddedMetadata('int4', 7);

        $streamName = new StreamName('Phayne\Model\User');

        $stream = new Stream($streamName, new ArrayIterator($event), []);

        $this->eventStore->create($stream);

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::EQUALS, 'baz');

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::NOT_EQUALS, 'bar');

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::GREATER_THAN, 9);

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int2', Operator::GREATER_THAN_EQUALS, 10);

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int3', Operator::LOWER_THAN, 1);

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int4', Operator::LOWER_THAN_EQUALS, 1);

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());
    }

    public function testReturnsOnlyMatchedMetadataReverse(): void
    {
        $event = UserCreated::with(['name' => 'John'], 1);
        $event = $event->withAddedMetadata('foo', 'bar');
        $event = $event->withAddedMetadata('int', 5);
        $event = $event->withAddedMetadata('int2', 4);
        $event = $event->withAddedMetadata('int3', 6);
        $event = $event->withAddedMetadata('int4', 7);

        $uuid = $event->uuid()->toString();
        $before = $event->createdAt()->modify('-5 secs')->format('Y-m-d\TH:i:s.u');
        $later = $event->createdAt()->modify('+5 secs')->format('Y-m-d\TH:i:s.u');

        $streamName = new StreamName('Phayne\Model\User');

        $stream = new Stream($streamName, new ArrayIterator([$event]));

        $this->eventStore->create($stream);

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::EQUALS, 'bar');
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::NOT_EQUALS, 'baz');
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::GREATER_THAN, 4);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int2', Operator::GREATER_THAN_EQUALS, 4);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::IN, [4, 5, 6]);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int3', Operator::LOWER_THAN, 7);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int4', Operator::LOWER_THAN_EQUALS, 7);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::NOT_IN, [4, 6]);
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::REGEX, '^b[a]r$');

        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::EQUALS,
            $uuid,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::NOT_EQUALS,
            'baz',
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::GREATER_THAN,
            $before,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::GREATER_THAN_EQUALS,
            $before,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::IN,
            [$uuid, 2, 3],
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::LOWER_THAN,
            $later,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::LOWER_THAN_EQUALS,
            $later,
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::NOT_IN,
            [$before, $later],
            FieldType::MESSAGE_PROPERTY
        );
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'messageName',
            Operator::REGEX,
            '.+UserCreated$',
            FieldType::MESSAGE_PROPERTY
        );

        $streamEvents = $this->eventStore->loadReverse($stream->streamName, 1, null, $metadataMatcher);

        $this->assertCount(1, $streamEvents);
    }

    public function testReturnsOnlyMatchedMetadata2Reverse(): void
    {
        $event = UserCreated::with(['name' => 'John'], 1);
        $event = $event->withAddedMetadata('foo', 'bar');
        $event = $event->withAddedMetadata('int', 5);
        $event = $event->withAddedMetadata('int2', 4);
        $event = $event->withAddedMetadata('int3', 6);
        $event = $event->withAddedMetadata('int4', 7);

        $streamName = new StreamName('Phayne\Model\User');

        $stream = new Stream($streamName, new ArrayIterator([$event]), []);

        $this->eventStore->create($stream);

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::EQUALS, 'baz');

        $result = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::NOT_EQUALS, 'bar');

        $result = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::GREATER_THAN, 9);

        $result = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int2', Operator::GREATER_THAN_EQUALS, 10);

        $result = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int3', Operator::LOWER_THAN, 1);

        $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int4', Operator::LOWER_THAN_EQUALS, 1);

        $result = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::IN, [4, 5, 6]);

        $streamEvents = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertCount(1, $streamEvents);

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::IN, [4, 6]);

        $streamEvents = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($streamEvents->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('int', Operator::NOT_IN, [4, 5, 6]);

        $streamEvents = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($streamEvents->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::REGEX, '^b[a]r$');

        $streamEvents = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertCount(1, $streamEvents);

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::REGEX, '^b[a]z$');

        $streamEvents = $this->eventStore->loadReverse($streamName, null, null, $metadataMatcher);

        $this->assertFalse($streamEvents->valid());

        $this->expectException(InvalidArgumentException::class);

        $value = new stdClass();
        $value->foo = 'bar';

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher->withMetadataMatch('meta', Operator::EQUALS, $value);
    }

    public function testReturnsOnlyMatchedMessageProperty(): void
    {
        $event = UserCreated::with(['name' => 'John'], 1);
        $event = $event->withAddedMetadata('foo', 'bar');
        $event = $event->withAddedMetadata('int', 5);
        $event = $event->withAddedMetadata('int2', 4);
        $event = $event->withAddedMetadata('int3', 6);
        $event = $event->withAddedMetadata('int4', 7);

        $uuid = $event->uuid()->toString();
        $createdAt = $event->createdAt()->format('Y-m-d\TH:i:s.u');
        $messageName = $event->messageName();

        $before = $event->createdAt()->modify('-5 secs')->format('Y-m-d\TH:i:s.u');
        $later = $event->createdAt()->modify('+5 secs')->format('Y-m-d\TH:i:s.u');

        $streamName = new StreamName('Phayne\Model\User');

        $stream = new Stream($streamName, new ArrayIterator([$event]));

        $this->eventStore->create($stream);

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::EQUALS,
            'baz',
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::NOT_EQUALS,
            $uuid,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::GREATER_THAN,
            $later,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'created_at',
            Operator::GREATER_THAN_EQUALS,
            $later,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'created_at',
            Operator::IN,
            [$before, $later],
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::LOWER_THAN,
            $before,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::LOWER_THAN_EQUALS,
            $before,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'created_at',
            Operator::NOT_IN,
            [$before, $createdAt, $later],
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'message_name',
            Operator::REGEX,
            'foobar',
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'event_name',
            Operator::REGEX,
            'foobar',
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->load($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());
    }

    public function testReturnsOnlyMatchedMessagePropertyReverse(): void
    {
        $event = UserCreated::with(['name' => 'John'], 1);
        $event = $event->withAddedMetadata('foo', 'bar');
        $event = $event->withAddedMetadata('int', 5);
        $event = $event->withAddedMetadata('int2', 4);
        $event = $event->withAddedMetadata('int3', 6);
        $event = $event->withAddedMetadata('int4', 7);

        $uuid = $event->uuid()->toString();
        $createdAt = $event->createdAt()->format('Y-m-d\TH:i:s.u');
        $before = $event->createdAt()->modify('-5 secs')->format('Y-m-d\TH:i:s.u');
        $later = $event->createdAt()->modify('+5 secs')->format('Y-m-d\TH:i:s.u');

        $streamName = new StreamName('Phayne\Model\User');
        $stream = new Stream($streamName, new ArrayIterator([$event]));

        $this->eventStore->create($stream);

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::EQUALS,
            'baz',
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'uuid',
            Operator::NOT_EQUALS,
            $uuid,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::GREATER_THAN,
            $later,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'created_at',
            Operator::GREATER_THAN_EQUALS,
            $later,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'created_at',
            Operator::IN,
            [$before, $later],
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::LOWER_THAN,
            $before,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'createdAt',
            Operator::LOWER_THAN_EQUALS,
            $before,
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'created_at',
            Operator::NOT_IN,
            [$before, $createdAt, $later],
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'message_name',
            Operator::REGEX,
            'foobar',
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch(
            'event_name',
            Operator::REGEX,
            'foobar',
            FieldType::MESSAGE_PROPERTY
        );

        $result = $this->eventStore->loadReverse($streamName, 1, null, $metadataMatcher);

        $this->assertFalse($result->valid());
    }

    public function testLoadsEmptyStream(): void
    {
        $streamName = new StreamName('Phayne\Model\User');

        $this->eventStore->create(new Stream($streamName, new ArrayIterator()));

        $it = $this->eventStore->load($streamName);

        $this->assertFalse($it->valid());
    }

    public function testLoadsReverseEmptyStream(): void
    {
        $streamName = new StreamName('Phayne\Model\User');

        $this->eventStore->create(new Stream($streamName, new ArrayIterator()));

        $it = $this->eventStore->loadReverse($streamName);

        $this->assertFalse($it->valid());
    }

    public function testThrowsStreamNotFoundExceptionIfItLoadsNothing(): void
    {
        $this->expectException(StreamNotFound::class);

        $stream = $this->getTestStream();

        $this->eventStore->load($stream->streamName);
    }

    public function testThrowsStreamNotFoundExceptionIfItLoadsNothingReverse(): void
    {
        $this->expectException(StreamNotFound::class);

        $stream = $this->getTestStream();

        $this->eventStore->loadReverse($stream->streamName);
    }

    public function testThrowsExceptionWhenAskedForUnknownStreamMetadata(): void
    {
        $this->expectException(StreamNotFound::class);

        $this->eventStore->fetchStreamMetadata(new StreamName('unknown'));
    }

    public function testReturnsMetadataWhenAskedForStreamMetadata(): void
    {
        $stream = new Stream(new StreamName('Phayne\Model\User'), new ArrayIterator(), ['foo' => 'bar']);

        $this->eventStore->create($stream);

        $this->assertEquals(['foo' => 'bar'], $this->eventStore->fetchStreamMetadata($stream->streamName));
    }

    public function testThrowsExceptionWhenTryingToDeleteUnknownStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $this->eventStore->delete(new StreamName('unknown'));
    }

    public function testThrowsExceptionWhenTryingToAppendOnNonExistingStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $event = UserCreated::with(['name' => 'John'], 1);

        $this->eventStore->appendTo(new StreamName('unknown'), new ArrayIterator([$event]));
    }

    public function testThrowsExceptionWhenTryingToLoadNonExistingStream(): void
    {
        $this->expectException(StreamNotFound::class);

        $streamName = new StreamName('test');

        $this->eventStore->load($streamName);
    }

    public function testDeletesStream(): void
    {
        $stream = $this->getTestStream();

        $this->eventStore->create($stream);

        $this->eventStore->delete($stream->streamName);

        $this->assertFalse($this->eventStore->hasStream($stream->streamName));
    }

    public function testCanCheckForStreamExistence(): void
    {
        $streamName = new StreamName('Phayne\Model\User');

        $this->assertFalse($this->eventStore->hasStream($streamName));

        $this->eventStore->create($this->getTestStream());

        $this->assertTrue($this->eventStore->hasStream($streamName));
    }

    public function testFetchesStreamNames(): void
    {
        $streamNames = [];

        try {
            for ($i = 0; $i < 50; $i++) {
                $streamNames[] = 'user-' . $i;
                $streamNames[] = 'admin-' . $i;
                $this->eventStore->create(
                    new Stream(
                        new StreamName('user-' . $i),
                        new EmptyIterator(),
                        ['foo' => 'bar']
                    )
                );
                $this->eventStore->create(
                    new Stream(
                        new StreamName('admin-' . $i),
                        new EmptyIterator(),
                        ['foo' => 'bar']
                    )
                );
            }

            for ($i = 0; $i < 20; $i++) {
                $streamName = uniqid('rand');
                $streamNames[] = $streamName;
                $this->eventStore->create(new Stream(new StreamName($streamName), new \EmptyIterator()));
            }

            $this->assertCount(1, $this->eventStore->fetchStreamNames('user-0', null, 200));
            $this->assertCount(120, $this->eventStore->fetchStreamNames(null, null, 200));
            $this->assertCount(0, $this->eventStore->fetchStreamNames(null, null, 200, 200));
            $this->assertCount(10, $this->eventStore->fetchStreamNames(null, null, 10));
            $this->assertCount(10, $this->eventStore->fetchStreamNames(null, null, 10, 10));
            $this->assertCount(5, $this->eventStore->fetchStreamNames(null, null, 10, 115));

            for ($i = 0; $i < 50; $i++) {
                $this->assertStringStartsWith(
                    'admin-',
                    $this->eventStore->fetchStreamNames(null, null, 1, $i)[0]->toString()
                );
            }

            for ($i = 50; $i < 70; $i++) {
                $this->assertStringStartsWith(
                    'rand',
                    $this->eventStore->fetchStreamNames(null, null, 1, $i)[0]->toString()
                );
            }

            for ($i = 0; $i < 50; $i++) {
                $this->assertStringStartsWith(
                    'user-',
                    $this->eventStore->fetchStreamNames(null, null, 1, $i + 70)[0]->toString()
                );
            }

            $this->assertCount(30, $this->eventStore->fetchStreamNamesRegex('s.*er-', null, 30, 0));
            $this->assertCount(20, $this->eventStore->fetchStreamNamesRegex('s.*er-', null, 20, 10));
            $this->assertCount(
                30,
                $this->eventStore->fetchStreamNamesRegex(
                    'n.*-',
                    (new MetadataMatcher())->withMetadataMatch('foo', Operator::EQUALS, 'bar'),
                    30,
                    0
                )
            );
            $this->assertCount(
                0,
                $this->eventStore->fetchStreamNamesRegex(
                    'n.*-',
                    (new MetadataMatcher())->withMetadataMatch('foo', Operator::NOT_EQUALS, 'bar'),
                    30,
                    0
                )
            );
            $this->assertCount(
                0,
                $this->eventStore->fetchStreamNames(
                    null,
                    (new MetadataMatcher())->withMetadataMatch('foo', Operator::NOT_EQUALS, 'bar'),
                    30,
                    0
                )
            );
        } finally {
            foreach ($streamNames as $streamName) {
                $this->eventStore->delete(new StreamName($streamName));
            }
        }
    }

    public function testThrowsExceptionWhenFetchingStreamNamesUsingInvalidRegex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex pattern given');

        $this->eventStore->fetchStreamNamesRegex('/invalid)/', null, 10, 0);
    }

    public function testFetchesStreamCategories(): void
    {
        $streamNames = [];

        try {
            for ($i = 0; $i < 5; $i++) {
                $streamNames[] = 'foo-' . $i;
                $streamNames[] = 'bar-' . $i;
                $streamNames[] = 'baz-' . $i;
                $streamNames[] = 'bam-' . $i;
                $streamNames[] = 'foobar-' . $i;
                $streamNames[] = 'foobaz-' . $i;
                $streamNames[] = 'foobam-' . $i;
                $this->eventStore->create(new Stream(new StreamName('foo-' . $i), new EmptyIterator()));
                $this->eventStore->create(new Stream(new StreamName('bar-' . $i), new EmptyIterator()));
                $this->eventStore->create(new Stream(new StreamName('baz-' . $i), new EmptyIterator()));
                $this->eventStore->create(new Stream(new StreamName('bam-' . $i), new EmptyIterator()));
                $this->eventStore->create(new Stream(new StreamName('foobar-' . $i), new EmptyIterator()));
                $this->eventStore->create(new Stream(new StreamName('foobaz-' . $i), new EmptyIterator()));
                $this->eventStore->create(new Stream(new StreamName('foobam-' . $i), new EmptyIterator()));
            }

            for ($i = 0; $i < 20; $i++) {
                $streamName = uniqid('rand');
                $streamNames[] = $streamName;
                $this->eventStore->create(new Stream(new StreamName($streamName), new EmptyIterator()));
            }

            $this->assertCount(7, $this->eventStore->fetchCategoryNames(null));
            $this->assertCount(0, $this->eventStore->fetchCategoryNames(null, 20, 20));
            $this->assertCount(3, $this->eventStore->fetchCategoryNames(null, 3));
            $this->assertCount(3, $this->eventStore->fetchCategoryNames(null, 3, 3));
            $this->assertCount(5, $this->eventStore->fetchCategoryNames(null, 10, 2));

            $this->assertCount(1, $this->eventStore->fetchCategoryNames('foo'));
            $this->assertCount(4, $this->eventStore->fetchCategoryNamesRegex('^foo'));
            $this->assertCount(2, $this->eventStore->fetchCategoryNamesRegex('^foo', 2, 2));
        } finally {
            foreach ($streamNames as $streamName) {
                $this->eventStore->delete(new StreamName($streamName));
            }
        }
    }

    public function testThrowsExceptionWhenFetchingStreamCategoriesUsingInvalidRegex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex pattern given');

        $this->eventStore->fetchCategoryNamesRegex('invalid)', 10);
    }

    public function testThrowsExceptionGivenInvalidMetadataValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $value = new stdClass();
        $value->foo = 'bar';

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher->withMetadataMatch('key', Operator::EQUALS, $value);
    }

    public function testThrowsOnInvalidFieldForMessageProperty(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $event = UserCreated::with(['name' => 'John'], 1);
        $event = $event->withAddedMetadata('foo', 'bar');
        $event = $event->withAddedMetadata('int', 5);
        $event = $event->withAddedMetadata('int2', 4);
        $event = $event->withAddedMetadata('int3', 6);
        $event = $event->withAddedMetadata('int4', 7);

        $streamName = new StreamName('Phayne\Model\User');
        $stream = new Stream($streamName, new ArrayIterator([$event]));

        $this->eventStore->create($stream);

        $metadataMatcher = $this->prophesize(MetadataMatcher::class);
        $metadataMatcher->data()->willReturn([[
            'field' => 'foo',
            'value' => 'bar',
            'operator' => Operator::EQUALS,
            'fieldType' => FieldType::MESSAGE_PROPERTY,
        ]])->shouldBeCalled();

        $this->eventStore->load($streamName, 1, null, $metadataMatcher->reveal());
    }

    public function getMatchingMetadata(): array
    {
        return [
            [['snapshot' => true]],
            [['some_id' => 123]],
            [['foo' => 'bar']],
            [['snapshot' => true, 'some_id' => 123, 'foo' => 'bar']],
        ];
    }

    public function testReturnStreamIteratorForLoad(): void
    {
        $this->eventStore->create($this->getTestStream());

        $this->assertInstanceOf(
            StreamIterator::class,
            $this->eventStore->load(new StreamName('Phayne\Model\User'))
        );
    }

    public function testReturnStreamIteratorForLoadReversed(): void
    {
        $this->eventStore->create($this->getTestStream());

        $this->assertInstanceOf(
            StreamIterator::class,
            $this->eventStore->loadReverse(new StreamName('Phayne\Model\User'))
        );
    }
}
