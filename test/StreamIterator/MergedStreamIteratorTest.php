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

namespace PhayneTest\EventStore\StreamIterator;

use DateTimeImmutable;
use Phayne\EventStore\StreamIterator\InMemoryStreamIterator;
use Phayne\EventStore\StreamIterator\MergedStreamIterator;
use Phayne\EventStore\StreamIterator\StreamIterator;
use PhayneTest\EventStore\Mock\TestDomainEvent;
use PHPUnit\Framework\TestCase;

use function array_chunk;
use function array_keys;
use function array_merge;
use function array_slice;
use function array_values;
use function floor;
use function sprintf;
use function shuffle;
use function usort;

/**
 * Class MergedStreamIteratorTest
 *
 * @package PhayneTest\EventStore\StreamIterator
 * @author Julien Guittard <julien@phayne.com>
 */
class MergedStreamIteratorTest extends TestCase
{
    public function getStreams(): array
    {
        return [
            'streamA' => new InMemoryStreamIterator([
                1 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 5,
                        'expected_position' => 1,
                        'expected_stream_name' => 'streamA'
                    ],
                    1,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388510')
                ),
                2 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 7,
                        'expected_position' => 2,
                        'expected_stream_name' => 'streamA'
                    ],
                    2,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388519')
                ),
                3 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 8,
                        'expected_position' => 3,
                        'expected_stream_name' => 'streamA'
                    ],
                    3,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388520')
                ),
            ]),
            'streamB' => new InMemoryStreamIterator([
                1 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 1,
                        'expected_position' => 1,
                        'expected_stream_name' => 'streamB'
                    ],
                    1,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388501')
                ),
                2 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 2,
                        'expected_position' => 2,
                        'expected_stream_name' => 'streamB'
                    ],
                    2,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388503')
                ),
                3 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 4,
                        'expected_position' => 3,
                        'expected_stream_name' => 'streamB'
                    ],
                    3,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388509')
                ),
                4 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 6,
                        'expected_position' => 4,
                        'expected_stream_name' => 'streamB'
                    ],
                    4,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388515')
                ),
            ]),
            'streamC' => new InMemoryStreamIterator([
                1 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 0,
                        'expected_position' => 1,
                        'expected_stream_name' => 'streamC'
                    ],
                    1,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388500')
                ),
                2 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 3,
                        'expected_position' => 2,
                        'expected_stream_name' => 'streamC'
                    ],
                    2,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2019-05-10T10:18:19.388503')
                ),
            ]),
        ];
    }

    public function getStreamsLarge($numberOfEvents = 977): array
    {
        $datetimeList = [];
        for ($i = 0; $i < $numberOfEvents; $i++) {
            $millis = 100000 + $i;
            $datetimeList[] = sprintf('2019-05-10T10:%d:%d.%d', 10, 10, $millis);
        }

        foreach ($datetimeList as $key => &$value) {
            $value = [
                'payload' => ['expected_index' => $key],
                'createdAt' => DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', $value),
            ];
        }
        unset($value);

        shuffle($datetimeList);

        $length = (int) floor($numberOfEvents / 3);
        $chunkThree = (int) floor($length / 3) * 3;

        $streamsThreeEvents = array_chunk(array_slice($datetimeList, 0, $chunkThree), 3);
        $streamsTwoEvents = array_chunk(array_slice($datetimeList, $chunkThree, $length), 2);
        $streamsOneEvents = array_chunk(array_slice($datetimeList, $length + $chunkThree), 1);

        $streamEvents = [...$streamsOneEvents, ...$streamsTwoEvents, ...$streamsThreeEvents];
        shuffle($streamEvents);

        $streams = [];

        foreach ($streamEvents as $streamNo => $stream) {
            $events = [];
            $streamName = 'stream' . $streamNo;

            // must be sorted
            usort($stream, static function ($a, $b): int {
                return $a['payload']['expected_index'] <=> $b['payload']['expected_index'];
            });

            foreach ($stream as $eventNo => $event) {
                $events[$eventNo + 1] = TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    array_merge(
                        $event['payload'],
                        ['expected_position' => $eventNo + 1, 'expected_stream_name' => $streamName]
                    ),
                    $eventNo + 1,
                    $event['createdAt']
                );
            }
            $streams[$streamName] = new InMemoryStreamIterator($events);
        }

        return $streams;
    }

    public function testImplementation(): void
    {
        $iterator = new MergedStreamIterator(array_keys($this->getStreams()), ...array_values($this->getStreams()));

        $this->assertInstanceOf(StreamIterator::class, $iterator);
    }

    public function testCount(): void
    {
        $iterator = new MergedStreamIterator(array_keys($this->getStreams()), ...array_values($this->getStreams()));

        $this->assertEquals(9, $iterator->count());
    }

    public function testCanRewind(): void
    {
        $iterator = new MergedStreamIterator(array_keys($this->getStreams()), ...array_values($this->getStreams()));

        $iterator->next();
        $iterator->rewind();
        $message = $iterator->current();

        $this->assertEquals(0, $message->payload()['expected_index']);
    }

    public function testReturnsMessagesInOrder(): void
    {
        $streams = $this->getStreams();
        $iterator = new MergedStreamIterator(array_keys($streams), ...array_values($streams));

        $index = 0;
        foreach ($iterator as $message) {
            $this->assertEquals($index, $message->payload()['expected_index']);
            $this->assertEquals($iterator->streamName(), $message->payload()['expected_stream_name']);

            $index++;
        }
    }

    /*public function testReturnsMessagesInOrderForLargeStreams(): void
    {
        $streams = $this->getStreamsLarge();
        $iterator = new MergedStreamIterator(array_keys($streams), ...array_values($streams));

        $index = 0;
        foreach ($iterator as $message) {
            $this->assertEquals($index, $message->payload()['expected_index']);
            $this->assertEquals($iterator->streamName(), $message->payload()['expected_stream_name']);

            $index++;
        }
    }*/

    public function testReturnsMessagesInOrderInConsiderationOfProvidedStreamOrder(): void
    {
        $streams = [
            'streamA' => new InMemoryStreamIterator([
                1 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 0,
                        'expected_position' => 1,
                        'expected_stream_name' => 'streamA'
                    ],
                    1,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2018-02-26T17:29:45.000000')
                ),
                2 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 2,
                        'expected_position' => 2,
                        'expected_stream_name' => 'streamA'
                    ],
                    2,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2018-02-26T20:28:25.000000')
                ),
            ]),
            'streamB' => new InMemoryStreamIterator([
                1 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 1,
                        'expected_position' => 1,
                        'expected_stream_name' => 'streamB'
                    ],
                    1,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2018-02-26T17:29:45.000000')
                ),
                2 => TestDomainEvent::withPayloadAndSpecifiedCreatedAt(
                    [
                        'expected_index' => 3,
                        'expected_position' => 2,
                        'expected_stream_name' => 'streamB'
                    ],
                    2,
                    DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2018-02-26T20:28:25.000000')
                ),
            ]),
        ];

        $iterator = new MergedStreamIterator(array_keys($streams), ...array_values($streams));

        $index = 0;
        foreach ($iterator as $message) {
            $this->assertEquals($index, $message->payload()['expected_index']);
            $this->assertEquals($iterator->streamName(), $message->payload()['expected_stream_name']);

            $index++;
        }
    }

    public function testReturnsCorrectStreamName(): void
    {
        $iterator = new MergedStreamIterator(array_keys($this->getStreams()), ...array_values($this->getStreams()));

        foreach ($iterator as $message) {
            $this->assertEquals($iterator->streamName(), $message->payload()['expected_stream_name']);
        }
    }

    public function testKeyRepresentsEventPosition(): void
    {
        $iterator = new MergedStreamIterator(array_keys($this->getStreams()), ...array_values($this->getStreams()));

        foreach ($iterator as $position => $message) {
            $this->assertEquals($position, $message->payload()['expected_position']);
        }
    }
}
