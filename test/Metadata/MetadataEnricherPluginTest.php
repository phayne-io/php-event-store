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

use ArrayIterator;
use Phayne\EventStore\ActionEventEmitterEventStore;
use Phayne\EventStore\InMemoryEventStore;
use Phayne\EventStore\Metadata\MetadataEnricher;
use Phayne\EventStore\Metadata\MetadataEnricherPlugin;
use Phayne\EventStore\Stream;
use Phayne\EventStore\StreamName;
use Phayne\Messaging\Event\DefaultActionEvent;
use Phayne\Messaging\Event\PhayneActionEventEmitter;
use Phayne\Messaging\Messaging\Message;
use PhayneTest\EventStore\Mock\TestDomainEvent;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Class MetadataEnricherPluginTest
 *
 * @package PhayneTest\EventStore\Metadata
 * @author Julien Guittard <julien@phayne.com>
 */
class MetadataEnricherPluginTest extends TestCase
{
    use ProphecyTrait;

    public function testEnrichMetadataOnStreamCreate(): void
    {
        $metadataEnricher = new class () implements MetadataEnricher {
            public function enrich(Message $message): Message
            {
                return $message->withAddedMetadata('foo', 'bar');
            }
        };

        $eventStore = new ActionEventEmitterEventStore(new InMemoryEventStore(), new PhayneActionEventEmitter());

        $plugin = new MetadataEnricherPlugin($metadataEnricher);
        $plugin->attachToEventStore($eventStore);

        $eventStore->create(
            new Stream(
                new StreamName('foo'),
                new ArrayIterator([new TestDomainEvent(['foo' => 'bar'])])
            )
        );

        $streamEvents = $eventStore->load(new StreamName('foo'));

        $this->assertEquals(
            ['foo' => 'bar'],
            $streamEvents->current()->metadata()
        );
    }

    public function testDoesNotEnrichMetadataOnCreateIfStreamIsNotSet(): void
    {
        $metadataEnricher = $this->prophesize(MetadataEnricher::class);
        $metadataEnricher->enrich(Argument::any())->shouldNotBeCalled();

        $actionEvent = new DefaultActionEvent('create');

        $plugin = new MetadataEnricherPlugin($metadataEnricher->reveal());
        $plugin->onEventStoreCreateStream($actionEvent);
    }

    public function testEnrichMetadataOnStreamAppendTo(): void
    {
        $metadataEnricher = new class () implements MetadataEnricher {
            public function enrich(Message $message): Message
            {
                return $message->withAddedMetadata('foo', 'bar');
            }
        };

        $eventStore = new ActionEventEmitterEventStore(new InMemoryEventStore(), new PhayneActionEventEmitter());

        $eventStore->create(new Stream(new StreamName('foo'), new \ArrayIterator()));

        $plugin = new MetadataEnricherPlugin($metadataEnricher);
        $plugin->attachToEventStore($eventStore);

        $eventStore->appendTo(new StreamName('foo'), new \ArrayIterator([new TestDomainEvent(['foo' => 'bar'])]));

        $streamEvents = $eventStore->load(new StreamName('foo'));

        $this->assertEquals(
            ['foo' => 'bar'],
            $streamEvents->current()->metadata()
        );
    }

    public function testDoesNotEnrichMetadataOnAppendToIfStreamIsNotSet(): void
    {
        $metadataEnricher = $this->prophesize(MetadataEnricher::class);
        $metadataEnricher->enrich(Argument::any())->shouldNotBeCalled();

        $actionEvent = new DefaultActionEvent('appendTo');

        $plugin = new MetadataEnricherPlugin($metadataEnricher->reveal());
        $plugin->onEventStoreAppendToStream($actionEvent);
    }

    public function testDetachesFromEventStore(): void
    {
        $metadataEnricher = $this->prophesize(MetadataEnricher::class);
        $metadataEnricher->enrich(Argument::any())->shouldNotBeCalled();

        $eventStore = new ActionEventEmitterEventStore(new InMemoryEventStore(), new PhayneActionEventEmitter());

        $plugin = new MetadataEnricherPlugin($metadataEnricher->reveal());
        $plugin->attachToEventStore($eventStore);
        $plugin->detachFromEventStore($eventStore);

        $eventStore->create(
            new Stream(
                new StreamName('foo'),
                new ArrayIterator([new TestDomainEvent(['foo' => 'bar'])])
            )
        );

        $stream = $eventStore->load(new StreamName('foo'));

        $this->assertEmpty($stream->current()->metadata());
    }
}
