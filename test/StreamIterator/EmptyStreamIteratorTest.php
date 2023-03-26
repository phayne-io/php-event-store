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

use EmptyIterator;
use Phayne\EventStore\StreamIterator\EmptyStreamIterator;
use Phayne\EventStore\StreamIterator\StreamIterator;
use PHPUnit\Framework\TestCase;

/**
 * Class EmptyStreamIteratorTest
 *
 * @package PhayneTest\EventStore\StreamIterator
 * @author Julien Guittard <julien@phayne.com>
 */
class EmptyStreamIteratorTest extends TestCase
{
    private EmptyStreamIterator $emptyStreamIterator;

    protected function setUp(): void
    {
        $this->emptyStreamIterator = new EmptyStreamIterator();
    }

    public function testImplementation(): void
    {
        $this->assertInstanceOf(StreamIterator::class, $this->emptyStreamIterator);
        $this->assertInstanceOf(StreamIterator::class, $this->emptyStreamIterator);
    }

    public function testCount(): void
    {
        $this->assertCount(0, $this->emptyStreamIterator);
    }
}
