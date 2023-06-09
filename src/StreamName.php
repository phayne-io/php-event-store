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

namespace Phayne\EventStore;

use Stringable;

/**
 * Class StreamName
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
final readonly class StreamName implements Stringable
{
    public function __construct(public string $name)
    {
    }

    public function toString(): string
    {
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
