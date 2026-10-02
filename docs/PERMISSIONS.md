# Permissions

| Permission | Purpose |
|---|---|
| `worldsmith.command.list` | List worlds. |
| `worldsmith.command.info` | Inspect world information. |
| `worldsmith.command.status` | Inspect lifecycle status. |
| `worldsmith.command.teleport` | Teleport self and use spawn. |
| `worldsmith.command.teleport.other` | Teleport another player. |
| `worldsmith.command.back` | Return to a previous location. |
| `worldsmith.command.create` | Create worlds. |
| `worldsmith.command.load` | Load worlds. |
| `worldsmith.command.save` | Save one world. |
| `worldsmith.command.saveall` | Save every loaded world. |
| `worldsmith.command.unload` | Unload empty worlds. |
| `worldsmith.command.unload.force` | Evacuate and unload occupied worlds. |
| `worldsmith.command.reload` | Reload configuration. |
| `worldsmith.command.configure` | Change managed-world settings. |
| `worldsmith.bypass.maintenance` | Enter maintenance worlds through Worldsmith. |
| `worldsmith.bypass.maximumplayers` | Ignore Worldsmith capacity limits. |
| `worldsmith.bypass.teleportwarmup` | Skip teleport warmup. |
| `worldsmith.bypass.teleportcooldown` | Skip teleport cooldown. |

World-specific permission strings are configured in `worlds.yml`. Bedriox operators and its permission store determine grants. Worldsmith does not create a parallel permission database.
