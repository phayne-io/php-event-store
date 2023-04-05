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

use Phayne\Exception\InvalidArgumentException;

use function is_array;
use function is_scalar;
use function is_string;
use function sprintf;

/**
 * Class MetadataMatcher
 *
 * @package Phayne\EventStore\Metadata
 * @author Julien Guittard <julien@phayne.com>
 */
class MetadataMatcher
{
    private array $data = [];

    public function data(): array
    {
        return $this->data;
    }

    public function withMetadataMatch(
        string $field,
        Operator $operator,
        mixed $value,
        FieldType $fieldType = FieldType::METADATA
    ): self {
        $this->validateValue($operator, $value);

        $self = clone $this;
        $self->data[] = ['field' => $field, 'operator' => $operator, 'value' => $value, 'fieldType' => $fieldType];

        return $self;
    }

    private function validateValue(Operator $operator, mixed $value): void
    {
        if (($operator === Operator::IN || $operator === Operator::NOT_IN)) {
            if (! is_array($value)) {
                throw new InvalidArgumentException(sprintf(
                    'Value must be an array for the operator %s.',
                    $operator->name
                ));
            } else {
                return;
            }
        }

        if ($operator === Operator::REGEX && ! is_string($value)) {
            throw new InvalidArgumentException('Value must be a string for the regex operator.');
        }

        if (! is_scalar($value)) {
            throw new InvalidArgumentException(sprintf(
                'Value must have a scalar type for the operator %s.',
                $operator->name
            ));
        }
    }
}
