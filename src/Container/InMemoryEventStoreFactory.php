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
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfig;
use Interop\Config\RequiresConfigId;
use Phayne\EventStore\ActionEventEmitterEventStore;
use Phayne\EventStore\EventStore;
use Phayne\EventStore\Exception\ConfigurationException;
use Phayne\EventStore\InMemoryEventStore;
use Phayne\EventStore\Metadata\MetadataEnricher;
use Phayne\EventStore\Metadata\MetadataEnricherAggregate;
use Phayne\EventStore\Metadata\MetadataEnricherPlugin;
use Phayne\EventStore\NonTransactionalInMemoryEventStore;
use Phayne\EventStore\Plugin\Plugin;
use Phayne\EventStore\ReadOnlyEventStore;
use Phayne\EventStore\ReadOnlyEventStoreWrapper;
use Phayne\EventStore\TransactionalActionEventEmitterEventStore;
use Phayne\EventStore\TransactionalEventStore;
use Phayne\Exception\InvalidArgumentException;
use Phayne\Messaging\Event\ActionEventEmitter;
use Phayne\Messaging\Event\PhayneActionEventEmitter;
use Psr\Container\ContainerInterface;

use function count;
use function sprintf;

/**
 * Class InMemoryEventStoreFactory
 *
 * @package Phayne\EventStore\Container
 * @author Julien Guittard <julien@phayne.com>
 */
final class InMemoryEventStoreFactory implements ProvidesDefaultOptions, RequiresConfig, RequiresConfigId
{
    use ConfigurationTrait;

    private ?bool $isTransactional = null;

    /**
     * Creates a new instance from a specified config, specifically meant to be used as static factory.
     *
     * In case you want to use another config key than provided by the factories, you can add the following factory to
     * your config:
     *
     * <code>
     * <?php
     * return [
     *     InMemoryEventStore::class => [InMemoryEventStoreFactory::class, 'service_name'],
     * ];
     * </code>
     *
     * @throws InvalidArgumentException
     */
    public static function __callStatic(string $name, array $arguments): ReadOnlyEventStore
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

    public function __invoke(ContainerInterface $container): ReadOnlyEventStore
    {
        $config = $container->get('config');
        $config = $this->options($config, $this->configId);

        $this->isTransactional = $this->isTransactional($config);

        $eventStore = $this->createEventStore();

        if ($config['read_only']) {
            $eventStore = new ReadOnlyEventStoreWrapper($eventStore);
        }

        if (! $config['wrap_action_event_emitter']) {
            return $eventStore;
        }

        if (! isset($config['event_emitter'])) {
            $eventEmitter = new PhayneActionEventEmitter($this->determineEventsForDefaultEmitter());
        } else {
            $eventEmitter = $container->get($config['event_emitter']);
        }

        $wrapper = $this->createActionEventEmitterDecorator($eventStore, $eventEmitter);

        foreach ($config['plugins'] as $pluginAlias) {
            $plugin = $container->get($pluginAlias);

            if (! $plugin instanceof Plugin) {
                throw ConfigurationException::configurationError(
                    sprintf(
                        'Plugin %s does not implement the Plugin interface',
                        $pluginAlias
                    )
                );
            }

            $plugin->attachToEventStore($wrapper);
        }

        if (count($config['metadata_enrichers']) > 0) {
            $metadataEnrichers = [];

            foreach ($config['metadata_enrichers'] as $metadataEnricherAlias) {
                $metadataEnricher = $container->get($metadataEnricherAlias);

                if (! $metadataEnricher instanceof MetadataEnricher) {
                    throw ConfigurationException::configurationError(
                        sprintf(
                            'Metadata enricher %s does not implement the MetadataEnricher interface',
                            $metadataEnricherAlias
                        )
                    );
                }

                $metadataEnrichers[] = $metadataEnricher;
            }

            $plugin = new MetadataEnricherPlugin(
                new MetadataEnricherAggregate($metadataEnrichers)
            );

            $plugin->attachToEventStore($wrapper);
        }

        return $wrapper;
    }

    public function dimensions(): iterable
    {
        return ['phayne', 'event_store'];
    }

    public function defaultOptions(): iterable
    {
        return [
            'metadata_enrichers' => [],
            'plugins' => [],
            'wrap_action_event_emitter' => true,
            'transactional' => true,
            'read_only' => false,
        ];
    }

    private function determineEventsForDefaultEmitter(): array
    {
        if ($this->isTransactional) {
            return TransactionalActionEventEmitterEventStore::ALL_EVENTS;
        }

        return ActionEventEmitterEventStore::ALL_EVENTS;
    }

    private function createEventStore(): EventStore
    {
        if ($this->isTransactional) {
            return new InMemoryEventStore();
        }

        return new NonTransactionalInMemoryEventStore();
    }

    private function createActionEventEmitterDecorator(
        EventStore $eventStore,
        ActionEventEmitter $actionEventEmitter
    ): ActionEventEmitterEventStore {
        if ($this->isTransactional) {
            /** @var TransactionalEventStore $eventStore */
            return new TransactionalActionEventEmitterEventStore($eventStore, $actionEventEmitter);
        }

        return new ActionEventEmitterEventStore($eventStore, $actionEventEmitter);
    }

    private function isTransactional(array $config): bool
    {
        return isset($config['transactional']) && $config['transactional'] === true;
    }
}
