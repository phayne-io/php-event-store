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

use Phayne\EventStore\Container\InMemoryProjectionManagerFactory;
use Phayne\EventStore\InMemoryEventStore;
use Phayne\EventStore\Projection\InMemoryProjectionManager;
use Phayne\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;

/**
 * Class InMemoryProjectionManagerFactoryTest
 *
 * @package PhayneTest\EventStore\Container
 * @author Julien Guittard <julien@phayne.com>
 */
class InMemoryProjectionManagerFactoryTest extends TestCase
{
    use ProphecyTrait;

    public function testCreatesProjectionManager(): void
    {
        $config['phayne']['projection_manager']['default'] = [
            'event_store' => 'my_event_store',
        ];

        $eventStore = new InMemoryEventStore();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn($config)->shouldBeCalled();
        $container->get('my_event_store')->willReturn($eventStore)->shouldBeCalled();

        $factory = new InMemoryProjectionManagerFactory();
        $projectionManager = $factory($container->reveal());

        $this->assertInstanceOf(InMemoryProjectionManager::class, $projectionManager);
    }

    public function testCreatesProjectionManagerViaCallstatic(): void
    {
        $config['phayne']['projection_manager']['default'] = [
            'event_store' => 'my_event_store',
        ];

        $eventStore = new InMemoryEventStore();

        $container = $this->prophesize(ContainerInterface::class);
        $container->get('config')->willReturn($config)->shouldBeCalled();
        $container->get('my_event_store')->willReturn($eventStore)->shouldBeCalled();

        $name = 'default';
        $projectionManager = InMemoryProjectionManagerFactory::$name($container->reveal());

        $this->assertInstanceOf(InMemoryProjectionManager::class, $projectionManager);
    }

    public function testThrowsExceptionWhenInvalidContainerGivenToCallstatic(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $type = 'another';
        InMemoryProjectionManagerFactory::$type('invalid container');
    }
}
