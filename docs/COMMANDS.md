# Commands

The main command is `/world`; `/ws` is an alias. Bedriox generates command usage and client autocomplete from the same typed schemas used for parsing.

## Discovery

| Command | Description | Permission |
|---|---|---|
| `/world help [page]` | Lists commands available to the sender. | None |
| `/world version` | Shows plugin version, author, API target, and repository. | None |
| `/world list [loaded\|managed\|all]` | Lists worlds in the selected category. | `worldsmith.command.list` |
| `/world info [world]` | Shows runtime and configured world information. Players may omit the world. | `worldsmith.command.info` |
| `/world status [world]` | Shows lifecycle or pending-operation state. | `worldsmith.command.status` |

## Lifecycle

| Command | Description | Permission |
|---|---|---|
| `/world create <id> [generator] [seed] [difficulty]` | Creates and loads a world. Defaults: `default`, `0`, `normal`. | `worldsmith.command.create` |
| `/world load <world>` | Loads an existing world. | `worldsmith.command.load` |
| `/world save <world>` | Starts a non-blocking save. | `worldsmith.command.save` |
| `/world save-all` | Starts saves for all loaded worlds. | `worldsmith.command.saveall` |
| `/world unload <world> [save]` | Unloads an empty non-default world. | `worldsmith.command.unload` |
| `/world unload --force <world> [save]` | Evacuates occupants to the default world before unloading. | `worldsmith.command.unload.force` |

Lifecycle commands acknowledge the request immediately and report the final result from Bedriox's completion callback. A started message is not a success message.

## Teleportation

| Command | Description | Permission |
|---|---|---|
| `/world teleport <world>` | Teleports the sender to a world's native spawn. | `worldsmith.command.teleport` |
| `/world teleport <player> <world>` | Teleports another connected player. | `worldsmith.command.teleport.other` |
| `/world spawn [world]` | Teleports to a native world spawn. | `worldsmith.command.teleport` |
| `/world back` | Swaps with the previous Worldsmith teleport location. | `worldsmith.command.back` |

When allowed, an unloaded managed destination is lazy-loaded first. The sender receives separate loading and teleporting feedback, and the queued teleport resumes from Bedriox's authoritative world-loaded event. Worldsmith submits one teleport request and waits up to 10 seconds for Bedriox to publish the committed destination; it never retries while a world transfer is pending. Warmups may be cancelled by movement or damage. Cooldowns are measured from the committed teleport request.

Cross-world transfers require a Bedrock client/server handshake. Worldsmith displays a yellow in-progress message immediately, waits for authoritative confirmation, and only then displays green success. An unconfirmed request times out with a red failure instead of submitting a duplicate transfer.

## Configuration commands

| Command | Effect |
|---|---|
| `/world maintenance <world> <true\|false>` | Changes maintenance mode. |
| `/world autoload <world> <true\|false>` | Changes startup loading. |
| `/world lazyload <world> <true\|false>` | Changes on-demand loading. |
| `/world idleunload <world> <minutes>` | Changes empty-world unload delay; zero disables it. |
| `/world profile <world> <profile>` | Assigns a profile. |
| `/world reload` | Reloads config, worlds, profiles, and messages. |

Configuration commands require `worldsmith.command.configure`, except reload, which requires `worldsmith.command.reload`. Changes are saved atomically to `worlds.yml`.
