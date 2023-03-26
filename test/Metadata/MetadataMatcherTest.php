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

namespace PhayneTest\EventStore\Metadata;

use Phayne\EventStore\Metadata\FieldType;
use Phayne\EventStore\Metadata\MetadataMatcher;
use Phayne\EventStore\Metadata\Operator;
use Phayne\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Class MetadataMatcherTest
 *
 * @package PhayneTest\EventStore\Metadata
 * @author Julien Guittard <julien@phayne.com>
 */
class MetadataMatcherTest extends TestCase
{
    public function testThrowsOnInvalidValueForInOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be an array for the operator IN');

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher->withMetadataMatch('foo', Operator::IN, 'bar', FieldType::METADATA);
    }

    public function testThrowsOnInvalidValueForNotInOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be an array for the operator NOT_IN.');

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher->withMetadataMatch('foo', Operator::NOT_IN, 'bar', FieldType::METADATA);
    }

    public function testThrowsOnInvalidValueForRegexOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be a string for the regex operator.');

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher->withMetadataMatch('foo', Operator::REGEX, false, FieldType::METADATA);
    }

    public function testThrowsOnInvalidValueForEqualsOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must have a scalar type for the operator EQUALS.');

        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher->withMetadataMatch('foo', Operator::EQUALS, ['bar' => 'baz'], FieldType::METADATA);
    }

    public function testMapMetadataData()
    {
        $metadataMatcher = new MetadataMatcher();
        $metadataMatcher = $metadataMatcher->withMetadataMatch('foo', Operator::EQUALS, 'bar');
        $this->assertEquals(
            ['field' => 'foo', 'operator' => Operator::EQUALS, 'value' => 'bar', 'fieldType' => FieldType::METADATA],
            $metadataMatcher->data()[0]
        );
    }
}
