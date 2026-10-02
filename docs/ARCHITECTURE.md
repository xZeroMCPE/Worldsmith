# Architecture and safety

## Components

- `Main` installs resources and composes services.
- `SettingsRepository` owns parsed global settings, managed worlds, and profiles.
- `WorldService` coordinates asynchronous lifecycle operations, teleportation, startup loading, idle unloading, saves, and autocomplete.
- `WorldCommand` defines every typed command overload and performs permission checks.
- `WorldPolicySubscriber` implements profiles, routing, warmup cancellation, and respawn selection through public events.
- `MessageCatalog` renders configurable messages.
- `AuditLogger` mirrors completed administration to the plugin logger and optional audit file.

## Lifecycle rules

Bedriox world operations are non-blocking. Worldsmith records an operation per world, attaches exactly one completion callback, and clears tracking only when that callback runs. Startup autoloading is deliberately sequential. Lazy teleports are queued by player UUID and resume from `WorldLoadedEvent` with a fresh player snapshot after the destination runtime becomes authoritative.

The default world is never unloaded. Ordinary unload refuses occupied worlds. Forced unload requests authoritative teleports to the default spawn, waits two ticks, verifies that the exact world generation is empty, and only then submits unload. Retained world handles are checked against the current load generation.

Worldsmith treats player teleports as asynchronous requests. A green success message is sent only after Bedriox publishes a player snapshot at the requested world and coordinates. Each command submits exactly one request; a missing confirmation times out after 10 seconds instead of submitting a competing request during an active world transfer. Duplicate commands are rejected while confirmation is pending, and disconnect or plugin shutdown cancels verification work.

## Boundaries

Production imports are restricted to `Bedriox\Api`. Worldsmith does not enumerate directories, mutate `level.dat`, access network packets, or retain internal simulation objects. Writable files remain in the path supplied by `PluginContext::data()`.

## Failure behavior

Malformed configuration fails plugin enablement with a clear exception. World operation failures are converted to bounded messages supplied by Bedriox. Unknown or unloaded teleport targets never trigger creation. If a player disconnects during lazy loading or warmup, the continuation resolves the current player by UUID and stops when absent.

## Current API limitations

- Permission checks exist on command senders, not immutable player event snapshots. Access permissions and bypasses are therefore enforced for Worldsmith commands, not claimed as universal rules for foreign-plugin teleports.
- Native spawn mutation, deletion, cloning, renaming, import, backup, and restore are unavailable in the public world API.
- Respawn routing requires its destination to already be loaded because the respawn event is synchronous.
- `/world back` locations are intentionally memory-only in 0.1.0 and disappear after restart.
