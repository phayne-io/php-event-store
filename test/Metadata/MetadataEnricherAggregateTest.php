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

namespace PhayneTest\EventStore\Metadata;

use Assert\InvalidArgumentException;
use Phayne\EventStore\Metadata\MetadataEnricher;
use Phayne\EventStore\Metadata\MetadataEnricherAggregate;
use Phayne\Messaging\Messaging\Message;
use PhayneTest\EventStore\Mock\TestDomainEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use stdClass;

/**
 * Class MetadataEnricherAggregateTest
 *
 * @package PhayneTest\EventStore\Metadata
 * @author Julien Guittard <julien@phayne.com>
 */
class MetadataEnricherAggregateTest extends TestCase
{
    use ProphecyTrait;

    public function testAggregatesMetadataEnrichers(): void
    {
        // Mocks
        $metadataEnricher1 = $this->prophesize(MetadataEnricher::class);
        $metadataEnricher2 = $this->prophesize(MetadataEnricher::class);

        // Class under test
        $metadataEnricherAgg = new MetadataEnricherAggregate([
            $metadataEnricher1->reveal(),
            $metadataEnricher2->reveal(),
        ]);

        // Initial payload and expected data
        $originalEvent = TestDomainEvent::with(['foo' => 'bar'], 1);
        $eventAfterEnricher1 = $originalEvent->withAddedMetadata('meta1', 'data1');
        $eventAfterEnricher2 = $eventAfterEnricher1->withAddedMetadata('meta2', 'data2');

        // Prepare mock
        $metadataEnricher1
            ->enrich(Argument::type(Message::class))
            ->shouldBeCalledTimes(1)
            ->willReturn($eventAfterEnricher1);

        $metadataEnricher2
            ->enrich(Argument::type(Message::class))
            ->shouldBeCalledTimes(1)
            ->willReturn($eventAfterEnricher2);

        // Call method under test
        $enrichedEvent = $metadataEnricherAgg->enrich($originalEvent);

        // Assertions
        $this->assertEquals($originalEvent->payload(), $enrichedEvent->payload());
        $this->assertEquals($originalEvent->version(), $enrichedEvent->version());
        $this->assertEquals($originalEvent->createdAt(), $enrichedEvent->createdAt());

        $expectedMetadata = ['meta1' => 'data1', 'meta2' => 'data2', '_aggregate_version' => 1];
        $this->assertEquals($expectedMetadata, $enrichedEvent->metadata());
    }

    public function testOnlyAcceptCorrectInstances(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MetadataEnricherAggregate([
            $this->prophesize(MetadataEnricher::class)->reveal(),
            new stdClass(),
            $this->prophesize(MetadataEnricher::class)->reveal(),
        ]);
    }
}
