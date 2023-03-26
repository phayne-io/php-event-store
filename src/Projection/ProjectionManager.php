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

namespace Phayne\EventStore\Projection;

use Phayne\EventStore\Exception\ProjectionNotFound;

/**
 * Interface ProjectionManager
 *
 * @package Phayne\EventStore\Projection
 * @author Julien Guittard <julien@phayne.com>
 */
interface ProjectionManager
{
    public function createQuery(): Query;

    public function createProjection(
        string $name,
        array $options = []
    ): Projector;

    public function createReadModelProjection(
        string $name,
        ReadModel $readModel,
        array $options = []
    ): ReadModelProjector;

    /**
     * @throws ProjectionNotFound
     */
    public function deleteProjection(string $name, bool $deleteEmittedEvents): void;

    /**
     * @throws ProjectionNotFound
     */
    public function resetProjection(string $name): void;

    /**
     * @throws ProjectionNotFound
     */
    public function stopProjection(string $name): void;

    /**
     * @return string[]
     */
    public function fetchProjectionNames(?string $filter, int $limit = 20, int $offset = 0): array;

    /**
     * @return string[]
     */
    public function fetchProjectionNamesRegex(string $regex, int $limit = 20, int $offset = 0): array;

    /**
     * @throws ProjectionNotFound
     */
    public function fetchProjectionStatus(string $name): ProjectionStatus;

    /**
     * @throws ProjectionNotFound
     */
    public function fetchProjectionStreamPositions(string $name): array;

    /**
     * @throws ProjectionNotFound
     */
    public function fetchProjectionState(string $name): array;
}
