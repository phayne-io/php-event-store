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

namespace Phayne\EventStore\Exception;

use Phayne\Exception\RuntimeException;

/**
 * Class ConfigurationException
 *
 * @package Phayne\EventStore\Exception
 * @author Julien Guittard <julien@phayne.com>
 */
class ConfigurationException extends RuntimeException implements EventStoreException
{
    public static function configurationError(string $msg): ConfigurationException
    {
        return new self('[Configuration Error] ' . $msg . "\n");
    }
}
