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

namespace PhayneTest\EventStore\StreamIterator;

use ArrayIterator;
use Phayne\EventStore\StreamIterator\InMemoryStreamIterator;
use Phayne\EventStore\StreamIterator\StreamIterator;
use PHPUnit\Framework\TestCase;

/**
 * Class InMemoryStreamIteratorTest
 *
 * @package PhayneTest\EventStore\StreamIterator
 * @author Julien Guittard <julien@phayne.com>
 */
class InMemoryStreamIteratorTest extends TestCase
{
    private InMemoryStreamIterator $inMemoryStreamIterator;

    protected function setUp(): void
    {
        $this->inMemoryStreamIterator = new InMemoryStreamIterator();
    }

    public function testImplementation(): void
    {
        $this->assertInstanceOf(StreamIterator::class, $this->inMemoryStreamIterator);
        $this->assertInstanceOf(ArrayIterator::class, $this->inMemoryStreamIterator);
    }
}
