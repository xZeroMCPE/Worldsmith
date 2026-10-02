<?php

declare(strict_types=1);

namespace XZeroMCPE\Worldsmith\World;

use Bedriox\Api\Command\CommandSender;
use Bedriox\Api\Command\CommandSoftEnum;
use Bedriox\Api\Player\Player;
use Bedriox\Api\Plugin\PluginLogger;
use Bedriox\Api\Scheduler\PluginScheduler;
use Bedriox\Api\Scheduler\TaskHandle;
use Bedriox\Api\Server;
use Bedriox\Api\World\Position;
use Bedriox\Api\World\World;
use Bedriox\Api\World\WorldCreationOptions;
use Bedriox\Api\World\WorldDifficulty;
use Bedriox\Api\World\WorldManager;
use Bedriox\Api\World\WorldOperation;
use Bedriox\Api\World\WorldOperationResult;
use Bedriox\Api\World\WorldUnloadOptions;
use Throwable;
use XZeroMCPE\Worldsmith\Configuration\SettingsRepository;
use XZeroMCPE\Worldsmith\Domain\WorldDefinition;
use XZeroMCPE\Worldsmith\Message\MessageCatalog;
use XZeroMCPE\Worldsmith\Support\AuditLogger;

final class WorldService
{
    private const int TELEPORT_CONFIRMATION_TIMEOUT_TICKS = 200;

    private WorldManager $worlds;

    /** @var array<string, string> */
    private array $operations = [];

    /** @var array<string, int> */
    private array $emptySince = [];

    /** @var array<string, Position> */
    private array $returnLocations = [];

    /** @var array<string, TaskHandle> */
    private array $pendingTeleports = [];

    /** @var array<string, Position> */
    private array $warmupOrigins = [];

    /** @var array<string, int> */
    private array $lastTeleport = [];

    /** @var array<string, PendingTeleport> */
    private array $teleportVerifications = [];

    /** @var array<string, array<string, array{sender: CommandSender, playerName: string}>> */
    private array $waitingForWorld = [];

    private int $lastSaveAt;

    public function __construct(
        private readonly Server $server,
        private readonly PluginScheduler $scheduler,
        private readonly SettingsRepository $settings,
        private readonly MessageCatalog $messages,
        private readonly CommandSoftEnum $worldNames,
        private readonly AuditLogger $audit,
        private readonly PluginLogger $logger,
    ) {
        $this->worlds = $server->getWorldManager();
        $this->lastSaveAt = time();
        $this->refreshSuggestions();
    }

    public function start(): void
    {
        $queue = array_values(array_filter(
            $this->settings->worlds(),
            static fn(WorldDefinition $world): bool => $world->autoload,
        ));
        $this->loadNext($queue, 0);
        $period = max(100, $this->settings->settings()->idleCheckSeconds * 20);
        $this->scheduler->delayedRepeating($period, $period, fn() => $this->maintenanceTick());
    }

    public function reload(): void
    {
        $this->settings->reload();
        $this->messages->reload();
        $this->refreshSuggestions();
    }

    /** @return list<World> */
    public function loaded(): array
    {
        return $this->worlds->getLoaded();
    }

    public function status(string $worldId): string
    {
        $id = strtolower($worldId);

        return $this->operations[$id] ?? $this->worlds->getState($id)->value ?? 'unknown';
    }

    public function create(CommandSender $sender, string $id, string $generator, int $seed, string $difficulty): void
    {
        $id = strtolower($id);
        if ($this->isBusy($id)) {
            $sender->sendMessage($this->messages->get('operation-failed', ['operation' => 'Create', 'world' => $id, 'failure' => 'another operation is active']));

            return;
        }
        $difficultyValue = WorldDifficulty::tryFrom($difficulty);
        if ($difficultyValue === null) {
            $sender->sendMessage($this->messages->get('operation-failed', ['operation' => 'Create', 'world' => $id, 'failure' => 'invalid difficulty']));

            return;
        }
        $this->track('create', $id, $this->worlds->create($id, new WorldCreationOptions(
            generator: $generator,
            seed: $seed,
            difficulty: $difficultyValue,
        )), $sender, function (WorldOperationResult $result) use ($id): void {
            if ($result->succeeded() && $this->settings->world($id) === null) {
                $this->settings->putWorld(WorldDefinition::fromArray($id, []));
            }
            $this->refreshSuggestions();
        });
    }

    public function load(CommandSender $sender, string $id): void
    {
        $id = strtolower($id);
        if ($this->worlds->get($id) !== null) {
            $sender->sendMessage($this->messages->get('operation-failed', ['operation' => 'Load', 'world' => $id, 'failure' => 'already loaded']));

            return;
        }
        $this->track('load', $id, $this->worlds->load($id), $sender, fn() => $this->refreshSuggestions());
    }

    public function save(CommandSender $sender, string $id): void
    {
        $world = $this->worlds->get(strtolower($id));
        if ($world === null) {
            $sender->sendMessage($this->messages->get('world-not-loaded', ['world' => $id]));

            return;
        }
        $this->track('save', $world->id(), $this->worlds->save($world), $sender);
    }

    public function saveAll(CommandSender $sender): void
    {
        foreach ($this->worlds->getLoaded() as $world) {
            $this->track('save', $world->id(), $this->worlds->save($world), $sender);
        }
    }

    public function unload(CommandSender $sender, string $id, bool $save, bool $force): void
    {
        $world = $this->worlds->get(strtolower($id));
        if ($world === null) {
            $sender->sendMessage($this->messages->get('world-not-loaded', ['world' => $id]));

            return;
        }
        if ($world->isSameWorld($this->worlds->getDefault())) {
            $sender->sendMessage($this->messages->get('operation-failed', ['operation' => 'Unload', 'world' => $id, 'failure' => 'the default world is protected']));

            return;
        }
        $occupants = $this->playersIn($world);
        if ($occupants !== [] && !$force) {
            $sender->sendMessage($this->messages->get('operation-failed', ['operation' => 'Unload', 'world' => $id, 'failure' => 'world is occupied']));

            return;
        }
        if ($occupants !== []) {
            $fallback = $this->worlds->info($this->worlds->getDefault())->spawn;
            foreach ($occupants as $player) {
                $player->teleport($fallback);
            }
            $this->scheduler->delayed(2, fn() => $this->finishUnload($sender, $world, $save));

            return;
        }
        $this->finishUnload($sender, $world, $save);
    }

    public function teleport(CommandSender $sender, Player $player, string $id): void
    {
        $id = strtolower($id);
        $definition = $this->settings->world($id);
        if ($definition?->maintenance === true && !$sender->hasPermission('worldsmith.bypass.maintenance')) {
            $sender->sendMessage($this->messages->get('maintenance', ['world' => $id]));

            return;
        }
        if ($definition !== null && $definition->accessPermission !== '' && !$sender->hasPermission($definition->accessPermission)) {
            $sender->sendMessage($this->messages->get('access-denied', ['world' => $id]));

            return;
        }
        $world = $this->worlds->get($id);
        if ($world === null) {
            if ($definition?->lazyLoad !== true) {
                $sender->sendMessage($this->messages->get('world-not-loaded', ['world' => $id]));

                return;
            }
            if (isset($this->waitingForWorld[$id][$player->uuid])) {
                $sender->sendMessage($this->messages->get('teleport-in-progress'));

                return;
            }
            $this->waitingForWorld[$id][$player->uuid] = [
                'sender' => $sender,
                'playerName' => $player->name,
            ];
            $sender->sendMessage($this->messages->get('world-loading', ['world' => $id]));
            if ($this->isBusy($id)) {
                return;
            }
            $operation = $this->worlds->load($id);
            $this->operations[$id] = 'load';
            $operation->onComplete(function (WorldOperationResult $result) use ($id): void {
                unset($this->operations[$id]);
                $this->refreshSuggestions();
                if ($result->succeeded()) {
                    return;
                }
                $waiting = $this->waitingForWorld[$id] ?? [];
                unset($this->waitingForWorld[$id]);
                foreach ($waiting as $request) {
                    $request['sender']->sendMessage($this->messages->get('operation-failed', [
                        'operation' => 'Load',
                        'world' => $id,
                        'failure' => $this->failureMessage($result),
                    ]));
                }
            });

            return;
        }
        $this->beginTeleport($sender, $player, $world);
    }

    /** Resumes lazy-load teleports from Bedriox's authoritative world-loaded event. */
    public function worldLoaded(World $world): void
    {
        $id = $world->id();
        $waiting = $this->waitingForWorld[$id] ?? [];
        unset($this->waitingForWorld[$id]);
        foreach ($waiting as $uuid => $request) {
            $player = $this->server->getPlayerByUuid($uuid);
            if ($player === null) {
                $request['sender']->sendMessage($this->messages->get('teleport-failed', [
                    'world' => $id,
                    'failure' => $request['playerName'] . ' disconnected while the world was loading',
                ]));

                continue;
            }
            $this->beginTeleport($request['sender'], $player, $world);
        }
    }

    public function back(CommandSender $sender, Player $player): void
    {
        $position = $this->returnLocations[$player->uuid] ?? null;
        if ($position === null || $position->world === null || $this->worlds->get($position->world->id()) === null) {
            $sender->sendMessage($this->messages->get('no-return'));

            return;
        }
        $this->commitTeleport($sender, $player, $position);
    }

    /** Routes lifecycle events without inventing a command sender or permission context. */
    public function routePlayer(Player $player, string $id): void
    {
        $id = strtolower($id);
        if ($id === '') {
            return;
        }
        $world = $this->worlds->get($id);
        if ($world !== null) {
            $player->teleport($this->worlds->info($world)->spawn);

            return;
        }
        $definition = $this->settings->world($id);
        if ($definition?->lazyLoad !== true || $this->isBusy($id)) {
            return;
        }
        $this->operations[$id] = 'route-load';
        $operation = $this->worlds->load($id);
        $operation->onComplete(function (WorldOperationResult $result) use ($player, $id): void {
            unset($this->operations[$id]);
            $this->refreshSuggestions();
            $current = $this->server->getPlayerByUuid($player->uuid);
            if ($result->succeeded() && $current !== null && $result->world !== null) {
                $current->teleport($this->worlds->info($result->world)->spawn);
            }
        });
    }

    public function cancelPending(Player $player): void
    {
        $task = $this->pendingTeleports[$player->uuid] ?? null;
        if ($task === null) {
            return;
        }
        $task->cancel();
        unset($this->pendingTeleports[$player->uuid], $this->warmupOrigins[$player->uuid]);
        $player->sendMessage($this->messages->get('teleport-cancelled'));
    }

    public function discardPending(Player $player): void
    {
        $this->cancelPendingSilently($player);
        $this->cancelTeleportVerification($player->uuid);
    }

    /** Completes a Worldsmith teleport only after Bedriox publishes authoritative player state. */
    public function confirmTeleport(Player $player): void
    {
        $request = $this->teleportVerifications[$player->uuid] ?? null;
        if ($request !== null && $this->reachedDestination($player, $request->destination)) {
            $this->completeTeleport($request);
        }
    }

    public function hasPending(Player $player): bool
    {
        return isset($this->pendingTeleports[$player->uuid]);
    }

    public function warmupOrigin(Player $player): ?Position
    {
        return $this->warmupOrigins[$player->uuid] ?? null;
    }

    public function updateSetting(string $worldId, string $setting, bool|int|string $value): void
    {
        $definition = $this->settings->world($worldId) ?? WorldDefinition::fromArray($worldId, []);
        $this->settings->putWorld($definition->with($setting, $value));
        $this->refreshSuggestions();
    }

    public function shutdown(): void
    {
        foreach ($this->pendingTeleports as $task) {
            $task->cancel();
        }
        $this->pendingTeleports = [];
        foreach ($this->teleportVerifications as $request) {
            $request->verification?->cancel();
        }
        $this->teleportVerifications = [];
        $this->waitingForWorld = [];
    }

    private function beginTeleport(CommandSender $sender, Player $player, World $world): void
    {
        $definition = $this->settings->world($world->id());
        if ($definition?->maximumPlayers > 0 && $this->worlds->info($world)->playerCount >= $definition->maximumPlayers
            && !$sender->hasPermission('worldsmith.bypass.maximumplayers')) {
            $sender->sendMessage($this->messages->get('world-full', ['world' => $world->id()]));

            return;
        }
        $now = time();
        $cooldown = $this->settings->settings()->cooldownSeconds;
        $remaining = $cooldown - ($now - ($this->lastTeleport[$player->uuid] ?? 0));
        if ($remaining > 0 && !$sender->hasPermission('worldsmith.bypass.teleportcooldown')) {
            $sender->sendMessage($this->messages->get('teleport-cooldown', ['seconds' => $remaining]));

            return;
        }
        $destination = $this->worlds->info($world)->spawn;
        $warmup = $this->settings->settings()->warmupSeconds;
        if ($warmup <= 0 || $sender->hasPermission('worldsmith.bypass.teleportwarmup')) {
            $this->commitTeleport($sender, $player, $destination);

            return;
        }
        $this->cancelPendingSilently($player);
        $this->warmupOrigins[$player->uuid] = $player->position;
        $sender->sendMessage($this->messages->get('teleport-warmup', ['seconds' => $warmup]));
        $this->pendingTeleports[$player->uuid] = $this->scheduler->delayed($warmup * 20, function () use ($sender, $player, $destination): void {
            unset($this->pendingTeleports[$player->uuid], $this->warmupOrigins[$player->uuid]);
            $current = $this->server->getPlayerByUuid($player->uuid);
            if ($current !== null) {
                $this->commitTeleport($sender, $current, $destination);
            }
        });
    }

    private function commitTeleport(CommandSender $sender, Player $player, Position $destination): void
    {
        if (isset($this->teleportVerifications[$player->uuid])) {
            $sender->sendMessage($this->messages->get('teleport-in-progress'));

            return;
        }
        $request = new PendingTeleport(
            $player->uuid,
            $player->name,
            $sender,
            $player->position,
            $destination,
        );
        $this->teleportVerifications[$player->uuid] = $request;
        $sender->sendMessage($this->messages->get('teleport-started', [
            'world' => $destination->world?->id() ?? 'unknown',
        ]));
        $this->submitTeleport($request, $player);
    }

    private function submitTeleport(PendingTeleport $request, Player $player): void
    {
        try {
            $player->teleport($request->destination);
        } catch (Throwable) {
            $this->failTeleport($request, 'the teleport request was rejected');

            return;
        }
        $request->verification = $this->scheduler->delayed(
            self::TELEPORT_CONFIRMATION_TIMEOUT_TICKS,
            fn() => $this->verifyTeleport($request),
        );
    }

    private function verifyTeleport(PendingTeleport $request): void
    {
        if (($this->teleportVerifications[$request->playerUuid] ?? null) !== $request) {
            return;
        }
        $player = $this->server->getPlayerByUuid($request->playerUuid);
        if ($player === null) {
            $this->failTeleport($request, 'the player disconnected');

            return;
        }
        if ($this->reachedDestination($player, $request->destination)) {
            $this->completeTeleport($request);

            return;
        }
        $this->failTeleport($request, 'the destination was not confirmed in time');
    }

    private function completeTeleport(PendingTeleport $request): void
    {
        $this->cancelTeleportVerification($request->playerUuid);
        $this->returnLocations[$request->playerUuid] = $request->origin;
        $this->lastTeleport[$request->playerUuid] = time();
        $world = $request->destination->world?->id() ?? 'unknown';
        $key = $request->sender->name() === $request->playerName ? 'teleported' : 'teleporting-other';
        $request->sender->sendMessage($this->messages->get($key, [
            'player' => $request->playerName,
            'world' => $world,
        ]));
    }

    private function failTeleport(PendingTeleport $request, string $failure): void
    {
        $this->cancelTeleportVerification($request->playerUuid);
        $request->sender->sendMessage($this->messages->get('teleport-failed', [
            'world' => $request->destination->world?->id() ?? 'unknown',
            'failure' => $failure,
        ]));
    }

    private function cancelTeleportVerification(string $uuid): void
    {
        ($this->teleportVerifications[$uuid] ?? null)?->verification?->cancel();
        unset($this->teleportVerifications[$uuid]);
    }

    private function reachedDestination(Player $player, Position $destination): bool
    {
        $expected = $destination->world;
        $actual = $player->position->world;
        if ($expected === null || $actual === null || !$actual->isSameLoad($expected)) {
            return false;
        }

        return abs($player->position->x - $destination->x) <= 1.0
            && abs($player->position->y - $destination->y) <= 1.0
            && abs($player->position->z - $destination->z) <= 1.0;
    }

    private function finishUnload(CommandSender $sender, World $world, bool $save): void
    {
        $current = $this->worlds->get($world->id());
        if ($current === null || !$current->isSameLoad($world) || $this->playersIn($world) !== []) {
            $sender->sendMessage($this->messages->get('operation-failed', ['operation' => 'Unload', 'world' => $world->id(), 'failure' => 'players could not be evacuated']));

            return;
        }
        $this->track('unload', $world->id(), $this->worlds->unload($world, new WorldUnloadOptions($save)), $sender, fn() => $this->refreshSuggestions());
    }

    /** @param callable(WorldOperationResult): void|null $after */
    private function track(string $type, string $id, WorldOperation $operation, CommandSender $sender, ?callable $after = null): void
    {
        $id = strtolower($id);
        $this->operations[$id] = $type;
        $sender->sendMessage($this->messages->get('operation-started', ['operation' => ucfirst($type), 'world' => $id]));
        $operation->onComplete(function (WorldOperationResult $result) use ($type, $id, $sender, $after): void {
            unset($this->operations[$id]);
            if ($result->succeeded()) {
                $sender->sendMessage($this->messages->get('operation-succeeded', ['operation' => ucfirst($type), 'world' => $id]));
                $this->audit->record("{$sender->name()} completed {$type} for world {$id}");
            } else {
                $sender->sendMessage($this->messages->get('operation-failed', [
                    'operation' => ucfirst($type),
                    'world' => $id,
                    'failure' => $this->failureMessage($result),
                ]));
            }
            if ($after !== null) {
                $after($result);
            }
        });
    }

    /** @param list<WorldDefinition> $queue */
    private function loadNext(array $queue, int $index): void
    {
        $definition = $queue[$index] ?? null;
        if ($definition === null) {
            return;
        }
        if ($this->worlds->get($definition->id) !== null) {
            $this->loadNext($queue, $index + 1);

            return;
        }
        $this->operations[$definition->id] = 'startup-load';
        $operation = $this->worlds->load($definition->id);
        $operation->onComplete(function (WorldOperationResult $result) use ($queue, $index, $definition): void {
            unset($this->operations[$definition->id]);
            if (!$result->succeeded()) {
                $this->logger->warning("Unable to autoload world {$definition->id}: " . $this->failureMessage($result));
            }
            $this->refreshSuggestions();
            $this->loadNext($queue, $index + 1);
        });
    }

    private function maintenanceTick(): void
    {
        $now = time();
        foreach ($this->worlds->getLoaded() as $world) {
            $definition = $this->settings->world($world->id());
            if ($world->isSameWorld($this->worlds->getDefault()) || $definition === null || $definition->idleUnloadMinutes === 0) {
                continue;
            }
            if ($this->worlds->info($world)->playerCount > 0) {
                unset($this->emptySince[$world->id()]);
                continue;
            }
            $this->emptySince[$world->id()] ??= $now;
            if ($now - $this->emptySince[$world->id()] >= $definition->idleUnloadMinutes * 60 && !$this->isBusy($world->id())) {
                $this->operations[$world->id()] = 'idle-unload';
                $operation = $this->worlds->unload($world);
                $operation->onComplete(function () use ($world): void {
                    unset($this->operations[$world->id()], $this->emptySince[$world->id()]);
                    $this->refreshSuggestions();
                });
            }
        }
        $saveMinutes = $this->settings->settings()->saveIntervalMinutes;
        if ($saveMinutes > 0 && $now - $this->lastSaveAt >= $saveMinutes * 60) {
            $this->lastSaveAt = $now;
            foreach ($this->worlds->getLoaded() as $world) {
                if (!$this->isBusy($world->id())) {
                    $this->worlds->save($world);
                }
            }
        }
    }

    /** @return list<Player> */
    private function playersIn(World $world): array
    {
        return array_values(array_filter(
            $this->server->getOnlinePlayers(),
            static fn(Player $player): bool => $player->position->world?->isSameLoad($world) === true,
        ));
    }

    private function isBusy(string $id): bool
    {
        return isset($this->operations[strtolower($id)]);
    }

    private function cancelPendingSilently(Player $player): void
    {
        ($this->pendingTeleports[$player->uuid] ?? null)?->cancel();
        unset($this->pendingTeleports[$player->uuid], $this->warmupOrigins[$player->uuid]);
    }

    private function refreshSuggestions(): void
    {
        $ids = $this->settings->worldIds();
        foreach ($this->worlds->getLoaded() as $world) {
            $ids[] = $world->id();
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);
        $this->worldNames->replace($ids);
    }

    private function failureMessage(WorldOperationResult $result): string
    {
        return $result->failure === null ? $result->state->value : $result->failure->message;
    }
}
