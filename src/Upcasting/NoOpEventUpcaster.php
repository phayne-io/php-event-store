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

namespace Phayne\EventStore\Upcasting;

use Phayne\Messaging\Messaging\Message;

/**
 * Class NoOpEventUpcaster
 *
 * @package Phayne\EventStore\Upcasting
 * @author Julien Guittard <julien@phayne.com>
 */
final class NoOpEventUpcaster implements Upcaster
{
    public function upcast(Message $message): array
    {
        return [$message];
    }
}
