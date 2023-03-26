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

use EmptyIterator;
use Phayne\EventStore\StreamIterator\StreamIterator;

use function array_shift;
use function reset;

/**
 * Class UpcastingIterator
 *
 * @package Phayne\EventStore\Upcasting
 * @author Julien Guittard <julien@phayne.com>
 */
class UpcastingIterator implements StreamIterator
{
    private array $storedMessages = [];

    public function __construct(private readonly Upcaster $upcaster, private readonly StreamIterator $innerIterator)
    {
    }

    public function current(): mixed
    {
        if (! empty($this->storedMessages)) {
            return reset($this->storedMessages);
        }

        $current = null;

        if (! $this->innerIterator instanceof EmptyIterator) {
            $current = $this->innerIterator->current();
        }

        if (null === $current) {
            return null;
        }

        while (empty($this->storedMessages)) {
            $this->innerIterator->next();

            if (! $this->innerIterator->valid()) {
                return null;
            }

            $this->storedMessages = $this->upcaster->upcast($this->innerIterator->current());
        }

        return reset($this->storedMessages);
    }

    public function next(): void
    {
        if (! empty($this->storedMessages)) {
            array_shift($this->storedMessages);
        }

        if (! empty($this->storedMessages)) {
            return;
        }

        while (empty($this->storedMessages)) {
            $this->innerIterator->next();

            if (! $this->innerIterator->valid()) {
                return;
            }

            $this->storedMessages = $this->upcaster->upcast($this->innerIterator->current());
        }
    }

    public function key(): mixed
    {
        return $this->innerIterator->key();
    }

    public function valid(): bool
    {
        if ($this->innerIterator instanceof EmptyIterator) {
            return false;
        }

        return null !== $this->current();
    }

    public function rewind(): void
    {
        $this->storedMessages = [];
        $this->innerIterator->rewind();
    }

    public function count(): int
    {
        return $this->innerIterator->count();
    }
}
