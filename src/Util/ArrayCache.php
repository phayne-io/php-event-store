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

namespace Phayne\EventStore\Util;

use Phayne\Exception\InvalidArgumentException;

use function array_fill;
use function in_array;

/**
 * Class ArrayCache
 *
 * @package Phayne\EventStore\Util
 * @author Julien Guittard <julien@phayne.com>
 */
class ArrayCache
{
    private array $container;

    private int $position = -1;

    public function __construct(public readonly int $size)
    {
        if ($this->size <= 0) {
            throw new InvalidArgumentException('Size must be a positive integer');
        }

        $this->container = array_fill(0, $this->size, null);
    }

    public function rollingAppend(mixed $value): void
    {
        $this->container[$this->nextPosition()] = $value;
    }

    public function has($value): bool
    {
        return in_array($value, $this->container, true);
    }

    public function get(int $position)
    {
        if ($position >= $this->size || $position < 0) {
            throw new InvalidArgumentException('Position must be between 0 and ' . ($this->size - 1));
        }

        return $this->container[$position];
    }

    private function nextPosition(): int
    {
        return $this->position = ++$this->position % $this->size;
    }
}
