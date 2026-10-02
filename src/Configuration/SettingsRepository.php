<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\Configuration;

use Bedriox\Api\Plugin\Data\Configuration;
use Bedriox\Api\Plugin\Data\PluginData;
use XZeroMCPE\Worldsmith\Domain\PluginSettings;
use XZeroMCPE\Worldsmith\Domain\Profile;
use XZeroMCPE\Worldsmith\Domain\WorldDefinition;

final class SettingsRepository
{
    private Configuration $config;
    private Configuration $worldConfig;
    private Configuration $profileConfig;
    private PluginSettings $settings;

    /** @var array<string, WorldDefinition> */
    private array $worlds = [];

    /** @var array<string, Profile> */
    private array $profiles = [];

    public function __construct(private readonly PluginData $data)
    {
        $data->saveResources();
        $this->config = $data->config();
        $this->worldConfig = $data->config('worlds.yml');
        $this->profileConfig = $data->config('profiles.yml');
        $this->reload();
    }

    public function reload(): void
    {
        $this->config->reload();
        $this->worldConfig->reload();
        $this->profileConfig->reload();
        $this->settings = PluginSettings::fromArray($this->config->all());
        $this->worlds = $this->decodeWorlds($this->worldConfig->getMap('worlds'));
        $this->profiles = $this->decodeProfiles($this->profileConfig->getMap('profiles'));
        if (!isset($this->profiles['default'])) {
            $this->profiles['default'] = Profile::fromArray('default', []);
        }
    }

    public function settings(): PluginSettings
    {
        return $this->settings;
    }

    /** @return array<string, WorldDefinition> */
    public function worlds(): array
    {
        return $this->worlds;
    }

    public function world(string $id): ?WorldDefinition
    {
        return $this->worlds[strtolower($id)] ?? null;
    }

    public function profileFor(string $worldId): Profile
    {
        $profile = $this->world($worldId)->profile ?? 'default';

        return $this->profiles[$profile] ?? $this->profiles['default'];
    }

    /** @return list<string> */
    public function worldIds(): array
    {
        $ids = array_keys($this->worlds);
        sort($ids, SORT_STRING);

        return $ids;
    }

    public function putWorld(WorldDefinition $definition): void
    {
        $this->worlds[$definition->id] = $definition;
        ksort($this->worlds, SORT_STRING);
        $encoded = [];
        foreach ($this->worlds as $id => $world) {
            $encoded[$id] = $world->toArray();
        }
        $this->worldConfig->set('worlds', $encoded);
        $this->worldConfig->save();
    }

    /** @param array<string, mixed> $values
     * @return array<string, WorldDefinition>
     */
    private function decodeWorlds(array $values): array
    {
        $worlds = [];
        foreach ($values as $id => $definition) {
            if (!is_array($definition) || array_is_list($definition) && $definition !== []) {
                throw new \InvalidArgumentException("World definition {$id} must be a map.");
            }
            $mapped = $this->stringMap($definition, "World definition {$id}");
            $world = WorldDefinition::fromArray($id, $mapped);
            $worlds[$world->id] = $world;
        }

        return $worlds;
    }

    /** @param array<string, mixed> $values
     * @return array<string, Profile>
     */
    private function decodeProfiles(array $values): array
    {
        $profiles = [];
        foreach ($values as $name => $definition) {
            if (!is_array($definition) || array_is_list($definition) && $definition !== []) {
                throw new \InvalidArgumentException("Profile {$name} must be a map.");
            }
            $profiles[$name] = Profile::fromArray($name, $this->stringMap($definition, "Profile {$name}"));
        }

        return $profiles;
    }

    /** @param array<mixed, mixed> $values
     * @return array<string, mixed>
     */
    private function stringMap(array $values, string $label): array
    {
        $mapped = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException("{$label} must use string keys.");
            }
            $mapped[$key] = $value;
        }

        return $mapped;
    }
}
