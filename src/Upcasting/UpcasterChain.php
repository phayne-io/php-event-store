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

namespace Phayne\EventStore\Upcasting;

use Phayne\Messaging\Messaging\Message;

use function array_merge;

/**
 * Class UpcasterChain
 *
 * @package Phayne\EventStore\Upcasting
 * @author Julien Guittard <julien@phayne.com>
 */
class UpcasterChain implements Upcaster
{
    private array $upcasters;

    public function __construct(Upcaster ...$upcasters)
    {
        $this->upcasters = $upcasters;
    }

    public function upcast(Message $message): array
    {
        $result = [];
        $messages = [$message];

        foreach ($this->upcasters as $upcaster) {
            $result = [];

            foreach ($messages as $message) {
                $result = array_merge($result, $upcaster->upcast($message));
            }

            $messages = $result;
        }

        return $result;
    }
}
