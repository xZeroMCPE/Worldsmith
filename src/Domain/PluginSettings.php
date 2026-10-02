<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Domain;

final readonly class PluginSettings
{
    public function __construct(
        public int $warmupSeconds,
        public int $cooldownSeconds,
        public bool $cancelOnMove,
        public bool $cancelOnDamage,
        public int $idleCheckSeconds,
        public int $saveIntervalMinutes,
        public string $joinWorld,
        public bool $routeEveryJoin,
        public string $defaultRespawnWorld,
        public bool $auditEnabled,
    ) {}

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        return new self(
            self::boundedInt($values, 'teleport', 'warmup-seconds', 0, 300),
            self::boundedInt($values, 'teleport', 'cooldown-seconds', 0, 86400),
            self::nestedBool($values, 'teleport', 'cancel-on-move', true),
            self::nestedBool($values, 'teleport', 'cancel-on-damage', true),
            self::boundedInt($values, 'lifecycle', 'idle-check-seconds', 5, 3600, 30),
            self::boundedInt($values, 'lifecycle', 'save-interval-minutes', 0, 1440, 15),
            self::nestedString($values, 'routing', 'join-world'),
            self::nestedBool($values, 'routing', 'route-every-join'),
            self::nestedString($values, 'routing', 'default-respawn-world'),
            self::nestedBool($values, 'audit', 'enabled', true),
        );
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private static function section(array $values, string $section): array
    {
        $value = $values[$section] ?? [];
        if (!is_array($value) || array_is_list($value) && $value !== []) {
            throw new \InvalidArgumentException("Configuration section {$section} must be a map.");
        }

        $result = [];
        foreach ($value as $key => $entry) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException("Configuration section {$section} must use string keys.");
            }
            $result[$key] = $entry;
        }

        return $result;
    }

    /** @param array<string, mixed> $values */
    private static function nestedBool(array $values, string $section, string $key, bool $default = false): bool
    {
        $value = self::section($values, $section)[$key] ?? $default;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("Configuration setting {$section}.{$key} must be a boolean.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function nestedString(array $values, string $section, string $key, string $default = ''): string
    {
        $value = self::section($values, $section)[$key] ?? $default;
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Configuration setting {$section}.{$key} must be a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function boundedInt(array $values, string $section, string $key, int $minimum, int $maximum, int $default = 0): int
    {
        $value = self::section($values, $section)[$key] ?? $default;
        if (!is_int($value) || $value < $minimum || $value > $maximum) {
            throw new \InvalidArgumentException("Configuration setting {$section}.{$key} is outside its supported range.");
        }

        return $value;
    }
}
