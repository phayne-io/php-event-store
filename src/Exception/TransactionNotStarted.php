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

use Phayne\Exception\LogicException;

/**
 * Class TransactionNotStarted
 *
 * @package Phayne\EventStore\Exception
 * @author Julien Guittard <julien@phayne.com>
 */
final class TransactionNotStarted extends LogicException implements EventStoreException
{
    protected $message = 'The transaction has not yet been started.';
}
