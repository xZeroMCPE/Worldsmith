<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Domain;

final readonly class WorldDefinition
{
    public function __construct(
        public string $id,
        public bool $autoload,
        public bool $lazyLoad,
        public int $idleUnloadMinutes,
        public string $profile,
        public bool $maintenance,
        public string $accessPermission,
        public int $maximumPlayers,
        public bool $joinDestination,
        public string $respawnWorld,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/D', $id) !== 1) {
            throw new \InvalidArgumentException("Invalid world ID {$id}.");
        }
        if ($idleUnloadMinutes < 0 || $idleUnloadMinutes > 10080) {
            throw new \InvalidArgumentException('Idle unload must be between 0 and 10080 minutes.');
        }
        if ($maximumPlayers < 0 || $maximumPlayers > 100000) {
            throw new \InvalidArgumentException('Maximum players must be between 0 and 100000.');
        }
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(string $id, array $values): self
    {
        return new self(
            strtolower($id),
            self::bool($values, 'autoload'),
            self::bool($values, 'lazy-load', true),
            self::int($values, 'idle-unload-minutes'),
            self::string($values, 'profile', 'default'),
            self::bool($values, 'maintenance'),
            self::string($values, 'access-permission'),
            self::int($values, 'maximum-players'),
            self::bool($values, 'join-destination'),
            self::string($values, 'respawn-world'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'autoload' => $this->autoload,
            'lazy-load' => $this->lazyLoad,
            'idle-unload-minutes' => $this->idleUnloadMinutes,
            'profile' => $this->profile,
            'maintenance' => $this->maintenance,
            'access-permission' => $this->accessPermission,
            'maximum-players' => $this->maximumPlayers,
            'join-destination' => $this->joinDestination,
            'respawn-world' => $this->respawnWorld,
        ];
    }

    public function with(string $setting, bool|int|string $value): self
    {
        $data = $this->toArray();
        $data[$setting] = $value;

        return self::fromArray($this->id, $data);
    }

    /** @param array<string, mixed> $values */
    private static function string(array $values, string $key, string $default = ''): string
    {
        $value = $values[$key] ?? $default;
        if (!is_string($value)) {
            throw new \InvalidArgumentException("World setting {$key} must be a string.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function bool(array $values, string $key, bool $default = false): bool
    {
        $value = $values[$key] ?? $default;
        if (!is_bool($value)) {
            throw new \InvalidArgumentException("World setting {$key} must be a boolean.");
        }

        return $value;
    }

    /** @param array<string, mixed> $values */
    private static function int(array $values, string $key, int $default = 0): int
    {
        $value = $values[$key] ?? $default;
        if (!is_int($value)) {
            throw new \InvalidArgumentException("World setting {$key} must be an integer.");
        }

        return $value;
    }
}
