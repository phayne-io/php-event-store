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

/**
 * Enum ProjectionStatus
 *
 * @package Phayne\EventStore\Projection
 * @author Julien Guittard <julien@phayne.com>
 */
enum ProjectionStatus: string
{
    case RUNNING = 'running';
    case STOPPING = 'stopping';
    case DELETING = 'deleting';
    case DELETING_INCL_EMITTED_EVENTS = 'deleting incl emitted events';
    case RESETTING = 'resetting';
    case IDLE = 'idle';
}
