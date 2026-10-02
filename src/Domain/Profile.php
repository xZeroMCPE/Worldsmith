<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Domain;

final readonly class Profile
{
    public function __construct(
        public string $name,
        public string $gameMode,
        public bool $lockWeather,
        public string $weather,
        public bool $allowBuilding,
        public bool $allowBlockBreaking,
        public bool $allowPvp,
        public bool $allowItemDropping,
        public bool $allowItemPickup,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(string $name, array $values): self
    {
        return new self(
            $name,
            self::choice($values, 'game-mode', ['', 'survival', 'creative', 'adventure', 'spectator'], ''),
            self::bool($values, 'lock-weather'),
            self::choice($values, 'weather', ['clear', 'rain', 'thunder'], 'clear'),
            self::bool($values, 'allow-building', true),
            self::bool($values, 'allow-block-breaking', true),
            self::bool($values, 'allow-pvp', true),
            self::bool($values, 'allow-item-dropping', true),
            self::bool($values, 'allow-item-pickup', true),
        );
    }

    /** @param array<string, mixed> $values */
    private static function string(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Profile setting {$key} must be a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function bool(array $values, string $key, bool $default = false): bool
    {
        $value = $values[$key] ?? $default;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Profile setting {$key} must be a boolean.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values
     * @param list<string> $choices
     */
    private static function choice(array $values, string $key, array $choices, string $default): string
    {
        $value = self::string($values, $key, $default);
        if (!in_array($value, $choices, true)) {
            throw new \InvalidArgumentException("Profile setting {$key} contains an unsupported value.");
        }

        return $value;
    }
}
