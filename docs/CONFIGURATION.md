# Configuration

Worldsmith installs four default resources under `plugin_data/Worldsmith/`. Existing files are not overwritten.

## config.yml

```yaml
config-version: 1
teleport:
  warmup-seconds: 0
  cooldown-seconds: 0
  cancel-on-move: true
  cancel-on-damage: true
lifecycle:
  idle-check-seconds: 30
  save-interval-minutes: 15
routing:
  join-world: ''
  route-every-join: false
  default-respawn-world: ''
audit:
  enabled: true
```

- `warmup-seconds`: Delay before Worldsmith teleports, from 0 through 300.
- `cooldown-seconds`: Delay between Worldsmith teleports, from 0 through 86400.
- `cancel-on-move`: Cancels a pending warmup after positional movement.
- `cancel-on-damage`: Cancels a pending warmup when damage intent occurs.
- `idle-check-seconds`: Frequency of empty-world checks, from 5 through 3600.
- `save-interval-minutes`: Automatic save interval; zero disables scheduled saves.
- `join-world`: Managed destination used when join routing is enabled.
- `route-every-join`: Routes every admitted player to `join-world`.
- `default-respawn-world`: Loaded world used as the global respawn override.
- `audit.enabled`: Appends administrative completions to `audit.log`.

## worlds.yml

World IDs are canonical lowercase identifiers supported by Bedriox.

```yaml
worlds:
  creative:
    autoload: false
    lazy-load: true
    idle-unload-minutes: 15
    profile: default
    maintenance: false
    access-permission: worldsmith.world.creative
    maximum-players: 20
    join-destination: false
    respawn-world: lobby
```

- `autoload`: Loads sequentially when Worldsmith enables.
- `lazy-load`: Permits Worldsmith teleport commands to load the destination.
- `idle-unload-minutes`: Saves and unloads after remaining empty; zero disables it.
- `profile`: Name from `profiles.yml`; missing names fall back to `default`.
- `maintenance`: Denies Worldsmith teleport commands without the bypass permission.
- `access-permission`: Permission required by Worldsmith teleport commands; empty means public.
- `maximum-players`: Capacity enforced by Worldsmith teleport commands; zero is unlimited.
- `join-destination`: Reserved metadata for lobby selection and management UIs.
- `respawn-world`: Loaded destination used when respawning from this world.

Worldsmith does not create a missing world merely because it appears here. Use `/world create` first or place an existing Bedrock world where Bedriox expects it and run `/world load`.

## profiles.yml

Profiles are reusable, opt-in policies:

```yaml
profiles:
  arena:
    game-mode: adventure
    lock-weather: true
    weather: clear
    allow-building: false
    allow-block-breaking: false
    allow-pvp: true
    allow-item-dropping: false
    allow-item-pickup: true
```

`game-mode` accepts an empty value, `survival`, `creative`, `adventure`, or `spectator`. Weather accepts `clear`, `rain`, or `thunder`. Gameplay booleans are enforced through cancellable Bedriox events. Policies apply to every player because the current immutable Player API does not expose permission checks; command-level access bypasses remain supported through `CommandSender`.

## messages.yml

All routine Worldsmith command messages are configurable. Supported placeholders include `{world}`, `{player}`, `{operation}`, `{failure}`, `{seconds}`, and `{setting}`. The `prefix` value is prepended to catalog messages. Worldsmith applies consistent semantic colors after loading the text: green for confirmed success, red for failures and denials, yellow for pending work and warnings, aqua for information, gray for details, and gold for headings and the prefix.

## Reloading

Run `/world reload`. Malformed files or wrong value types fail explicitly rather than silently coercing values. Resource defaults are never reapplied over existing files during reload.
