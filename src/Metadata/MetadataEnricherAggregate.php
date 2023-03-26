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

use Assert\Assertion;
use Phayne\Messaging\Messaging\Message;

/**
 * Class MetadataEnricherAggregate
 *
 * @package Phayne\EventStore\Metadata
 * @author Julien Guittard <julien@phayne.com>
 */
final class MetadataEnricherAggregate implements MetadataEnricher
{
    public function __construct(private readonly array $metadataEnrichers)
    {
        Assertion::allIsInstanceOf($this->metadataEnrichers, MetadataEnricher::class);
    }

    public function enrich(Message $message): Message
    {
        foreach ($this->metadataEnrichers as $metadataEnricher) {
            $message = $metadataEnricher->enrich($message);
        }

        return $message;
    }
}
