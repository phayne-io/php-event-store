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

namespace Phayne\EventStore\Metadata;

use Phayne\Messaging\Messaging\Message;

/**
 * Interface MetadataEnricher
 *
 * @package Phayne\EventStore\Metadata
 * @author Julien Guittard <julien@phayne.com>
 */
interface MetadataEnricher
{
    public function enrich(Message $message): Message;
}
