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

namespace Phayne\EventStore;

/**
 * Interface TransactionalEventStore
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
interface TransactionalEventStore extends EventStore
{
    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function inTransaction(): bool;

    public function transactional(callable $callable): mixed;
}
