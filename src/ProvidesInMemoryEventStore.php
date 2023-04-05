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

namespace Phayne\EventStore;

use Assert\Assertion;
use Iterator;
use Phayne\EventStore\Exception\StreamNotFound;
use Phayne\EventStore\Metadata\FieldType;
use Phayne\EventStore\Metadata\MetadataMatcher;
use Phayne\EventStore\Metadata\Operator;
use Phayne\EventStore\StreamIterator\EmptyStreamIterator;
use Phayne\EventStore\StreamIterator\InMemoryStreamIterator;
use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\UnexpectedValueException;
use Phayne\Messaging\Messaging\Message;

use function array_key_exists;
use function array_keys;
use function array_reduce;
use function array_unique;
use function in_array;
use function ksort;
use function preg_match;

/**
 * Trait ProvidesInMemoryEventStore
 *
 * @package Phayne\EventStore
 * @author Julien Guittard <julien@phayne.com>
 */
trait ProvidesInMemoryEventStore
{
    private array $streams = [];

    private array $cachedStreams = [];

    public function delete(StreamName $streamName): void
    {
        $streamNameString = (string)$streamName;

        if (isset($this->streams[$streamNameString])) {
            unset($this->streams[$streamNameString]);
        } else {
            throw StreamNotFound::with($streamName);
        }
    }

    public function fetchStreamMetadata(StreamName $streamName): array
    {
        if (! isset($this->streams[(string)$streamName])) {
            throw StreamNotFound::with($streamName);
        }

        return $this->streams[(string)$streamName]['metadata'];
    }

    public function updateStreamMetadata(StreamName $streamName, array $newMetadata): void
    {
        if (! isset($this->streams[(string)$streamName])) {
            throw StreamNotFound::with($streamName);
        }

        $this->streams[(string)$streamName]['metadata'] = $newMetadata;
    }

    public function hasStream(StreamName $streamName): bool
    {
        return isset($this->streams[(string)$streamName]);
    }

    public function load(
        StreamName $streamName,
        int $fromNumber = 1,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator {
        Assertion::greaterOrEqualThan($fromNumber, 1);
        Assertion::nullOrGreaterOrEqualThan($count, 1);

        if (! isset($this->streams[(string)$streamName])) {
            throw StreamNotFound::with($streamName);
        }

        if (null === $metadataMatcher) {
            $metadataMatcher = new MetadataMatcher();
        }

        $found = 0;
        $streamEvents = [];

        foreach ($this->streams[(string)$streamName]['events'] as $key => $streamEvent) {
            /* @var Message $streamEvent */
            if (
                ($key + 1) >= $fromNumber &&
                $this->matchesMetadata($metadataMatcher, $streamEvent->metadata()) &&
                $this->matchesMessagesProperty($metadataMatcher, $streamEvent)
            ) {
                ++$found;
                $streamEvents[] = $streamEvent;

                if ($found === $count) {
                    break;
                }
            }
        }

        if (0 === $found) {
            return new EmptyStreamIterator();
        }

        return new InMemoryStreamIterator($streamEvents);
    }

    public function loadReverse(
        StreamName $streamName,
        int $fromNumber = null,
        int $count = null,
        MetadataMatcher $metadataMatcher = null
    ): Iterator {
        if (null === $fromNumber) {
            $fromNumber = PHP_INT_MAX;
        }

        Assertion::greaterOrEqualThan($fromNumber, 1);
        Assertion::nullOrGreaterOrEqualThan($count, 1);

        if (! isset($this->streams[(string)$streamName])) {
            throw StreamNotFound::with($streamName);
        }

        if (null === $metadataMatcher) {
            $metadataMatcher = new MetadataMatcher();
        }

        $found = 0;
        $streamEvents = [];

        foreach (array_reverse($this->streams[(string)$streamName]['events'], true) as $key => $streamEvent) {
            /* @var Message $streamEvent */
            if (
                ($key + 1) <= $fromNumber &&
                $this->matchesMetadata($metadataMatcher, $streamEvent->metadata()) &&
                $this->matchesMessagesProperty($metadataMatcher, $streamEvent)
            ) {
                $streamEvents[] = $streamEvent;
                ++$found;

                if ($found === $count) {
                    break;
                }
            }
        }

        if (0 === $found) {
            return new EmptyStreamIterator();
        }

        return new InMemoryStreamIterator($streamEvents);
    }

    public function fetchStreamNames(
        ?string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array {
        $result = [];

        $skipped = 0;
        $found = 0;

        $streams = $this->streams;

        if (
            $filter &&
            array_key_exists($filter, $streams) &&
            (
                ! $metadataMatcher ||
                $this->matchesMetadata($metadataMatcher, $streams[$filter]['metadata'])
            )
        ) {
            return [$filter];
        }

        ksort($streams);

        foreach ($streams as $streamName => $data) {
            if (null === $filter || $filter === $streamName) {
                if ($offset > $skipped) {
                    ++$skipped;
                    continue;
                }

                if ($metadataMatcher && ! $this->matchesMetadata($metadataMatcher, $data['metadata'])) {
                    continue;
                }

                $result[] = new StreamName($streamName);
                ++$found;
            }

            if ($found === $limit) {
                break;
            }
        }

        return $result;
    }

    public function fetchStreamNamesRegex(
        string $filter,
        ?MetadataMatcher $metadataMatcher,
        int $limit = 20,
        int $offset = 0
    ): array {
        if (false === @preg_match("/$filter/", '')) {
            throw new InvalidArgumentException('Invalid regex pattern given');
        }

        $result = [];
        $found = 0;
        $streams = $this->streams;
        ksort($streams);

        foreach ($streams as $streamName => $data) {
            if (! preg_match("/$filter/", $streamName)) {
                continue;
            }

            if ($metadataMatcher && ! $this->matchesMetadata($metadataMatcher, $data['metadata'])) {
                continue;
            }

            $result[] = new StreamName($streamName);
            ++$found;

            if ($found === $limit) {
                break;
            }
        }

        return $result;
    }

    public function fetchCategoryNames(?string $filter, int $limit = 20, int $offset = 0): array
    {
        $result = [];

        $skipped = 0;
        $found = 0;

        $categories = array_unique(array_reduce(
            array_keys($this->streams),
            function (array $result, string $streamName): array {
                if (preg_match('/^(.+)-.+$/', $streamName, $matches)) {
                    $result[] = $matches[1];
                }

                return $result;
            },
            []
        ));

        if ($filter && in_array($filter, $categories, true)) {
            return [$filter];
        }

        ksort($categories);

        foreach ($categories as $category) {
            if (null === $filter || $filter === $category) {
                if ($offset > $skipped) {
                    ++$skipped;
                    continue;
                }

                $result[] = $category;
                ++$found;
            }

            if ($found === $limit) {
                break;
            }
        }

        return $result;
    }

    public function fetchCategoryNamesRegex(string $filter, int $limit = 20, int $offset = 0): array
    {
        if (false === @preg_match("/$filter/", '')) {
            throw new InvalidArgumentException('Invalid regex pattern given');
        }

        $result = [];

        $skipped = 0;
        $found = 0;

        $categories = array_unique(array_reduce(
            array_keys($this->streams),
            function (array $result, string $streamName): array {
                if (preg_match('/^(.+)-.+$/', $streamName, $matches)) {
                    $result[] = $matches[1];
                }

                return $result;
            },
            []
        ));

        ksort($categories);

        foreach ($categories as $category) {
            if (! preg_match("/$filter/", $category)) {
                continue;
            }

            if ($offset > $skipped) {
                ++$skipped;
                continue;
            }

            $result[] = $category;
            ++$found;

            if ($found === $limit) {
                break;
            }
        }

        return $result;
    }

    private function matchesMetadata(MetadataMatcher $metadataMatcher, array $metadata): bool
    {
        foreach ($metadataMatcher->data() as $match) {
            if ($match['fieldType'] !== FieldType::METADATA) {
                continue;
            }

            $field = $match['field'];

            if (! isset($metadata[$field])) {
                return false;
            }

            if (! $match['operator']->match($metadata[$field], $match['value'])) {
                return false;
            }
        }

        return true;
    }

    private function matchesMessagesProperty(MetadataMatcher $metadataMatcher, Message $message): bool
    {
        foreach ($metadataMatcher->data() as $match) {
            if ($match['fieldType'] !== FieldType::MESSAGE_PROPERTY) {
                continue;
            }

            $value = match ($match['field']) {
                'uuid' => $message->uuid()->toString(),
                'event_name', 'message_name', 'messageName' => $message->messageName(),
                'created_at', 'createdAt' => $message->createdAt()->format('Y-m-d\TH:i:s.u'),
                default => throw new UnexpectedValueException(sprintf(
                    'Unexpected field "%s" given',
                    $match['field']
                )),
            };

            if (! $match['operator']->match($value, $match['value'])) {
                return false;
            }
        }

        return true;
    }
}
