# Changelog

## 0.1.0 - 2026-10-02

- Confirm teleports against authoritative Bedriox state without submitting duplicate requests while a world transfer is pending.
- Give players explicit feedback while a destination world is loading and when teleporting begins.
- Resume lazy-load teleports from Bedriox's authoritative world-loaded event with a fresh player snapshot.
- Apply consistent semantic colors to command output and stop reporting teleport success before a cross-world transfer completes.
- Add typed `/world` and `/ws` command suite.
- Add asynchronous create, load, save, unload, startup autoload, lazy load, idle unload, and scheduled save workflows.
- Add native-spawn teleports, warmups, cooldowns, return locations, forced evacuation, join routing, and respawn routing.
- Add managed-world access, maintenance, capacity, and profile configuration.
- Add game-mode, weather, building, block breaking, PvP, item drop, and pickup policies.
- Add bundled API 0.4 resources, customizable messages, auditing, documentation, and tests.
