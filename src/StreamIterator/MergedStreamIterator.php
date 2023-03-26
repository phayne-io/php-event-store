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

namespace Phayne\EventStore\StreamIterator;

use function count;

/**
 * Class MergedStreamIterator
 *
 * @package Phayne\EventStore\StreamIterator
 * @author Julien Guittard <julien@phayne.com>
 */
class MergedStreamIterator implements StreamIterator
{
    use TimSort;

    private array $iterators = [];

    private int $numberOfIterators;

    private array $originalIteratorOrder;

    public function __construct(array $streamNames, StreamIterator ...$iterators)
    {
        foreach ($iterators as $key => $iterator) {
            $this->iterators[$key][0] = $iterator;
            $this->iterators[$key][1] = $streamNames[$key];
        }
        $this->numberOfIterators = count($this->iterators);
        $this->originalIteratorOrder = $this->iterators;

        $this->prioritizeIterators();
    }

    public function current(): mixed
    {
        return $this->iterators[0][0]->current();
    }

    public function next(): void
    {
        $this->iterators[0][0]->next();

        $this->prioritizeIterators();
    }

    public function key(): mixed
    {
        return $this->iterators[0][0]->key();
    }

    public function valid(): bool
    {
        foreach ($this->iterators as $key => $iterator) {
            if ($iterator[0]->valid()) {
                return true;
            }
        }

        return false;
    }

    public function rewind(): void
    {
        foreach ($this->iterators as $iter) {
            $iter[0]->rewind();
        }

        $this->prioritizeIterators();
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->iterators as $iterator) {
            $count += count($iterator[0]);
        }

        return $count;
    }

    public function streamName(): string
    {
        return $this->iterators[0][1];
    }

    private function prioritizeIterators(): void
    {
        if ($this->numberOfIterators > 1) {
            $this->iterators = $this->originalIteratorOrder;

            $this->timSort($this->iterators, $this->numberOfIterators);
        }
    }
}
