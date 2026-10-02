<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Message;

use Bedriox\Api\Plugin\Data\Configuration;
use Bedriox\Api\TextFormat;

final readonly class MessageCatalog
{
    private const array DEFAULTS = [
        'teleport-started' => 'Teleporting you to {world} now...',
        'teleport-failed' => 'Could not teleport to {world}: {failure}.',
        'teleport-in-progress' => 'A teleport is already in progress.',
        'world-loading' => 'World {world} is not loaded. Loading it now...',
    ];

    private const array SUCCESS = [
        'operation-succeeded',
        'teleported',
        'teleporting-other',
        'reloaded',
        'saved-setting',
    ];

    private const array WARNING = [
        'teleport-warmup',
        'operation-started',
        'teleport-started',
        'world-loading',
    ];

    private const array ERROR = [
        'no-permission',
        'player-only',
        'world-not-found',
        'world-not-loaded',
        'operation-failed',
        'teleport-failed',
        'teleport-in-progress',
        'teleport-cancelled',
        'teleport-cooldown',
        'access-denied',
        'maintenance',
        'world-full',
        'no-return',
    ];

    public function __construct(private Configuration $configuration) {}

    /** @param array<string, int|string> $replacements */
    public function get(string $key, array $replacements = []): string
    {
        $message = $this->configuration->getString($key, self::DEFAULTS[$key] ?? $key);
        foreach ($replacements as $name => $value) {
            $message = str_replace('{' . $name . '}', (string) $value, $message);
        }

        $color = match (true) {
            in_array($key, self::SUCCESS, true) => TextFormat::GREEN,
            in_array($key, self::WARNING, true) => TextFormat::YELLOW,
            in_array($key, self::ERROR, true) => TextFormat::RED,
            default => TextFormat::AQUA,
        };

        return $this->render($message, $color);
    }

    public function heading(string $message): string
    {
        return $this->render($message, TextFormat::GOLD);
    }

    public function info(string $message): string
    {
        return $this->render($message, TextFormat::AQUA);
    }

    public function detail(string $message): string
    {
        return $this->render($message, TextFormat::GRAY);
    }

    public function error(string $message): string
    {
        return $this->render($message, TextFormat::RED);
    }

    public function reload(): void
    {
        $this->configuration->reload();
    }

    private function render(string $message, string $color): string
    {
        $prefix = $this->configuration->getString('prefix', '');

        return TextFormat::GOLD . $prefix . $color . $message . TextFormat::RESET;
    }
}
