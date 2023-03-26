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
 * Class SingleEventUpcaster
 *
 * @package Phayne\EventStore\Upcasting
 * @author Julien Guittard <julien@phayne.com>
 */
abstract class SingleEventUpcaster implements Upcaster
{
    public function upcast(Message $message): array
    {
        if (! $this->canUpcast($message)) {
            return [$message];
        }

        return $this->doUpcast($message);
    }

    abstract protected function canUpcast(Message $message): bool;

    abstract protected function doUpcast(Message $message): array;
}
