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

namespace Phayne\EventStore\Container;

use Interop\Config\ConfigurationTrait;
use Interop\Config\RequiresConfig;
use Interop\Config\RequiresConfigId;
use Interop\Config\RequiresMandatoryOptions;
use Phayne\EventStore\Projection\InMemoryProjectionManager;
use Phayne\Exception\InvalidArgumentException;
use Psr\Container\ContainerInterface;

/**
 * Class InMemoryProjectionManagerFactory
 *
 * @package Phayne\EventStore\Container
 * @author Julien Guittard <julien@phayne.com>
 */
class InMemoryProjectionManagerFactory implements RequiresMandatoryOptions, RequiresConfig, RequiresConfigId
{
    use ConfigurationTrait;

    /**
     * Creates a new instance from a specified config, specifically meant to be used as static factory.
     *
     * In case you want to use another config key than provided by the factories, you can add the following factory to
     * your config:
     *
     * <code>
     * <?php
     * return [
     *     InMemoryProjectionManager::class => [InMemoryProjectionManager::class, 'service_name'],
     * ];
     * </code>
     *
     * @throws InvalidArgumentException
     */
    public static function __callStatic(string $name, array $arguments): InMemoryProjectionManager
    {
        if (! isset($arguments[0]) || ! $arguments[0] instanceof ContainerInterface) {
            throw new InvalidArgumentException(
                sprintf('The first argument must be of type %s', ContainerInterface::class)
            );
        }

        return (new self($name))->__invoke($arguments[0]);
    }


    public function __construct(private readonly string $configId = 'default')
    {
    }

    public function __invoke(ContainerInterface $container): InMemoryProjectionManager
    {
        $config = $container->get('config');
        $config = $this->options($config, $this->configId);

        $eventStore = $container->get($config['event_store']);

        return new InMemoryProjectionManager($eventStore);
    }

    public function dimensions(): iterable
    {
        return ['phayne', 'projection_manager'];
    }

    public function mandatoryOptions(): iterable
    {
        return [
            'event_store',
        ];
    }
}
