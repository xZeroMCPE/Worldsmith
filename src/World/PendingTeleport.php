<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\World;

use Bedriox\Api\Command\CommandSender;
use Bedriox\Api\Scheduler\TaskHandle;
use Bedriox\Api\World\Position;

final class PendingTeleport
{
    public ?TaskHandle $verification = null;

    public function __construct(
        public readonly string $playerUuid,
        public readonly string $playerName,
        public readonly CommandSender $sender,
        public readonly Position $origin,
        public readonly Position $destination,
    ) {}
}
