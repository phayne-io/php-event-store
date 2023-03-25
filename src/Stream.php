<?php
//phpcs:ignoreFile

/**
 * This file is part of phayne-io/php-event-store package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see       https://github.com/phayne-io/php-event-store for the canonical source repository
 * @copyright Copyright (c) 2023 Phayne. (https://phayne.io)
 */

declare(strict_types=1);

namespace Phayne\PhpEventStore;

use Iterator;

/**
 * Class Stream
 *
 * @package Phayne\PhpEventStore
 * @author Julien Guittard <julien@phayne.com>
 */
final readonly class Stream
{
    public function __construct(public StreamName $streamName, public Iterator $iterator, public array $metadata = [])
    {
    }
}
