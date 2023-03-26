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

use Iterator;

/**
 * Interface EventStore
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
interface EventStore extends ReadOnlyEventStore
{
    public function updateStreamMetadata(StreamName $streamName, array $newMetadata): void;

    public function create(Stream $stream): void;

    public function appendTo(StreamName $streamName, Iterator $streamEvents): void;

    public function delete(StreamName $streamName): void;
}
