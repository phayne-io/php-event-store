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

namespace PhayneTest\EventStore\Mock;

use DateTimeImmutable;
use Phayne\Messaging\Messaging\DomainEvent;
use Phayne\Messaging\Messaging\PayloadConstructable;
use Phayne\Messaging\Messaging\PayloadTrait;

/**
 * Class TestDomainEvent
 *
 * @package PhayneTest\EventStore\Mock
 * @author Julien Guittard <julien@phayne.com>
 */
class TestDomainEvent extends DomainEvent implements PayloadConstructable
{
    use PayloadTrait;

    public static function with(array $payload, int $version): TestDomainEvent
    {
        $event = new static($payload);

        return $event->withVersion($version);
    }

    public static function withPayloadAndSpecifiedCreatedAt(
        array $payload,
        int $version,
        DateTimeImmutable $createdAt
    ): TestDomainEvent {
        $event = new static($payload);
        $event->createdAt = $createdAt;

        return $event->withVersion($version);
    }

    public function withVersion(int $version): TestDomainEvent
    {
        return $this->withAddedMetadata('_aggregate_version', $version);
    }

    public function version(): int
    {
        return $this->metadata['_aggregate_version'];
    }
}
