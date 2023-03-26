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

/**
 * Interface Query
 *
 * @package Phayne\EventStore\Projection
 * @author Julien Guittard <julien@phayne.com>
 */
interface Query
{
    public const OPTION_PCNTL_DISPATCH = 'trigger_pcntl_dispatch';

    public const DEFAULT_PCNTL_DISPATCH = false;

    /**
     * The callback has to return an array
     */
    public function init(Closure $callback): Query;

    public function fromStream(string $streamName): Query;

    public function fromStreams(string ...$streamNames): Query;

    public function fromCategory(string $name): Query;

    public function fromCategories(string ...$names): Query;

    public function fromAll(): Query;

    /**
     * For example:
     *
     * when([
     *     'UserCreated' => function (array $state, Message $event) {
     *         $state->count++;
     *         return $state;
     *     },
     *     'UserDeleted' => function (array $state, Message $event) {
     *         $state->count--;
     *         return $state;
     *     }
     * ])
     */
    public function when(array $handlers): Query;

    /**
     * For example:
     * function(array $state, Message $event) {
     *     $state->count++;
     *     return $state;
     * }
     */
    public function whenAny(Closure $closure): Query;

    public function reset(): void;

    public function run(): void;

    public function stop(): void;

    public function state(): array;
}
