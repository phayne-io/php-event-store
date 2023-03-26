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

namespace Phayne\EventStore\Projection;

use Closure;
use Phayne\Messaging\Messaging\Message;

/**
 * Interface Projector
 *
 * @package Phayne\EventStore\Projection
 * @author Julien Guittard <julien@phayne.com>
 */
interface Projector
{
    public const OPTION_CACHE_SIZE = 'cache_size';
    public const OPTION_SLEEP = 'sleep';
    public const OPTION_PERSIST_BLOCK_SIZE = 'persist_block_size';
    public const OPTION_LOCK_TIMEOUT_MS = 'lock_timeout_ms';
    public const OPTION_PCNTL_DISPATCH = 'trigger_pcntl_dispatch';
    public const OPTION_UPDATE_LOCK_THRESHOLD = 'update_lock_threshold';

    public const DEFAULT_CACHE_SIZE = 1000;
    public const DEFAULT_SLEEP = 100000;
    public const DEFAULT_PERSIST_BLOCK_SIZE = 1000;
    public const DEFAULT_LOCK_TIMEOUT_MS = 1000;
    public const DEFAULT_PCNTL_DISPATCH = false;
    public const DEFAULT_UPDATE_LOCK_THRESHOLD = 0;

    /**
     * The callback has to return an array
     */
    public function init(Closure $callback): Projector;

    public function fromStream(string $streamName): Projector;

    public function fromStreams(string ...$streamNames): Projector;

    public function fromCategory(string $name): Projector;

    public function fromCategories(string ...$names): Projector;

    public function fromAll(): Projector;

    /**
     * For example:
     *
     * when([
     *     'UserCreated' => function (array $state, Message $event) {
     *         $state['count']++;
     *         return $state;
     *     },
     *     'UserDeleted' => function (array $state, Message $event) {
     *         $state['count']--;
     *         return $state;
     *     }
     * ])
     */
    public function when(array $handlers): Projector;

    /**
     * For example:
     * function(array $state, Message $event) {
     *     $state['count']++;
     *     return $state;
     * }
     */
    public function whenAny(Closure $closure): Projector;

    public function reset(): void;

    public function stop(): void;

    public function state(): array;

    public function name(): string;

    public function emit(Message $event): void;

    public function linkTo(string $streamName, Message $event): void;

    public function delete(bool $deleteEmittedEvents): void;

    public function run(bool $keepRunning = true): void;
}
