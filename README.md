<p align="center">
  <img src="assets/worldsmith-icon.png" alt="Worldsmith icon" width="180">
</p>

<h1 align="center">Worldsmith</h1>

<p align="center">
  Multi-world management for <a href="https://bedriox.com">Bedriox</a>.
</p>

Worldsmith gives server owners one place to create, load, configure, and move between worlds. Every managed world can have its own access rules and gameplay profile.

## Features

- Create, load, save, and unload worlds in-game
- Teleport yourself or another player to a world's native spawn
- Lazy-load an unloaded world from a teleport command
- Autoload selected worlds when the plugin starts
- Save and unload empty worlds after a configurable delay
- Return to your previous Worldsmith location with `/world back`
- Route joins and respawns to managed worlds
- Set maintenance mode, access permissions, and player limits per world
- Reuse profiles for game mode, weather, building, PvP, and item rules
- Configure teleport warmups, cooldowns, and cancellation behavior
- Customize every routine message in `messages.yml`
- Record completed administrative operations in an audit log

## Requirements

- Bedriox with plugin API `^0.4`
- PHP 8.4 or newer

## First start

On its first start, Worldsmith copies its default files to `plugin_data/Worldsmith/`. Existing configuration is left untouched when the plugin starts again.

## Getting started

Create a world and teleport to it:

```text
/world create survival
/world teleport survival
```

If a managed world is unloaded and has lazy loading enabled, one teleport command is enough. Worldsmith reports that the world is loading, waits for Bedriox's world-loaded event, and continues the teleport automatically.

Useful commands:

```text
/world help
/world list
/world info
/world status survival
/world spawn survival
/world back
```

`/ws` is available as a shorter alias for `/world`.

## Managing worlds

World settings live in `plugin_data/Worldsmith/worlds.yml`:

```yaml
worlds:
  survival:
    autoload: true
    lazy-load: true
    idle-unload-minutes: 0
    profile: default
    maintenance: false
    access-permission: ''
    maximum-players: 0
    join-destination: false
    respawn-world: ''
```

After editing configuration files, apply the changes with:

```text
/world reload
```

Worldsmith will not create a missing world just because its name appears in `worlds.yml`. Use `/world create` for a new world or `/world load` for an existing Bedrock world.

## Configuration files

| File | Purpose |
|---|---|
| `config.yml` | Teleports, lifecycle timing, routing, and auditing |
| `worlds.yml` | Managed worlds, loading behavior, access, and capacity |
| `profiles.yml` | Reusable gameplay and weather rules |
| `messages.yml` | Prefix and user-facing messages |

## Documentation

- [Commands](docs/COMMANDS.md)
- [Configuration](docs/CONFIGURATION.md)
- [Permissions](docs/PERMISSIONS.md)
- [Architecture and safety](docs/ARCHITECTURE.md)
- [Contributing](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)

## License

Worldsmith is released under the [GPL-3.0-only](LICENSE) license.
