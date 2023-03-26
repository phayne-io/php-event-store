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

namespace Phayne\EventStore\Metadata;

use function in_array;

/**
 * Enum Operator
 *
 * @package Phayne\EventStore\Metadata
 * @author Julien Guittard <julien@phayne.com>
 */
enum Operator: string
{
    case EQUALS = '=';
    case GREATER_THAN = '>';
    case GREATER_THAN_EQUALS = '>=';
    case IN = 'in';
    case LOWER_THAN = '<';
    case LOWER_THAN_EQUALS = '<=';
    case NOT_EQUALS = '!=';
    case NOT_IN = 'nin';
    case REGEX = 'regex';

    public function match(mixed $value, mixed $expected): bool
    {
        return Operator::doMatch($this, $value, $expected);
    }

    public static function doMatch(self $enum, mixed $value, mixed $expected): bool
    {
        return match ($enum) {
            Operator::EQUALS => $expected === $value,
            Operator::GREATER_THAN => $expected > $value,
            Operator::GREATER_THAN_EQUALS => $expected >= $value,
            Operator::IN => in_array($value, $expected, true),
            Operator::LOWER_THAN => $expected < $value,
            Operator::LOWER_THAN_EQUALS => $expected <= $value,
            Operator::NOT_EQUALS => $expected !== $value,
            Operator::NOT_IN => ! in_array($value, $expected, true),
            Operator::REGEX => preg_match('/' . $expected . '/', $value),
        };
    }
}
