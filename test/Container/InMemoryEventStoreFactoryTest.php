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

namespace PhayneTest\EventStore\Container;

use ArrayIterator;
use Phayne\EventStore\ActionEventEmitterEventStore;
use Phayne\EventStore\Container\InMemoryEventStoreFactory;
use Phayne\EventStore\Exception\ConfigurationException;
use Phayne\EventStore\InMemoryEventStore;
use Phayne\EventStore\Metadata\MetadataEnricher;
use Phayne\EventStore\NonTransactionalInMemoryEventStore;
use Phayne\EventStore\Plugin\Plugin;
use Phayne\EventStore\ReadOnlyEventStoreWrapper;
use Phayne\EventStore\Stream;
use Phayne\EventStore\StreamName;
use Phayne\EventStore\TransactionalActionEventEmitterEventStore;
use Phayne\Exception\InvalidArgumentException;
use Phayne\Messaging\Event\ActionEventEmitter;
use Phayne\Messaging\Messaging\Message;
use PhayneTest\EventStore\Mock;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use stdClass;

use function count;

/**
 * Class InMemoryEventStoreFactoryTest
 *
 * @package PhayneTest\EventStore\Container
 * @author Julien Guittard <julien@phayne.com>
 */
class InMemoryEventStoreFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testCreatesEventStoreWithDefaultEventEmitter(): void
    {
        $config['phayne']['event_store']['default'] = [];

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')->with('config')->willReturn($config);

        $factory = new InMemoryEventStoreFactory();
        $eventStore = $factory($containerMock);

        $this->assertInstanceOf(TransactionalActionEventEmitterEventStore::class, $eventStore);
    }

    public function testCreatesEventStoreWithoutEventEmitter(): void
    {
        $config['phayne']['event_store']['default'] = ['wrap_action_event_emitter' => false];

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')->with('config')->willReturn($config);

        $factory = new InMemoryEventStoreFactory();
        $eventStore = $factory($containerMock);

        $this->assertInstanceOf(InMemoryEventStore::class, $eventStore);
    }

    public function testCreatesNonTransactionalEventStoreWithoutEventEmitter(): void
    {
        $config['phayne']['event_store']['default'] = ['wrap_action_event_emitter' => false, 'transactional' => false];

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')->with('config')->willReturn($config);

        $factory = new InMemoryEventStoreFactory();
        $eventStore = $factory($containerMock);

        $this->assertInstanceOf(NonTransactionalInMemoryEventStore::class, $eventStore);
    }

    public function testCreatesReadOnlyEventStore(): void
    {
        $config['phayne']['event_store']['default'] = ['wrap_action_event_emitter' => false, 'read_only' => true];

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')->with('config')->willReturn($config);

        $factory = new InMemoryEventStoreFactory();
        $eventStore = $factory($containerMock);

        $this->assertInstanceOf(ReadOnlyEventStoreWrapper::class, $eventStore);
    }

    public function testCreatesEventStoreWithDefaultEventEmitterViaCallstatic(): void
    {
        $config['phayne']['event_store']['another'] = [];

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')->with('config')->willReturn($config);

        $type = 'another';
        $eventStore = InMemoryEventStoreFactory::$type($containerMock);

        $this->assertInstanceOf(TransactionalActionEventEmitterEventStore::class, $eventStore);
    }

    public function testCreatesNonTransactionalEventStoreWithNonTransactionalEventEmitterViaCallstatic(): void
    {
        $config['phayne']['event_store']['another'] = ['transactional' => false];

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')->with('config')->willReturn($config);

        $type = 'another';
        $eventStore = InMemoryEventStoreFactory::$type($containerMock);

        $this->assertInstanceOf(ActionEventEmitterEventStore::class, $eventStore);
    }

    public function testInjectsCustomEventEmitter(): void
    {
        $config['phayne']['event_store']['default']['event_emitter'] = 'event_emitter';

        $eventEmitterMock = $this->getMockForAbstractClass(ActionEventEmitter::class);

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')
            ->withConsecutive(['config'], ['event_emitter'])
            ->willReturnOnConsecutiveCalls($config, $eventEmitterMock);

        $factory = new InMemoryEventStoreFactory();
        $eventStore = $factory($containerMock);

        $this->assertInstanceOf(TransactionalActionEventEmitterEventStore::class, $eventStore);
    }

    public function testInjectsPlugins(): void
    {
        $config['phayne']['event_store']['default']['plugins'][] = 'plugin';

        $featureMock = $this->prophesize(Plugin::class);
        $featureMock->attachToEventStore(
            Argument::type(TransactionalActionEventEmitterEventStore::class)
        )->shouldBeCalled();

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')
            ->withConsecutive(['config'], ['plugin'])
            ->willReturnOnConsecutiveCalls($config, $featureMock->reveal());

        $factory = new InMemoryEventStoreFactory();
        $eventStore = $factory($containerMock);

        $this->assertInstanceOf(TransactionalActionEventEmitterEventStore::class, $eventStore);
    }

    public function testThrowsExceptionWhenInvalidPluginConfigured(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Plugin plugin does not implement the Plugin interface');

        $config['phayne']['event_store']['default']['plugins'][] = 'plugin';

        $featureMock = 'foo';

        $containerMock = $this->getMockForAbstractClass(ContainerInterface::class);
        $containerMock->method('get')
            ->withConsecutive(['config'], ['plugin'])
            ->willReturnOnConsecutiveCalls($config, $featureMock);

        $factory = new InMemoryEventStoreFactory();
        $factory($containerMock);
    }

    public function testInjectsMetadataEnrichers(): void
    {
        $config['phayne']['event_store']['default']['metadata_enrichers'][] = 'metadata_enricher1';
        $config['phayne']['event_store']['default']['metadata_enrichers'][] = 'metadata_enricher2';

        $metadataEnricher1 = $this->prophesize(MetadataEnricher::class);
        $metadataEnricher2 = $this->prophesize(MetadataEnricher::class);

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn($config);
        $container->get('metadata_enricher1')->willReturn($metadataEnricher1->reveal());
        $container->get('metadata_enricher2')->willReturn($metadataEnricher2->reveal());

        $factory = new InMemoryEventStoreFactory();
        $eventStore = $factory($container->reveal());

        $this->assertInstanceOf(TransactionalActionEventEmitterEventStore::class, $eventStore);

        // Some events to inject into the event store
        $events = [
            Mock\UserCreated::with(['name' => 'John'], 1),
            Mock\UsernameChanged::with(['name' => 'Jane'], 2),
        ];

        // The metadata enrichers should be called as many
        // times as there are events
        $metadataEnricher1
            ->enrich(Argument::type(Message::class))
            ->shouldBeCalledTimes(count($events))
            ->willReturnArgument(0);

        $metadataEnricher2
            ->enrich(Argument::type(Message::class))
            ->shouldBeCalledTimes(count($events))
            ->willReturnArgument(0);

        $stream = new Stream(new StreamName('test'), new ArrayIterator($events));

        /* @var InMemoryEventStore $eventStore */
        $eventStore->create($stream);
    }

    public function testThrowsExceptionWhenInvalidMetadataEnricherConfigured(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Metadata enricher foobar does not implement the MetadataEnricher interface');

        $config['phayne']['event_store']['default']['metadata_enrichers'][] = 'foobar';

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn($config);
        $container->get('foobar')->willReturn(new stdClass());

        $factory = new InMemoryEventStoreFactory();
        $factory($container->reveal());
    }

    public function testThrowsExceptionWhenInvalidContainerGivenToCallstatic(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $type = 'another';
        InMemoryEventStoreFactory::$type('invalid container');
    }
}
